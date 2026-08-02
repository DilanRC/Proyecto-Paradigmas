<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = test_document();
$normalizado = str_replace('-', '', $id);
$a = test_new_db();
$b = test_new_db();
try {
    $a->beginTransaction();
    $insertarA = $a->prepare('INSERT INTO tbproductores
        (tbproductoresIdentificacionNumero,tbproductoresIdentificacionTipo,tbproductoresNombre,
         tbproductoresTelefono,tbproductoresCorreoElectronico,tbproductoresEstado)
        VALUES (:id,\'PASAPORTE\',\'Concurrente A\',\'88887777\',\'a@example.test\',1)');
    $insertarA->execute(['id' => $normalizado]);
    $a->commit();

    try {
        $b->prepare('INSERT INTO tbproductores
            (tbproductoresIdentificacionNumero,tbproductoresIdentificacionTipo,tbproductoresNombre,
             tbproductoresTelefono,tbproductoresCorreoElectronico,tbproductoresEstado)
            VALUES (:id,\'PASAPORTE\',\'Concurrente B\',\'88886666\',\'b@example.test\',1)')->execute(['id' => $normalizado]);
        throw new RuntimeException('La segunda conexión insertó la misma PK.');
    } catch (PDOException $exception) {
        test_same(1062, (int) ($exception->errorInfo[1] ?? 0), 'La PK serializa la identidad');
    }
} finally {
    test_cleanup_productores([$normalizado]);
}

echo "OK concurrency_test: dos conexiones no pueden crear la misma identificación.\n";
