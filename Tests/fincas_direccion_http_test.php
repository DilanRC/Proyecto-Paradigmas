<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/fincas-direccion.php';
$id = test_document();
$nombreFinca = 'Finca HTTP ' . strtoupper(bin2hex(random_bytes(3)));

try {
    // ============================================================
    // Fixture: productor activo con una finca activa (vía controller, no HTTP)
    // ============================================================
    test_create(['fincas' => [['nombre' => $nombreFinca]]], $id);

    // ============================================================
    // Robustez del endpoint (igual que api_productores_test.php)
    // ============================================================
    test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
        'HTTP 405 debe ser JSON en el endpoint de finca');
    test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
        'HTTP 415 debe ser JSON en el endpoint de finca');
    test_same(400, test_http_json('POST', '{', 'application/json', $url)['status'],
        'HTTP 400 (JSON malformado) debe ser JSON en el endpoint de finca');

    // ============================================================
    // GET sin dirección todavía
    // ============================================================
    $queryString = http_build_query(['identificacionNumero' => $id, 'nombreFinca' => $nombreFinca]);
    $getSinDireccion = test_http_json('GET', null, 'application/json', "{$url}?{$queryString}");
    test_same(404, $getSinDireccion['status'], 'GET HTTP sin dirección registrada debe responder 404');

    // ============================================================
    // POST — crear dirección vía HTTP real
    // ============================================================
    $cuerpoPost = json_encode([
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(['provincia' => 'San José']),
    ], JSON_THROW_ON_ERROR);
    $post = test_http_json('POST', $cuerpoPost, 'application/json', $url);
    test_same(201, $post['status'], 'POST HTTP válido debe responder 201');
    test_same('San José', $post['body']['data']['direccionFinca']['provincia'], 'POST HTTP debe persistir el valor enviado');

    // ============================================================
    // GET tras crear
    // ============================================================
    $getConDireccion = test_http_json('GET', null, 'application/json', "{$url}?{$queryString}");
    test_same(200, $getConDireccion['status'], 'GET HTTP tras crear debe responder 200');
    test_same('San José', $getConDireccion['body']['data']['direccionFinca']['provincia'],
        'GET HTTP debe reflejar lo creado por POST');

    // ============================================================
    // PUT — actualizar vía HTTP real
    // ============================================================
    $cuerpoPut = json_encode([
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(['provincia' => 'Heredia']),
    ], JSON_THROW_ON_ERROR);
    $put = test_http_json('PUT', $cuerpoPut, 'application/json', $url);
    test_same(200, $put['status'], 'PUT HTTP válido debe responder 200');
    test_same('Heredia', $put['body']['data']['direccionFinca']['provincia'], 'PUT HTTP debe persistir el nuevo valor');

    // ============================================================
    // POST duplicado — conflicto real vía HTTP
    // ============================================================
    $postDuplicado = test_http_json('POST', $cuerpoPost, 'application/json', $url);
    test_same(409, $postDuplicado['status'], 'POST HTTP sobre finca que ya tiene dirección debe responder 409');

    // ============================================================
    // DELETE — vaciar vía HTTP real
    // ============================================================
    $cuerpoDelete = json_encode([
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
    ], JSON_THROW_ON_ERROR);
    $delete = test_http_json('DELETE', $cuerpoDelete, 'application/json', $url);
    test_same(200, $delete['status'], 'DELETE HTTP válido debe responder 200');
    test_same('', $delete['body']['data']['direccionFinca']['provincia'], 'DELETE HTTP debe vaciar provincia');
    test_same(null, $delete['body']['data']['direccionFinca']['pueblo'], 'DELETE HTTP debe vaciar pueblo (NULL)');

    // ============================================================
    // Errores de validación vía HTTP real (422)
    // ============================================================
    $cuerpoInvalido = json_encode([
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => ['provincia' => '', 'canton' => 'X', 'distrito' => 'X'],
    ], JSON_THROW_ON_ERROR);
    $postInvalido = test_http_json('POST', $cuerpoInvalido, 'application/json', $url);
    test_same(422, $postInvalido['status'], 'POST HTTP con provincia vacía debe responder 422');
} finally {
    test_cleanup_productores([$id]);
}

echo "OK fincas_direccion_http_test: ciclo GET/POST/PUT/DELETE real vía HTTP contra "
    . "fincas-direccion.php, más 405/415/400/422/409 de robustez del endpoint.\n";