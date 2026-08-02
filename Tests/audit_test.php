<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$participantIds = [];
try {
    $requestPrefix = test_token('audit');
    $controller = test_controller($requestPrefix . '_create');
    $payload = test_payload();
    $created = $controller->procesar('POST', [], $payload);
    test_same(201, $created['status'], 'Creación para bitácora');
    $participantIds[] = $id = $created['body']['data']['participanteId'];

    $updatePayload = test_payload($payload['identificacion']['numero'], [
        'participanteId' => $id,
        'telefono' => '88880000',
    ]);
    test_same(200, test_controller($requestPrefix . '_update')->procesar('PUT', [], $updatePayload)['status'], 'Actualización para bitácora');
    test_same(200, test_controller($requestPrefix . '_disable')->procesar('DELETE', [], ['participanteId' => $id])['status'], 'Desactivación para bitácora');
    test_same(200, test_controller($requestPrefix . '_enable')->procesar('PATCH', [], ['participanteId' => $id])['status'], 'Reactivación para bitácora');

    $statement = test_db()->prepare(
        'SELECT tbbitacoraAccion, tbbitacoraDatosAnteriores, tbbitacoraDatosNuevos,
                tbbitacoraActorTipo, tbusuarioId, tbbitacoraOrigen, tbbitacoraSolicitudId
         FROM tbbitacora WHERE tbbitacoraRegistroId = :id ORDER BY tbbitacoraId'
    );
    $statement->execute(['id' => $id]);
    $entries = $statement->fetchAll();
    test_same(['CREAR', 'ACTUALIZAR', 'DESACTIVAR', 'REACTIVAR'], array_column($entries, 'tbbitacoraAccion'), 'La bitácora conserva el ciclo CRUD');
    foreach ($entries as $entry) {
        test_same('NO_AUTENTICADO', $entry['tbbitacoraActorTipo'], 'Actor antes de autenticación');
        test_same(null, $entry['tbusuarioId'], 'No se inventa un usuario');
        test_same('API_PRODUCTORES', $entry['tbbitacoraOrigen'], 'Origen técnico');
        test_assert(str_starts_with($entry['tbbitacoraSolicitudId'], $requestPrefix), 'Cada evento conserva su solicitud técnica.');
        if ($entry['tbbitacoraDatosAnteriores'] !== null) {
            test_assert(is_array(json_decode($entry['tbbitacoraDatosAnteriores'], true, 512, JSON_THROW_ON_ERROR)), 'Los datos anteriores son JSON válido.');
        }
        if ($entry['tbbitacoraDatosNuevos'] !== null) {
            test_assert(is_array(json_decode($entry['tbbitacoraDatosNuevos'], true, 512, JSON_THROW_ON_ERROR)), 'Los datos nuevos son JSON válido.');
        }
    }
    test_same(null, $entries[0]['tbbitacoraDatosAnteriores'], 'CREAR no inventa estado anterior');
    test_assert($entries[0]['tbbitacoraDatosNuevos'] !== null, 'CREAR registra estado nuevo');
    test_assert($entries[1]['tbbitacoraDatosAnteriores'] !== null && $entries[1]['tbbitacoraDatosNuevos'] !== null, 'ACTUALIZAR registra antes y después');
} finally {
    test_cleanup_participants($participantIds);
}

echo "OK audit_test: acciones, JSON, actor nulo, origen y solicitud.\n";
