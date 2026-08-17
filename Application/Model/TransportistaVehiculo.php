<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class TransportistaVehiculo
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

    /**
     * Enlaza un vehículo a un transportista. DEC-08: la política del modelo
     * espera un solo transportista por vehículo (el motor no lo impide), así
     * que se rechaza si el vehículo ya tiene un enlace, sea con este
     * transportista o con otro. Use reasignar() para moverlo.
     */
    public function asignar(int $transportistaId, int $vehiculoId): void
    {
        $existente = $this->buscarTransportistaDeVehiculo($vehiculoId);
        if ($existente !== null) {
            throw new \RuntimeException('El vehículo ya está asignado a un transportista; use reasignar.');
        }
        $enlaceId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportistavehiculo
             (tbtransportistavehiculoid, tbtransportistaid, tbvehiculoid)
             VALUES (:enlaceId, :transportistaId, :vehiculoId)'
        );
        $sentencia->execute(['enlaceId' => $enlaceId, 'transportistaId' => $transportistaId, 'vehiculoId' => $vehiculoId]);
    }

    /**
     * Mueve un vehículo a otro transportista: elimina cualquier enlace previo
     * (con cualquier transportista) y crea el nuevo. A diferencia de
     * FincaDireccion/ProductorDireccion, esta tabla no tiene columna de
     * estado: "reasignar" sustituye físicamente la fila de enlace, no la
     * desactiva, porque el enlace en sí no es una entidad con historia propia
     * (la persona y el vehículo sí conservan su propio estado lógico).
     */
    public function reasignar(int $transportistaId, int $vehiculoId): void
    {
        $this->eliminarEnlaceSiExiste($vehiculoId);
        $enlaceId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportistavehiculo
             (tbtransportistavehiculoid, tbtransportistaid, tbvehiculoid)
             VALUES (:enlaceId, :transportistaId, :vehiculoId)'
        );
        $sentencia->execute(['enlaceId' => $enlaceId, 'transportistaId' => $transportistaId, 'vehiculoId' => $vehiculoId]);
    }

    public function desasignar(int $vehiculoId): void
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbtransportistavehiculo WHERE tbvehiculoid = :vehiculoId'
        );
        $sentencia->execute(['vehiculoId' => $vehiculoId]);
        if ((int) $sentencia->fetchColumn() === 0) {
            throw new \RuntimeException('El vehículo no tiene un transportista asignado.');
        }
        $this->eliminarEnlaceSiExiste($vehiculoId);
    }

    public function buscarTransportistaDeVehiculo(int $vehiculoId): ?int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbtransportistaid FROM tbtransportistavehiculo WHERE tbvehiculoid = :vehiculoId'
        );
        $sentencia->execute(['vehiculoId' => $vehiculoId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) > 1) {
            throw new \RuntimeException(
                'El vehículo tiene más de un transportista asignado; revise la integridad de los datos.'
            );
        }

        return $filas === [] ? null : (int) $filas[0];
    }

    public function listarVehiculosPorTransportista(int $transportistaId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT v.tbvehiculoid AS vehiculoId, v.tbvehiculoplaca AS placa,
                    v.tbvehiculovin AS vin, v.tbvehiculomodelo AS modelo,
                    v.tbvehiculoestado AS estado
             FROM tbtransportistavehiculo tv
             INNER JOIN tbvehiculo v ON v.tbvehiculoid = tv.tbvehiculoid
             WHERE tv.tbtransportistaid = :transportistaId
             ORDER BY v.tbvehiculoplaca'
        );
        $sentencia->execute(['transportistaId' => $transportistaId]);

        return array_map(
            fn (array $fila): array => [
                'vehiculoId' => (int) $fila['vehiculoId'],
                'placa' => $fila['placa'],
                'vin' => $fila['vin'],
                'modelo' => $fila['modelo'],
                'estado' => (int) $fila['estado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            ],
            $sentencia->fetchAll(),
        );
    }

    private function eliminarEnlaceSiExiste(int $vehiculoId): void
    {
        $sentencia = $this->conexion->prepare(
            'DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid = :vehiculoId'
        );
        $sentencia->execute(['vehiculoId' => $vehiculoId]);
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbtransportistavehiculoid), 0) + 1 FROM tbtransportistavehiculo'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_transportista_vehiculo_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_transportista_vehiculo_alta');
    }
}