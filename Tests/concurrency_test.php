<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = test_document();
$normalizado = str_replace('-', '', $id);
$productorId = -random_int(3000000, 3999999);
$a = test_new_db();
$b = test_new_db();
$lockName = 'tindercows_productor_alta';
try {
    $insert = 'INSERT INTO tbproductor
        (tbproductorId,tbproductorIdentificacionNumero,tbproductorIdentificacionTipo,tbproductorNombre,
         tbproductorTelefono,tbproductorCorreoElectronico,tbproductorEstado)
        VALUES (:productorId,:identificacion,\'PASAPORTE\',:nombre,\'88887777\',:correo,1)';
    $a->prepare($insert)->execute([
        'productorId' => $productorId,
        'identificacion' => $normalizado,
        'nombre' => 'Concurrente A',
        'correo' => 'a@example.test',
    ]);
    $b->prepare($insert)->execute([
        'productorId' => $productorId,
        'identificacion' => $normalizado,
        'nombre' => 'Concurrente B',
        'correo' => 'b@example.test',
    ]);
    $count = $a->prepare('SELECT COUNT(*) FROM tbproductor
        WHERE tbproductorId = :productorId AND tbproductorIdentificacionNumero = :identificacion');
    $count->execute(['productorId' => $productorId, 'identificacion' => $normalizado]);
    test_same(2, (int) $count->fetchColumn(), 'MySQL debe carecer de PK y UNIQUE, por lo que SQL directo acepta duplicados');

    $lockA = $a->prepare('SELECT GET_LOCK(:lockName, 0)');
    $lockB = $b->prepare('SELECT GET_LOCK(:lockName, 0)');
    $lockA->execute(['lockName' => $lockName]);
    test_same(1, (int) $lockA->fetchColumn(), 'La primera conexión adquiere el bloqueo de alta');
    $lockB->execute(['lockName' => $lockName]);
    test_same(0, (int) $lockB->fetchColumn(), 'La segunda conexión no puede calcular el consecutivo al mismo tiempo');
    $a->prepare('SELECT RELEASE_LOCK(:lockName)')->execute(['lockName' => $lockName]);
    $lockB->execute(['lockName' => $lockName]);
    test_same(1, (int) $lockB->fetchColumn(), 'La segunda conexión adquiere el bloqueo después de liberarlo');
} finally {
    try {
        $a->prepare('SELECT RELEASE_LOCK(:lockName)')->execute(['lockName' => $lockName]);
        $b->prepare('SELECT RELEASE_LOCK(:lockName)')->execute(['lockName' => $lockName]);
    } catch (Throwable) {
    }
    test_cleanup_productores([$normalizado]);
}

echo "OK concurrency_test: MySQL permite duplicados y PHP serializa el consecutivo con GET_LOCK.\n";
