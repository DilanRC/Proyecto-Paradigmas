<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalComercial;
use Application\Model\Bitacora;
use Application\Model\PublicacionInteraccion;
use PDO;
use Throwable;

final class PublicacionInteraccionController
{
    private const TIPOS = ['ME_INTERESA', 'PASAR', 'CONTACTAR'];

    private readonly PublicacionInteraccion $interacciones;
    private readonly AnimalComercial $animales;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->interacciones = new PublicacionInteraccion($conexion);
        $this->animales = new AnimalComercial($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $cuerpo, array $consulta = []): array
    {
        try {
            return match ($metodo) {
                'POST' => $this->crear($cuerpo),
                'GET' => $this->listar($consulta),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
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

    /** Publicaciones que la persona autenticada tiene en "Me interesa", sin importar su estado. */
    private function listar(array $consulta): array
    {
        if (!$this->actor->tienePersona()) {
            throw new HttpException('Debe iniciar sesión para ver sus publicaciones guardadas.', 401);
        }
        $tipo = mb_strtoupper(trim((string) ($consulta['tipo'] ?? 'ME_INTERESA')), 'UTF-8');
        if ($tipo !== 'ME_INTERESA') {
            throw new HttpException('Solo se puede listar ME_INTERESA.', 422, null, ['tipo' => 'Use ME_INTERESA.']);
        }
        $pagina = filter_var($consulta['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tamano = filter_var($consulta['tamanoPagina'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($pagina === false || $tamano === false) {
            throw new HttpException('La paginación no es válida.', 422, null, [
                'pagina' => 'Debe ser un entero positivo.',
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->animales->listarPublicaciones(
            '', 'TODOS', $pagina, $tamano, null, (int) $this->actor->personaId, true
        );
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Publicaciones guardadas consultadas correctamente.', $resultado, 200);
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
        $accion = is_string($cuerpo['accion'] ?? null) ? mb_strtoupper(trim($cuerpo['accion']), 'UTF-8') : 'REGISTRAR';
        if (!in_array($accion, ['REGISTRAR', 'RETIRAR'], true) || ($accion === 'RETIRAR' && $tipo !== 'ME_INTERESA')) {
            throw new HttpException('Revise la publicación y la acción solicitadas.', 422, null, [
                'accion' => 'Use REGISTRAR, o RETIRAR solo para ME_INTERESA.',
            ]);
        }
        if ($accion === 'RETIRAR') {
            // Idempotente: quitar algo que no está marcado no escribe nada. Se permite
            // aunque la publicación ya no esté activa, para limpiar la lista.
            if (!$this->interacciones->estaMarcada((int) $this->actor->personaId, (int) $publicacionId, $tipo)) {
                return $this->respuesta(true, 'La publicación ya no estaba en tu lista.', [
                    'publicacionId' => (int) $publicacionId, 'tipo' => $tipo, 'accion' => $accion, 'cambiado' => false,
                ], 200);
            }
        } elseif (!$this->publicacionActiva((int) $publicacionId)) {
            throw new HttpException('La publicación ya no está activa.', 409);
        }

        // Los ids de la interacción y de la bitácora salen del último + 1: sus bloqueos deben durar hasta el COMMIT.
        $resultado = $this->interacciones->ejecutarConBloqueoAlta(fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
            function () use ($publicacionId, $tipo, $accion): array {
                $this->conexion->beginTransaction();
                try {
                    $interaccionId = $this->interacciones->registrar(
                        (int) $this->actor->personaId,
                        (int) $publicacionId,
                        $tipo,
                        'API_PUBLICACION_INTERACCIONES',
                        $accion,
                    );
                    $resultado = [
                        'interaccionId' => $interaccionId,
                        'publicacionId' => (int) $publicacionId,
                        'tipo' => $tipo,
                        'accion' => $accion,
                    ];
                    $this->bitacora->registrar(
                        $accion === 'RETIRAR' ? 'RETIRAR' : 'CREAR',
                        'PUBLICACION_INTERACCION:' . $interaccionId,
                        null,
                        $resultado,
                        $this->solicitudId,
                        entidad: 'PUBLICACION_INTERACCION',
                        origen: 'API_PUBLICACION_INTERACCIONES',
                    );
                    $this->conexion->commit();
                    return $resultado;
                } catch (Throwable $error) {
                    if ($this->conexion->inTransaction()) $this->conexion->rollBack();
                    throw $error;
                }
            },
        ));

        return $this->respuesta(true, $accion === 'RETIRAR' ? 'Publicación quitada de tu lista.' : 'Interacción guardada correctamente.', $resultado, 201);
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
