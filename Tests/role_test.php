<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$participantIds = [];
try {
    $producer = test_create();
    $participantIds[] = $id = $producer['participanteId'];
    $buyerRole = test_role_id('COMPRADOR');
    $statement = test_db()->prepare(
        'INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado) VALUES (?, ?, 1)'
    );
    $statement->execute([$id, $buyerRole]);

    $statement = test_db()->prepare(
        'SELECT r.tbrolCodigo FROM tbparticipanterol pr INNER JOIN tbrol r ON r.tbrolId = pr.tbrolId
         WHERE pr.tbparticipanteId = :id AND pr.tbparticipanterolEstado = 1 ORDER BY r.tbrolCodigo'
    );
    $statement->execute(['id' => $id]);
    test_same(['COMPRADOR', 'PRODUCTOR'], $statement->fetchAll(PDO::FETCH_COLUMN), 'Una persona conserva ambos roles en el mismo participanteId');

    $duplicateRejected = false;
    try {
        $statement = test_db()->prepare(
            'INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado) VALUES (?, ?, 1)'
        );
        $statement->execute([$id, test_role_id('PRODUCTOR')]);
    } catch (PDOException) {
        $duplicateRejected = true;
    }
    test_assert($duplicateRejected, 'La PK compuesta debe rechazar el mismo rol duplicado.');

    $db = test_db();
    $db->beginTransaction();
    try {
        $payload = test_payload();
        $db->prepare(
            'INSERT INTO tbparticipante
             (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
             VALUES (:nombre, :telefono, :correo, 1)'
        )->execute(['nombre' => $payload['nombre'], 'telefono' => '88886666', 'correo' => 'buyer.only@example.test']);
        $buyerOnlyId = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado) VALUES (?, ?, 1)'
        )->execute([$buyerOnlyId, $buyerRole]);
        $db->prepare(
            'INSERT INTO tbparticipanteidentificacion
             (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
              tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
              tbparticipanteidentificacionEstado) VALUES (?, ?, ?, ?, 1, 1)'
        )->execute([$buyerOnlyId, test_type_id(), $payload['identificacion']['numero'], $payload['identificacion']['numero']]);
        $db->prepare(
            'INSERT INTO tbparticipantedireccion
             (tbparticipanteId, tbparticipantedireccionProvincia, tbparticipantedireccionCanton,
              tbparticipantedireccionDistrito, tbparticipantedireccionEsPrincipal, tbparticipantedireccionEstado)
             VALUES (?, \'P\', \'C\', \'D\', 1, 1)'
        )->execute([$buyerOnlyId]);
        $response = test_controller()->procesar('GET', ['id' => (string) $buyerOnlyId], []);
        test_same(404, $response['status'], 'El CRUD de productores excluye participantes sin rol PRODUCTOR');
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
} finally {
    test_cleanup_participants($participantIds);
}

echo "OK role_test: roles múltiples, no duplicación y filtro PRODUCTOR.\n";
