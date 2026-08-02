<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = test_document();
try {
    $sinDireccion = test_payload($id);
    unset($sinDireccion['direccionPrincipal']);
    test_same(422, test_controller()->procesar('POST', [], $sinDireccion)['status'], 'Dirección obligatoria');
    $productor = test_create([], $id);
    $conteo = $db = test_db()->prepare('SELECT COUNT(*) FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = :id');
    $conteo->execute(['id' => $productor['identificacionNumero']]);
    test_same(1, (int) $conteo->fetchColumn(), 'Debe existir exactamente una dirección por PK compartida');
    try {
        test_db()->prepare('INSERT INTO tbproductoresdireccion
            (tbproductoresIdentificacionNumero,tbproductoresdireccionProvincia,tbproductoresdireccionCanton,tbproductoresdireccionDistrito)
            VALUES (:id,\'Otra\',\'Otra\',\'Otra\')')->execute(['id' => $productor['identificacionNumero']]);
        throw new RuntimeException('Se aceptó una segunda dirección.');
    } catch (PDOException $exception) {
        test_same(1062, (int) ($exception->errorInfo[1] ?? 0), 'La PK compartida debe impedir segunda dirección');
    }
} finally {
    test_cleanup_productores([$id]);
}

echo "OK address_policy_test: dirección obligatoria y relación 1:1 por PK/FK compartida.\n";
