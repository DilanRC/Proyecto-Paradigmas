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

$url = 'http://127.0.0.1/api/transportistas-vehiculos.php';
$conn = \Configuration\Database::getConnection();
$tvModel = new TransportistaVehiculo($conn);
$transportistaModel = new Transportista($conn, $tvModel);
$vehiculoModel = new Vehiculo($conn);

$identificacionA = 'FIXTVA' . strtoupper(bin2hex(random_bytes(3)));
$identificacionB = 'FIXTVB' . strtoupper(bin2hex(random_bytes(3)));
$transportistaIdA = null;
$transportistaIdB = null;
$vehiculoId = null;

try {
    $transportistaIdA = $transportistaModel->ejecutarConBloqueoAlta(
        fn (): int => $transportistaModel->crear([
            'identificacionNumero' => $identificacionA,
            'identificacionTipo' => 'PASAPORTE',
            'nombre' => 'Transportista HTTP A',
            'telefono' => '88888888',
            'correoElectronico' => 'fix-tva@test.com',
        ])
    );
    $transportistaIdB = $transportistaModel->ejecutarConBloqueoAlta(
        fn (): int => $transportistaModel->crear([
            'identificacionNumero' => $identificacionB,
            'identificacionTipo' => 'PASAPORTE',
            'nombre' => 'Transportista HTTP B',
            'telefono' => '87777777',
            'correoElectronico' => 'fix-tvb@test.com',
        ])
    );
    $vehiculoId = $vehiculoModel->ejecutarConBloqueoAlta(
        fn (): int => $vehiculoModel->crear([
            'placa' => 'FIX-TV-' . strtoupper(bin2hex(random_bytes(3))),
            'vin' => 'VIN-FIX-TV-' . strtoupper(bin2hex(random_bytes(3))),
            'modelo' => 'Vehiculo HTTP relacion',
        ])
    );

    test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
        'HTTP 405 JSON en transportistas-vehiculos.php');
    test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
        'HTTP 415 JSON en transportistas-vehiculos.php');
    test_same(400, test_http_json('POST', '{bad', 'application/json', $url)['status'],
        'HTTP 400 JSON malformado en transportistas-vehiculos.php');

    $cuerpoAsignar = json_encode([
        'identificacionNumero' => $identificacionA,
        'vehiculoId' => $vehiculoId,
    ], JSON_THROW_ON_ERROR);
    $post = test_http_json('POST', $cuerpoAsignar, 'application/json', $url);
    test_same(201, $post['status'], 'POST asignar vehículo responde 201');

    $getA = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionA}");
    test_same(200, $getA['status'], 'GET lista vehículos del transportista A');
    test_same(1, count($getA['body']['data']['vehiculos']), 'Transportista A tiene el vehículo asignado');

    $postDuplicado = test_http_json('POST', $cuerpoAsignar, 'application/json', $url);
    test_same(409, $postDuplicado['status'], 'POST duplicado debe responder 409');

    $cuerpoReasignar = json_encode([
        'identificacionNumero' => $identificacionB,
        'vehiculoId' => $vehiculoId,
    ], JSON_THROW_ON_ERROR);
    $put = test_http_json('PUT', $cuerpoReasignar, 'application/json', $url);
    test_same(200, $put['status'], 'PUT debe reasignar el vehículo a otro transportista');

    $getATrasPut = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionA}");
    test_same(0, count($getATrasPut['body']['data']['vehiculos']), 'Transportista A queda sin el vehículo tras PUT');

    $getB = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionB}");
    test_same(1, count($getB['body']['data']['vehiculos']), 'Transportista B recibe el vehículo tras PUT');
    test_same($vehiculoId, $getB['body']['data']['vehiculos'][0]['vehiculoId'], 'PUT conserva el vehículo correcto');

    $cuerpoDelete = json_encode(['vehiculoId' => $vehiculoId], JSON_THROW_ON_ERROR);
    $delete = test_http_json('DELETE', $cuerpoDelete, 'application/json', $url);
    test_same(200, $delete['status'], 'DELETE desasignar responde 200');

    $getBFinal = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionB}");
    test_same(0, count($getBFinal['body']['data']['vehiculos']), 'GET tras DELETE muestra 0 vehículos');
} finally {
    if ($vehiculoId !== null) {
        $conn->prepare(
            'DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN (:asignar, :reasignar, :desasignar)'
        )->execute([
            'asignar' => $identificacionA . ':' . $vehiculoId,
            'reasignar' => $identificacionB . ':' . $vehiculoId,
            'desasignar' => (string) $vehiculoId,
        ]);
        $conn->prepare('DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid = :id')->execute(['id' => $vehiculoId]);
        $conn->prepare('DELETE FROM tbvehiculo WHERE tbvehiculoid = :id')->execute(['id' => $vehiculoId]);
    }
    foreach ([$transportistaIdA, $transportistaIdB] as $transportistaId) {
        if ($transportistaId !== null) {
            $conn->prepare('DELETE FROM tbtransportista WHERE tbtransportistaid = :id')->execute(['id' => $transportistaId]);
        }
    }
}

echo "OK api_transportistas_vehiculos_http_test: POST/GET/PUT/DELETE + 400/405/409/415 vía HTTP real.\n";
