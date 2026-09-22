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

    /** @return array<int,array{fincaId:int,nombre:string}> */
    public function listarActivasConIds(int $productorId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincaid AS fincaId, tbfincanombre AS nombre
             FROM tbfinca
             WHERE tbproductorid = :productorId
               AND tbfincaestado = 1
             ORDER BY tbfincanombre'
        );
        $sentencia->execute(['productorId' => $productorId]);

        return array_map(
            static fn (array $fila): array => [
                'fincaId' => (int) $fila['fincaId'],
                'nombre' => $fila['nombre'],
            ],
            $sentencia->fetchAll(),
        );
    }

    public function bloquearPropia(int $fincaId, int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincaid AS fincaId, tbproductorid AS productorId,
                    tbfincanombre AS nombre, tbfincaestado AS estado
             FROM tbfinca
             WHERE tbfincaid = :fincaId
               AND tbproductorid = :productorId
             FOR UPDATE'
        );
        $sentencia->execute(['fincaId' => $fincaId, 'productorId' => $productorId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : [
            'fincaId' => (int) $fila['fincaId'],
            'productorId' => (int) $fila['productorId'],
            'nombre' => $fila['nombre'],
            'estado' => (int) $fila['estado'],
        ];
    }

    public function buscarIdPorNombre(int $productorId, string $nombre): ?int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincaid
             FROM tbfinca
             WHERE tbproductorid = :productorId
               AND tbfincanombre = :nombre
             ORDER BY tbfincaid
             LIMIT 2'
        );
        $sentencia->execute(['productorId' => $productorId, 'nombre' => $nombre]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);
        if (count($filas) > 1) {
            throw new \RuntimeException('Existen fincas duplicadas para el productor.');
        }

        return $filas === [] ? null : (int) $filas[0];
    }

    public function crear(int $productorId, string $nombre): int
    {
        $fincaId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbfinca
             (tbfincaid, tbproductorid, tbfincanombre, tbfincaestado)
             VALUES (:fincaId, :productorId, :nombre, 1)'
        );
        $sentencia->execute([
            'fincaId' => $fincaId,
            'productorId' => $productorId,
            'nombre' => $nombre,
        ]);

        return $fincaId;
    }

    public function cambiarEstado(int $fincaId, int $productorId, bool $activo): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbfinca SET tbfincaestado = :estado
             WHERE tbfincaid = :fincaId AND tbproductorid = :productorId'
        );
        $sentencia->execute([
            'estado' => $activo ? 1 : 0,
            'fincaId' => $fincaId,
            'productorId' => $productorId,
        ]);
    }

    public function actualizarNombre(int $fincaId, int $productorId, string $nombre): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbfinca SET tbfincanombre = :nombre
             WHERE tbfincaid = :fincaId AND tbproductorid = :productorId'
        );
        $sentencia->execute([
            'nombre' => $nombre,
            'fincaId' => $fincaId,
            'productorId' => $productorId,
        ]);
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

    public function buscarIdActivo(int $productorId, string $nombre): ?int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincaid FROM tbfinca
             WHERE tbproductorid = :productorId
               AND tbfincanombre = :nombre
               AND tbfincaestado = 1'
        );
        $sentencia->execute(['productorId' => $productorId, 'nombre' => $nombre]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) > 1) {
            throw new \RuntimeException('Existen fincas duplicadas para el productor.');
        }

        return $filas === [] ? null : (int) $filas[0];
    }
}
