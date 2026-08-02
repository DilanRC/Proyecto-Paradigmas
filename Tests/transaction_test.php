<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$db = test_db();
$marker = test_token('rollback');
$existing = test_create([], test_document());
try {
    $statement = $db->prepare(
        'SELECT tbidentificaciontipoId, tbparticipanteidentificacionNumeroNormalizado
         FROM tbparticipanteidentificacion WHERE tbparticipanteId = :id'
    );
    $statement->execute(['id' => $existing['participanteId']]);
    $identity = $statement->fetch();
    test_assert(is_array($identity), 'La fixture debe tener identificación.');

    $failed = false;
    $db->beginTransaction();
    try {
        $db->prepare(
            'INSERT INTO tbparticipante
             (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
             VALUES (:nombre, \'88889999\', \'rollback@example.test\', 1)'
        )->execute(['nombre' => $marker]);
        $partialId = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO tbparticipanteidentificacion
             (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
              tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
              tbparticipanteidentificacionEstado)
             VALUES (:participanteId, :tipoId, :numero, :normalizado, 1, 1)'
        )->execute([
            'participanteId' => $partialId,
            'tipoId' => $identity['tbidentificaciontipoId'],
            'numero' => $identity['tbparticipanteidentificacionNumeroNormalizado'],
            'normalizado' => $identity['tbparticipanteidentificacionNumeroNormalizado'],
        ]);
        $db->commit();
    } catch (PDOException) {
        $failed = true;
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    test_assert($failed, 'La segunda operación debe provocar una violación UNIQUE.');

    $statement = $db->prepare('SELECT COUNT(*) FROM tbparticipante WHERE tbparticipanteNombre = :marker');
    $statement->execute(['marker' => $marker]);
    test_same(0, (int) $statement->fetchColumn(), 'ROLLBACK no deja un participante parcial');
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM tbbitacora b INNER JOIN tbparticipante p
         ON p.tbparticipanteId = b.tbbitacoraRegistroId WHERE p.tbparticipanteNombre = :marker'
    );
    $statement->execute(['marker' => $marker]);
    test_same(0, (int) $statement->fetchColumn(), 'ROLLBACK no deja una bitácora falsa');

    $inactiveFarm = test_create_farm(false);
    try {
        $before = (int) $db->query('SELECT COUNT(*) FROM tbparticipante')->fetchColumn();
        $response = test_controller()->procesar('POST', [], test_payload(test_document(), [
            'fincas' => [['fincaId' => $inactiveFarm]],
        ]));
        test_same(422, $response['status'], 'Una finca inactiva debe abortar la operación');
        $after = (int) $db->query('SELECT COUNT(*) FROM tbparticipante')->fetchColumn();
        test_same($before, $after, 'La transacción fallida no crea un participante');
    } finally {
        test_cleanup_farms([$inactiveFarm]);
    }

    $auditFailureRequest = test_token('audit_fail');
    $auditFailureNumber = test_document();
    $constraintExists = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbbitacora'
           AND CONSTRAINT_NAME = 'ck_test_forzar_fallo_bitacora'"
    )->fetchColumn();
    if ($constraintExists === 1) {
        $db->exec('ALTER TABLE tbbitacora DROP CHECK ck_test_forzar_fallo_bitacora');
    }
    $db->exec(
        "ALTER TABLE tbbitacora ADD CONSTRAINT ck_test_forzar_fallo_bitacora
         CHECK (tbbitacoraSolicitudId <> '{$auditFailureRequest}')"
    );
    try {
        $auditFailed = false;
        try {
            test_controller($auditFailureRequest)->procesar('POST', [], test_payload($auditFailureNumber));
        } catch (PDOException) {
            $auditFailed = true;
        }
        test_assert($auditFailed, 'La prueba debe provocar un fallo al insertar la bitácora.');
        $normalizedAuditIdentity = strtoupper(str_replace([' ', '-'], '', $auditFailureNumber));
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM tbparticipanteidentificacion
             WHERE tbparticipanteidentificacionNumeroNormalizado = :numero'
        );
        $statement->execute(['numero' => $normalizedAuditIdentity]);
        test_same(0, (int) $statement->fetchColumn(), 'El fallo de bitácora revierte participante, identidad, dirección y rol');
        $statement = $db->prepare('SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraSolicitudId = :solicitud');
        $statement->execute(['solicitud' => $auditFailureRequest]);
        test_same(0, (int) $statement->fetchColumn(), 'El fallo provocado no deja una bitácora falsa');
    } finally {
        $db->exec('ALTER TABLE tbbitacora DROP CHECK ck_test_forzar_fallo_bitacora');
    }
} finally {
    test_cleanup_participants([$existing['participanteId']]);
}

echo "OK transaction_test: rollback por UNIQUE, finca inválida y fallo de bitácora.\n";
