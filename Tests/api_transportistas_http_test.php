<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/transportistas.php';
$identificacion = test_document();
$identificacionCanonica = null;

try {
    test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
        'HTTP 405 JSON en transportistas.php');
    test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
        'HTTP 415 JSON en transportistas.php');
    test_same(400, test_http_json('POST', '{bad', 'application/json', $url)['status'],
        'HTTP 400 JSON malformado en transportistas.php');

    $cuerpoPost = json_encode([
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $identificacion],
        'nombre' => 'Transportista HTTP Test',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'http-test@transporte.test',
    ], JSON_THROW_ON_ERROR);
    $post = test_http_json('POST', $cuerpoPost, 'application/json', $url);
    test_same(201, $post['status'], 'POST transportistas.php responde 201');
    $identificacionCanonica = $post['body']['data']['identificacionNumero'];

    $get = test_http_json('GET', null, 'application/json', "{$url}?identificacionNumero={$identificacionCanonica}");
    test_same(200, $get['status'], 'GET transportistas.php responde 200');
    test_same('Transportista HTTP Test', $get['body']['data']['nombre'], 'GET refleja nombre');

    $cuerpoPut = json_encode([
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $identificacionCanonica],
        'identificacionNumeroOriginal' => $identificacionCanonica,
        'nombre' => 'Transportista HTTP Actualizado',
        'telefono' => '+506 9999-8888',
        'correoElectronico' => 'actualizado@transporte.test',
    ], JSON_THROW_ON_ERROR);
    $put = test_http_json('PUT', $cuerpoPut, 'application/json', $url);
    test_same(200, $put['status'], 'PUT transportistas.php responde 200');
    test_same('+50699998888', $put['body']['data']['telefono'], 'PUT actualiza teléfono');

    $cuerpoDelete = json_encode(['identificacionNumero' => $identificacionCanonica], JSON_THROW_ON_ERROR);
    $delete = test_http_json('DELETE', $cuerpoDelete, 'application/json', $url);
    test_same(200, $delete['status'], 'DELETE transportistas.php responde 200');
    test_same('INACTIVO', $delete['body']['data']['estado'], 'DELETE desactiva lógicamente el transportista');

    $cuerpoPatch = json_encode(['identificacionNumero' => $identificacionCanonica], JSON_THROW_ON_ERROR);
    $patch = test_http_json('PATCH', $cuerpoPatch, 'application/json', $url);
    test_same(200, $patch['status'], 'PATCH transportistas.php responde 200');
    test_same('ACTIVO', $patch['body']['data']['estado'], 'PATCH reactiva la misma fila');

    $cuerpoInvalido = json_encode([
        'identificacion' => ['tipoCodigo' => 'INVENTADO', 'numero' => $identificacionCanonica],
        'nombre' => 'Transportista HTTP Test',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'http-test@transporte.test',
    ], JSON_THROW_ON_ERROR);
    $postInvalido = test_http_json('POST', $cuerpoInvalido, 'application/json', $url);
    test_same(422, $postInvalido['status'], 'POST con tipo de identificación inválido responde 422');
} finally {
    if ($identificacionCanonica !== null) {
        $conn = \Configuration\Database::getConnection();
        $conn->prepare(
            'DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id'
        )->execute(['id' => $identificacionCanonica]);
        $conn->prepare(
            'DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid=t.tbpersonaid WHERE p.tbpersonaidentificacionnumero = :id'
        )->execute(['id' => $identificacionCanonica]);
        $conn->prepare('DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero = :id')
            ->execute(['id' => $identificacionCanonica]);
    }
}

echo "OK api_transportistas_http_test: POST/GET/PUT/DELETE/PATCH + 400/405/415/422 vía HTTP real.\n";
