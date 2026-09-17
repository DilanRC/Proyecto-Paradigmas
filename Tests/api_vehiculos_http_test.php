<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/vehiculos.php';

test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
    'HTTP 405 debe ser JSON en vehiculos.php');
test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
    'HTTP 415 debe ser JSON en vehiculos.php');
test_same(400, test_http_json('POST', '{invalid', 'application/json', $url)['status'],
    'HTTP 400 JSON malformado en vehiculos.php');

// Superficie de administración (DEC-30): sin sesión todos los verbos del CRUD
// responden 401. El flujo autenticado (201/200/ID/estados) se cubre por unidad
// en vehiculo_test.php.
foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $verbo) {
    $respuesta = test_http_json($verbo, $verbo === 'GET' ? null : '{"a":1}', 'application/json', $url);
    test_same(401, $respuesta['status'], "vehiculos.php {$verbo} sin sesión debe responder 401");
    if ($verbo === 'POST') {
        test_assert(is_string($respuesta['body']['message'] ?? null)
            && str_contains($respuesta['body']['message'], 'iniciar sesión'),
            'vehiculos.php POST 401 debe pedir iniciar sesión');
    }
}

$malformado = test_http_json('GET', null, 'application/json', $url, ['Authorization: Basic abc']);
test_same(401, $malformado['status'], 'Authorization mal formado responde 401 sin red');

echo "OK api_vehiculos_http_test: robustez 405/415/400 sin sesión y superficie admin 401 en todos los verbos.\n";