<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Tools/schema-manifest.php';

$db = test_db();
$manifest = schema_manifest();

$schemaStatement = $db->prepare("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
    FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE() LIMIT 1");
$schemaStatement->execute();
$schema = $schemaStatement->fetch();
test_same('utf8mb4', $schema['DEFAULT_CHARACTER_SET_NAME'] ?? null, 'La base debe usar utf8mb4');
test_same('utf8mb4_unicode_ci', $schema['DEFAULT_COLLATION_NAME'] ?? null, 'La base debe usar utf8mb4_unicode_ci');

$tablesStatement = $db->prepare("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
$tablesStatement->execute();
$tableRows = $tablesStatement->fetchAll();
test_same($manifest['tables_sorted'], array_column($tableRows, 'TABLE_NAME'),
    'El modelo debe tener exactamente las tablas del SQL canónico');
foreach ($tableRows as $table) {
    test_same('utf8mb4_unicode_ci', $table['TABLE_COLLATION'], "{$table['TABLE_NAME']} debe usar utf8mb4_unicode_ci");
}

$constraints = $db->prepare("SELECT CONSTRAINT_TYPE, COUNT(*) AS cantidad
    FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
    GROUP BY CONSTRAINT_TYPE ORDER BY CONSTRAINT_TYPE");
$constraints->execute();
test_same([], $constraints->fetchAll(), 'El esquema no debe contener PRIMARY KEY, FOREIGN KEY, UNIQUE ni CHECK');

$indexes = $db->prepare("SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME");
$indexes->execute();
test_same([], $indexes->fetchAll(), 'El modelo no debe contener índices');

$automaticColumns = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (COLUMN_DEFAULT IS NOT NULL OR EXTRA <> '' OR GENERATION_EXPRESSION <> '')");
$automaticColumns->execute();
test_same(0, (int) $automaticColumns->fetchColumn(),
    'Ninguna columna debe tener DEFAULT, AUTO_INCREMENT ni expresión generada');

$expectedColumns = [
    'tbpersona' => [
        'tbpersonaid', 'tbpersonaidentificacionnumero', 'tbpersonaidentificaciontipo',
        'tbpersonanombre', 'tbpersonaalias', 'tbpersonatelefono',
        'tbpersonacorreoelectronico', 'tbpersonaestado',
    ],
    'tbproductor' => ['tbproductorid', 'tbpersonaid'],
    'tbcomprador' => ['tbcompradorid', 'tbpersonaid', 'tbcompradorestado'],
    'tbproductorpersonatelefonohistorico' => [
        'tbproductorpersonatelefonohistoricoid', 'tbproductorid',
        'tbproductorpersonatelefonohistoriconuevo', 'tbproductorpersonatelefonohistoricofecha',
    ],
    'tbcompradorpersonatelefonohistorico' => [
        'tbcompradorpersonatelefonohistoricoid', 'tbcompradorid',
        'tbcompradorpersonatelefonohistoriconuevo', 'tbcompradorpersonatelefonohistoricofecha',
    ],
];
foreach ($expectedColumns as $table => $expected) {
    $statement = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName ORDER BY ORDINAL_POSITION');
    $statement->execute(['tableName' => $table]);
    test_same($expected, $statement->fetchAll(PDO::FETCH_COLUMN), "Columnas inesperadas en {$table}");
}

foreach (['tbproductorpersonatelefonohistorico', 'tbcompradorpersonatelefonohistorico'] as $table) {
    $stateColumns = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName
          AND (COLUMN_NAME LIKE '%estado%' OR COLUMN_NAME LIKE '%fechafin%')");
    $stateColumns->execute(['tableName' => $table]);
    test_same(0, (int) $stateColumns->fetchColumn(),
        "{$table} no debe inventar estado ni fecha fin para el histórico de teléfono");
}

$pagoMetodoEstructura = $db->prepare('SELECT tbpagometodoid, tbpagometodonombre,
    tbpagometododescripcion, tbpagometodoactivo FROM tbpagometodo ORDER BY tbpagometodoid');
$pagoMetodoEstructura->execute();
test_same([['tbpagometodoid' => 1, 'tbpagometodonombre' => 'Efectivo',
    'tbpagometododescripcion' => 'Pago realizado en efectivo', 'tbpagometodoactivo' => 1]],
    $pagoMetodoEstructura->fetchAll(), 'Los datos iniciales deben dejar solo Efectivo en tbpagometodo');

echo "OK schema_test: SQL canónico, persona con alias, históricos de teléfono por contexto y cero lógica estructural en MySQL.\n";
