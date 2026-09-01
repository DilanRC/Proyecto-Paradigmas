<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Clasificación comercial histórica del productor.
 *
 * Productor es la entidad núcleo; COMPRADOR y VENDEDOR son clasificaciones
 * temporales independientes. El motor no tiene CHECK ni UNIQUE: PHP conserva
 * el vocabulario aprobado y evita periodos abiertos duplicados por
 * productor+tipo bajo locks nombrados.
 */
final class ProductorClasificacionPeriodo
{
    private const TIPOS = ['COMPRADOR', 'VENDEDOR'];
    private const PREFIJO_LOCK = 'tindercows_productor_clasificacion_';
    private const LOCK_ALTA = 'tindercows_productor_clasificacion_alta';
    private int $profundidadBloqueo = 0;
    private string $lockActual = '';

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueo(int $productorId, string $tipo, callable $operacion): mixed
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $lockEntidad = self::PREFIJO_LOCK . $productorId . '_' . $tipoNormalizado;

        // Orden fijo: entidad+tipo, despues alta global. El primero protege
        // la invariante local; el segundo protege MAX(id)+1 de toda la tabla.
        NamedLock::acquire($this->conexion, $lockEntidad);
        try {
            NamedLock::acquire($this->conexion, self::LOCK_ALTA);
            $this->profundidadBloqueo++;
            $this->lockActual = $lockEntidad;
            try {
                return $operacion($tipoNormalizado);
            } finally {
                $this->profundidadBloqueo--;
                $this->lockActual = '';
                NamedLock::release($this->conexion, self::LOCK_ALTA);
            }
        } finally {
            NamedLock::release($this->conexion, $lockEntidad);
        }
    }

    public function abrir(int $productorId, string $tipo, ?string $motivo): int
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $this->exigirLock($productorId, $tipoNormalizado);
        if ($this->consultarAbierto($productorId, $tipoNormalizado) !== null) {
            throw new \RuntimeException('El productor ya tiene esa clasificación abierta.');
        }

        $periodoId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductorclasificacionperiodo
             (tbproductorclasificacionperiodoid, tbproductorid, tbproductorclasificacionperiodotipo,
              tbproductorclasificacionperiodofechainicio, tbproductorclasificacionperiodofechafin,
              tbproductorclasificacionperiodomotivo)
             VALUES (:id, :productorId, :tipo, :fechaInicio, NULL, :motivo)'
        );
        $sentencia->execute([
            'id' => $periodoId,
            'productorId' => $productorId,
            'tipo' => $tipoNormalizado,
            'fechaInicio' => date('Y-m-d H:i:s'),
            'motivo' => $motivo,
        ]);

        return $periodoId;
    }

    public function cerrar(int $productorId, string $tipo): void
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $this->exigirLock($productorId, $tipoNormalizado);
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductorclasificacionperiodo
             SET tbproductorclasificacionperiodofechafin = :fechaFin
             WHERE tbproductorid = :productorId
               AND tbproductorclasificacionperiodotipo = :tipo
               AND tbproductorclasificacionperiodofechafin IS NULL'
        );
        $sentencia->execute([
            'fechaFin' => date('Y-m-d H:i:s'),
            'productorId' => $productorId,
            'tipo' => $tipoNormalizado,
        ]);
        if ($sentencia->rowCount() !== 1) {
            throw new \RuntimeException('Cerrar clasificación debía afectar exactamente una fila.');
        }
    }

    public function consultarAbierto(int $productorId, string $tipo): ?array
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorclasificacionperiodo
             WHERE tbproductorid = :productorId
               AND tbproductorclasificacionperiodotipo = :tipo
               AND tbproductorclasificacionperiodofechafin IS NULL'
        );
        $sentencia->execute(['productorId' => $productorId, 'tipo' => $tipoNormalizado]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('El productor conserva clasificaciones abiertas duplicadas.');
        }

        return $filas === [] ? null : $this->mapear($filas[0]);
    }

    public function listarAbiertas(int $productorId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorclasificacionperiodo
             WHERE tbproductorid = :productorId
               AND tbproductorclasificacionperiodofechafin IS NULL
             ORDER BY tbproductorclasificacionperiodotipo ASC'
        );
        $sentencia->execute(['productorId' => $productorId]);

        return array_map(fn (array $fila): array => $this->mapear($fila), $sentencia->fetchAll());
    }

    private function exigirLock(int $productorId, string $tipo): void
    {
        $esperado = self::PREFIJO_LOCK . $productorId . '_' . $tipo;
        if ($this->profundidadBloqueo <= 0 || $this->lockActual !== $esperado) {
            throw new \LogicException('La conexión debe poseer el lock de clasificación antes de modificar periodos.');
        }
        if (!$this->conexion->inTransaction()) {
            throw new \LogicException('La modificación de clasificación debe ejecutarse dentro de una transacción.');
        }
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbproductorclasificacionperiodoid
             FROM tbproductorclasificacionperiodo
             ORDER BY tbproductorclasificacionperiodoid DESC
             LIMIT 1 FOR UPDATE'
        );
        $sentencia->execute();
        $maximo = $sentencia->fetchColumn();

        return $maximo === false ? 1 : ((int) $maximo) + 1;
    }

    private function normalizarTipo(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Clasificación de productor no aprobada.');
        }

        return $tipo;
    }

    private function mapear(array $fila): array
    {
        return [
            'tbproductorclasificacionperiodoid' => (int) $fila['tbproductorclasificacionperiodoid'],
            'tbproductorid' => (int) $fila['tbproductorid'],
            'tbproductorclasificacionperiodotipo' => $fila['tbproductorclasificacionperiodotipo'],
            'tbproductorclasificacionperiodofechainicio' => $fila['tbproductorclasificacionperiodofechainicio'],
            'tbproductorclasificacionperiodofechafin' => $fila['tbproductorclasificacionperiodofechafin'],
            'tbproductorclasificacionperiodomotivo' => $fila['tbproductorclasificacionperiodomotivo'],
        ];
    }
}
