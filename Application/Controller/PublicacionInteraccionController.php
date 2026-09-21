<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\PublicacionInteraccion;
use PDO;
use Throwable;

final class PublicacionInteraccionController
{
    private const TIPOS = ['ME_INTERESA', 'PASAR', 'CONTACTAR'];

    private readonly PublicacionInteraccion $interacciones;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->interacciones = new PublicacionInteraccion($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        try {
            if ($metodo !== 'POST') {
                return $this->respuesta(false, 'Método no permitido.', null, 405);
            }
            return $this->crear($cuerpo);
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores,
            );
        }
    }

    private function crear(array $cuerpo): array
    {
        if (!$this->actor->tienePersona()) {
            throw new HttpException('Debe iniciar sesión para registrar la interacción.', 401);
        }
        $publicacionId = filter_var($cuerpo['publicacionId'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $tipo = is_string($cuerpo['tipo'] ?? null)
            ? mb_strtoupper(trim($cuerpo['tipo']), 'UTF-8')
            : '';
        if ($publicacionId === false || !in_array($tipo, self::TIPOS, true)) {
            throw new HttpException('Revise la publicación y la acción solicitadas.', 422, null, [
                'publicacionId' => 'Debe ser un identificador positivo.',
                'tipo' => 'Use ME_INTERESA, PASAR o CONTACTAR.',
            ]);
        }
        if (!$this->publicacionActiva((int) $publicacionId)) {
            throw new HttpException('La publicación ya no está activa.', 409);
        }

        $this->conexion->beginTransaction();
        try {
            $interaccionId = $this->interacciones->ejecutarConBloqueoAlta(
                fn (): int => $this->interacciones->registrar(
                    (int) $this->actor->personaId,
                    (int) $publicacionId,
                    $tipo,
                    'API_PUBLICACION_INTERACCIONES',
                ),
            );
            $resultado = [
                'interaccionId' => $interaccionId,
                'publicacionId' => (int) $publicacionId,
                'tipo' => $tipo,
            ];
            $this->bitacora->registrar(
                'CREAR',
                'PUBLICACION_INTERACCION:' . $interaccionId,
                null,
                $resultado,
                $this->solicitudId,
                entidad: 'PUBLICACION_INTERACCION',
                origen: 'API_PUBLICACION_INTERACCIONES',
            );
            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $error;
        }

        return $this->respuesta(true, 'Interacción guardada correctamente.', $resultado, 201);
    }

    private function publicacionActiva(int $publicacionId): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT 1 FROM tbanimalpublicacionestadoperiodo
             WHERE tbanimalpublicacionid = :publicacionId
               AND tbanimalpublicacionestadoperiodofechafin IS NULL
               AND tbanimalpublicacionestadoperiodoestado = :estado
             LIMIT 1'
        );
        $sentencia->execute(['publicacionId' => $publicacionId, 'estado' => 'ACTIVO']);

        return $sentencia->fetchColumn() !== false;
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $status,
        array $errores = []): array
    {
        $body = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $body['errors'] = $errores;

        return ['status' => $status, 'body' => $body];
    }
}
