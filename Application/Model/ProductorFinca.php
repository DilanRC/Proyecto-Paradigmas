<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorFinca
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function sincronizar(int $productorId, array $nombres): void
    {
        if ($nombres === []) {
            $sentencia = $this->conexion->prepare(
                'UPDATE tbproductorfinca SET tbproductorfincaEstado = 0
                 WHERE tbproductorId = :productorId'
            );
            $sentencia->execute(['productorId' => $productorId]);
            return;
        }

        $marcadores = implode(',', array_fill(0, count($nombres), '?'));
        $desactivar = $this->conexion->prepare(
            "UPDATE tbproductorfinca SET tbproductorfincaEstado = 0
             WHERE tbproductorId = ?
               AND tbproductorfincaNombre NOT IN ({$marcadores})"
        );
        $desactivar->execute([$productorId, ...$nombres]);

        $contar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductorfinca
             WHERE tbproductorId = :productorId
               AND tbproductorfincaNombre = :nombre'
        );
        $reactivar = $this->conexion->prepare(
            'UPDATE tbproductorfinca SET tbproductorfincaEstado = 1
             WHERE tbproductorId = :productorId
               AND tbproductorfincaNombre = :nombre'
        );
        $asociar = $this->conexion->prepare(
            'INSERT INTO tbproductorfinca
             (tbproductorId, tbproductorfincaNombre, tbproductorfincaEstado)
             VALUES (:productorId, :nombre, 1)'
        );
        foreach ($nombres as $nombre) {
            $parametros = ['productorId' => $productorId, 'nombre' => $nombre];
            $contar->execute($parametros);
            $coincidencias = (int) $contar->fetchColumn();
            if ($coincidencias > 1) {
                throw new \RuntimeException('Existen fincas duplicadas para el productor.');
            }
            ($coincidencias === 1 ? $reactivar : $asociar)->execute($parametros);
        }
    }

    public function listarActivas(int $productorId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbproductorfincaNombre AS nombre
             FROM tbproductorfinca
             WHERE tbproductorId = :productorId
               AND tbproductorfincaEstado = 1
             ORDER BY tbproductorfincaNombre'
        );
        $sentencia->execute(['productorId' => $productorId]);

        return $sentencia->fetchAll();
    }

    public function listarPorProductores(array $productorIds): array
    {
        if ($productorIds === []) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($productorIds), '?'));
        $sentencia = $this->conexion->prepare(
            "SELECT tbproductorId, tbproductorfincaNombre AS nombre
             FROM tbproductorfinca
             WHERE tbproductorId IN ({$marcadores})
               AND tbproductorfincaEstado = 1
             ORDER BY tbproductorfincaNombre"
        );
        $sentencia->execute($productorIds);
        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[(int) $fila['tbproductorId']][] = ['nombre' => $fila['nombre']];
        }

        return $resultado;
    }
}
