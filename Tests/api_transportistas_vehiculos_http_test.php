<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/transportistas-vehiculos.php';

test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
    'HTTP 405 JSON en transportistas-vehiculos.php');
test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
    'HTTP 415 JSON en transportistas-vehiculos.php');
test_same(400, test_http_json('POST', '{bad', 'application/json', $url)['status'],
    'HTTP 400 JSON malformado en transportistas-vehiculos.php');

// Superficie de administración (DEC-30): sin sesión todos los verbos del CRUD
// responden 401. La asignación real (201/200/409/reasignación) se cubre por
// unidad en transportista_vehiculo_test.php.
foreach (['GET', 'POST', 'PUT', 'DELETE'] as $verbo) {
    $respuesta = test_http_json($verbo, $verbo === 'GET' ? null : '{"a":1}', 'application/json', $url);
    test_same(401, $respuesta['status'], "transportistas-vehiculos.php {$verbo} sin sesión debe responder 401");
}

test_same(405, test_http_json('PATCH', '{}', 'application/json', $url)['status'],
    'transportistas-vehiculos.php rechaza PATCH con 405 antes de la sesión');

$malformado = test_http_json('GET', null, 'application/json', $url, ['Authorization: Basic abc']);
test_same(401, $malformado['status'], 'Authorization mal formado responde 401 sin red');

echo "OK api_transportistas_vehiculos_http_test: robustez 405/415/400 sin sesión y superficie admin 401 en todos los verbos.\n";