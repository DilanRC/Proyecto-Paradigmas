<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/SupabaseStorage.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Persona;
use Application\Service\SupabaseStorage;
use PDO;
use Throwable;

/**
 * Verificación de identidad para el administrador (P2-6, opción c del plan):
 * lista las personas con documento, abre el archivo privado con un enlace
 * firmado temporal y lo marca VERIFICADO o RECHAZADO con motivo. La
 * autorización de administrador la exige el endpoint antes de llegar aquí.
 */
final class AdminDocumentoController
{
    private const BUCKET = 'documentos';
    private const ORIGEN = 'API_ADMIN_DOCUMENTOS';
    private const ESTADOS_FILTRO = ['PENDIENTE', 'VERIFICADO', 'RECHAZADO', 'TODOS'];
    private const DECISIONES = ['VERIFICADO', 'RECHAZADO'];

    private readonly Persona $personas;
    private readonly Bitacora $bitacora;
    private readonly ActorContext $actor;
    private readonly SupabaseStorage $storage;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null,
        ?ActorContext $actor = null, ?SupabaseStorage $storage = null)
    {
        $this->actor = $actor ?? ActorContext::noAutenticado();
        $this->personas = new Persona($conexion);
        $this->bitacora = new Bitacora($conexion, $this->actor);
        $this->storage = $storage ?? new SupabaseStorage();
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->listar($consulta),
                'POST' => $this->enlace($cuerpo),
                'PATCH' => $this->decidir($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function listar(array $consulta): array
    {
        $errores = [];
        $estado = mb_strtoupper(trim((string) ($consulta['estado'] ?? 'PENDIENTE')), 'UTF-8');
        if (!in_array($estado, self::ESTADOS_FILTRO, true)) {
            $errores['estado'] = 'Use ' . implode(', ', self::ESTADOS_FILTRO) . '.';
        }
        $q = $consulta['q'] ?? '';
        if (!is_string($q) || mb_strlen($q) > 150) {
            $errores['q'] = 'Use hasta 150 caracteres.';
        }
        $pagina = filter_var($consulta['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tamano = filter_var($consulta['tamanoPagina'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($pagina === false) $errores['pagina'] = 'Debe ser un entero positivo.';
        if ($tamano === false) $errores['tamanoPagina'] = 'Debe estar entre 1 y 100.';
        if ($errores !== []) {
            throw new HttpException('Revise los filtros indicados.', 422, null, $errores);
        }

        $resultado = $this->personas->listarDocumentos($estado, trim((string) $q), (int) $pagina, (int) $tamano);

        return $this->respuesta(true, 'Documentos consultados correctamente.', $resultado + [
            'pagina' => (int) $pagina,
            'tamanoPagina' => (int) $tamano,
        ]);
    }

    /** Enlace firmado para ver el documento. Cada acceso queda en la bitácora. */
    private function enlace(array $cuerpo): array
    {
        $this->rechazarCampos($cuerpo, ['personaId']);
        $personaId = $this->personaId($cuerpo);
        $persona = $this->personas->buscarPorId($personaId);
        $ruta = $persona['tbpersonadocumentoruta'] ?? null;
        if ($persona === null || !is_string($ruta) || $ruta === '') {
            throw new HttpException('La persona no tiene documento subido.', 404);
        }

        $url = $this->storage->enlaceFirmado(self::BUCKET, $ruta);
        $this->registrar('VER_DOCUMENTO', $personaId, null, ['documentoEstado' => $persona['tbpersonadocumentoestado']]);

        return $this->respuesta(true, 'Enlace generado. Caduca en pocos minutos.', [
            'url' => $url,
            'expiraEnSegundos' => SupabaseStorage::SEGUNDOS_ENLACE,
        ]);
    }

    private function decidir(array $cuerpo): array
    {
        $this->rechazarCampos($cuerpo, ['personaId', 'estado', 'motivo']);
        $personaId = $this->personaId($cuerpo);
        $estado = mb_strtoupper(trim((string) ($cuerpo['estado'] ?? '')), 'UTF-8');
        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));
        $errores = [];
        if (!in_array($estado, self::DECISIONES, true)) {
            $errores['estado'] = 'Use VERIFICADO o RECHAZADO.';
        }
        if (mb_strlen($motivo) > 250) {
            $errores['motivo'] = 'No puede superar 250 caracteres.';
        } elseif ($estado === 'RECHAZADO' && $motivo === '') {
            $errores['motivo'] = 'Indique el motivo: la persona lo verá para saber qué corregir.';
        }
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $nueva = $this->bitacora->ejecutarConBloqueoAlta(fn (): array => $this->transaccion(
            function () use ($personaId, $estado, $motivo): array {
                $anterior = $this->personas->bloquearPorId($personaId);
                if ($anterior === null || ($anterior['tbpersonadocumentoestado'] ?? null) === null) {
                    throw new HttpException('La persona no tiene documento subido.', 404);
                }
                if ($anterior['tbpersonadocumentoestado'] !== 'PENDIENTE') {
                    throw new HttpException('Este documento ya fue revisado. Si la persona sube otro, vuelve a quedar pendiente.', 409);
                }
                // Un documento verificado no lleva motivo.
                $this->personas->decidirDocumento($personaId, $estado, $estado === 'RECHAZADO' ? $motivo : null);
                $nueva = $this->personas->buscarPorId($personaId);
                $this->registrar(
                    $estado === 'VERIFICADO' ? 'VERIFICAR_DOCUMENTO' : 'RECHAZAR_DOCUMENTO',
                    $personaId,
                    ['documentoEstado' => 'PENDIENTE'],
                    ['documentoEstado' => $estado, 'motivo' => $estado === 'RECHAZADO' ? $motivo : null],
                );
                return $nueva;
            },
        ));

        return $this->respuesta(true, $estado === 'VERIFICADO' ? 'Documento verificado.' : 'Documento rechazado.', [
            'personaId' => $personaId,
            'documento' => Persona::documentoPublico($nueva),
        ]);
    }

    private function personaId(array $cuerpo): int
    {
        $id = filter_var($cuerpo['personaId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['personaId' => 'Debe ser un entero positivo.']);
        }
        return $id;
    }

    /** Un admin puede no tener Persona: la bitácora guarda también su correo. */
    private function registrar(string $accion, int $personaId, ?array $anterior, array $nuevo): void
    {
        $this->bitacora->registrar(
            $accion,
            'PERSONA:' . $personaId,
            $anterior,
            $nuevo + ['realizadoPor' => $this->actor->correoElectronico],
            $this->solicitudId,
            entidad: 'PERSONA',
            origen: self::ORIGEN,
        );
    }

    private function rechazarCampos(array $cuerpo, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($cuerpo), $permitidos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null,
                array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();
            return $resultado;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
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
