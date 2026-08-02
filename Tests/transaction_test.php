<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$db = test_db();
$idFallaBitacora = test_document();
try {
    $siguiente = $db->prepare('SELECT COALESCE(MAX(tbproductorId), 0) + 1 FROM tbproductor');
    $siguiente->execute();
    $productorIdEsperado = (int) $siguiente->fetchColumn();

    $db->prepare('ALTER TABLE tbbitacora MODIFY tbbitacoraSolicitudId VARCHAR(5) NOT NULL')->execute();
    try {
        test_controller('FORZAR_FALLO')->procesar('POST', [], test_payload($idFallaBitacora));
        throw new RuntimeException('La bitácora debía rechazar la solicitud más larga que la columna temporal.');
    } catch (PDOException $exception) {
        test_same(1406, (int) ($exception->errorInfo[1] ?? 0), 'La columna temporal debe forzar el rollback');
    }

    $normalizado = str_replace('-', '', $idFallaBitacora);
    $conteo = $db->prepare('SELECT COUNT(*) FROM tbproductor WHERE tbproductorIdentificacionNumero = :id');
    $conteo->execute(['id' => $normalizado]);
    test_same(0, (int) $conteo->fetchColumn(), 'Rollback elimina productor si falla bitácora');
    foreach (['tbproductordireccion', 'tbproductorfinca'] as $tabla) {
        $hijo = $db->prepare("SELECT COUNT(*) FROM {$tabla} WHERE tbproductorId = :id");
        $hijo->execute(['id' => $productorIdEsperado]);
        test_same(0, (int) $hijo->fetchColumn(), "Rollback elimina {$tabla}");
    }
} finally {
    try {
        $db->prepare('ALTER TABLE tbbitacora MODIFY tbbitacoraSolicitudId VARCHAR(100) NOT NULL')->execute();
    } catch (Throwable) {
    }
    test_cleanup_productores([$idFallaBitacora]);
}

echo "OK transaction_test: rollback forzado sin CHECK ni triggers privilegiados.\n";
