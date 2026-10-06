<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalComercial;
use Application\Model\Bitacora;
use PDO;
use Throwable;

/**
 * Moderación de publicaciones para el administrador: lista todas (con estado,
 * vendedor y finca) y permite pausar, retirar o reactivar con un motivo. La
 * autorización de administrador la exige el endpoint antes de llegar aquí.
 */
final class AdminPublicacionController
{
    private const ESTADOS_FILTRO = ['TODOS', 'ACTIVO', 'PAUSADO', 'VENDIDO', 'RETIRADO'];
    private const ESTADOS_MODERABLES = ['ACTIVO', 'PAUSADO', 'RETIRADO'];
    /** Una publicación vendida o retirada ya es final; solo ACTIVA y PAUSADA se moderan. */
    private const ESTADOS_ABIERTOS = ['ACTIVO', 'PAUSADO'];

    private readonly AnimalComercial $animales;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null,
        ?ActorContext $actor = null)
    {
        $this->animales = new AnimalComercial($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->listar($consulta),
                'PATCH' => $this->moderar($cuerpo),
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
        $busqueda = $consulta['q'] ?? '';
        if (!is_string($busqueda) || mb_strlen($busqueda) > 150) {
            throw new HttpException('La búsqueda no es válida.', 422, null, ['q' => 'Use hasta 150 caracteres.']);
        }
        $estado = mb_strtoupper(trim((string) ($consulta['estado'] ?? 'TODOS')), 'UTF-8');
        if (!in_array($estado, self::ESTADOS_FILTRO, true)) {
            throw new HttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use ' . implode(', ', self::ESTADOS_FILTRO) . '.',
            ]);
        }
        $pagina = filter_var($consulta['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tamano = filter_var($consulta['tamanoPagina'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($pagina === false || $tamano === false) {
            throw new HttpException('La paginación no es válida.', 422, null, [
                'pagina' => 'Debe ser un entero positivo.',
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->animales->listarPublicaciones(trim($busqueda), $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Publicaciones consultadas correctamente.', $resultado);
    }

    private function moderar(array $cuerpo): array
    {
        $publicacionId = filter_var($cuerpo['publicacionId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($publicacionId === false) {
            throw new HttpException('publicacionId debe ser un entero positivo.', 422);
        }
        $estado = mb_strtoupper(trim((string) ($cuerpo['estado'] ?? '')), 'UTF-8');
        if (!in_array($estado, self::ESTADOS_MODERABLES, true)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                'estado' => 'Use ' . implode(', ', self::ESTADOS_MODERABLES) . '.',
            ]);
        }
        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));
        if (mb_strlen($motivo) > 250) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['motivo' => 'No puede superar 250 caracteres.']);
        }
        if ($estado !== 'ACTIVO' && $motivo === '') {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                'motivo' => 'Indique el motivo para pausar o retirar la publicación.',
            ]);
        }

        // Los ids del periodo de estado y de la bitácora salen del último + 1: sus bloqueos deben durar hasta el COMMIT.
        $nueva = $this->animales->ejecutarConBloqueoAlta('tbanimalpublicacionestadoperiodo', fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
            function () use ($publicacionId, $estado, $motivo): array {
                $this->conexion->beginTransaction();
                try {
                    $anterior = $this->animales->buscarPublicacion((int) $publicacionId);
                    if ($anterior === null) {
                        throw new HttpException('La publicación no existe.', 404);
                    }
                    if (!in_array($anterior['estado'], self::ESTADOS_ABIERTOS, true)) {
                        throw new HttpException('La publicación ya está cerrada y no admite cambios.', 409);
                    }
                    if ($anterior['estado'] !== $estado) {
                        $this->animales->cambiarEstadoPublicacion(
                            (int) $publicacionId, $estado, $motivo === '' ? null : $motivo, 'API_ADMIN_PUBLICACIONES'
                        );
                        $nueva = $this->animales->buscarPublicacion((int) $publicacionId);
                        $this->bitacora->registrar(
                            'MODERAR',
                            'PUBLICACION:' . $publicacionId,
                            $anterior,
                            $nueva + ['motivo' => $motivo === '' ? null : $motivo],
                            $this->solicitudId,
                            entidad: 'PUBLICACION',
                            origen: 'API_ADMIN_PUBLICACIONES',
                        );
                    } else {
                        $nueva = $anterior;
                    }
                    $this->conexion->commit();
                    return $nueva;
                } catch (Throwable $error) {
                    if ($this->conexion->inTransaction()) {
                        $this->conexion->rollBack();
                    }
                    throw $error;
                }
            },
        ));

        return $this->respuesta(true, 'Publicación actualizada correctamente.', ['publicacion' => $nueva]);
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
