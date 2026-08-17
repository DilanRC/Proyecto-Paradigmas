<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class PagoMetodo
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbpagometodo pm {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT pm.* FROM tbpagometodo pm
                {$where}
                ORDER BY pm.tbpagometodoactivo DESC, pm.tbpagometodonombre
                LIMIT :limite OFFSET :desplazamiento";
        $sentencia = $this->conexion->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();

        return [
            'pagoMetodos' => array_map(fn (array $fila): array => $this->mapear($fila), $filas),
            'total' => $total,
        ];
    }

    public function buscarPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbpagometodo WHERE tbpagometodoid = :id'
        );
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    public function bloquearPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbpagometodo
             WHERE tbpagometodoid = :id FOR UPDATE'
        );
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        try {
            return $operacion();
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_pagometodo_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_pagometodo_alta');
    }

    public function crear(array $datos): int
    {
        $pagoMetodoId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbpagometodo
             (tbpagometodoid, tbpagometodonombre, tbpagometododescripcion, tbpagometodoactivo)
             VALUES (:pagoMetodoId, :nombre, :descripcion, :activo)'
        );
        $sentencia->execute([
            'pagoMetodoId' => $pagoMetodoId,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'activo' => $datos['activo'] ? 1 : 0,
        ]);

        return $pagoMetodoId;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbpagometodoid), 0) + 1 FROM tbpagometodo');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(int $id, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbpagometodo
             SET tbpagometodonombre = :nombre,
                 tbpagometododescripcion = :descripcion
             WHERE tbpagometodoid = :id'
        );
        $sentencia->execute([
            'id' => $id,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
        ]);
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbpagometodo SET tbpagometodoactivo = :activo
             WHERE tbpagometodoid = :id'
        );
        $sentencia->execute([
            'activo' => $activo ? 1 : 0,
            'id' => $id,
        ]);
    }

    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(pm.tbpagometodonombre LIKE :busquedaNombre
                OR pm.tbpagometododescripcion LIKE :busquedaDescripcion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaDescripcion' => "%{$busqueda}%",
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'pm.tbpagometodoactivo = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila): array
    {
        return [
            'pagoMetodoId' => (int) $fila['tbpagometodoid'],
            'nombre' => $fila['tbpagometodonombre'],
            'descripcion' => $fila['tbpagometododescripcion'],
            'activo' => (int) $fila['tbpagometodoactivo'] === 1,
            'estado' => (int) $fila['tbpagometodoactivo'] === 1 ? 'ACTIVO' : 'INACTIVO',
        ];
    }
}