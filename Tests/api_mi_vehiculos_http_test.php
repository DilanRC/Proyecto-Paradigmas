<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/v1/mi-vehiculos';

test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'], 'Mi vehículos debe responder JSON 405');
test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'], 'Mi vehículos debe exigir JSON');
test_same(400, test_http_json('POST', '{bad', 'application/json', $url)['status'], 'Mi vehículos debe rechazar JSON malformado');
foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $verbo) {
    $respuesta = test_http_json($verbo, $verbo === 'GET' ? null : '{"a":1}', 'application/json', $url);
    test_same(401, $respuesta['status'], "mi-vehiculos {$verbo} sin JWT debe responder 401");
}

echo "OK api_mi_vehiculos_http_test: contrato JSON y autenticación propia.\n";
