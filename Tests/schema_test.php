<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$db = test_db();
$tablas = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
test_same(['tbbitacora', 'tbproductores', 'tbproductoresdireccion', 'tbproductoresfinca'], $tablas, 'El modelo debe tener exactamente cuatro tablas');

$pk = $db->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductores' AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
test_same(['tbproductoresIdentificacionNumero'], $pk, 'La identificación debe ser la PK de productores');

$pkDireccion = $db->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductoresdireccion' AND CONSTRAINT_NAME = 'PRIMARY'")->fetchAll(PDO::FETCH_COLUMN);
test_same(['tbproductoresIdentificacionNumero'], $pkDireccion, 'Dirección debe compartir la PK natural');

$pkFinca = $db->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductoresfinca' AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
test_same(['tbproductoresIdentificacionNumero', 'tbproductoresfincaNombre'], $pkFinca, 'Finca debe usar PK natural compuesta');

$fks = (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('tbproductoresdireccion','tbproductoresfinca')")->fetchColumn();
test_same(2, $fks, 'Las dos tablas hijas deben proteger al productor con FK');

$ids = [test_document(), test_document()];
try {
    foreach ($ids as $id) {
        test_create(['correoElectronico' => 'compartido@example.test'], $id);
    }
    $duplicado = test_controller()->procesar('POST', [], test_payload($ids[0]));
    test_same(409, $duplicado['status'], 'La PK debe rechazar identificación duplicada');
    try {
        $db->prepare("INSERT INTO tbproductoresdireccion
            (tbproductoresIdentificacionNumero,tbproductoresdireccionProvincia,tbproductoresdireccionCanton,tbproductoresdireccionDistrito)
            VALUES ('NOEXISTE','X','X','X')")->execute();
        throw new RuntimeException('La FK inválida fue aceptada.');
    } catch (PDOException $exception) {
        test_same(1452, (int) ($exception->errorInfo[1] ?? 0), 'La FK debe rechazar huérfanos');
    }
} finally {
    test_cleanup_productores($ids);
}

echo "OK schema_test: cuatro tablas, PK natural, PK compuesta, FK y correo compartido.\n";
