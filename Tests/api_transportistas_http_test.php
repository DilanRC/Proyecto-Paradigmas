<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/transportistas.php';

// Robustez del endpoint que permanece ANTES de la sesión: método prohibido y
// tipo de contenido se validan sin necesidad de autenticarse. El JSON
// malformado también, porque el parseo ocurre antes del guard.
test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
    'HTTP 405 JSON en transportistas.php');
test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
    'HTTP 415 JSON en transportistas.php');
test_same(400, test_http_json('POST', '{bad', 'application/json', $url)['status'],
    'HTTP 400 JSON malformado en transportistas.php');

// Superficie de administración (DEC-30): sin sesión todos los verbos del CRUD
// responden 401 con el código SIN_SESION que el frontend convierte en
// "inicie sesión". El flujo autenticado (201/200/404/409…) se cubre por unidad
// en transportista_test.php y auth_guard_test.php.
foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $verbo) {
    $respuesta = test_http_json($verbo, $verbo === 'GET' ? null : '{"a":1}', 'application/json', $url);
    test_same(401, $respuesta['status'], "transportistas.php {$verbo} sin sesión debe responder 401");
    if ($verbo === 'POST') {
        test_same(['auth' => 'SIN_SESION'], $respuesta['body']['errors'] ?? null,
            'El 401 sin sesión debe llevar el código SIN_SESION');
    }
}

$compradores = 'http://127.0.0.1/api/compradores.php';
$putCompradores = test_http_json('PUT', '{}', 'application/json', $compradores);
test_same(405, $putCompradores['status'],
    'El verbo prohibido de un CRUD responde 405 antes de exigir sesión');
test_same('Método no permitido.', $putCompradores['body']['message'], 'Mensaje de verbo prohibido');

$malformado = test_http_json('GET', null, 'application/json', $url, ['Authorization: Basic abc']);
test_same(401, $malformado['status'],
    'Authorization mal formado responde 401 sin depender de la red');

echo "OK api_transportistas_http_test: robustez 405/415/400 sin sesión y superficie admin 401 en todos los verbos.\n";