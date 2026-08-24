<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Ubicación observada del productor (plan §9, §14-16). La tabla es
 * append-only: cada lectura GPS genera una fila nueva y ninguna fila se
 * actualiza ni se elimina, por lo que este modelo no expone UPDATE ni DELETE.
 */
final class ProductorUbicacion
{
    private const LOCK_ALTA = 'tindercows_productor_ubicacion_alta';
    private int $profundidadBloqueoAlta = 0;

    public function __construct(private readonly PDO $conexion) {}

    /**
     * Envuelve la operación bajo el lock global de alta de ubicaciones.
     * El llamador debe incluir dentro del callback toda la transacción que
     * contiene el cálculo MAX(tbproductorubicacionid)+1, el INSERT y su
     * COMMIT/ROLLBACK: liberar el lock antes del COMMIT permitiría que otra
     * conexión calculara el mismo ID con la tabla aún sin confirmar.
     */
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK_ALTA);
        $this->profundidadBloqueoAlta++;
        try {
            return $operacion();
        } finally {
            $this->profundidadBloqueoAlta--;
            NamedLock::release($this->conexion, self::LOCK_ALTA);
        }
    }

    /**
     * Append-only: única operación de escritura permitida. Exige que la
     * conexión ya posea el lock de alta para garantizar IDs consecutivos
     * incluso bajo escrituras simultáneas.
     */
    public function registrar(int $productorId, string $latitud, string $longitud,
        ?string $precision, string $origen): int
    {
        if ($this->profundidadBloqueoAlta <= 0) {
            throw new \LogicException(
                'La conexión debe poseer el lock de alta de ubicación antes de registrar la fila.'
            );
        }

        $ubicacionId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductorubicacion
             (tbproductorubicacionid, tbproductorid, tbproductorubicacionlatitud,
              tbproductorubicacionlongitud, tbproductorubicacionprecision,
              tbproductorubicacionfecha, tbproductorubicacionorigen)
             VALUES (:id, :productorId, :latitud, :longitud, :precision, :fecha, :origen)'
        );
        $sentencia->execute([
            'id' => $ubicacionId,
            'productorId' => $productorId,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'precision' => $precision,
            'fecha' => date('Y-m-d H:i:s'),
            'origen' => $origen,
        ]);

        return $ubicacionId;
    }

    /** Histórico paginado del productor, del más reciente al más antiguo. */
    public function listarPorProductor(int $productorId, int $pagina = 1, int $tamano = 25): array
    {
        $conteo = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductorubicacion WHERE tbproductorid = :productorId'
        );
        $conteo->execute(['productorId' => $productorId]);
        $total = (int) $conteo->fetchColumn();

        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorubicacion
             WHERE tbproductorid = :productorId
             ORDER BY tbproductorubicacionfecha DESC, tbproductorubicacionid DESC
             LIMIT :tamano OFFSET :desplazamiento'
        );
        $sentencia->bindValue(':productorId', $productorId, PDO::PARAM_INT);
        $sentencia->bindValue(':tamano', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();

        return [
            'total' => $total,
            'pagina' => $pagina,
            'tamanoPagina' => $tamano,
            'ubicaciones' => array_map(fn (array $fila): array => $this->mapear($fila), $filas),
        ];
    }

    /** Histórico del productor dentro de un rango de fechas, en orden cronológico. */
    public function listarPorPeriodo(int $productorId, string $desde, string $hasta): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorubicacion
             WHERE tbproductorid = :productorId
               AND tbproductorubicacionfecha BETWEEN :desde AND :hasta
             ORDER BY tbproductorubicacionfecha ASC, tbproductorubicacionid ASC'
        );
        $sentencia->bindValue(':productorId', $productorId, PDO::PARAM_INT);
        $sentencia->bindValue(':desde', $desde);
        $sentencia->bindValue(':hasta', $hasta);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();

        return [
            'total' => count($filas),
            'desde' => $desde,
            'hasta' => $hasta,
            'ubicaciones' => array_map(fn (array $fila): array => $this->mapear($fila), $filas),
        ];
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbproductorubicacionid), 0) + 1 FROM tbproductorubicacion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function mapear(array $fila): array
    {
        return [
            'tbproductorubicacionid' => (int) $fila['tbproductorubicacionid'],
            'tbproductorid' => (int) $fila['tbproductorid'],
            'tbproductorubicacionlatitud' => $fila['tbproductorubicacionlatitud'],
            'tbproductorubicacionlongitud' => $fila['tbproductorubicacionlongitud'],
            'tbproductorubicacionprecision' => $fila['tbproductorubicacionprecision'],
            'tbproductorubicacionfecha' => $fila['tbproductorubicacionfecha'],
            'tbproductorubicacionorigen' => $fila['tbproductorubicacionorigen'],
        ];
    }
}
