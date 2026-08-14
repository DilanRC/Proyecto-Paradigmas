<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$db = test_db();
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
test_same(['tbbitacora', 'tbcomprador', 'tbdireccion', 'tbfinca', 'tbfincadireccion', 'tbpagometodo',
    'tbproductor', 'tbproductordireccion', 'tbtransportista', 'tbtransportistavehiculo', 'tbvehiculo'],
    array_column($tableRows, 'TABLE_NAME'), 'El modelo debe tener exactamente once tablas singulares');
foreach ($tableRows as $table) {
    test_same('utf8mb4_unicode_ci', $table['TABLE_COLLATION'], "{$table['TABLE_NAME']} debe usar utf8mb4_unicode_ci");
}

$constraints = $db->prepare("SELECT CONSTRAINT_TYPE, COUNT(*) AS cantidad
    FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()
    GROUP BY CONSTRAINT_TYPE ORDER BY CONSTRAINT_TYPE");
$constraints->execute();
test_same([], $constraints->fetchAll(), 'El esquema no debe contener PRIMARY KEY, FOREIGN KEY, UNIQUE ni CHECK');
foreach (['KEY_COLUMN_USAGE', 'REFERENTIAL_CONSTRAINTS', 'CHECK_CONSTRAINTS'] as $metadataTable) {
    $metadata = $db->prepare("SELECT COUNT(*) FROM information_schema.{$metadataTable}
        WHERE CONSTRAINT_SCHEMA = DATABASE()");
    $metadata->execute();
    test_same(0, (int) $metadata->fetchColumn(), "{$metadataTable} debe estar vacío para dbtindervacas");
}

$productorIdColumn = $db->prepare("SELECT DATA_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbproductor' AND COLUMN_NAME = 'tbproductorid'");
$productorIdColumn->execute();
test_same(['DATA_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'COLUMN_KEY' => '', 'EXTRA' => ''],
    $productorIdColumn->fetch(), 'tbproductorid debe ser INT ordinario sin clave ni AUTO_INCREMENT');

$auditColumn = $db->prepare("SELECT EXTRA FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbbitacora' AND COLUMN_NAME = 'tbbitacoraid'");
$auditColumn->execute();
test_same('', $auditColumn->fetchColumn(), 'tbbitacoraid no debe usar AUTO_INCREMENT');

$indexStatement = $db->prepare("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
    GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE ORDER BY TABLE_NAME, INDEX_NAME");
$indexStatement->execute();
$indexes = $indexStatement->fetchAll();
test_same([], $indexes, 'El modelo no debe contener índices');

$automaticColumns = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (COLUMN_DEFAULT IS NOT NULL OR EXTRA <> '' OR GENERATION_EXPRESSION <> '')");
$automaticColumns->execute();
test_same(0, (int) $automaticColumns->fetchColumn(),
    'Ninguna columna debe tener DEFAULT, AUTO_INCREMENT ni expresión generada por MySQL');

foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $metadataTable => $schemaColumn) {
    $automaticObjects = $db->prepare("SELECT COUNT(*) FROM information_schema.{$metadataTable}
        WHERE {$schemaColumn} = DATABASE()");
    $automaticObjects->execute();
    test_same(0, (int) $automaticObjects->fetchColumn(), "El esquema no debe contener {$metadataTable}");
}

$expectedColumns = [
    'tbproductor' => ['tbproductorid', 'tbproductoridentificacionnumero', 'tbproductoridentificaciontipo',
        'tbproductornombre', 'tbproductortelefono', 'tbproductorcorreoelectronico', 'tbproductorestado'],
    'tbproductordireccion' => ['tbproductordireccionid', 'tbproductorid', 'tbdireccionid',
        'tbproductordireccionprovincia', 'tbproductordireccioncanton', 'tbproductordirecciondistrito',
        'tbproductordireccionpueblo', 'tbproductordireccionsenas'],
    'tbdireccion' => ['tbdireccionid', 'tbdireccionprovincia', 'tbdireccioncanton', 'tbdirecciondistrito',
        'tbdireccionpueblo', 'tbdireccionsenas'],
    'tbfinca' => ['tbfincaid', 'tbproductorid', 'tbfincanombre', 'tbfincaestado'],
    'tbfincadireccion' => ['tbfincadireccionid', 'tbfincaid', 'tbdireccionid'],
    'tbpagometodo' => ['tbpagometodoid', 'tbpagometodonombre', 'tbpagometododescripcion', 'tbpagometodoactivo'],
    'tbtransportista' => ['tbtransportistaid', 'tbtransportistaidentificacionnumero',
        'tbtransportistaidentificaciontipo', 'tbtransportistanombre', 'tbtransportistatelefono',
        'tbtransportistacorreoelectronico', 'tbtransportistaestado'],
    'tbvehiculo' => ['tbvehiculoid', 'tbvehiculoplaca', 'tbvehiculovin', 'tbvehiculomodelo', 'tbvehiculoestado'],
    'tbtransportistavehiculo' => ['tbtransportistavehiculoid', 'tbtransportistaid', 'tbvehiculoid'],
    'tbbitacora' => ['tbbitacoraid', 'tbbitacoraentidad', 'tbbitacoraregistroidentificacionnumero',
        'tbbitacoraaccion', 'tbbitacorafecha', 'tbbitacoradatosanteriores', 'tbbitacoradatosnuevos',
        'tbbitacoraactortipo', 'tbbitacorausuarioid', 'tbbitacoraorigen', 'tbbitacorasolicitudid'],
    'tbcomprador' => ['tbcompradorid', 'tbcompradoridentificacionnumero', 'tbcompradoridentificaciontipo',
        'tbcompradornombre', 'tbcompradortelefono', 'tbcompradorcorreoelectronico', 'tbcompradorestado'],
];
foreach ($expectedColumns as $table => $expected) {
    $statement = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName ORDER BY ORDINAL_POSITION');
    $statement->execute(['tableName' => $table]);
    test_same($expected, $statement->fetchAll(PDO::FETCH_COLUMN), "Columnas inesperadas en {$table}");
}

$apiIds = [test_document(), test_document(), test_document()];
$directIdentification = test_document();
$directProductorIds = [-random_int(100000, 999999), -random_int(1000000, 1999999)];
$orphanId = -random_int(2000000, 2999999);
try {
    $first = test_create([], $apiIds[0]);
    $second = test_create([], $apiIds[1]);
    $third = test_create(['fincas' => [['nombre' => 'Finca Norte'], ['nombre' => 'Finca Sur']]], $apiIds[2]);
    test_same($first['productorId'] + 1, $second['productorId'],
        'PHP debe calcular el siguiente tbproductorid bajo el bloqueo de alta');
    test_same(409, test_controller()->procesar('POST', [], test_payload($apiIds[0]))['status'],
        'La aplicación debe rechazar una identificación repetida aunque MySQL no tenga claves');

    // Cada dirección conserva su propio tbproductordireccionId, distinto del de otros productores,
    // y sigue relacionada mediante tbproductorId (una sola fila por productor).
    $direccionId = $db->prepare('SELECT tbproductordireccionid FROM tbproductordireccion WHERE tbproductorid = :id');
    $direccionId->execute(['id' => $first['productorId']]);
    $idDireccion1 = (int) $direccionId->fetchColumn();
    $direccionId->execute(['id' => $second['productorId']]);
    $idDireccion2 = (int) $direccionId->fetchColumn();
    test_assert($idDireccion1 > 0, 'La dirección debe generar un tbproductordireccionId propio');
    test_same($idDireccion1 + 1, $idDireccion2,
        'Las direcciones consecutivas deben generar tbproductordireccionId consecutivos');
    $direccionesPorProductor = $db->prepare('SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :id');
    $direccionesPorProductor->execute(['id' => $first['productorId']]);
    test_same(1, (int) $direccionesPorProductor->fetchColumn(), 'Cada productor conserva exactamente una dirección');

    // Cada finca conserva su propio tbproductorfincaId y queda relacionada mediante tbproductorId.
    $fincasCreadas = $db->prepare('SELECT tbfincaid FROM tbfinca
        WHERE tbproductorid = :id ORDER BY tbfincaid');
    $fincasCreadas->execute(['id' => $third['productorId']]);
    $idsFincas = array_map('intval', $fincasCreadas->fetchAll(PDO::FETCH_COLUMN));
    test_same(2, count($idsFincas), 'Deben crearse dos fincas con su propio identificador');
    test_same($idsFincas[0] + 1, $idsFincas[1], 'Las fincas deben generar tbfincaid consecutivos');

    $directInsert = $db->prepare("INSERT INTO tbproductor
        (tbproductorid,tbproductoridentificacionnumero,tbproductoridentificaciontipo,tbproductornombre,
         tbproductortelefono,tbproductorcorreoelectronico,tbproductorestado)
        VALUES (:productorId,:identificacion,'SIN_CATALOGO','', '', 'directo@example.test',9)");
    foreach ($directProductorIds as $directId) {
        $directInsert->execute(['productorId' => $directId, 'identificacion' => $directIdentification]);
    }
    $directCount = $db->prepare('SELECT COUNT(*) FROM tbproductor WHERE tbproductoridentificacionnumero = :identificacion');
    $directCount->execute(['identificacion' => $directIdentification]);
    test_same(2, (int) $directCount->fetchColumn(), 'Sin PK, UNIQUE ni CHECK, SQL directo acepta duplicados y dominio inválido');

    $db->prepare("INSERT INTO tbproductordireccion
        (tbproductordireccionid,tbproductorid,tbproductordireccionprovincia,
         tbproductordireccioncanton,tbproductordirecciondistrito)
        VALUES (:direccionId,:id,'X','X','X')")->execute(['direccionId' => $orphanId, 'id' => $orphanId]);
    $db->prepare("INSERT INTO tbfinca
        (tbfincaid,tbproductorid,tbfincanombre,tbfincaestado)
        VALUES (:fincaId,:id,'Finca sin productor',1)")->execute(['fincaId' => $orphanId, 'id' => $orphanId]);
    foreach (['tbproductordireccion', 'tbfinca'] as $table) {
        $orphanCount = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE tbproductorid = :id");
        $orphanCount->execute(['id' => $orphanId]);
        test_same(1, (int) $orphanCount->fetchColumn(), "Sin FK, MySQL acepta la relación lógica huérfana en {$table}");
    }
} finally {
    $db->prepare('DELETE FROM tbfinca WHERE tbproductorid = :id')->execute(['id' => $orphanId]);
    $db->prepare('DELETE FROM tbproductordireccion WHERE tbproductorid = :id')->execute(['id' => $orphanId]);
    $deleteDirect = $db->prepare('DELETE FROM tbproductor WHERE tbproductorid IN (?, ?)');
    $deleteDirect->execute($directProductorIds);
    test_cleanup_productores($apiIds);
}

$pagoMetodo = $db->prepare('SELECT tbpagometodoid, tbpagometodonombre, tbpagometododescripcion,
    tbpagometodoactivo FROM tbpagometodo ORDER BY tbpagometodoid');
$pagoMetodo->execute();
test_same([['tbpagometodoid' => 1, 'tbpagometodonombre' => 'Efectivo',
    'tbpagometododescripcion' => 'Pago realizado en efectivo', 'tbpagometodoactivo' => 1]],
    $pagoMetodo->fetchAll(), 'Los datos iniciales deben dejar solo Efectivo en tbpagometodo');

echo "OK schema_test: once tablas y cero claves, índices, defaults, generación automática u objetos programables.\n";
