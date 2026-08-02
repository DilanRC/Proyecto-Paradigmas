<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$db = test_db();
$idDuplicado = test_document();
$idFallaBitacora = test_document();
try {
    $creado = test_create([], $idDuplicado);
    $duplicado = test_controller()->procesar('POST', [], test_payload($idDuplicado));
    test_same(409, $duplicado['status'], 'La PK final protege concurrencia y duplicados');
    $conteo = $db->prepare('SELECT COUNT(*) FROM tbproductores WHERE tbproductoresIdentificacionNumero = :id');
    $conteo->execute(['id' => $creado['identificacionNumero']]);
    test_same(1, (int) $conteo->fetchColumn(), 'No deja segunda fila');

    $db->exec("ALTER TABLE tbbitacora ADD CONSTRAINT ck_test_fallo_bitacora CHECK (tbbitacoraSolicitudId <> 'FORZAR_FALLO')");
    try {
        $respuesta = test_controller('FORZAR_FALLO')->procesar('POST', [], test_payload($idFallaBitacora));
        test_same(500, $respuesta['status'] ?? 500, 'La falla inesperada debe propagarse al endpoint como 500');
    } catch (PDOException) {
        // El controlador deja que la excepción inesperada llegue al endpoint.
    }
    $normalizado = str_replace('-', '', $idFallaBitacora);
    $conteo->execute(['id' => $normalizado]);
    test_same(0, (int) $conteo->fetchColumn(), 'Rollback elimina productor si falla bitácora');
    foreach (['tbproductoresdireccion', 'tbproductoresfinca'] as $tabla) {
        $hijo = $db->prepare("SELECT COUNT(*) FROM {$tabla} WHERE tbproductoresIdentificacionNumero = :id");
        $hijo->execute(['id' => $normalizado]);
        test_same(0, (int) $hijo->fetchColumn(), "Rollback elimina {$tabla}");
    }
} finally {
    try { $db->exec('ALTER TABLE tbbitacora DROP CHECK ck_test_fallo_bitacora'); } catch (Throwable) {}
    test_cleanup_productores([$idDuplicado, $idFallaBitacora]);
}

echo "OK transaction_test: rollback por PK duplicada y falla de bitácora.\n";
