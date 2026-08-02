<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$db = test_db();
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
test_same('dbtindercows', $database, 'Las pruebas deben ejecutarse contra la base oficial');

$expectedTables = ['tbbitacora', 'tbfinca', 'tbidentificaciontipo', 'tbparticipante',
    'tbparticipantedireccion', 'tbparticipanteidentificacion', 'tbparticipanterol', 'tbproductorfinca', 'tbrol'];
$statement = $db->prepare(
    'SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = \'BASE TABLE\''
);
$statement->execute(['schema' => $database]);
$actualTables = $statement->fetchAll(PDO::FETCH_COLUMN);
foreach ($expectedTables as $table) {
    test_assert(in_array($table, $actualTables, true), "Falta la tabla {$table}.");
}

$requiredConstraints = [
    'uq_tbparticipanteidentificacion_tipo_numero_normalizado' => 'UNIQUE',
    'uq_tbparticipanteidentificacion_principal_activa' => 'UNIQUE',
    'uq_tbparticipantedireccion_principal_activa' => 'UNIQUE',
    'fk_tbparticipanterol_participante' => 'FOREIGN KEY',
    'fk_tbproductorfinca_finca' => 'FOREIGN KEY',
];
$statement = $db->prepare(
    'SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = :schema'
);
$statement->execute(['schema' => $database]);
$constraints = [];
foreach ($statement->fetchAll() as $row) {
    $constraints[$row['CONSTRAINT_NAME']] = $row['CONSTRAINT_TYPE'];
}
foreach ($requiredConstraints as $name => $type) {
    test_same($type, $constraints[$name] ?? null, "Restricción {$name}");
}

$statement = $db->prepare(
    'SELECT tbidentificaciontipoNombre FROM tbidentificaciontipo WHERE tbidentificaciontipoCodigo = \'CEDULA_FISICA\''
);
$statement->execute();
test_same('Cédula física', $statement->fetchColumn(), 'El catálogo debe conservar tildes en UTF-8');

$db->beginTransaction();
try {
    $insertParticipant = $db->prepare(
        'INSERT INTO tbparticipante
         (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
         VALUES (:nombre, \'88887777\', \'correo.compartido.tests@example.test\', 1)'
    );
    $insertParticipant->execute(['nombre' => 'Participante Ficticio Uno']);
    $firstId = (int) $db->lastInsertId();
    $insertParticipant->execute(['nombre' => 'Participante Ficticio Dos']);
    $secondId = (int) $db->lastInsertId();
    test_assert($firstId !== $secondId, 'El mismo correo de contacto debe permitirse para participantes distintos.');

    $typeId = test_type_id();
    $insertIdentification = $db->prepare(
        'INSERT INTO tbparticipanteidentificacion
         (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
          tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
          tbparticipanteidentificacionEstado)
         VALUES (:participanteId, :tipoId, :numero, :normalizado, 1, 1)'
    );
    $normalized = test_token('schema_identity');
    $insertIdentification->execute([
        'participanteId' => $firstId, 'tipoId' => $typeId, 'numero' => $normalized, 'normalizado' => $normalized,
    ]);
    $duplicateRejected = false;
    try {
        $insertIdentification->execute([
            'participanteId' => $secondId, 'tipoId' => $typeId, 'numero' => $normalized, 'normalizado' => $normalized,
        ]);
    } catch (PDOException) {
        $duplicateRejected = true;
    }
    test_assert($duplicateRejected, 'MySQL debe rechazar tipo + número normalizado duplicados.');

    $invalidForeignKeyRejected = false;
    try {
        $db->prepare(
            'INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado) VALUES (?, ?, 1)'
        )->execute([$firstId, 65535]);
    } catch (PDOException) {
        $invalidForeignKeyRejected = true;
    }
    test_assert($invalidForeignKeyRejected, 'MySQL debe rechazar llaves foráneas inválidas.');
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo "OK schema_test: tablas, restricciones, correo compartido, unicidad y FK.\n";
