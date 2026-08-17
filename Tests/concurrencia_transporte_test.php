<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$raiz = dirname(__DIR__);
foreach (['NamedLock', 'Vehiculo', 'Transportista', 'TransportistaVehiculo'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}

use Application\Model\Transportista;
use Application\Model\Vehiculo;
use Application\Model\TransportistaVehiculo;
use Configuration\Database;

echo "=== Prueba de concurrencia transporte ===\n";

$conn1 = Database::getConnection();
$conn2 = Database::getConnection();

$vehiculo1 = new Vehiculo($conn1);
$vehiculo2 = new Vehiculo($conn2);
$tv1 = new TransportistaVehiculo($conn1);
$tv2 = new TransportistaVehiculo($conn2);

// --- Test 1: IDs únicos bajo concurrencia en Vehiculo ---
$idsGenerados = [];
$errores = [];

for ($i = 0; $i < 4; $i++) {
    $modelo = ($i % 2 === 0) ? $vehiculo1 : $vehiculo2;

    try {
        $id = $modelo->ejecutarConBloqueoAlta(function () use ($modelo, $i): int {
            return $modelo->crear([
                'placa' => 'CONC-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'vin' => 'VIN-CONC-' . $i . '-' . bin2hex(random_bytes(2)),
                'modelo' => 'Test-Concurrencia',
            ]);
        });
        $idsGenerados[] = $id;
    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
    }
}

test_same([], $errores, 'No deben haber errores durante creación concurrente de vehículos');
test_same(
    count($idsGenerados),
    count(array_unique($idsGenerados)),
    'Los IDs de vehículo generados concurrentemente deben ser todos únicos'
);

// --- Test 2: IDs únicos bajo concurrencia en Transportista ---
$transportistaModel1 = new Transportista($conn1, $tv1);
$transportistaModel2 = new Transportista($conn2, $tv2);
$idsTransportista = [];
$erroresTransportista = [];

for ($i = 0; $i < 4; $i++) {
    $modelo = ($i % 2 === 0) ? $transportistaModel1 : $transportistaModel2;

    try {
        $id = $modelo->ejecutarConBloqueoAlta(function () use ($modelo, $i): int {
            return $modelo->crear([
                'identificacionNumero' => 'CONC-T-' . $i . '-' . bin2hex(random_bytes(3)),
                'identificacionTipo' => 'CEDULA_FISICA',
                'nombre' => 'Transportista Concurrencia ' . $i,
                'telefono' => '88888888',
                'correoElectronico' => "conc{$i}@test.com",
            ]);
        });
        $idsTransportista[] = $id;
    } catch (Throwable $e) {
        $erroresTransportista[] = $e->getMessage();
    }
}

test_same([], $erroresTransportista, 'No deben haber errores durante creación concurrente de transportistas');
test_same(
    count($idsTransportista),
    count(array_unique($idsTransportista)),
    'Los IDs de transportista generados concurrentemente deben ser todos únicos'
);

// --- Test 3: Asignación simultánea del mismo vehículo ---
$transportistaIdAsignacion = $idsTransportista[0];
$vehiculoIdAsignacion = $idsGenerados[0];

$falloEsperado = false;
try {
    $tv1->ejecutarConBloqueoAlta(
        fn () => $tv1->asignar($transportistaIdAsignacion, $vehiculoIdAsignacion)
    );

    $tv2->ejecutarConBloqueoAlta(
        fn () => $tv2->asignar($transportistaIdAsignacion, $vehiculoIdAsignacion)
    );
} catch (RuntimeException $e) {
    $falloEsperado = true;
    test_assert(
        str_contains($e->getMessage(), 'ya está asignado'),
        'La segunda asignación concurrente debe rechazar con mensaje claro: ' . $e->getMessage()
    );
}

test_same(true, $falloEsperado,
    'Debe ser imposible asignar el mismo vehículo dos veces concurrentemente');

// --- Limpieza ---
foreach ($idsGenerados as $vid) {
    $conn1->prepare('DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid = :id')->execute(['id' => $vid]);
    $conn1->prepare('DELETE FROM tbvehiculo WHERE tbvehiculoid = :id')->execute(['id' => $vid]);
}
foreach ($idsTransportista as $tid) {
    $conn1->prepare('DELETE FROM tbtransportistavehiculo WHERE tbtransportistaid = :id')->execute(['id' => $tid]);
    $conn1->prepare('DELETE FROM tbtransportista WHERE tbtransportistaid = :id')->execute(['id' => $tid]);
}

echo "OK concurrencia_transporte_test: IDs únicos en Vehiculo/Transportista "
    . "y asignación exclusiva en TransportistaVehiculo validados bajo concurrencia.\n";