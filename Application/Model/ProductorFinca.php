<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorFinca
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function sincronizar(string $identificacionNumero, array $nombres): void
    {
        if ($nombres === []) {
            $sentencia = $this->conexion->prepare(
                'UPDATE tbproductoresfinca SET tbproductoresfincaEstado = 0
                 WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
            );
            $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
            return;
        }

        $marcadores = implode(',', array_fill(0, count($nombres), '?'));
        $desactivar = $this->conexion->prepare(
            "UPDATE tbproductoresfinca SET tbproductoresfincaEstado = 0
             WHERE tbproductoresIdentificacionNumero = ?
               AND tbproductoresfincaNombre NOT IN ({$marcadores})"
        );
        $desactivar->execute([$identificacionNumero, ...$nombres]);

        $contar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductoresfinca
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero
               AND tbproductoresfincaNombre = :nombre'
        );
        $reactivar = $this->conexion->prepare(
            'UPDATE tbproductoresfinca SET tbproductoresfincaEstado = 1
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero
               AND tbproductoresfincaNombre = :nombre'
        );
        $asociar = $this->conexion->prepare(
            'INSERT INTO tbproductoresfinca
             (tbproductoresIdentificacionNumero, tbproductoresfincaNombre, tbproductoresfincaEstado)
             VALUES (:identificacionNumero, :nombre, 1)'
        );
        foreach ($nombres as $nombre) {
            $parametros = ['identificacionNumero' => $identificacionNumero, 'nombre' => $nombre];
            $contar->execute($parametros);
            $coincidencias = (int) $contar->fetchColumn();
            if ($coincidencias > 1) {
                throw new \RuntimeException('Existen fincas duplicadas para el productor.');
            }
            ($coincidencias === 1 ? $reactivar : $asociar)->execute($parametros);
        }
    }

    public function listarActivas(string $identificacionNumero): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbproductoresfincaNombre AS nombre
             FROM tbproductoresfinca
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero
               AND tbproductoresfincaEstado = 1
             ORDER BY tbproductoresfincaNombre'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);

        return $sentencia->fetchAll();
    }

    public function listarPorProductores(array $identificaciones): array
    {
        if ($identificaciones === []) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($identificaciones), '?'));
        $sentencia = $this->conexion->prepare(
            "SELECT tbproductoresIdentificacionNumero, tbproductoresfincaNombre AS nombre
             FROM tbproductoresfinca
             WHERE tbproductoresIdentificacionNumero IN ({$marcadores})
               AND tbproductoresfincaEstado = 1
             ORDER BY tbproductoresfincaNombre"
        );
        $sentencia->execute($identificaciones);
        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[$fila['tbproductoresIdentificacionNumero']][] = ['nombre' => $fila['nombre']];
        }

        return $resultado;
    }
}
