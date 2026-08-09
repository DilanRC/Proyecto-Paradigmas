<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = test_document();
try {
    // El alta ya no acepta dirección en el cuerpo: es un campo desconocido en POST.
    $conDireccion = test_payload($id, ['direccionPrincipal' => ['provincia' => 'Cartago']]);
    test_same(422, test_controller()->procesar('POST', [], $conDireccion)['status'],
        'POST debe rechazar direccionPrincipal; se instancia vacía automáticamente');

    $productor = test_create([], $id);
    $conteo = test_db()->prepare('SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :id');
    $conteo->execute(['id' => $productor['productorId']]);
    test_same(1, (int) $conteo->fetchColumn(), 'POST debe instanciar exactamente una dirección vacía por política de aplicación');

    // PUT sin dirección debe rechazarse: al actualizar sí es obligatoria (es cuando se completa).
    $sinDireccion = test_payload($id);
    $sinDireccion['identificacionNumeroOriginal'] = $productor['identificacionNumero'];
    test_same(422, test_controller()->procesar('PUT', [], $sinDireccion)['status'],
        'PUT debe exigir la dirección: es el paso donde se completa');

    $actualizacion = test_payload($id, ['direccionPrincipal' => test_direccion_payload(['provincia' => 'Cartago'])]);
    $actualizacion['identificacionNumeroOriginal'] = $productor['identificacionNumero'];
    $respuestaPut = test_controller()->procesar('PUT', [], $actualizacion);
    test_same(200, $respuestaPut['status'], 'PUT actualiza/completa la dirección existente');
    test_same('Cartago', $respuestaPut['body']['data']['direccionPrincipal']['provincia'], 'PUT persiste el valor completado');
    $conteo->execute(['id' => $productor['productorId']]);
    test_same(1, (int) $conteo->fetchColumn(), 'PUT debe conservar una sola dirección por política de aplicación');

    // Intentar "crear" una dirección para un productor que ya tiene una debe fallar (ruta de reparación).
    $reparacion = test_controller()->crearDireccion([
        'identificacionNumero' => $productor['identificacionNumero'],
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    test_same(409, $reparacion['status'], 'La ruta de reparación no debe duplicar la dirección de un productor existente');
} finally {
    test_cleanup_productores([$id]);
}

echo "OK address_policy_test: dirección vacía al crear, obligatoria y única al completar con PUT.\n";
