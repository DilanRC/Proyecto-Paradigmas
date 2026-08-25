<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Application/Model/TransportistaVehiculo.php';
require dirname(__DIR__) . '/Application/Model/Transportista.php';
require dirname(__DIR__) . '/Application/Model/Vehiculo.php';
require dirname(__DIR__) . '/Application/Controller/TransportistaController.php';
require dirname(__DIR__) . '/Application/Controller/VehiculoController.php';
require dirname(__DIR__) . '/Application/Controller/TransportistaVehiculoController.php';

use Application\Controller\TransportistaController;
use Application\Controller\TransportistaVehiculoController;
use Application\Controller\VehiculoController;

function test_tv_transportista_payload(?string $number = null, array $overrides = []): array
{
    $base = [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $number ?? test_document()],
        'nombre' => 'Transportista Enlace de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'transportista.enlace.tests@example.test',
    ];
    return array_replace_recursive($base, $overrides);
}

function test_tv_vehiculo_payload(array $overrides = []): array
{
    $base = [
        'placa' => 'TVE-' . strtoupper(bin2hex(random_bytes(3))),
        'vin' => strtoupper(bin2hex(random_bytes(8))),
        'modelo' => 'Vehículo Enlace de Prueba',
    ];
    return array_replace($base, $overrides);
}

function test_tv_transportista_controller(?string $requestId = null): TransportistaController
{
    return new TransportistaController(test_db(), $requestId ?? test_token('request'));
}

function test_tv_vehiculo_controller(?string $requestId = null): VehiculoController
{
    return new VehiculoController(test_db(), $requestId ?? test_token('request'));
}

function test_tv_controller(?string $requestId = null): TransportistaVehiculoController
{
    return new TransportistaVehiculoController(test_db(), $requestId ?? test_token('request'));
}

function test_tv_create_transportista(?string $number = null, array $overrides = []): array
{
    $response = test_tv_transportista_controller()->procesar('POST', [], test_tv_transportista_payload($number, $overrides));
    test_same(201, $response['status'], 'La fixture de transportista debe responder HTTP 201');
    return $response['body']['data'];
}

function test_tv_create_vehiculo(array $overrides = []): array
{
    $response = test_tv_vehiculo_controller()->procesar('POST', [], test_tv_vehiculo_payload($overrides));
    test_same(201, $response['status'], 'La fixture de vehículo debe responder HTTP 201');
    return $response['body']['data'];
}

function test_tv_cleanup(array $identificaciones, array $vehiculoIds): void
{
    $idsTransportista = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    $idsVehiculo = array_values(array_unique(array_filter(array_map('intval', $vehiculoIds))));
    $db = test_db();
    $db->beginTransaction();
    try {
        if ($idsVehiculo !== []) {
            $marcadoresVehiculo = implode(',', array_fill(0, count($idsVehiculo), '?'));
            $db->prepare("DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid IN ({$marcadoresVehiculo})")->execute($idsVehiculo);
            $idsVehiculoTexto = array_map('strval', $idsVehiculo);
            $marcadoresVehiculoTexto = implode(',', array_fill(0, count($idsVehiculoTexto), '?'));
            $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadoresVehiculoTexto})")
                ->execute($idsVehiculoTexto);
            $db->prepare("DELETE FROM tbvehiculo WHERE tbvehiculoid IN ({$marcadoresVehiculo})")->execute($idsVehiculo);
        }
        if ($idsTransportista !== []) {
            $marcadoresTransportista = implode(',', array_fill(0, count($idsTransportista), '?'));
            $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadoresTransportista})")
                ->execute($idsTransportista);
            // La bitácora de asignaciones usa una clave compuesta "identificacion:vehiculoId";
            // se limpia aparte porque no coincide exactamente con ninguna de las dos listas anteriores.
            foreach ($idsTransportista as $identificacion) {
                $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero LIKE :patron")
                    ->execute(['patron' => $identificacion . ':%']);
            }
            $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid=t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marcadoresTransportista})")
                ->execute($idsTransportista);
            $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marcadoresTransportista})")
                ->execute($idsTransportista);
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}

$idsTransportista = [];
$idsVehiculo = [];
try {
    // ============================================================
    // Fixtures: dos transportistas y tres vehículos
    // ============================================================
    $transportistaA = test_tv_create_transportista();
    $idsTransportista[] = $transportistaA['identificacionNumero'];
    $transportistaB = test_tv_create_transportista();
    $idsTransportista[] = $transportistaB['identificacionNumero'];

    $vehiculo1 = test_tv_create_vehiculo();
    $idsVehiculo[] = $vehiculo1['vehiculoId'];
    $vehiculo2 = test_tv_create_vehiculo();
    $idsVehiculo[] = $vehiculo2['vehiculoId'];

    // ============================================================
    // Asignar (POST)
    // ============================================================
    $asignacion = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaA['identificacionNumero'],
        'vehiculoId' => $vehiculo1['vehiculoId'],
    ]);
    test_same(201, $asignacion['status'], 'Asignar un vehículo libre debe responder 201');
    test_same(1, count($asignacion['body']['data']['vehiculos']), 'El transportista queda con un vehículo asignado');
    test_same($vehiculo1['vehiculoId'], $asignacion['body']['data']['vehiculos'][0]['vehiculoId'],
        'El vehículo asignado es el correcto');

    // ============================================================
    // DEC-08: un vehículo solo puede tener un transportista a la vez.
    // Asignar el mismo vehículo a otro transportista debe rechazarse.
    // ============================================================
    $dobleAsignacion = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaB['identificacionNumero'],
        'vehiculoId' => $vehiculo1['vehiculoId'],
    ]);
    test_same(409, $dobleAsignacion['status'], 'No debe permitir asignar un vehículo ya asignado; debe usarse reasignar');

    // Tampoco debe permitirse asignarlo de nuevo al mismo transportista.
    $reintentoMismoTransportista = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaA['identificacionNumero'],
        'vehiculoId' => $vehiculo1['vehiculoId'],
    ]);
    test_same(409, $reintentoMismoTransportista['status'],
        'Asignar dos veces el mismo vehículo al mismo transportista también debe rechazarse');

    // ============================================================
    // Consultar por identificacionNumero y por vehiculoId
    // ============================================================
    $consultaPorTransportista = test_tv_controller()->procesar(
        'GET', ['identificacionNumero' => $transportistaA['identificacionNumero']], []
    );
    test_same(200, $consultaPorTransportista['status'], 'Consulta de vehículos por transportista');
    test_same(1, count($consultaPorTransportista['body']['data']['vehiculos']), 'Devuelve el vehículo asignado');

    $consultaPorVehiculo = test_tv_controller()->procesar('GET', ['vehiculoId' => $vehiculo1['vehiculoId']], []);
    test_same(200, $consultaPorVehiculo['status'], 'Consulta del transportista dueño de un vehículo');
    test_same(
        $transportistaA['identificacionNumero'],
        $consultaPorVehiculo['body']['data']['transportista']['identificacionNumero'],
        'El vehículo aparece asignado al transportista correcto',
    );

    $consultaVehiculoLibre = test_tv_controller()->procesar('GET', ['vehiculoId' => $vehiculo2['vehiculoId']], []);
    test_same(200, $consultaVehiculoLibre['status'], 'Consulta de un vehículo sin asignar');
    test_same(null, $consultaVehiculoLibre['body']['data']['transportista'], 'Un vehículo libre no tiene transportista');

    $consultaSinParametros = test_tv_controller()->procesar('GET', [], []);
    test_same(422, $consultaSinParametros['status'], 'La consulta exige identificacionNumero o vehiculoId');

    // ============================================================
    // Reasignar (PUT): mover el vehículo 1 de A hacia B
    // ============================================================
    $reasignacion = test_tv_controller()->procesar('PUT', [], [
        'identificacionNumero' => $transportistaB['identificacionNumero'],
        'vehiculoId' => $vehiculo1['vehiculoId'],
    ]);
    test_same(200, $reasignacion['status'], 'Reasignar un vehículo ya asignado debe responder 200');
    test_same(1, count($reasignacion['body']['data']['vehiculos']), 'El nuevo transportista queda con el vehículo');

    $consultaTrasReasignar = test_tv_controller()->procesar(
        'GET', ['identificacionNumero' => $transportistaA['identificacionNumero']], []
    );
    test_same([], $consultaTrasReasignar['body']['data']['vehiculos'],
        'El transportista original ya no conserva el vehículo reasignado');

    $conteoEnlaces = test_db()->prepare('SELECT COUNT(*) FROM tbtransportistavehiculo WHERE tbvehiculoid = :id');
    $conteoEnlaces->execute(['id' => $vehiculo1['vehiculoId']]);
    test_same(1, (int) $conteoEnlaces->fetchColumn(),
        'Reasignar no debe dejar dos enlaces para el mismo vehículo');

    // Reasignar también puede usarse como alta directa cuando el vehículo está libre.
    $reasignacionDirecta = test_tv_controller()->procesar('PUT', [], [
        'identificacionNumero' => $transportistaA['identificacionNumero'],
        'vehiculoId' => $vehiculo2['vehiculoId'],
    ]);
    test_same(200, $reasignacionDirecta['status'], 'Reasignar un vehículo libre también debe funcionar');

    // ============================================================
    // Desasignar (DELETE)
    // ============================================================
    $desasignacion = test_tv_controller()->procesar('DELETE', [], ['vehiculoId' => $vehiculo2['vehiculoId']]);
    test_same(200, $desasignacion['status'], 'Desasignar un vehículo asignado debe responder 200');
    test_same(null, $desasignacion['body']['data']['transportistaId'], 'Tras desasignar, el vehículo no tiene dueño');

    $reintentoDesasignar = test_tv_controller()->procesar('DELETE', [], ['vehiculoId' => $vehiculo2['vehiculoId']]);
    test_same(404, $reintentoDesasignar['status'], 'Desasignar un vehículo ya libre debe responder 404');

    // ============================================================
    // Transportista o vehículo inactivo (409) e inexistente (404)
    // ============================================================
    $inactivarTransportistaB = test_tv_transportista_controller()->procesar(
        'DELETE', [], ['identificacionNumero' => $transportistaB['identificacionNumero']]
    );
    test_same(200, $inactivarTransportistaB['status'], 'Fixture: desactivar transportista B');

    $asignarTransportistaInactivo = test_tv_controller()->procesar('PUT', [], [
        'identificacionNumero' => $transportistaB['identificacionNumero'],
        'vehiculoId' => $vehiculo2['vehiculoId'],
    ]);
    test_same(409, $asignarTransportistaInactivo['status'],
        'No debe permitir asignar vehículos a un transportista inactivo');

    test_tv_transportista_controller()->procesar('PATCH', [], ['identificacionNumero' => $transportistaB['identificacionNumero']]);

    $inactivarVehiculo2 = test_tv_vehiculo_controller()->procesar('DELETE', [], ['vehiculoId' => $vehiculo2['vehiculoId']]);
    test_same(200, $inactivarVehiculo2['status'], 'Fixture: desactivar vehículo 2');

    $asignarVehiculoInactivo = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaB['identificacionNumero'],
        'vehiculoId' => $vehiculo2['vehiculoId'],
    ]);
    test_same(409, $asignarVehiculoInactivo['status'], 'No debe permitir asignar un vehículo inactivo');

    $asignarTransportistaInexistente = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => 'NOEXISTE999',
        'vehiculoId' => $vehiculo1['vehiculoId'],
    ]);
    test_same(404, $asignarTransportistaInexistente['status'], 'Transportista inexistente debe responder 404');

    $asignarVehiculoInexistente = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaA['identificacionNumero'],
        'vehiculoId' => 999999999,
    ]);
    test_same(404, $asignarVehiculoInexistente['status'], 'Vehículo inexistente debe responder 404');

    $consultaTransportistaInexistente = test_tv_controller()->procesar(
        'GET', ['identificacionNumero' => 'NOEXISTE999'], []
    );
    test_same(404, $consultaTransportistaInexistente['status'], 'Consultar transportista inexistente responde 404');

    $consultaVehiculoInexistente = test_tv_controller()->procesar('GET', ['vehiculoId' => 999999999], []);
    test_same(404, $consultaVehiculoInexistente['status'], 'Consultar vehículo inexistente responde 404');

    // ============================================================
    // Datos inválidos y método no permitido
    // ============================================================
    $sinIdentificacion = test_tv_controller()->procesar('POST', [], ['vehiculoId' => $vehiculo1['vehiculoId']]);
    test_same(422, $sinIdentificacion['status'], 'Asignar sin identificacionNumero debe rechazarse');

    $sinVehiculoId = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaA['identificacionNumero'],
    ]);
    test_same(422, $sinVehiculoId['status'], 'Asignar sin vehiculoId debe rechazarse');

    $campoDesconocido = test_tv_controller()->procesar('POST', [], [
        'identificacionNumero' => $transportistaA['identificacionNumero'],
        'vehiculoId' => $vehiculo1['vehiculoId'],
        'notas' => 'campo no permitido',
    ]);
    test_same(422, $campoDesconocido['status'], 'Rechaza campos desconocidos en el cuerpo');

    $metodo = test_tv_controller()->procesar('TRACE', [], []);
    test_same(405, $metodo['status'], 'Método no permitido');

    // ============================================================
    // Bitácora: verifica entidad y origen de la asignación
    // ============================================================
    $bitacora = test_db()->prepare(
        "SELECT tbbitacoraentidad, tbbitacoraorigen FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :registro AND tbbitacoraaccion = 'ASIGNAR_VEHICULO'"
    );
    $bitacora->execute(['registro' => $transportistaA['identificacionNumero'] . ':' . $vehiculo1['vehiculoId']]);
    $filaBitacora = $bitacora->fetch();
    test_assert($filaBitacora !== false, 'Debe existir un registro de bitácora para la asignación inicial');
    test_same('TRANSPORTISTA_VEHICULO', $filaBitacora['tbbitacoraentidad'],
        'La bitácora debe etiquetar la entidad como TRANSPORTISTA_VEHICULO');
    test_same('API_TRANSPORTISTAS_VEHICULOS', $filaBitacora['tbbitacoraorigen'],
        'La bitácora debe etiquetar el origen como API_TRANSPORTISTAS_VEHICULOS');
} finally {
    test_tv_cleanup($idsTransportista, $idsVehiculo);
}

echo "OK transportista_vehiculo_test: asignar, rechazo de doble asignación (DEC-08), reasignar, "
    . "desasignar, consultas por identificación y vehiculoId, estados inactivos y bitácora correcta.\n";
