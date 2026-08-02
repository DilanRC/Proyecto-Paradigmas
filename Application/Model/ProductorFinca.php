<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorFinca
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function sincronizar(int $participanteId, array $fincaIds): void
    {
        if ($fincaIds === []) {
            $sentencia = $this->conexion->prepare(
                'UPDATE tbproductorfinca SET tbproductorfincaEstado = 0 WHERE tbparticipanteId = :participanteId'
            );
            $sentencia->execute(['participanteId' => $participanteId]);
            return;
        }

        $marcadores = implode(',', array_fill(0, count($fincaIds), '?'));
        $desactivar = $this->conexion->prepare(
            "UPDATE tbproductorfinca SET tbproductorfincaEstado = 0
             WHERE tbparticipanteId = ? AND tbfincaId NOT IN ({$marcadores})"
        );
        $desactivar->execute([$participanteId, ...$fincaIds]);

        $asociar = $this->conexion->prepare(
            'INSERT INTO tbproductorfinca (tbparticipanteId, tbfincaId, tbproductorfincaEstado)
             VALUES (:participanteId, :fincaId, 1)
             ON DUPLICATE KEY UPDATE tbproductorfincaEstado = VALUES(tbproductorfincaEstado)'
        );
        foreach ($fincaIds as $fincaId) {
            $asociar->execute(['participanteId' => $participanteId, 'fincaId' => $fincaId]);
        }
    }

    public function listarIdsAsociadosActivos(int $participanteId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincaId FROM tbproductorfinca
             WHERE tbparticipanteId = :participanteId AND tbproductorfincaEstado = 1'
        );
        $sentencia->execute(['participanteId' => $participanteId]);

        return array_map('intval', $sentencia->fetchAll(PDO::FETCH_COLUMN));
    }

    public function listarPorParticipantes(array $participanteIds): array
    {
        if ($participanteIds === []) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($participanteIds), '?'));
        $sentencia = $this->conexion->prepare(
            "SELECT pf.tbparticipanteId, f.tbfincaId AS fincaId, f.tbfincaNombre AS nombre
             FROM tbproductorfinca pf
             INNER JOIN tbfinca f ON f.tbfincaId = pf.tbfincaId
             WHERE pf.tbparticipanteId IN ({$marcadores}) AND pf.tbproductorfincaEstado = 1
             ORDER BY f.tbfincaNombre"
        );
        $sentencia->execute($participanteIds);
        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[(int) $fila['tbparticipanteId']][] = [
                'fincaId' => (int) $fila['fincaId'],
                'nombre' => $fila['nombre'],
            ];
        }

        return $resultado;
    }
}
