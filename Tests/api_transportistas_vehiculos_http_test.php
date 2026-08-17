<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$raiz = dirname(__DIR__);
foreach (['NamedLock', 'Vehiculo', 'Transportista', 'TransportistaVehiculo'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}

use Application\Model\TransportistaVehiculo;
use Application\Model\Transportista;
use Application\Model\Vehiculo;

$url = 'http://127.0.0.1/api/transportistas-vehiculos.php';

// Crear fixtures vía modelo para tener IDs válidos
$conn = \Configuration\Database::getConnection();
$tvModel = new TransportistaVehiculo($conn);
$transportistaModel = new Transportista($conn, $tvModel);
$vehiculoModel = new Vehiculo($conn);

$identificacionFix = 'FIXTV' . bin2hex(random_bytes(3));
$transportistaId = $transportistaModel->ejecutarConBloqueoAlta(
    fn () => $transportistaModel->crear([
        'identificacionNumero' => $identificacionFix,
        'identificacionTipo' => 'CEDULA_FISICA',
        'nombre' => 'Fix Transportista TV',
        'telefono' => '00000000',
        'correoElectronico' => 'fix-tv@test.com',
    ])
);
$placaFix = 'FIX-TV-' . bin2hex(random_bytes(3));
$vehiculoId = $vehiculoModel->ejecutarConBloqueoAlta(
    fn () => $vehiculoModel->crear([
        'placa' => $placaFix,
        'vin' => 'VIN-FIX-TV',
        'modelo' => 'Fix Modelo',
    ])
);

try {
    // Robustez del endpoint
    test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
        'HTTP 405 JSON en transportistas-vehiculos.php');
    test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
        'HTTP 415 JSON en transportistas-vehiculos.php');
    test_same(400, test_http_json('POST', '{bad', 'application/json', $url)['status'],
        'HTTP 400 JSON malformado en transportistas-vehiculos.php');

    // POST — asignar (usa identificacionNumero + vehiculoId, NO transportistaId)
    $cuerpoPost = json_encode([
        'identificacionNumero' => $identificacionFix,
        'vehiculoId' => $vehiculoId,
    ], JSON_THROW_ON_ERROR);
    $post = test_http_json('POST', $cuerpoPost, 'application/json', $url);
    test_same(201, $post['status'], 'POST asignar vehículo responde 201');

    // GET — verificar asignación por identificacionNumero
    $get = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionFix}");
    test_same(200, $get['status'], 'GET lista vehículos del transportista');
    test_same(1, count($get['body']['data']['vehiculos']), 'GET muestra 1 vehículo asignado');
    test_same($vehiculoId, $get['body']['data']['vehiculos'][0]['vehiculoId'], 'GET muestra vehículo correcto');

    // POST duplicado — debe fallar con 409
    $postDup = test_http_json('POST', $cuerpoPost, 'application/json', $url);
    test_same(409, $postDup['status'], 'POST asignar vehículo ya asignado responde 409');

    // DELETE — desasignar
    $cuerpoDelete = json_encode(['vehiculoId' => $vehiculoId], JSON_THROW_ON_ERROR);
    $delete = test_http_json('DELETE', $cuerpoDelete, 'application/json', $url);
    test_same(200, $delete['status'], 'DELETE desasignar responde 200');

    // GET tras desasignar
    $getVacio = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionFix}");
    test_same(0, count($getVacio['body']['data']['vehiculos']), 'GET tras DELETE muestra 0 vehículos');

} finally {
    $conn->exec("DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid = {$vehiculoId}");
    $conn->exec("DELETE FROM tbvehiculo WHERE tbvehiculoid = {$vehiculoId}");
    $conn->exec("DELETE FROM tbtransportista WHERE tbtransportistaid = {$transportistaId}");
}

echo "OK api_transportistas_vehiculos_http_test: asignar/desasignar/listar + robustez vía HTTP.\n";