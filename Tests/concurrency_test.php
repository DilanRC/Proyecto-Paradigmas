<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$first = test_new_db();
$second = test_new_db();
$participantIds = [];
$document = test_document();
$normalized = strtoupper(str_replace('-', '', $document));
$typeId = test_type_id();

try {
    $first->beginTransaction();
    $first->prepare(
        'INSERT INTO tbparticipante
         (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
         VALUES (\'Concurrencia Ficticia Uno\', \'88881111\', \'concurrencia@example.test\', 1)'
    )->execute();
    $participantIds[] = $firstId = (int) $first->lastInsertId();
    $first->prepare(
        'INSERT INTO tbparticipanteidentificacion
         (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
          tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
          tbparticipanteidentificacionEstado) VALUES (?, ?, ?, ?, 1, 1)'
    )->execute([$firstId, $typeId, $document, $normalized]);

    $second->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $second->beginTransaction();
    $second->prepare(
        'INSERT INTO tbparticipante
         (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
         VALUES (\'Concurrencia Ficticia Dos\', \'88882222\', \'concurrencia@example.test\', 1)'
    )->execute();
    $secondId = (int) $second->lastInsertId();
    $waitedForUniqueLock = false;
    try {
        $second->prepare(
            'INSERT INTO tbparticipanteidentificacion
             (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
              tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
              tbparticipanteidentificacionEstado) VALUES (?, ?, ?, ?, 1, 1)'
        )->execute([$secondId, $typeId, $document, $normalized]);
    } catch (PDOException $exception) {
        $waitedForUniqueLock = (int) ($exception->errorInfo[1] ?? 0) === 1205;
    }
    test_assert($waitedForUniqueLock, 'La segunda conexión debe esperar el índice único no confirmado.');
    $second->rollBack();
    $first->commit();

    $second->beginTransaction();
    $second->prepare(
        'INSERT INTO tbparticipante
         (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
         VALUES (\'Concurrencia Ficticia Tres\', \'88883333\', \'concurrencia@example.test\', 1)'
    )->execute();
    $thirdId = (int) $second->lastInsertId();
    $duplicateRejected = false;
    try {
        $second->prepare(
            'INSERT INTO tbparticipanteidentificacion
             (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
              tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
              tbparticipanteidentificacionEstado) VALUES (?, ?, ?, ?, 1, 1)'
        )->execute([$thirdId, $typeId, $document, $normalized]);
    } catch (PDOException $exception) {
        $duplicateRejected = (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
    test_assert($duplicateRejected, 'Tras el primer COMMIT, la segunda identidad debe fallar con 1062.');
    $second->rollBack();

    $first->beginTransaction();
    $first->prepare('SELECT tbidentificaciontipoId FROM tbidentificaciontipo WHERE tbidentificaciontipoId = ? FOR SHARE')->execute([$typeId]);
    $second->beginTransaction();
    $catalogLockWorked = false;
    try {
        $second->prepare('UPDATE tbidentificaciontipo SET tbidentificaciontipoEstado = 0 WHERE tbidentificaciontipoId = ?')->execute([$typeId]);
    } catch (PDOException $exception) {
        $catalogLockWorked = (int) ($exception->errorInfo[1] ?? 0) === 1205;
    }
    test_assert($catalogLockWorked, 'FOR SHARE debe impedir desactivar el catálogo durante una operación.');
    $second->rollBack();
    $first->rollBack();

    $producerRoleId = test_role_id('PRODUCTOR');
    $first->beginTransaction();
    $first->prepare('SELECT tbrolId FROM tbrol WHERE tbrolId = ? FOR SHARE')->execute([$producerRoleId]);
    $second->beginTransaction();
    $roleLockWorked = false;
    try {
        $second->prepare('UPDATE tbrol SET tbrolEstado = 0 WHERE tbrolId = ?')->execute([$producerRoleId]);
    } catch (PDOException $exception) {
        $roleLockWorked = (int) ($exception->errorInfo[1] ?? 0) === 1205;
    }
    test_assert($roleLockWorked, 'FOR SHARE debe impedir desactivar PRODUCTOR durante una operación.');
    $second->rollBack();
    $first->rollBack();
} finally {
    if ($first->inTransaction()) {
        $first->rollBack();
    }
    if ($second->inTransaction()) {
        $second->rollBack();
    }
    test_cleanup_participants($participantIds);
}

echo "OK concurrency_test: UNIQUE serializa identidad y FOR SHARE protege catálogos.\n";
