<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Application/Model/Vehiculo.php';
require dirname(__DIR__) . '/Application/Controller/VehiculoController.php';

use Application\Controller\VehiculoController;

function test_vehiculo_payload(array $overrides = []): array
{
    $base = [
        'placa' => 'TST-' . strtoupper(bin2hex(random_bytes(3))),
        'vin' => strtoupper(bin2hex(random_bytes(8))),
        'modelo' => 'Camión Ficticio de Prueba',
    ];
    return array_replace($base, $overrides);
}

function test_vehiculo_controller(?string $requestId = null): VehiculoController
{
    return new VehiculoController(test_db(), $requestId ?? test_token('request'));
}

function test_create_vehiculo(array $overrides = []): array
{
    $response = test_vehiculo_controller()->procesar('POST', [], test_vehiculo_payload($overrides));
    test_same(201, $response['status'], 'La fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La fixture debe ser exitosa.');
    return $response['body']['data'];
}

function test_cleanup_vehiculos(array $vehiculoIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $vehiculoIds))));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $idsTexto = array_map('strval', $ids);
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadores})")->execute($idsTexto);
        $db->prepare("DELETE FROM tbvehiculo WHERE tbvehiculoid IN ({$marcadores})")->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}

$ids = [];
try {
    // ============================================================
    // Alta y consulta
    // ============================================================
    $placaVisible = 'VH-00-' . strtoupper(bin2hex(random_bytes(4)));
    $creado = test_create_vehiculo(['placa' => $placaVisible]);
    $ids[] = $creado['vehiculoId'];
    test_assert(is_int($creado['vehiculoId']) && $creado['vehiculoId'] > 0,
        'PHP debe asignar tbvehiculoid sin AUTO_INCREMENT en MySQL');
    test_same($placaVisible, $creado['placa'], 'La placa se almacena tal cual se envió');
    test_same('ACTIVO', $creado['estado'], 'El alta debe crear el vehículo activo');

    $consulta = test_vehiculo_controller()->procesar('GET', ['vehiculoId' => (string) $creado['vehiculoId']], []);
    test_same(200, $consulta['status'], 'Consulta por vehiculoId');
    $lista = test_vehiculo_controller()->procesar('GET', ['q' => $placaVisible, 'estado' => 'ACTIVO'], []);
    test_same(1, $lista['body']['data']['total'], 'Búsqueda por placa');

    // ============================================================
    // Placa/VIN duplicados: es diagnóstico, NO una restricción (DEC-08).
    // El motor y la aplicación deben permitir el duplicado sin rechazarlo.
    // ============================================================
    $segundo = test_create_vehiculo(['placa' => $placaVisible]);
    $ids[] = $segundo['vehiculoId'];
    test_assert($segundo['vehiculoId'] !== $creado['vehiculoId'],
        'Dos vehículos con la misma placa deben tener vehiculoId distintos');
    $conteoPlacaDuplicada = test_db()->prepare('SELECT COUNT(*) FROM tbvehiculo WHERE tbvehiculoplaca = :placa');
    $conteoPlacaDuplicada->execute(['placa' => $placaVisible]);
    test_same(2, (int) $conteoPlacaDuplicada->fetchColumn(),
        'La aplicación no debe bloquear placas repetidas; es solo una consulta de diagnóstico');

    // ============================================================
    // Actualización
    // ============================================================
    $actualizadoPayload = test_vehiculo_payload(['modelo' => 'Modelo Actualizado']);
    $actualizadoPayload['vehiculoId'] = $creado['vehiculoId'];
    $actualizado = test_vehiculo_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $actualizado['status'], 'Actualización por vehiculoId');
    test_same('Modelo Actualizado', $actualizado['body']['data']['modelo'], 'Actualiza el modelo');

    $putNoExiste = test_vehiculo_payload();
    $putNoExiste['vehiculoId'] = 999999999;
    test_same(404, test_vehiculo_controller()->procesar('PUT', [], $putNoExiste)['status'],
        'PUT de vehículo inexistente debe responder 404');

    // ============================================================
    // Campos desconocidos
    // ============================================================
    $conCamposAjenos = test_vehiculo_payload(['transportistaId' => 1]);
    test_same(422, test_vehiculo_controller()->procesar('POST', [], $conCamposAjenos)['status'],
        'Vehículo no admite transportistaId en el alta; la asignación se hace aparte');

    // ============================================================
    // Desactivación / reactivación lógica
    // ============================================================
    $desactivado = test_vehiculo_controller()->procesar('DELETE', [], ['vehiculoId' => $creado['vehiculoId']]);
    test_same(200, $desactivado['status'], 'Desactivación debe responder 200');
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica');

    $conteoFisico = test_db()->prepare('SELECT COUNT(*) FROM tbvehiculo WHERE tbvehiculoid = :id');
    $conteoFisico->execute(['id' => $creado['vehiculoId']]);
    test_same(1, (int) $conteoFisico->fetchColumn(), 'Desactivar no debe borrar físicamente la fila');

    $reintentoDesactivar = test_vehiculo_controller()->procesar('DELETE', [], ['vehiculoId' => $creado['vehiculoId']]);
    test_same(200, $reintentoDesactivar['status'], 'Desactivar dos veces debe ser idempotente');

    $actualizarInactivo = test_vehiculo_payload();
    $actualizarInactivo['vehiculoId'] = $creado['vehiculoId'];
    test_same(409, test_vehiculo_controller()->procesar('PUT', [], $actualizarInactivo)['status'],
        'No debe permitir actualizar un vehículo inactivo');

    $reactivado = test_vehiculo_controller()->procesar('PATCH', [], ['vehiculoId' => $creado['vehiculoId']]);
    test_same(200, $reactivado['status'], 'Reactivación debe responder 200');
    test_same('ACTIVO', $reactivado['body']['data']['estado'], 'Reactiva la misma fila');

    $reintentoReactivar = test_vehiculo_controller()->procesar('PATCH', [], ['vehiculoId' => $creado['vehiculoId']]);
    test_same(200, $reintentoReactivar['status'], 'Reactivar dos veces debe ser idempotente');

    $desactivarNoExiste = test_vehiculo_controller()->procesar('DELETE', [], ['vehiculoId' => 999999999]);
    test_same(404, $desactivarNoExiste['status'], 'Desactivar vehículo inexistente debe responder 404');

    $reactivarNoExiste = test_vehiculo_controller()->procesar('PATCH', [], ['vehiculoId' => 999999999]);
    test_same(404, $reactivarNoExiste['status'], 'Reactivar vehículo inexistente debe responder 404');

    // ============================================================
    // Datos inválidos y método no permitido
    // ============================================================
    $sinPlaca = test_vehiculo_payload(['placa' => '']);
    test_same(422, test_vehiculo_controller()->procesar('POST', [], $sinPlaca)['status'],
        'Rechaza placa vacía');

    $sinVin = test_vehiculo_payload(['vin' => '']);
    test_same(422, test_vehiculo_controller()->procesar('POST', [], $sinVin)['status'],
        'Rechaza vin vacío');

    $sinModelo = test_vehiculo_payload(['modelo' => '']);
    test_same(422, test_vehiculo_controller()->procesar('POST', [], $sinModelo)['status'],
        'Rechaza modelo vacío');

    $idInvalido = test_vehiculo_controller()->procesar('GET', ['vehiculoId' => 'no-es-numero'], []);
    test_same(422, $idInvalido['status'], 'Rechaza vehiculoId no numérico en la consulta');

    $sinId = test_vehiculo_controller()->procesar('GET', ['vehiculoId' => '999999999'], []);
    test_same(404, $sinId['status'], 'vehiculoId inexistente');

    $metodo = test_vehiculo_controller()->procesar('TRACE', [], []);
    test_same(405, $metodo['status'], 'Método no permitido');

    // ============================================================
    // Bitácora: verifica que la entidad quede correctamente etiquetada
    // ============================================================
    $bitacora = test_db()->prepare(
        'SELECT tbbitacoraentidad, tbbitacoraorigen FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id AND tbbitacoraaccion = :accion'
    );
    $bitacora->execute(['id' => (string) $creado['vehiculoId'], 'accion' => 'CREAR']);
    $filaBitacora = $bitacora->fetch();
    test_assert($filaBitacora !== false, 'Debe existir un registro de bitácora para la creación del vehículo');
    test_same('VEHICULO', $filaBitacora['tbbitacoraentidad'], 'La bitácora debe etiquetar la entidad como VEHICULO');
    test_same('API_VEHICULOS', $filaBitacora['tbbitacoraorigen'], 'La bitácora debe etiquetar el origen como API_VEHICULOS');
} finally {
    test_cleanup_vehiculos($ids);
}

echo "OK vehiculo_test: CRUD de vehículo, placas/VIN duplicados permitidos (solo diagnóstico), "
    . "desactivación/reactivación y bitácora correctamente etiquetada.\n";