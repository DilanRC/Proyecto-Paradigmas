<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Vehiculo
{
    public function __construct(private readonly PDO $conexion) {}

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbvehiculo v {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT v.* FROM tbvehiculo v
                {$where}
                ORDER BY v.tbvehiculoestado DESC, v.tbvehiculoplaca
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
            'vehiculos' => array_map(fn (array $fila): array => $this->mapear($fila), $filas),
            'total' => $total,
        ];
    }

    public function buscarPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare('SELECT * FROM tbvehiculo WHERE tbvehiculoid = :id');
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    public function bloquearPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare('SELECT * FROM tbvehiculo WHERE tbvehiculoid = :id FOR UPDATE');
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
        NamedLock::acquire($this->conexion, 'tindercows_vehiculo_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_vehiculo_alta');
    }

    public function crear(array $datos): int
    {
        $vehiculoId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbvehiculo (tbvehiculoid, tbvehiculoplaca, tbvehiculovin, tbvehiculomodelo, tbvehiculoestado)
             VALUES (:vehiculoId, :placa, :vin, :modelo, :estado)'
        );
        $sentencia->execute([
            'vehiculoId' => $vehiculoId,
            'placa' => $datos['placa'],
            'vin' => $datos['vin'],
            'modelo' => $datos['modelo'],
            'estado' => 1,
        ]);

        return $vehiculoId;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbvehiculoid), 0) + 1 FROM tbvehiculo');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(int $id, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbvehiculo
             SET tbvehiculoplaca = :placa, tbvehiculovin = :vin, tbvehiculomodelo = :modelo
             WHERE tbvehiculoid = :id'
        );
        $sentencia->execute([
            'id' => $id,
            'placa' => $datos['placa'],
            'vin' => $datos['vin'],
            'modelo' => $datos['modelo'],
        ]);
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbvehiculo SET tbvehiculoestado = :estado WHERE tbvehiculoid = :id'
        );
        $sentencia->execute(['estado' => $activo ? 1 : 0, 'id' => $id]);
    }

    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(v.tbvehiculoplaca LIKE :busquedaPlaca
                OR v.tbvehiculovin LIKE :busquedaVin
                OR v.tbvehiculomodelo LIKE :busquedaModelo)';
            $parametros = [
                ':busquedaPlaca' => "%{$busqueda}%",
                ':busquedaVin' => "%{$busqueda}%",
                ':busquedaModelo' => "%{$busqueda}%",
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'v.tbvehiculoestado = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila): array
    {
        return [
            'vehiculoId' => (int) $fila['tbvehiculoid'],
            'placa' => $fila['tbvehiculoplaca'],
            'vin' => $fila['tbvehiculovin'],
            'modelo' => $fila['tbvehiculomodelo'],
            'estado' => (int) $fila['tbvehiculoestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
        ];
    }
}