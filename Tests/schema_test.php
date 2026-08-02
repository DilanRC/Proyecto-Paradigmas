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
$tables = array_column($tableRows, 'TABLE_NAME');
test_same(['tbbitacora', 'tbproductores', 'tbproductoresdireccion', 'tbproductoresfinca'], $tables,
    'El modelo debe tener exactamente cuatro tablas');
foreach ($tableRows as $table) {
    test_same('utf8mb4_unicode_ci', $table['TABLE_COLLATION'], "{$table['TABLE_NAME']} debe usar utf8mb4_unicode_ci");
}

$primaryKey = static function (string $table) use ($db): array {
    $statement = $db->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION");
    $statement->execute(['tableName' => $table]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
};
test_same(['tbproductoresIdentificacionNumero'], $primaryKey('tbproductores'),
    'La identificación debe ser la PK de productores');
test_same(['tbproductoresIdentificacionNumero'], $primaryKey('tbproductoresdireccion'),
    'Dirección debe compartir la PK natural');
test_same(['tbproductoresIdentificacionNumero', 'tbproductoresfincaNombre'], $primaryKey('tbproductoresfinca'),
    'Finca debe usar PK natural compuesta');

$foreignKeys = $db->query("SELECT rc.TABLE_NAME, rc.CONSTRAINT_NAME, rc.UPDATE_RULE, rc.DELETE_RULE,
        kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
    FROM information_schema.REFERENTIAL_CONSTRAINTS rc
    JOIN information_schema.KEY_COLUMN_USAGE kcu
      ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND kcu.TABLE_NAME = rc.TABLE_NAME
     AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
    WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
      AND rc.TABLE_NAME IN ('tbproductoresdireccion', 'tbproductoresfinca')
    ORDER BY rc.TABLE_NAME")->fetchAll();
test_same(2, count($foreignKeys), 'Las dos tablas hijas deben conservar su FK');
foreach ($foreignKeys as $foreignKey) {
    test_same('tbproductoresIdentificacionNumero', $foreignKey['COLUMN_NAME'], 'La FK debe usar la identificación');
    test_same('tbproductores', $foreignKey['REFERENCED_TABLE_NAME'], 'La FK debe referenciar productores');
    test_same('tbproductoresIdentificacionNumero', $foreignKey['REFERENCED_COLUMN_NAME'],
        'La FK debe referenciar la PK natural');
    test_same('RESTRICT', $foreignKey['UPDATE_RULE'], "{$foreignKey['TABLE_NAME']} debe restringir UPDATE");
    test_same('RESTRICT', $foreignKey['DELETE_RULE'], "{$foreignKey['TABLE_NAME']} debe restringir DELETE");
}

$expectedColumns = [
    'tbproductores' => [
        'tbproductoresIdentificacionNumero', 'tbproductoresIdentificacionTipo', 'tbproductoresNombre',
        'tbproductoresTelefono', 'tbproductoresCorreoElectronico', 'tbproductoresEstado',
    ],
    'tbproductoresdireccion' => [
        'tbproductoresIdentificacionNumero', 'tbproductoresdireccionProvincia', 'tbproductoresdireccionCanton',
        'tbproductoresdireccionDistrito', 'tbproductoresdireccionPueblo', 'tbproductoresdireccionSenas',
    ],
    'tbproductoresfinca' => [
        'tbproductoresIdentificacionNumero', 'tbproductoresfincaNombre', 'tbproductoresfincaEstado',
    ],
];
foreach ($expectedColumns as $table => $expected) {
    $statement = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName ORDER BY ORDINAL_POSITION');
    $statement->execute(['tableName' => $table]);
    test_same($expected, $statement->fetchAll(PDO::FETCH_COLUMN), "{$table} no debe tener IDs artificiales");
}

$ids = [test_document(), test_document()];
try {
    foreach ($ids as $id) {
        test_create(['correoElectronico' => 'compartido@example.test'], $id);
    }
    $duplicate = test_controller()->procesar('POST', [], test_payload($ids[0]));
    test_same(409, $duplicate['status'], 'La PK debe rechazar identificación duplicada');
    foreach (['tbproductoresdireccion', 'tbproductoresfinca'] as $table) {
        $columns = $table === 'tbproductoresdireccion'
            ? '(tbproductoresIdentificacionNumero,tbproductoresdireccionProvincia,tbproductoresdireccionCanton,tbproductoresdireccionDistrito)'
            : '(tbproductoresIdentificacionNumero,tbproductoresfincaNombre,tbproductoresfincaEstado)';
        $values = $table === 'tbproductoresdireccion' ? "('NOEXISTE','X','X','X')" : "('NOEXISTE','Finca Huérfana',1)";
        try {
            $db->exec("INSERT INTO {$table} {$columns} VALUES {$values}");
            throw new RuntimeException("{$table} aceptó una FK huérfana.");
        } catch (PDOException $exception) {
            test_same(1452, (int) ($exception->errorInfo[1] ?? 0), "La FK de {$table} debe rechazar huérfanos");
        }
    }
} finally {
    test_cleanup_productores($ids);
}

echo "OK schema_test: collation, cuatro tablas, PK naturales/compuesta, FK RESTRICT y ausencia de IDs artificiales.\n";
