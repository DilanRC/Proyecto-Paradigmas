<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$raiz = dirname(__DIR__);
foreach (['NamedLock', 'Vehiculo', 'Transportista', 'TransportistaVehiculo'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}

use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use Application\Model\Vehiculo;
use PDO;

function transporte_try_lock(PDO $conexion, string $nombre): int
{
    $sentencia = $conexion->prepare('SELECT GET_LOCK(:nombre, 0)');
    $sentencia->execute(['nombre' => $nombre]);
    return (int) $sentencia->fetchColumn();
}

function transporte_release_lock(PDO $conexion, string $nombre): void
{
    $sentencia = $conexion->prepare('SELECT RELEASE_LOCK(:nombre)');
    $sentencia->execute(['nombre' => $nombre]);
}

function transporte_connection_id(PDO $conexion): int
{
    return (int) $conexion->query('SELECT CONNECTION_ID()')->fetchColumn();
}

function transporte_transaccion(PDO $conexion, callable $operacion): mixed
{
    $conexion->beginTransaction();
    try {
        $resultado = $operacion();
        $conexion->commit();
        return $resultado;
    } catch (Throwable $excepcion) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $excepcion;
    }
}

$conn1 = test_new_db();
$conn2 = test_new_db();
test_assert(
    transporte_connection_id($conn1) !== transporte_connection_id($conn2),
    'La prueba de concurrencia debe usar dos conexiones MySQL realmente independientes.'
);

$vehiculo1 = new Vehiculo($conn1);
$vehiculo2 = new Vehiculo($conn2);
$tv1 = new TransportistaVehiculo($conn1);
$tv2 = new TransportistaVehiculo($conn2);
$transportista1 = new Transportista($conn1, $tv1);
$transportista2 = new Transportista($conn2, $tv2);

$vehiculoIds = [];
$transportistaIds = [];

try {
    // Vehiculo: la segunda conexión no puede adquirir el lock ni antes ni
    // inmediatamente después del COMMIT, mientras el wrapper aún no terminó.
    $vehiculoId1 = $vehiculo1->ejecutarConBloqueoAlta(function () use ($conn1, $conn2, $vehiculo1): int {
        $conn1->beginTransaction();
        try {
            $id = $vehiculo1->crear([
                'placa' => 'CONC-V1-' . strtoupper(bin2hex(random_bytes(3))),
                'vin' => 'VIN-CONC-V1-' . bin2hex(random_bytes(4)),
                'modelo' => 'Concurrencia Vehiculo 1',
            ]);
            test_same(0, transporte_try_lock($conn2, 'tindercows_vehiculo_alta'),
                'Otra conexión no debe adquirir el lock de vehículo antes del COMMIT');
            $conn1->commit();
            test_same(0, transporte_try_lock($conn2, 'tindercows_vehiculo_alta'),
                'El lock de vehículo debe seguir retenido después del COMMIT hasta salir del wrapper');
            return $id;
        } catch (Throwable $excepcion) {
            if ($conn1->inTransaction()) {
                $conn1->rollBack();
            }
            throw $excepcion;
        }
    });
    $vehiculoIds[] = $vehiculoId1;
    test_same(1, transporte_try_lock($conn2, 'tindercows_vehiculo_alta'),
        'El lock de vehículo debe liberarse al terminar el wrapper');
    transporte_release_lock($conn2, 'tindercows_vehiculo_alta');

    $vehiculoId2 = $vehiculo2->ejecutarConBloqueoAlta(
        fn (): int => transporte_transaccion($conn2, fn (): int => $vehiculo2->crear([
            'placa' => 'CONC-V2-' . strtoupper(bin2hex(random_bytes(3))),
            'vin' => 'VIN-CONC-V2-' . bin2hex(random_bytes(4)),
            'modelo' => 'Concurrencia Vehiculo 2',
        ]))
    );
    $vehiculoIds[] = $vehiculoId2;
    test_assert($vehiculoId1 !== $vehiculoId2, 'Dos altas protegidas no pueden producir el mismo tbvehiculoid.');

    // Transportista: misma garantía sobre MAX(id)+1 y duración del lock.
    $identificacion1 = 'CONCT1' . strtoupper(bin2hex(random_bytes(4)));
    $transportistaId1 = $transportista1->ejecutarConBloqueoAlta(function () use (
        $conn1, $conn2, $transportista1, $identificacion1
    ): int {
        $conn1->beginTransaction();
        try {
            $id = $transportista1->crear([
                'identificacionNumero' => $identificacion1,
                'identificacionTipo' => 'PASAPORTE',
                'nombre' => 'Transportista Concurrencia 1',
                'telefono' => '88888888',
                'correoElectronico' => 'conc1@test.com',
            ]);
            test_same(0, transporte_try_lock($conn2, 'tindercows_transportista_alta'),
                'Otra conexión no debe adquirir el lock de transportista antes del COMMIT');
            $conn1->commit();
            test_same(0, transporte_try_lock($conn2, 'tindercows_transportista_alta'),
                'El lock de transportista debe seguir retenido después del COMMIT hasta salir del wrapper');
            return $id;
        } catch (Throwable $excepcion) {
            if ($conn1->inTransaction()) {
                $conn1->rollBack();
            }
            throw $excepcion;
        }
    });
    $transportistaIds[] = $transportistaId1;
    test_same(1, transporte_try_lock($conn2, 'tindercows_transportista_alta'),
        'El lock de transportista debe liberarse al terminar el wrapper');
    transporte_release_lock($conn2, 'tindercows_transportista_alta');

    $identificacion2 = 'CONCT2' . strtoupper(bin2hex(random_bytes(4)));
    $transportistaId2 = $transportista2->ejecutarConBloqueoAlta(
        fn (): int => transporte_transaccion($conn2, fn (): int => $transportista2->crear([
            'identificacionNumero' => $identificacion2,
            'identificacionTipo' => 'PASAPORTE',
            'nombre' => 'Transportista Concurrencia 2',
            'telefono' => '87777777',
            'correoElectronico' => 'conc2@test.com',
        ]))
    );
    $transportistaIds[] = $transportistaId2;
    test_assert($transportistaId1 !== $transportistaId2,
        'Dos altas protegidas no pueden producir el mismo tbtransportistaid.');

    // Relación: el lock impide competir por el mismo MAX(id)+1 y la política
    // rechaza una segunda asignación del mismo vehículo después de serializar.
    $tv1->ejecutarConBloqueoAlta(function () use (
        $conn1, $conn2, $tv1, $transportistaId1, $vehiculoId1
    ): void {
        $conn1->beginTransaction();
        try {
            $tv1->asignar($transportistaId1, $vehiculoId1);
            test_same(0, transporte_try_lock($conn2, 'tindercows_transportista_vehiculo_alta'),
                'Otra conexión no debe adquirir el lock de la relación antes del COMMIT');
            $conn1->commit();
            test_same(0, transporte_try_lock($conn2, 'tindercows_transportista_vehiculo_alta'),
                'El lock de la relación debe seguir retenido después del COMMIT hasta salir del wrapper');
        } catch (Throwable $excepcion) {
            if ($conn1->inTransaction()) {
                $conn1->rollBack();
            }
            throw $excepcion;
        }
    });
    test_same(1, transporte_try_lock($conn2, 'tindercows_transportista_vehiculo_alta'),
        'El lock de la relación debe liberarse al terminar el wrapper');
    transporte_release_lock($conn2, 'tindercows_transportista_vehiculo_alta');

    $rechazada = false;
    try {
        $tv2->ejecutarConBloqueoAlta(
            fn (): mixed => transporte_transaccion(
                $conn2,
                fn (): mixed => $tv2->asignar($transportistaId2, $vehiculoId1)
            )
        );
    } catch (RuntimeException $excepcion) {
        $rechazada = true;
        test_assert(str_contains($excepcion->getMessage(), 'ya está asignado'),
            'La segunda asignación debe rechazarse por la política de un transportista por vehículo.');
    }
    test_same(true, $rechazada, 'Un vehículo no puede quedar asignado a dos transportistas.');

    $conteo = $conn1->prepare('SELECT COUNT(*) FROM tbtransportistavehiculo WHERE tbvehiculoid = :id');
    $conteo->execute(['id' => $vehiculoId1]);
    test_same(1, (int) $conteo->fetchColumn(), 'Debe persistir exactamente un enlace para el vehículo.');
} finally {
    foreach ($vehiculoIds as $vehiculoId) {
        $conn1->prepare('DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid = :id')->execute(['id' => $vehiculoId]);
        $conn1->prepare('DELETE FROM tbvehiculo WHERE tbvehiculoid = :id')->execute(['id' => $vehiculoId]);
    }
    foreach ($transportistaIds as $transportistaId) {
        $conn1->prepare('DELETE FROM tbtransportistavehiculo WHERE tbtransportistaid = :id')->execute(['id' => $transportistaId]);
        $conn1->prepare('DELETE FROM tbtransportista WHERE tbtransportistaid = :id')->execute(['id' => $transportistaId]);
    }
}

echo "OK concurrencia_transporte_test: dos conexiones reales validan duración de locks, IDs únicos y asignación exclusiva.\n";
