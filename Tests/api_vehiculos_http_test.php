<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/vehiculos.php';
$placa = 'HTTP-' . strtoupper(bin2hex(random_bytes(3)));
$vehiculoId = null;

try {
    test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
        'HTTP 405 debe ser JSON en vehiculos.php');
    test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
        'HTTP 415 debe ser JSON en vehiculos.php');
    test_same(400, test_http_json('POST', '{invalid', 'application/json', $url)['status'],
        'HTTP 400 JSON malformado en vehiculos.php');

    $cuerpoPost = json_encode([
        'placa' => $placa,
        'vin' => 'VIN-HTTP-TEST',
        'modelo' => 'Toyota Hilux 2024',
    ], JSON_THROW_ON_ERROR);
    $post = test_http_json('POST', $cuerpoPost, 'application/json', $url);
    test_same(201, $post['status'], 'POST vehiculos.php válido responde 201');
    test_same($placa, $post['body']['data']['placa'], 'POST persiste placa correctamente');
    $vehiculoId = (int) $post['body']['data']['vehiculoId'];

    $get = test_http_json('GET', null, 'application/json', "{$url}?vehiculoId={$vehiculoId}");
    test_same(200, $get['status'], 'GET vehiculos.php tras crear responde 200');
    test_same($placa, $get['body']['data']['placa'], 'GET refleja datos creados');

    $cuerpoPut = json_encode([
        'vehiculoId' => $vehiculoId,
        'placa' => $placa,
        'vin' => 'VIN-HTTP-ACTUALIZADO',
        'modelo' => 'Toyota Hilux 2025',
    ], JSON_THROW_ON_ERROR);
    $put = test_http_json('PUT', $cuerpoPut, 'application/json', $url);
    test_same(200, $put['status'], 'PUT vehiculos.php válido responde 200');
    test_same('Toyota Hilux 2025', $put['body']['data']['modelo'], 'PUT actualiza modelo');

    $cuerpoDelete = json_encode(['vehiculoId' => $vehiculoId], JSON_THROW_ON_ERROR);
    $delete = test_http_json('DELETE', $cuerpoDelete, 'application/json', $url);
    test_same(200, $delete['status'], 'DELETE vehiculos.php responde 200');
    test_same('INACTIVO', $delete['body']['data']['estado'], 'DELETE cambia estado a INACTIVO');

    $cuerpoPatch = json_encode(['vehiculoId' => $vehiculoId], JSON_THROW_ON_ERROR);
    $patch = test_http_json('PATCH', $cuerpoPatch, 'application/json', $url);
    test_same(200, $patch['status'], 'PATCH vehiculos.php responde 200');
    test_same('ACTIVO', $patch['body']['data']['estado'], 'PATCH reactiva el vehículo');

    $cuerpoInvalido = json_encode(['placa' => '', 'vin' => '', 'modelo' => ''], JSON_THROW_ON_ERROR);
    $postInvalido = test_http_json('POST', $cuerpoInvalido, 'application/json', $url);
    test_same(422, $postInvalido['status'], 'POST con campos vacíos responde 422');
} finally {
    $conn = \Configuration\Database::getConnection();
    if ($vehiculoId !== null) {
        $conn->prepare(
            'DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id'
        )->execute(['id' => (string) $vehiculoId]);
        $conn->prepare('DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid = :id')
            ->execute(['id' => $vehiculoId]);
    }
    $conn->prepare('DELETE FROM tbvehiculo WHERE tbvehiculoplaca = :placa')->execute(['placa' => $placa]);
}

echo "OK api_vehiculos_http_test: POST/GET/PUT/DELETE/PATCH + 400/405/415/422 vía HTTP real.\n";
