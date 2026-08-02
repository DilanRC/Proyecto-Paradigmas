<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function http_json(string $method, ?string $body = null, string $contentType = 'application/json'): array
{
    $headers = "Accept: application/json\r\n";
    if ($body !== null) {
        $headers .= "Content-Type: {$contentType}\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);
    $raw = file_get_contents('http://127.0.0.1/api/productores.php', false, $context);
    test_assert(is_string($raw), 'Apache debe responder al endpoint desde el contenedor app.');
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    $headersText = strtolower(implode("\n", $responseHeaders));
    test_assert(str_contains($headersText, 'content-type: application/json'), 'El endpoint siempre responde application/json.');

    return ['status' => (int) ($match[1] ?? 0), 'body' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

$participantIds = [];
$farmIds = [];
try {
    $farmIds[] = $farmOne = test_create_farm();
    $farmIds[] = $farmTwo = test_create_farm();
    $number = 'TST-' . strtoupper(bin2hex(random_bytes(5)));
    $payload = test_payload($number, [
        'correoElectronico' => 'CONTACTO.COMPARTIDO@EXAMPLE.TEST',
        'fincas' => [['fincaId' => $farmOne], ['fincaId' => $farmTwo]],
    ]);
    $controller = test_controller();
    $created = $controller->procesar('POST', [], $payload);
    test_same(201, $created['status'], 'POST válido');
    test_same(true, $created['body']['success'], 'POST success');
    $participantIds[] = $id = $created['body']['data']['participanteId'];
    test_same('contacto.compartido@example.test', $created['body']['data']['correoElectronico'], 'El correo se normaliza');
    test_same($number, $created['body']['data']['identificacion']['numero'], 'La identificación visible conserva letras y guion');
    test_same(2, count($created['body']['data']['fincas']), 'POST asocia varias fincas');

    $found = $controller->procesar('GET', ['id' => (string) $id], []);
    test_same(200, $found['status'], 'GET por ID');
    test_same($id, $found['body']['data']['participanteId'], 'GET devuelve el productor creado');

    $listed = $controller->procesar('GET', ['q' => $number, 'estado' => 'ACTIVO'], []);
    test_same(200, $listed['status'], 'GET con búsqueda y filtro');
    test_assert(($listed['body']['data']['total'] ?? 0) >= 1, 'La búsqueda por identificación debe encontrar el registro.');
    $typeNames = array_column($listed['body']['data']['catalogos']['tiposIdentificacion'], 'nombre', 'codigo');
    test_same('Cédula física', $typeNames['CEDULA_FISICA'] ?? null, 'La API conserva las tildes del catálogo UTF-8');

    $sameEmail = test_create(['correoElectronico' => 'contacto.compartido@example.test'], 'TST0' . bin2hex(random_bytes(5)));
    $participantIds[] = $sameEmail['participanteId'];

    $duplicate = $controller->procesar('POST', [], test_payload(strtolower(str_replace('-', ' - ', $number))));
    test_same(409, $duplicate['status'], 'POST con identidad normalizada duplicada');
    test_same(false, $duplicate['body']['success'], 'El duplicado debe fallar');
    test_assert(isset($duplicate['body']['errors']['identificacion.numero']), 'El conflicto identifica el campo duplicado.');

    $updatedPayload = test_payload($number, [
        'participanteId' => $id,
        'telefono' => '+506 2222 3333',
        'correoElectronico' => 'actualizado@example.test',
        'direccionPrincipal' => ['provincia' => 'Nueva Provincia', 'canton' => 'Nuevo Cantón', 'distrito' => 'Nuevo Distrito'],
        'fincas' => [['fincaId' => $farmTwo]],
    ]);
    $updated = $controller->procesar('PUT', [], $updatedPayload);
    test_same(200, $updated['status'], 'PUT válido');
    test_same('actualizado@example.test', $updated['body']['data']['correoElectronico'], 'PUT actualiza contacto');
    test_same('Nueva Provincia', $updated['body']['data']['direccionPrincipal']['provincia'], 'PUT actualiza dirección');
    test_same(1, count($updated['body']['data']['fincas']), 'PUT retira asociaciones no seleccionadas');

    test_db()->prepare('UPDATE tbfinca SET tbfincaEstado = 0 WHERE tbfincaId = :id')->execute(['id' => $farmTwo]);
    $updatedPayload['telefono'] = '88885555';
    $retainedInactiveFarm = $controller->procesar('PUT', [], $updatedPayload);
    test_same(200, $retainedInactiveFarm['status'], 'PUT permite conservar una asociación histórica cuya finca se inactivó');
    $newInactiveAssociation = $controller->procesar('POST', [], test_payload(test_document(), [
        'fincas' => [['fincaId' => $farmTwo]],
    ]));
    test_same(422, $newInactiveAssociation['status'], 'POST rechaza crear una asociación nueva con finca inactiva');
    test_db()->prepare('UPDATE tbfinca SET tbfincaEstado = 1 WHERE tbfincaId = :id')->execute(['id' => $farmTwo]);

    $deactivated = $controller->procesar('DELETE', [], ['participanteId' => $id]);
    test_same(200, $deactivated['status'], 'DELETE lógico');
    test_same('INACTIVO', $deactivated['body']['data']['estado'], 'DELETE conserva y marca inactivo');

    $inactiveConflict = $controller->procesar('POST', [], $payload);
    test_same(409, $inactiveConflict['status'], 'POST no reutiliza identidad inactiva');
    test_same($id, $inactiveConflict['body']['data']['reactivacion']['participanteId'], 'El conflicto indica qué registro reactivar');

    $reactivated = $controller->procesar('PATCH', [], ['participanteId' => $id]);
    test_same(200, $reactivated['status'], 'PATCH válido');
    test_same('ACTIVO', $reactivated['body']['data']['estado'], 'PATCH reactiva la misma fila');
    test_same($id, $reactivated['body']['data']['participanteId'], 'PATCH no duplica la identidad');

    $invalidType = $controller->procesar('POST', [], test_payload('TST-INVALID', [
        'identificacion' => ['tipoId' => 65535, 'numero' => 'TST-INVALID'],
    ]));
    test_same(422, $invalidType['status'], 'Tipo de identificación inexistente');
    $missing = $controller->procesar('GET', ['id' => '999999999999'], []);
    test_same(404, $missing['status'], 'ID inexistente');
    $method = $controller->procesar('TRACE', [], []);
    test_same(405, $method['status'], 'Método no permitido');

    foreach ([$created, $found, $listed, $updated, $deactivated, $inactiveConflict, $reactivated, $invalidType, $missing, $method] as $response) {
        test_assert(isset($response['body']['success'], $response['body']['message']) && array_key_exists('data', $response['body']), 'Toda respuesta conserva el contrato JSON.');
        json_encode($response['body'], JSON_THROW_ON_ERROR);
    }

    $httpList = http_json('GET');
    test_same(200, $httpList['status'], 'El endpoint HTTP lista productores');
    test_same(true, $httpList['body']['success'], 'GET HTTP devuelve JSON exitoso');
    $httpPayload = test_payload(test_document());
    $httpCreated = http_json('POST', json_encode($httpPayload, JSON_THROW_ON_ERROR));
    test_same(201, $httpCreated['status'], 'POST HTTP crea un productor');
    $participantIds[] = $httpId = $httpCreated['body']['data']['participanteId'];
    $httpPayload['participanteId'] = $httpId;
    $httpPayload['telefono'] = '88884444';
    $httpUpdated = http_json('PUT', json_encode($httpPayload, JSON_THROW_ON_ERROR));
    test_same(200, $httpUpdated['status'], 'PUT HTTP actualiza un productor');
    test_same('88884444', $httpUpdated['body']['data']['telefono'], 'PUT HTTP persiste el contacto');
    $httpIdentifier = json_encode(['participanteId' => $httpId], JSON_THROW_ON_ERROR);
    $httpDeleted = http_json('DELETE', $httpIdentifier);
    test_same(200, $httpDeleted['status'], 'DELETE HTTP desactiva un productor');
    test_same('INACTIVO', $httpDeleted['body']['data']['estado'], 'DELETE HTTP es lógico');
    $httpReactivated = http_json('PATCH', $httpIdentifier);
    test_same(200, $httpReactivated['status'], 'PATCH HTTP reactiva un productor');
    test_same('ACTIVO', $httpReactivated['body']['data']['estado'], 'PATCH HTTP conserva el mismo registro');
    $httpMethod = http_json('TRACE');
    test_same(405, $httpMethod['status'], 'TRACE HTTP devuelve 405 JSON desde Apache');
    $malformed = http_json('POST', '{"nombre":');
    test_same(400, $malformed['status'], 'JSON mal formado');
    test_same(false, $malformed['body']['success'], 'JSON mal formado conserva contrato de error');
    $unsupportedMedia = http_json('POST', '{}', 'text/plain');
    test_same(415, $unsupportedMedia['status'], 'Content-Type no JSON');
} finally {
    test_cleanup_participants($participantIds);
    test_cleanup_farms($farmIds);
}

echo "OK api_productores_test: CRUD JSON, búsqueda, validaciones, fincas y reactivación.\n";
