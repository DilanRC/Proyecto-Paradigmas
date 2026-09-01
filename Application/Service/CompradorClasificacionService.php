<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Model\ProductorClasificacionPeriodo;
use PDO;

/**
 * Escritura de la clasificación COMPRADOR (DEC-DBREADY-005/007/008).
 *
 * Único punto por el que se abre o cierra un periodo COMPRADOR. Hoy su único
 * llamador es el backfill; cuando exista T10, el algoritmo que derive la
 * clasificación del comportamiento debe entrar por aquí y no escribir la tabla
 * a mano. Tras el paso (d) no queda ningún CRUD que clasifique: la
 * clasificación dejó de ser una decisión administrativa.
 *
 * Nunca crea `tbproductor`: si la persona no es productora, no hay dónde
 * clasificarla y quien llama decide qué hacer con ese caso.
 *
 * Las operaciones son idempotentes por definición del periodo abierto: activar
 * dos veces deja un solo periodo abierto y desactivar dos veces no toca la
 * historia ya cerrada. Todo corre dentro de la transacción del llamador y bajo
 * el lock nombrado de clasificación por productor+tipo.
 */
final class CompradorClasificacionService
{
    public const TIPO = 'COMPRADOR';
    public const MOTIVO_MIGRACION = 'MIGRACION_TBCOMPRADOR_LEGACY';

    private ProductorClasificacionPeriodo $clasificacion;

    public function __construct(private readonly PDO $conexion)
    {
        $this->clasificacion = new ProductorClasificacionPeriodo($conexion);
    }

    /** Productor de una persona, o null si esa persona no es productora. */
    public function productorDePersona(int $personaId): ?int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbproductorid FROM tbproductor WHERE tbpersonaid = :personaId'
        );
        $sentencia->execute(['personaId' => $personaId]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('La persona conserva más de un productor.');
        }

        return $filas === [] ? null : (int) $filas[0]['tbproductorid'];
    }

    /** Abre el periodo COMPRADOR si no hay uno abierto. Devuelve si abrió. */
    public function activar(int $productorId, string $motivo): bool
    {
        return (bool) $this->clasificacion->ejecutarConBloqueo(
            $productorId,
            self::TIPO,
            function () use ($productorId, $motivo): bool {
                if ($this->clasificacion->esComprador($productorId)) {
                    return false;
                }
                $this->clasificacion->abrir($productorId, self::TIPO, $motivo);

                return true;
            },
        );
    }

    /** Cierra el periodo COMPRADOR abierto si existe. Devuelve si cerró. */
    public function desactivar(int $productorId): bool
    {
        return (bool) $this->clasificacion->ejecutarConBloqueo(
            $productorId,
            self::TIPO,
            function () use ($productorId): bool {
                if (!$this->clasificacion->esComprador($productorId)) {
                    return false;
                }
                $this->clasificacion->cerrar($productorId, self::TIPO);

                return true;
            },
        );
    }

    public function esComprador(int $productorId): bool
    {
        return $this->clasificacion->esComprador($productorId);
    }
}
