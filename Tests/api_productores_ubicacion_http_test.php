<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$url = 'http://127.0.0.1/api/productores-ubicacion.php';
$id = test_document();

try {
    $productor = test_create([], $id);
    $productorId = (int) $productor['productorId'];

    // Contrato de transporte: 405 JSON, 415 por Content-Type y 400 por JSON malformado.
    test_same(405, test_http_json('TRACE', null, 'application/json', $url)['status'],
        'HTTP 405 debe ser JSON en productores-ubicacion.php');
    test_same(415, test_http_json('POST', '{}', 'text/plain', $url)['status'],
        'HTTP 415 debe ser JSON en productores-ubicacion.php');
    test_same(400, test_http_json('POST', '{invalid', 'application/json', $url)['status'],
        'HTTP 400 JSON malformado en productores-ubicacion.php');

    $postear = fn (array $cuerpo): array => test_http_json('POST',
        json_encode($cuerpo, JSON_THROW_ON_ERROR), 'application/json', $url);

    // POST válido → 201 con id devuelto.
    $valido = $postear(['productorId' => $productorId, 'latitud' => 9.9345678,
        'longitud' => -84.0876543, 'precisionMetros' => 25.4, 'origen' => 'NAVEGADOR']);
    test_same(201, $valido['status'], 'POST válido responde 201');
    test_same(true, $valido['body']['success'], 'POST válido reporta success true');
    test_assert(is_int($valido['body']['data']['tbproductorubicacionid'] ?? null)
        && $valido['body']['data']['tbproductorubicacionid'] > 0,
        'POST válido debe devolver el identificador de la ubicación creada');
    $primerId = $valido['body']['data']['tbproductorubicacionid'];

    // Errores por campo con estado 422.
    $latFuera = $postear(['productorId' => $productorId, 'latitud' => 95, 'longitud' => -84, 'origen' => 'MANUAL']);
    test_same(422, $latFuera['status'], 'POST con latitud 95 responde 422');
    test_assert(isset($latFuera['body']['errors']['latitud']), 'El error de latitud se reporta por campo');

    $lonFuera = $postear(['productorId' => $productorId, 'latitud' => 9.9, 'longitud' => -200, 'origen' => 'MANUAL']);
    test_same(422, $lonFuera['status'], 'POST con longitud -200 responde 422');
    test_assert(isset($lonFuera['body']['errors']['longitud']), 'El error de longitud se reporta por campo');

    $precisionNegativa = $postear(['productorId' => $productorId, 'latitud' => 9.9,
        'longitud' => -84.0, 'precisionMetros' => -1, 'origen' => 'MANUAL']);
    test_same(422, $precisionNegativa['status'], 'POST con precisión -1 responde 422');
    test_assert(isset($precisionNegativa['body']['errors']['precisionMetros']),
        'El error de precisión se reporta por campo');

    $origenMagico = $postear(['productorId' => $productorId, 'latitud' => 9.9,
        'longitud' => -84.0, 'origen' => 'GPS_MAGICO']);
    test_same(422, $origenMagico['status'], 'POST con origen fuera del catálogo responde 422');
    test_assert(isset($origenMagico['body']['errors']['origen']), 'El error de origen se reporta por campo');

    $inexistente = $postear(['productorId' => 2147483000, 'latitud' => 9.9,
        'longitud' => -84.0, 'origen' => 'MANUAL']);
    test_same(404, $inexistente['status'],
        'POST con productorId inexistente responde 404');

    // El campo "fecha" del cliente se ignora: la fila guarda la hora del servidor.
    $fechaCliente = '1999-12-31 23:59:59';
    $conFecha = $postear(['productorId' => $productorId, 'latitud' => 9.95,
        'longitud' => -84.09, 'origen' => 'NAVEGADOR', 'fecha' => $fechaCliente]);
    test_same(201, $conFecha['status'], 'POST que incluye "fecha" del cliente se acepta y el campo se descarta');
    $segundoId = $conFecha['body']['data']['tbproductorubicacionid'];

    // Escritura destructiva prohibida en la capa HTTP.
    test_same(405, test_http_json('DELETE',
        json_encode(['id' => $primerId], JSON_THROW_ON_ERROR), 'application/json', $url)['status'],
        'DELETE debe responder 405 porque la tabla es append-only');
    test_same(405, test_http_json('PUT',
        json_encode(['id' => $primerId], JSON_THROW_ON_ERROR), 'application/json', $url)['status'],
        'PUT debe responder 405 porque la tabla es append-only');
    test_same(405, test_http_json('PATCH',
        json_encode(['id' => $primerId], JSON_THROW_ON_ERROR), 'application/json', $url)['status'],
        'PATCH debe responder 405 porque la tabla es append-only');

    // GET paginado: filas correctas + total.
    $getPagina = test_http_json('GET', null, 'application/json', "{$url}?productorId={$productorId}&pagina=1&tamano=1");
    test_same(200, $getPagina['status'], 'GET paginado responde 200');
    test_same(2, $getPagina['body']['data']['total'], 'El total cubre las dos ubicaciones registradas');
    test_same([$segundoId], array_column($getPagina['body']['data']['ubicaciones'], 'tbproductorubicacionid'),
        'Con tamaño 1 la primera página trae solo la más reciente');

    // GET con desde/hasta: solo filas del rango.
    $desde = gmdate('Y-m-d H:i:s', time() - 60);
    $hasta = gmdate('Y-m-d H:i:s', time() + 60);
    $getRango = test_http_json('GET', null, 'application/json',
        "{$url}?productorId={$productorId}&desde=" . rawurlencode($desde) . '&hasta=' . rawurlencode($hasta));
    test_same(200, $getRango['status'], 'GET con rango responde 200');
    test_same([$primerId, $segundoId], array_column($getRango['body']['data']['ubicaciones'], 'tbproductorubicacionid'),
        'El rango amplio devuelve ambas filas en orden cronológico');
    test_same(2, $getRango['body']['data']['total'], 'El total refleja las filas del rango');

    $getVacio = test_http_json('GET', null, 'application/json',
        "{$url}?productorId={$productorId}&desde=" . rawurlencode('2100-01-01') . '&hasta=' . rawurlencode('2100-12-31'));
    test_same([], $getVacio['body']['data']['ubicaciones'], 'Un rango futuro devuelve cero filas');

    // La fecha persistida es del servidor, no la falseada por el cliente.
    $db = \Configuration\Database::getConnection();
    $fila = $db->prepare('SELECT tbproductorubicacionfecha FROM tbproductorubicacion WHERE tbproductorubicacionid = :id');
    $fila->execute(['id' => $segundoId]);
    $fechaGuardada = $fila->fetchColumn();
    test_assert($fechaGuardada !== $fechaCliente, 'La fecha del cliente nunca se persiste');
    test_assert(abs(strtotime((string) $fechaGuardada) - time()) <= 120,
        'La fecha persistida corresponde al reloj del servidor');
} finally {
    test_cleanup_ubicaciones([(int) ($productor['productorId'] ?? 0)]);
    test_cleanup_productores([$id]);
}

echo "OK api_productores_ubicacion_http_test: POST/GET reales con errores por campo (422), 405 append-only, "
    . "paginación, rango de fechas y fecha asignada por el servidor.\n";
