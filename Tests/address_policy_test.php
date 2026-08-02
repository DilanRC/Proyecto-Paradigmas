<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = test_document();
try {
    $sinDireccion = test_payload($id);
    unset($sinDireccion['direccionPrincipal']);
    test_same(422, test_controller()->procesar('POST', [], $sinDireccion)['status'], 'Dirección obligatoria');
    $productor = test_create([], $id);
    $conteo = $db = test_db()->prepare('SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorId = :id');
    $conteo->execute(['id' => $productor['productorId']]);
    test_same(1, (int) $conteo->fetchColumn(), 'POST debe crear exactamente una dirección por política de aplicación');
    $actualizacion = test_payload($id, ['direccionPrincipal' => ['provincia' => 'Cartago']]);
    $actualizacion['identificacionNumeroOriginal'] = $productor['identificacionNumero'];
    test_same(200, test_controller()->procesar('PUT', [], $actualizacion)['status'], 'PUT actualiza la dirección existente');
    $conteo->execute(['id' => $productor['productorId']]);
    test_same(1, (int) $conteo->fetchColumn(), 'PUT debe conservar una sola dirección por política de aplicación');
} finally {
    test_cleanup_productores([$id]);
}

echo "OK address_policy_test: dirección obligatoria y relación 1:1 controlada por la aplicación.\n";
