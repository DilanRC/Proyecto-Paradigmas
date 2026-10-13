<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/ValidacionService.php';
require_once dirname(__DIR__) . '/Service/SupabaseAuthAdmin.php';
// Solo se usa su validación estática imagenUrl(); no instancia sus modelos.
require_once __DIR__ . '/AnimalPublicacionController.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Persona;
use Application\Service\SupabaseAuthAdmin;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/**
 * La persona edita sus propios datos de perfil: nombre, identificación, correo,
 * foto, alias, teléfono y el documento de identidad que acaba de subir (queda
 * PENDIENTE de revisión). La Persona sale del JWT verificado, nunca del cuerpo.
 * El correo cambia también en la cuenta de Supabase (la sesión se vincula por él).
 */
final class MiPerfilController
{
    private const EDITABLES = ['nombre', 'identificacion', 'correoElectronico', 'alias', 'telefono', 'fotoUrl',
        'documentoRuta', 'documentoLectura'];
    /** Documento de identidad (P2-5): archivo del bucket privado "documentos". */
    private const DOCUMENTO_EXTENSIONES = 'jpg|png|webp|pdf';

    private Persona $personas;
    private Bitacora $bitacora;
    private string $solicitudId;

    private SupabaseAuthAdmin $authAdmin;

    public function __construct(private PDO $conexion, private ActorContext $actor,
        ?string $solicitudId = null, ?SupabaseAuthAdmin $authAdmin = null)
    {
        $this->authAdmin = $authAdmin ?? new SupabaseAuthAdmin();
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
        $validacion = new ValidacionService();
        if (array_key_exists('nombre', $cuerpo)) {
            $cambios['nombre'] = $validacion->textoCampo($cuerpo['nombre'], 'nombre', 150, $errores, 3);
        }
        if (array_key_exists('identificacion', $cuerpo)) {
            $identificacion = $validacion->validarIdentificacion($cuerpo['identificacion'], $errores);
            $cambios['identificacionTipo'] = $identificacion['tipoCodigo'];
            $cambios['identificacionNumero'] = $identificacion['numero'];
        }
        if (array_key_exists('correoElectronico', $cuerpo)) {
            $cambios['correo'] = $validacion->validarCorreo($cuerpo['correoElectronico'], $errores);
        }
        if (array_key_exists('alias', $cuerpo)) {
            $alias = is_string($cuerpo['alias']) ? trim($cuerpo['alias']) : '';
            if ($cuerpo['alias'] !== null && !is_string($cuerpo['alias'])) {
                $errores['alias'] = 'Debe ser texto.';
            } elseif (mb_strlen($alias) > 150) {
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
        $numeroLeido = $this->numeroLeido($cuerpo, $errores);
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $personaId = (int) $this->actor->personaId;
        // Los consecutivos de la bitácora y del histórico de teléfono salen de MAX()+1:
        // sus bloqueos deben durar hasta el COMMIT o dos ediciones simultáneas repiten el id.
        // El bloqueo de alta de Persona serializa la identificación y el correo con el registro (que lo toma primero).
        $nueva = $this->personas->ejecutarConBloqueoAlta(fn (): array => $this->personas->ejecutarConBloqueoTelefono(fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
            function () use ($personaId, $cambios, $numeroLeido): array {
                $this->conexion->beginTransaction();
                $authIntentado = false;
                $commitIntentado = false;
                $anterior = null;
                try {
                    $anterior = $this->personas->buscarPorId($personaId);
                    if ($anterior === null) {
                        throw new HttpException('La persona no existe.', 404);
                    }
                    if ((int) $anterior['tbpersonaestado'] !== 1) {
                        throw new HttpException('La cuenta está inactiva y no puede editar su perfil.', 409);
                    }
                    $identificacionCambiada = $this->revisarIdentificacion($cambios, $anterior);
                    $correoCambiado = $this->revisarCorreo($cambios, $anterior);
                    // Para la lectura del documento cuenta la identificación nueva, si cambió.
                    $conIdentificacion = [
                        'tbpersonaidentificaciontipo' => $cambios['identificacionTipo'] ?? $anterior['tbpersonaidentificaciontipo'],
                        'tbpersonaidentificacionnumero' => $cambios['identificacionNumero'] ?? $anterior['tbpersonaidentificacionnumero'],
                    ] + $anterior;
                    if (array_key_exists('documentoRuta', $cambios)) {
                        // Un documento nuevo reemplaza la lectura anterior; sin lectura (PDF) queda en NULL.
                        $cambios['documentoLectura'] = $numeroLeido === false ? null : $this->resultadoLectura($numeroLeido, $conIdentificacion);
                        $cambios['documentoNumeroLeido'] = $cambios['documentoLectura'] === null ? null : $numeroLeido;
                    } elseif ($identificacionCambiada && ($anterior['tbpersonadocumentoruta'] ?? null) !== null) {
                        // El documento subido era de la identificación anterior: vuelve a revisión con la lectura recalculada.
                        $cambios['documentoEstado'] = 'PENDIENTE';
                        $cambios['documentoMotivo'] = null;
                        $leido = $anterior['tbpersonadocumentonumeroleido'] ?? null;
                        $cambios['documentoLectura'] = $leido === null ? null : $this->resultadoLectura((string) $leido, $conIdentificacion);
                    }
                    $nueva = $this->personas->actualizarPerfil($personaId, $cambios);
                    // El teléfono es dato sensible: la bitácora solo dice que cambió.
                    $this->bitacora->registrar(
                        'ACTUALIZAR',
                        'PERSONA:' . $personaId,
                        [
                            'nombre' => $anterior['tbpersonanombre'],
                            'correoElectronico' => $anterior['tbpersonacorreoelectronico'],
                            'alias' => $anterior['tbpersonaalias'],
                            'fotoUrl' => $anterior['tbpersonafotourl'] ?? null,
                            'documentoEstado' => $anterior['tbpersonadocumentoestado'] ?? null,
                        ],
                        [
                            'nombre' => $nueva['tbpersonanombre'],
                            'correoElectronico' => $nueva['tbpersonacorreoelectronico'],
                            'alias' => $nueva['tbpersonaalias'],
                            'fotoUrl' => $nueva['tbpersonafotourl'] ?? null,
                            'documentoEstado' => $nueva['tbpersonadocumentoestado'] ?? null,
                            // El resultado de la lectura sí; el número leído no (es la identificación).
                            'documentoLectura' => $nueva['tbpersonadocumentolectura'] ?? null,
                            // Identificación y teléfono son datos sensibles: solo se anota que cambiaron.
                            'identificacionCambiada' => $identificacionCambiada,
                            'telefonoCambiado' => ($anterior['tbpersonatelefono'] ?? null) !== ($nueva['tbpersonatelefono'] ?? null),
                        ],
                        $this->solicitudId,
                        entidad: 'PERSONA',
                        origen: 'API_MI_PERFIL',
                    );
                    if ($correoCambiado) {
                        $authIntentado = true;
                        $this->authAdmin->cambiarCorreo((string) $this->actor->proveedorSujeto, $cambios['correo']);
                    }
                    $commitIntentado = true;
                    if (!$this->conexion->commit()) {
                        throw new \RuntimeException('No se confirmó la transacción del perfil.');
                    }
                    return $nueva;
                } catch (Throwable $error) {
                    $rollbackConfirmado = false;
                    try {
                        if ($this->conexion->inTransaction()) {
                            $rollbackConfirmado = $this->conexion->rollBack();
                        }
                    } catch (Throwable) {
                        // La desconexión impide conocer el resultado local.
                    }
                    if ($authIntentado && !($error instanceof HttpException && $error->estadoHttp === 409)) {
                        if (!$rollbackConfirmado) {
                            throw new HttpException('No se pudo confirmar el resultado del cambio de correo. La cuenta requiere comprobar la sincronización antes de reintentar.', 503);
                        }
                        $this->authAdmin->recuperarCorreo(
                            (string) $this->actor->proveedorSujeto,
                            $cambios['correo'],
                            (string) $anterior['tbpersonacorreoelectronico'],
                        );
                        if ($commitIntentado) {
                            throw new HttpException('No se guardó el perfil; el correo anterior fue restaurado. Puede reintentar.', 503);
                        }
                    }
                    throw $error;
                }
            },
        )));

        return $this->respuesta(true, 'Perfil actualizado correctamente.', ['persona' => [
            'personaId' => (int) $nueva['tbpersonaid'],
            'nombre' => $nueva['tbpersonanombre'],
            'identificacionTipo' => $nueva['tbpersonaidentificaciontipo'],
            'identificacionNumero' => $nueva['tbpersonaidentificacionnumero'],
            'correoElectronico' => $nueva['tbpersonacorreoelectronico'],
            'alias' => $nueva['tbpersonaalias'],
            'telefono' => $nueva['tbpersonatelefono'],
            'fotoUrl' => $nueva['tbpersonafotourl'] ?? null,
            'documento' => Persona::documentoPublico($nueva),
        ]]);
    }

    /**
     * Identificación igual a la actual: se quita de los cambios. Distinta: no puede ser la de otra
     * Persona (409). Devuelve si cambia.
     */
    private function revisarIdentificacion(array &$cambios, array $anterior): bool
    {
        if (!array_key_exists('identificacionNumero', $cambios)) return false;
        if ($cambios['identificacionNumero'] === $anterior['tbpersonaidentificacionnumero']
            && $cambios['identificacionTipo'] === $anterior['tbpersonaidentificaciontipo']) {
            unset($cambios['identificacionNumero'], $cambios['identificacionTipo']);
            return false;
        }
        if ($cambios['identificacionNumero'] !== $anterior['tbpersonaidentificacionnumero']
            && $this->personas->existeIdentificacion($cambios['identificacionNumero'])) {
            throw new HttpException('Revise los campos indicados.', 409, null, [
                'identificacion.numero' => 'Esa identificación ya está registrada en otra cuenta.',
            ]);
        }
        return true;
    }

    /**
     * Correo igual al actual (sin distinguir mayúsculas): se quita de los cambios. Distinto: no puede ser
     * el de otra Persona ni el de un administrador (tomarlo daría acceso al panel). Devuelve si cambia.
     */
    private function revisarCorreo(array &$cambios, array $anterior): bool
    {
        if (!array_key_exists('correo', $cambios)) return false;
        if ($cambios['correo'] === mb_strtolower((string) $anterior['tbpersonacorreoelectronico'], 'UTF-8')) {
            unset($cambios['correo']);
            return false;
        }
        $administrador = $this->conexion->prepare(
            'SELECT 1 FROM tbadministrador WHERE LOWER(tbadministradorcorreoelectronico) = LOWER(:correo) LIMIT 1'
        );
        $administrador->execute(['correo' => $cambios['correo']]);
        if ($this->personas->existeCorreo($cambios['correo']) || $administrador->fetchColumn() !== false) {
            throw new HttpException('Revise los campos indicados.', 409, null, [
                'correoElectronico' => 'Ese correo ya tiene una cuenta.',
            ]);
        }
        return true;
    }

    /**
     * `documentoLectura: { numero }` llega junto con `documentoRuta`: es lo que el
     * OCR del navegador leyó en la foto. Solo se acepta el número; el resultado lo
     * calcula el servidor (resultadoLectura). Devuelve false si no se envió
     * lectura, '' si se intentó y no encontró número, o los dígitos leídos.
     */
    private function numeroLeido(array $cuerpo, array &$errores): string|false
    {
        if (!array_key_exists('documentoLectura', $cuerpo)) {
            return false;
        }
        $lectura = $cuerpo['documentoLectura'];
        if (!array_key_exists('documentoRuta', $cuerpo) || !is_array($lectura)
            || array_diff(array_keys($lectura), ['numero']) !== []) {
            $errores['documentoLectura'] = 'La lectura acompaña a un documento nuevo: { numero }.';
            return false;
        }
        $numero = $lectura['numero'] ?? null;
        if ($numero === null || $numero === '') {
            return '';
        }
        $digitos = is_string($numero) ? preg_replace('/\D+/', '', $numero) : '';
        if ($digitos === '' || strlen($digitos) > 20) {
            $errores['documentoLectura'] = 'El número leído debe tener hasta 20 dígitos.';
            return false;
        }
        return $digitos;
    }

    /**
     * COINCIDE, NO_COINCIDE, OTRA_CUENTA o SIN_LECTURA. Lo decide PHP, no el
     * navegador. Solo aplica a identificaciones numéricas: un pasaporte tiene
     * letras y el OCR de dígitos no lo puede leer (null = no aplica).
     */
    private function resultadoLectura(string $numero, array $persona): ?string
    {
        if (!in_array($persona['tbpersonaidentificaciontipo'] ?? '', ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX', 'NITE'], true)) {
            return null;
        }
        if ($numero === '') {
            return 'SIN_LECTURA';
        }
        $propio = preg_replace('/\D+/', '', (string) $persona['tbpersonaidentificacionnumero']);
        if ($numero === $propio) {
            return 'COINCIDE';
        }
        return $this->personas->existeIdentificacion($numero) ? 'OTRA_CUENTA' : 'NO_COINCIDE';
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
