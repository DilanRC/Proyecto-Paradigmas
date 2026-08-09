<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorFinca
{
    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        try {
            return $operacion();
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    public function sincronizar(int $productorId, array $nombres): void
    {
        $this->sincronizarBloqueado($productorId, $nombres);
    }

    private function sincronizarBloqueado(int $productorId, array $nombres): void
    {
        if ($nombres === []) {
            $sentencia = $this->conexion->prepare(
                'UPDATE tbfinca SET tbfincaestado = :estado
                 WHERE tbproductorid = :productorId'
            );
            $sentencia->execute(['estado' => 0, 'productorId' => $productorId]);
            return;
        }

        $marcadores = implode(',', array_fill(0, count($nombres), '?'));
        $desactivar = $this->conexion->prepare(
            "UPDATE tbfinca SET tbfincaestado = ?
             WHERE tbproductorid = ?
               AND tbfincanombre NOT IN ({$marcadores})"
        );
        $desactivar->execute([0, $productorId, ...$nombres]);

        $contar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbfinca
             WHERE tbproductorid = :productorId
               AND tbfincanombre = :nombre'
        );
        $reactivar = $this->conexion->prepare(
            'UPDATE tbfinca SET tbfincaestado = :estado
             WHERE tbproductorid = :productorId
               AND tbfincanombre = :nombre'
        );
        $asociar = $this->conexion->prepare(
            'INSERT INTO tbfinca
             (tbfincaid, tbproductorid, tbfincanombre, tbfincaestado)
             VALUES (:fincaId, :productorId, :nombre, :estado)'
        );
        foreach ($nombres as $nombre) {
            $parametros = ['productorId' => $productorId, 'nombre' => $nombre];
            $contar->execute($parametros);
            $coincidencias = (int) $contar->fetchColumn();
            if ($coincidencias > 1) {
                throw new \RuntimeException('Existen fincas duplicadas para el productor.');
            }
            if ($coincidencias === 1) {
                $reactivar->execute(['estado' => 1, ...$parametros]);
                continue;
            }
            $asociar->execute(['fincaId' => $this->siguienteId(), 'estado' => 1, ...$parametros]);
        }
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbfincaid), 0) + 1 FROM tbfinca'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_finca_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_finca_alta');
    }

    public function listarActivas(int $productorId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincanombre AS nombre
             FROM tbfinca
             WHERE tbproductorid = :productorId
               AND tbfincaestado = 1
             ORDER BY tbfincanombre'
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
            "SELECT tbproductorid, tbfincanombre AS nombre
             FROM tbfinca
             WHERE tbproductorid IN ({$marcadores})
               AND tbfincaestado = 1
             ORDER BY tbfincanombre"
        );
        $sentencia->execute($productorIds);
        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[(int) $fila['tbproductorid']][] = ['nombre' => $fila['nombre']];
        }

        return $resultado;
    }
}
