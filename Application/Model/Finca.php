<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Finca
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function listarActivas(): array
    {
        $sentencia = $this->conexion->query(
            'SELECT tbfincaId AS fincaId, tbfincaNombre AS nombre FROM tbfinca WHERE tbfincaEstado = 1 ORDER BY tbfincaNombre'
        );

        return $sentencia->fetchAll();
    }

    public function bloquearActivas(array $fincaIds): bool
    {
        if ($fincaIds === []) {
            return true;
        }
        $marcadores = implode(',', array_fill(0, count($fincaIds), '?'));
        $sentencia = $this->conexion->prepare(
            "SELECT tbfincaId FROM tbfinca WHERE tbfincaId IN ({$marcadores}) AND tbfincaEstado = 1 FOR UPDATE"
        );
        $sentencia->execute($fincaIds);

        return count($sentencia->fetchAll(PDO::FETCH_COLUMN)) === count($fincaIds);
    }
}
