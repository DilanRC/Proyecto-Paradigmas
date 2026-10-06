<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/ValidacionService.php';
// Solo se usa su validación estática imagenUrl(); no instancia sus modelos.
require_once __DIR__ . '/AnimalPublicacionController.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Persona;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/**
 * La persona edita sus propios datos de perfil: foto, alias, teléfono y el
 * documento de identidad que acaba de subir (queda PENDIENTE de revisión). El
 * nombre, la identificación y el correo no se editan aquí. La Persona sale del
 * JWT verificado, nunca del cuerpo.
 */
final class MiPerfilController
{
    private const EDITABLES = ['alias', 'telefono', 'fotoUrl', 'documentoRuta'];
    /** Documento de identidad (P2-5): archivo del bucket privado "documentos". */
    private const DOCUMENTO_EXTENSIONES = 'jpg|png|webp|pdf';

    private readonly Persona $personas;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, private readonly ActorContext $actor,
        ?string $solicitudId = null)
    {
        $this->personas = new Persona($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        try {
            return $metodo === 'PATCH'
                ? $this->actualizar($cuerpo)
                : $this->respuesta(false, 'Método no permitido.', null, 405);
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function actualizar(array $cuerpo): array
    {
        if (!$this->actor->tienePersona()) {
            throw new HttpException('Debe iniciar sesión para editar su perfil.', 401);
        }
        $desconocidos = array_diff(array_keys($cuerpo), self::EDITABLES);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(
                array_map('strval', $desconocidos), 'Este dato no se puede editar aquí.'
            ));
        }
        if ($cuerpo === []) {
            throw new HttpException('Indique qué desea cambiar.', 422);
        }

        $errores = [];
        $cambios = [];
        if (array_key_exists('alias', $cuerpo)) {
            $alias = $cuerpo['alias'] === null ? '' : trim((string) $cuerpo['alias']);
            if (mb_strlen($alias) > 150) {
                $errores['alias'] = 'No puede superar 150 caracteres.';
            }
            $cambios['alias'] = $alias === '' ? null : $alias;
        }
        if (array_key_exists('telefono', $cuerpo)) {
            $cambios['telefono'] = (new ValidacionService())->validarTelefono($cuerpo['telefono'], $errores);
        }
        if (array_key_exists('fotoUrl', $cuerpo)) {
            try {
                $cambios['fotoUrl'] = AnimalPublicacionController::imagenUrl($cuerpo['fotoUrl'], 'fotoUrl');
            } catch (HttpException $error) {
                $errores += $error->errores;
            }
        }
        if (array_key_exists('documentoRuta', $cuerpo)) {
            // Solo una ruta dentro de la carpeta propia del bucket (la política de
            // Storage solo deja subir ahí), con el nombre que genera el navegador.
            $ruta = is_string($cuerpo['documentoRuta']) ? trim($cuerpo['documentoRuta']) : '';
            $carpeta = preg_quote((string) $this->actor->proveedorSujeto, '/');
            if ($carpeta === '' || !preg_match(
                '/^' . $carpeta . '\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(' . self::DOCUMENTO_EXTENSIONES . ')$/',
                $ruta,
            )) {
                $errores['documentoRuta'] = 'Sube el documento desde Ajustes → Perfil.';
            }
            // Un documento nuevo siempre vuelve a revisión, aunque el anterior estuviera verificado.
            $cambios['documentoRuta'] = $ruta;
            $cambios['documentoEstado'] = 'PENDIENTE';
            $cambios['documentoFecha'] = gmdate('Y-m-d H:i:s');
            // El motivo de un rechazo anterior ya no aplica al documento nuevo.
            $cambios['documentoMotivo'] = null;
        }
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $personaId = (int) $this->actor->personaId;
        $this->conexion->beginTransaction();
        try {
            $anterior = $this->personas->buscarPorId($personaId);
            if ($anterior === null) {
                throw new HttpException('La persona no existe.', 404);
            }
            if ((int) $anterior['tbpersonaestado'] !== 1) {
                throw new HttpException('La cuenta está inactiva y no puede editar su perfil.', 409);
            }
            $nueva = $this->personas->actualizarPerfil($personaId, $cambios);
            // El teléfono es dato sensible: la bitácora solo dice que cambió.
            $this->bitacora->registrar(
                'ACTUALIZAR',
                'PERSONA:' . $personaId,
                [
                    'alias' => $anterior['tbpersonaalias'],
                    'fotoUrl' => $anterior['tbpersonafotourl'] ?? null,
                    'documentoEstado' => $anterior['tbpersonadocumentoestado'] ?? null,
                ],
                [
                    'alias' => $nueva['tbpersonaalias'],
                    'fotoUrl' => $nueva['tbpersonafotourl'] ?? null,
                    'documentoEstado' => $nueva['tbpersonadocumentoestado'] ?? null,
                    'telefonoCambiado' => ($anterior['tbpersonatelefono'] ?? null) !== ($nueva['tbpersonatelefono'] ?? null),
                ],
                $this->solicitudId,
                entidad: 'PERSONA',
                origen: 'API_MI_PERFIL',
            );
            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }

        return $this->respuesta(true, 'Perfil actualizado correctamente.', ['persona' => [
            'personaId' => (int) $nueva['tbpersonaid'],
            'nombre' => $nueva['tbpersonanombre'],
            'alias' => $nueva['tbpersonaalias'],
            'telefono' => $nueva['tbpersonatelefono'],
            'fotoUrl' => $nueva['tbpersonafotourl'] ?? null,
            'documento' => Persona::documentoPublico($nueva),
        ]]);
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200,
        array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
