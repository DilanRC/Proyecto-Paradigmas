<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Application/Model/TransportistaVehiculo.php';
require dirname(__DIR__) . '/Application/Model/Transportista.php';
require dirname(__DIR__) . '/Application/Controller/TransportistaController.php';

use Application\Controller\TransportistaController;

function test_transportista_payload(?string $number = null, array $overrides = []): array
{
    $base = [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $number ?? test_document()],
        'nombre' => 'Transportista Ficticio de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'transportista.tests@example.test',
    ];
    return array_replace_recursive($base, $overrides);
}

function test_transportista_controller(?string $requestId = null): TransportistaController
{
    return new TransportistaController(test_db(), $requestId ?? test_token('request'));
}

function test_create_transportista(array $overrides = [], ?string $number = null): array
{
    $response = test_transportista_controller()->procesar('POST', [], test_transportista_payload($number, $overrides));
    test_same(201, $response['status'], 'La fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La fixture debe ser exitosa.');
    return $response['body']['data'];
}

function test_cleanup_transportistas(array $identificaciones): void
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbtransportista WHERE tbtransportistaidentificacionnumero IN ({$marcadores})")->execute($ids);
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
    $visible = 'TR-00-' . strtoupper(bin2hex(random_bytes(4)));
    $creado = test_create_transportista([], $visible);
    $ids[] = $creado['identificacionNumero'];
    test_same(str_replace('-', '', $visible), $creado['identificacionNumero'], 'La identificación debe almacenarse canónica');
    test_assert(is_int($creado['transportistaId']) && $creado['transportistaId'] > 0,
        'PHP debe asignar tbtransportistaid sin AUTO_INCREMENT en MySQL');
    test_same('ACTIVO', $creado['estado'], 'El alta debe crear el transportista activo');
    test_same([], $creado['vehiculos'], 'Un transportista recién creado no tiene vehículos asignados');

    $consulta = test_transportista_controller()->procesar('GET', ['identificacionNumero' => $visible], []);
    test_same(200, $consulta['status'], 'Consulta por identificación');
    $lista = test_transportista_controller()->procesar('GET', ['q' => $visible, 'estado' => 'ACTIVO'], []);
    test_same(1, $lista['body']['data']['total'], 'Búsqueda por identificación');

    // ============================================================
    // Duplicados (activo e inactivo)
    // ============================================================
    $duplicado = test_transportista_controller()->procesar('POST', [], test_transportista_payload($visible));
    test_same(409, $duplicado['status'], 'No debe permitir dos altas con la misma identificación');

    // ============================================================
    // Actualización
    // ============================================================
    $actualizadoPayload = test_transportista_payload($visible, [
        'telefono' => '+506 2222-3333',
        'correoElectronico' => 'actualizado.transportista@example.test',
    ]);
    $actualizadoPayload['identificacionNumeroOriginal'] = $creado['identificacionNumero'];
    $actualizado = test_transportista_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $actualizado['status'], 'Actualización por identificación inmutable');
    test_same('actualizado.transportista@example.test', $actualizado['body']['data']['correoElectronico'],
        'Actualiza el correo electrónico');

    $identificacionModificada = $actualizadoPayload;
    $identificacionModificada['identificacion']['numero'] = test_document();
    test_same(422, test_transportista_controller()->procesar('PUT', [], $identificacionModificada)['status'],
        'PUT debe rechazar cambios de identificación');

    $putNoExiste = test_transportista_payload('NOEXISTE999');
    $putNoExiste['identificacionNumeroOriginal'] = 'NOEXISTE999';
    test_same(404, test_transportista_controller()->procesar('PUT', [], $putNoExiste)['status'],
        'PUT de transportista inexistente debe responder 404');

    // ============================================================
    // Campos desconocidos (transportista no admite direccionPrincipal, fincas ni vehiculos)
    // ============================================================
    $conCamposAjenos = test_transportista_payload(test_document(), [
        'direccionPrincipal' => ['provincia' => 'Cartago'],
    ]);
    test_same(422, test_transportista_controller()->procesar('POST', [], $conCamposAjenos)['status'],
        'Transportista no admite direccionPrincipal; debe rechazarse como campo desconocido');

    $conVehiculos = test_transportista_payload(test_document(), ['vehiculos' => []]);
    test_same(422, test_transportista_controller()->procesar('POST', [], $conVehiculos)['status'],
        'Transportista no admite vehiculos en el alta; los vehículos se asignan aparte');

    // ============================================================
    // Desactivación / reactivación lógica
    // ============================================================
    $desactivado = test_transportista_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same(200, $desactivado['status'], 'Desactivación debe responder 200');
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica');

    $conteoFisico = test_db()->prepare('SELECT COUNT(*) FROM tbtransportista WHERE tbtransportistaidentificacionnumero = :id');
    $conteoFisico->execute(['id' => $creado['identificacionNumero']]);
    test_same(1, (int) $conteoFisico->fetchColumn(), 'Desactivar no debe borrar físicamente la fila');

    $reintentoDesactivar = test_transportista_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same(200, $reintentoDesactivar['status'], 'Desactivar dos veces debe ser idempotente');

    $conflicto = test_transportista_controller()->procesar('POST', [], test_transportista_payload($visible));
    test_same(409, $conflicto['status'], 'Una identificación inactiva permanece reservada por la aplicación');
    test_same($creado['identificacionNumero'], $conflicto['body']['data']['reactivacion']['identificacionNumero'],
        'Indica la identificación para reactivar');

    $actualizarInactivo = test_transportista_payload($visible, ['telefono' => '+506 2222-4444']);
    $actualizarInactivo['identificacionNumeroOriginal'] = $visible;
    test_same(409, test_transportista_controller()->procesar('PUT', [], $actualizarInactivo)['status'],
        'No debe permitir actualizar un transportista inactivo');

    $reactivado = test_transportista_controller()->procesar('PATCH', [], ['identificacionNumero' => $visible]);
    test_same(200, $reactivado['status'], 'Reactivación debe responder 200');
    test_same('ACTIVO', $reactivado['body']['data']['estado'], 'Reactiva la misma fila');

    $reintentoReactivar = test_transportista_controller()->procesar('PATCH', [], ['identificacionNumero' => $visible]);
    test_same(200, $reintentoReactivar['status'], 'Reactivar dos veces debe ser idempotente');

    $desactivarNoExiste = test_transportista_controller()->procesar('DELETE', [], ['identificacionNumero' => 'NOEXISTE999']);
    test_same(404, $desactivarNoExiste['status'], 'Desactivar transportista inexistente debe responder 404');

    $reactivarNoExiste = test_transportista_controller()->procesar('PATCH', [], ['identificacionNumero' => 'NOEXISTE999']);
    test_same(404, $reactivarNoExiste['status'], 'Reactivar transportista inexistente debe responder 404');

    // ============================================================
    // Datos inválidos y método no permitido
    // ============================================================
    $invalido = test_transportista_controller()->procesar('POST', [],
        test_transportista_payload(test_document(), ['identificacion' => ['tipoCodigo' => 'INVENTADO']]));
    test_same(422, $invalido['status'], 'Rechaza tipo de identificación no admitido');

    $sinNombre = test_transportista_payload(test_document(), ['nombre' => 'AB']);
    test_same(422, test_transportista_controller()->procesar('POST', [], $sinNombre)['status'],
        'Rechaza nombre demasiado corto');

    $correoInvalido = test_transportista_payload(test_document(), ['correoElectronico' => 'no-es-correo']);
    test_same(422, test_transportista_controller()->procesar('POST', [], $correoInvalido)['status'],
        'Rechaza correo electrónico inválido');

    $telefonoInvalido = test_transportista_payload(test_document(), ['telefono' => '123']);
    test_same(422, test_transportista_controller()->procesar('POST', [], $telefonoInvalido)['status'],
        'Rechaza teléfono con muy pocos dígitos');

    $sinId = test_transportista_controller()->procesar('GET', ['identificacionNumero' => 'NOEXISTE999'], []);
    test_same(404, $sinId['status'], 'Identificación inexistente');

    $metodo = test_transportista_controller()->procesar('TRACE', [], []);
    test_same(405, $metodo['status'], 'Método no permitido');

    // ============================================================
    // Bitácora: verifica que la entidad quede correctamente etiquetada
    // ============================================================
    $bitacora = test_db()->prepare(
        'SELECT tbbitacoraentidad, tbbitacoraorigen FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id AND tbbitacoraaccion = :accion'
    );
    $bitacora->execute(['id' => $creado['identificacionNumero'], 'accion' => 'CREAR']);
    $filaBitacora = $bitacora->fetch();
    test_assert($filaBitacora !== false, 'Debe existir un registro de bitácora para la creación del transportista');
    test_same('TRANSPORTISTA', $filaBitacora['tbbitacoraentidad'], 'La bitácora debe etiquetar la entidad como TRANSPORTISTA');
    test_same('API_TRANSPORTISTAS', $filaBitacora['tbbitacoraorigen'], 'La bitácora debe etiquetar el origen como API_TRANSPORTISTAS');
} finally {
    test_cleanup_transportistas($ids);
}

echo "OK transportista_test: CRUD de transportista, duplicados, desactivación/reactivación, "
    . "campos ajenos rechazados y bitácora correctamente etiquetada.\n";