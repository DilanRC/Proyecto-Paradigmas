<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;
use Application\Model\ProductorClasificacionPeriodo;
use PDO;

/**
 * Paso (b) del retiro de la tabla legacy de comprador (DEC-DBREADY-005/007).
 *
 * Traduce el alta/baja del CRUD heredado a periodos de
 * `tbproductorclasificacionperiodo`. Comprador es una clasificación del
 * Productor: si la persona no es productora no hay dónde clasificarla y esta
 * capa falla explícitamente en vez de inventar un `tbproductor`.
 *
 * Las operaciones son idempotentes por definición del periodo abierto: activar
 * dos veces deja un solo periodo abierto y desactivar dos veces no toca la
 * historia ya cerrada. Todo corre dentro de la transacción del llamador y bajo
 * el lock nombrado de clasificación por productor+tipo.
 */
final class CompradorClasificacionService
{
    public const TIPO = 'COMPRADOR';
    public const MOTIVO_ALTA = 'ALTA_CRUD_COMPRADOR';
    public const MOTIVO_REACTIVACION = 'REACTIVACION_CRUD_COMPRADOR';
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

    /**
     * Igual que productorDePersona(), pero convierte la ausencia en un error de
     * negocio explícito. Nunca crea el productor: esa decisión es de quien
     * administra los datos, no de una migración ni de un CRUD.
     */
    public function exigirProductor(int $personaId, string $identificacion): int
    {
        $productorId = $this->productorDePersona($personaId);
        if ($productorId === null) {
            throw new HttpException(
                'Comprador es una clasificación del Productor y esta persona no es productora. '
                . 'Registre primero al productor.',
                409,
                null,
                ['identificacion.numero' => "La persona {$identificacion} no tiene productor asociado."],
            );
        }

        return $productorId;
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
