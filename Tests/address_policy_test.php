<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$idConDireccion = test_document();
$idLegado = test_document();
try {
    // El formulario real envía direccionPrincipal en el mismo POST de alta.
    // Debe validarse y persistirse dentro de la misma transacción que el productor.
    $conDireccion = test_payload($idConDireccion, [
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Cartago']),
    ]);
    $respuestaPost = test_controller()->procesar('POST', [], $conDireccion);
    test_same(201, $respuestaPost['status'], 'POST debe aceptar direccionPrincipal enviada por la UI');
    $productor = $respuestaPost['body']['data'];
    test_same('Cartago', $productor['direccionPrincipal']['provincia'],
        'POST debe devolver la dirección ya persistida');

    $conteo = test_db()->prepare('SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :id');
    $conteo->execute(['id' => $productor['productorId']]);
    test_same(1, (int) $conteo->fetchColumn(),
        'POST con dirección debe crear exactamente un enlace de dirección');

    // Compatibilidad con clientes anteriores: si omiten direccionPrincipal, el alta
    // conserva la invariante 1:1 creando una dirección vacía que luego puede completarse.
    $productorLegado = test_create([], $idLegado);
    test_same('', $productorLegado['direccionPrincipal']['provincia'],
        'POST legado sin dirección debe conservar una dirección vacía');
    $conteo->execute(['id' => $productorLegado['productorId']]);
    test_same(1, (int) $conteo->fetchColumn(),
        'POST legado debe instanciar exactamente una dirección vacía');

    // PUT sigue siendo una actualización completa: la dirección es obligatoria.
    $sinDireccion = test_payload($idConDireccion);
    $sinDireccion['identificacionNumeroOriginal'] = $productor['identificacionNumero'];
    test_same(422, test_controller()->procesar('PUT', [], $sinDireccion)['status'],
        'PUT debe exigir la dirección principal');

    $actualizacion = test_payload($idConDireccion, [
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Heredia']),
    ]);
    $actualizacion['identificacionNumeroOriginal'] = $productor['identificacionNumero'];
    $respuestaPut = test_controller()->procesar('PUT', [], $actualizacion);
    test_same(200, $respuestaPut['status'], 'PUT actualiza la dirección existente');
    test_same('Heredia', $respuestaPut['body']['data']['direccionPrincipal']['provincia'],
        'PUT persiste el valor actualizado');
    $abiertos = test_db()->prepare('SELECT COUNT(*) FROM tbproductordireccion
        WHERE tbproductorid = :id AND tbproductordireccionfechafin IS NULL');
    $abiertos->execute(['id' => $productor['productorId']]);
    test_same(1, (int) $abiertos->fetchColumn(),
        'PUT conserva exactamente un periodo de dirección abierto (histórico crece con cada cambio)');

    // La ruta POST de dirección continúa siendo solo de reparación para datos sin enlace.
    $reparacion = test_controller()->crearDireccion([
        'identificacionNumero' => $productor['identificacionNumero'],
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    test_same(409, $reparacion['status'],
        'La ruta de reparación no debe duplicar la dirección de un productor existente');
} finally {
    test_cleanup_productores([$idConDireccion, $idLegado]);
}

echo "OK address_policy_test: dirección atómica en POST, compatibilidad legado y unicidad 1:1.\n";
