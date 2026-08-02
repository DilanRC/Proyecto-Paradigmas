<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$participantIds = [];
try {
    $missing = test_controller()->procesar('POST', [], array_diff_key(test_payload(), ['direccionPrincipal' => true]));
    test_same(422, $missing['status'], 'Un participante activo sin dirección principal debe rechazarse');

    $producer = test_create();
    $participantIds[] = $id = $producer['participanteId'];
    $db = test_db();
    $additional = $db->prepare(
        'INSERT INTO tbparticipantedireccion
         (tbparticipanteId, tbparticipantedireccionProvincia, tbparticipantedireccionCanton,
          tbparticipantedireccionDistrito, tbparticipantedireccionEsPrincipal, tbparticipantedireccionEstado)
         VALUES (:id, \'Otra Provincia\', \'Otro Cantón\', \'Otro Distrito\', :principal, 1)'
    );
    $additional->execute(['id' => $id, 'principal' => 0]);

    $statement = $db->prepare('SELECT COUNT(*) FROM tbparticipantedireccion WHERE tbparticipanteId = :id');
    $statement->execute(['id' => $id]);
    test_same(2, (int) $statement->fetchColumn(), 'La estructura permite una dirección adicional no principal');

    $secondPrimaryRejected = false;
    try {
        $additional->execute(['id' => $id, 'principal' => 1]);
    } catch (PDOException) {
        $secondPrimaryRejected = true;
    }
    test_assert($secondPrimaryRejected, 'MySQL debe rechazar dos direcciones principales activas.');

    $statement = $db->prepare(
        'UPDATE tbparticipantedireccion SET tbparticipantedireccionEstado = 0
         WHERE tbparticipanteId = :id AND tbparticipantedireccionEsPrincipal = 1'
    );
    $statement->execute(['id' => $id]);
    $update = test_payload($producer['identificacion']['numero'], ['participanteId' => $id]);
    $response = test_controller()->procesar('PUT', [], $update);
    test_same(409, $response['status'], 'No se puede actualizar un activo sin dirección principal válida');
    $statement = $db->prepare('SELECT tbparticipanteTelefono FROM tbparticipante WHERE tbparticipanteId = :id');
    $statement->execute(['id' => $id]);
    test_same('+50688887777', $statement->fetchColumn(), 'La validación ocurre antes de modificar contacto');
} finally {
    test_cleanup_participants($participantIds);
}

echo "OK address_policy_test: dirección obligatoria, adicional y principal única.\n";
