<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$db = test_db();
$schema = $db->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
    FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE() LIMIT 1")->fetch();
test_same('utf8mb4', $schema['DEFAULT_CHARACTER_SET_NAME'] ?? null, 'La base debe usar utf8mb4');
test_same('utf8mb4_unicode_ci', $schema['DEFAULT_COLLATION_NAME'] ?? null, 'La base debe usar utf8mb4_unicode_ci');

$tableRows = $db->query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll();
test_same(['tbbitacora', 'tbproductores', 'tbproductoresdireccion', 'tbproductoresfinca'],
    array_column($tableRows, 'TABLE_NAME'), 'El modelo debe tener exactamente cuatro tablas');
foreach ($tableRows as $table) {
    test_same('utf8mb4_unicode_ci', $table['TABLE_COLLATION'], "{$table['TABLE_NAME']} debe usar utf8mb4_unicode_ci");
}

$primaryKeys = $db->query("SELECT tc.TABLE_NAME, kcu.COLUMN_NAME
    FROM information_schema.TABLE_CONSTRAINTS tc
    JOIN information_schema.KEY_COLUMN_USAGE kcu
      ON kcu.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND kcu.TABLE_NAME = tc.TABLE_NAME
     AND kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
    WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
    ORDER BY tc.TABLE_NAME, kcu.ORDINAL_POSITION")->fetchAll();
test_same([['TABLE_NAME' => 'tbproductores', 'COLUMN_NAME' => 'tbproductoresIdentificacionNumero']],
    $primaryKeys, 'Solo la identificación de productores puede ser PRIMARY KEY');

$foreignKeys = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
test_same(0, $foreignKeys, 'El esquema no debe contener FOREIGN KEY');

$auditColumn = $db->query("SELECT EXTRA FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbbitacora' AND COLUMN_NAME = 'tbbitacoraId'")->fetchColumn();
test_same('auto_increment', $auditColumn, 'Bitácora conserva la secuencia AUTO_INCREMENT sin convertirla en PK');
$auditPrimary = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbbitacora' AND CONSTRAINT_NAME = 'PRIMARY'")->fetchColumn();
test_same(0, (int) $auditPrimary, 'tbbitacoraId no debe ser PRIMARY KEY');
$unexpectedUniqueIndexes = (int) $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME <> 'PRIMARY' AND NON_UNIQUE = 0")->fetchColumn();
test_same(0, $unexpectedUniqueIndexes, 'Los índices auxiliares no deben convertirse en claves únicas');

$expectedColumns = [
    'tbproductores' => ['tbproductoresIdentificacionNumero', 'tbproductoresIdentificacionTipo',
        'tbproductoresNombre', 'tbproductoresTelefono', 'tbproductoresCorreoElectronico', 'tbproductoresEstado'],
    'tbproductoresdireccion' => ['tbproductoresIdentificacionNumero', 'tbproductoresdireccionProvincia',
        'tbproductoresdireccionCanton', 'tbproductoresdireccionDistrito', 'tbproductoresdireccionPueblo',
        'tbproductoresdireccionSenas'],
    'tbproductoresfinca' => ['tbproductoresIdentificacionNumero', 'tbproductoresfincaNombre',
        'tbproductoresfincaEstado'],
];
foreach ($expectedColumns as $table => $expected) {
    $statement = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName ORDER BY ORDINAL_POSITION');
    $statement->execute(['tableName' => $table]);
    test_same($expected, $statement->fetchAll(PDO::FETCH_COLUMN), "{$table} no debe tener IDs artificiales");
}

$ids = [test_document(), test_document()];
$orphan = test_document();
try {
    foreach ($ids as $id) {
        test_create(['correoElectronico' => 'compartido@example.test'], $id);
    }
    test_same(409, test_controller()->procesar('POST', [], test_payload($ids[0]))['status'],
        'La única PK debe rechazar identificación duplicada');

    $db->prepare("INSERT INTO tbproductoresdireccion
        (tbproductoresIdentificacionNumero,tbproductoresdireccionProvincia,tbproductoresdireccionCanton,tbproductoresdireccionDistrito)
        VALUES (:id,'X','X','X')")->execute(['id' => $orphan]);
    $db->prepare("INSERT INTO tbproductoresfinca
        (tbproductoresIdentificacionNumero,tbproductoresfincaNombre,tbproductoresfincaEstado)
        VALUES (:id,'Finca sin productor',1)")->execute(['id' => $orphan]);
    test_same(1, (int) $db->query("SELECT COUNT(*) FROM tbproductoresdireccion
        WHERE tbproductoresIdentificacionNumero = " . $db->quote($orphan))->fetchColumn(),
        'Sin FK, MySQL acepta la referencia lógica de dirección');
    test_same(1, (int) $db->query("SELECT COUNT(*) FROM tbproductoresfinca
        WHERE tbproductoresIdentificacionNumero = " . $db->quote($orphan))->fetchColumn(),
        'Sin FK, MySQL acepta la referencia lógica de finca');
} finally {
    $deleteFinca = $db->prepare('DELETE FROM tbproductoresfinca WHERE tbproductoresIdentificacionNumero = :id');
    $deleteDireccion = $db->prepare('DELETE FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = :id');
    $deleteFinca->execute(['id' => $orphan]);
    $deleteDireccion->execute(['id' => $orphan]);
    test_cleanup_productores($ids);
}

echo "OK schema_test: cuatro tablas, una sola PK, cero FK, collation y bitácora AUTO_INCREMENT indexada.\n";
