<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Application/Model/Comprador.php';
require dirname(__DIR__) . '/Application/Controller/CompradorController.php';

use Application\Controller\CompradorController;

function test_comprador_payload(?string $number = null, array $overrides = []): array
{
    $base = [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $number ?? test_document()],
        'nombre' => 'Comprador Ficticio de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'comprador.tests@example.test',
    ];
    return array_replace_recursive($base, $overrides);
}

function test_comprador_controller(?string $requestId = null): CompradorController
{
    return new CompradorController(test_db(), $requestId ?? test_token('request'));
}

function test_create_comprador(array $overrides = [], ?string $number = null): array
{
    $response = test_comprador_controller()->procesar('POST', [], test_comprador_payload($number, $overrides));
    test_same(201, $response['status'], 'La fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La fixture debe ser exitosa.');
    return $response['body']['data'];
}

function test_cleanup_compradores(array $identificaciones): void
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbcomprador WHERE tbcompradoridentificacionnumero IN ({$marcadores})")->execute($ids);
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
    $visible = 'CO-00-' . strtoupper(bin2hex(random_bytes(4)));
    $creado = test_create_comprador([], $visible);
    $ids[] = $creado['identificacionNumero'];
    test_same(str_replace('-', '', $visible), $creado['identificacionNumero'], 'La identificación debe almacenarse canónica');
    test_assert(is_int($creado['compradorId']) && $creado['compradorId'] > 0,
        'PHP debe asignar tbcompradorid sin AUTO_INCREMENT en MySQL');
    test_same('ACTIVO', $creado['estado'], 'El alta debe crear el comprador activo');

    $consulta = test_comprador_controller()->procesar('GET', ['identificacionNumero' => $visible], []);
    test_same(200, $consulta['status'], 'Consulta por identificación');
    $lista = test_comprador_controller()->procesar('GET', ['q' => $visible, 'estado' => 'ACTIVO'], []);
    test_same(1, $lista['body']['data']['total'], 'Búsqueda por identificación');

    // ============================================================
    // Duplicados (activo e inactivo)
    // ============================================================
    $duplicado = test_comprador_controller()->procesar('POST', [], test_comprador_payload($visible));
    test_same(409, $duplicado['status'], 'No debe permitir dos altas con la misma identificación');

    // ============================================================
    // Actualización
    // ============================================================
    $actualizadoPayload = test_comprador_payload($visible, [
        'telefono' => '+506 2222-3333',
        'correoElectronico' => 'actualizado.comprador@example.test',
    ]);
    $actualizadoPayload['identificacionNumeroOriginal'] = $creado['identificacionNumero'];
    $actualizado = test_comprador_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $actualizado['status'], 'Actualización por identificación inmutable');
    test_same('actualizado.comprador@example.test', $actualizado['body']['data']['correoElectronico'],
        'Actualiza el correo electrónico');

    $identificacionModificada = $actualizadoPayload;
    $identificacionModificada['identificacion']['numero'] = test_document();
    test_same(422, test_comprador_controller()->procesar('PUT', [], $identificacionModificada)['status'],
        'PUT debe rechazar cambios de identificación');

    $putNoExiste = test_comprador_payload('NOEXISTE999');
    $putNoExiste['identificacionNumeroOriginal'] = 'NOEXISTE999';
    test_same(404, test_comprador_controller()->procesar('PUT', [], $putNoExiste)['status'],
        'PUT de comprador inexistente debe responder 404');

    // ============================================================
    // Campos desconocidos (comprador no admite direccionPrincipal ni fincas)
    // ============================================================
    $conCamposAjenos = test_comprador_payload(test_document(), [
        'direccionPrincipal' => ['provincia' => 'Cartago'],
    ]);
    test_same(422, test_comprador_controller()->procesar('POST', [], $conCamposAjenos)['status'],
        'Comprador no admite direccionPrincipal; debe rechazarse como campo desconocido');

    $conFincas = test_comprador_payload(test_document(), ['fincas' => []]);
    test_same(422, test_comprador_controller()->procesar('POST', [], $conFincas)['status'],
        'Comprador no admite fincas; debe rechazarse como campo desconocido');

    // ============================================================
    // Desactivación / reactivación lógica
    // ============================================================
    $desactivado = test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same(200, $desactivado['status'], 'Desactivación debe responder 200');
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica');

    $conteoFisico = test_db()->prepare('SELECT COUNT(*) FROM tbcomprador WHERE tbcompradoridentificacionnumero = :id');
    $conteoFisico->execute(['id' => $creado['identificacionNumero']]);
    test_same(1, (int) $conteoFisico->fetchColumn(), 'Desactivar no debe borrar físicamente la fila');

    $reintentoDesactivar = test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same(200, $reintentoDesactivar['status'], 'Desactivar dos veces debe ser idempotente');

    $conflicto = test_comprador_controller()->procesar('POST', [], test_comprador_payload($visible));
    test_same(409, $conflicto['status'], 'Una identificación inactiva permanece reservada por la aplicación');
    test_same($creado['identificacionNumero'], $conflicto['body']['data']['reactivacion']['identificacionNumero'],
        'Indica la identificación para reactivar');

    $reactivado = test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => $visible]);
    test_same(200, $reactivado['status'], 'Reactivación debe responder 200');
    test_same('ACTIVO', $reactivado['body']['data']['estado'], 'Reactiva la misma fila');

    $reintentoReactivar = test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => $visible]);
    test_same(200, $reintentoReactivar['status'], 'Reactivar dos veces debe ser idempotente');

    $desactivarNoExiste = test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => 'NOEXISTE999']);
    test_same(404, $desactivarNoExiste['status'], 'Desactivar comprador inexistente debe responder 404');

    $reactivarNoExiste = test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => 'NOEXISTE999']);
    test_same(404, $reactivarNoExiste['status'], 'Reactivar comprador inexistente debe responder 404');

    // ============================================================
    // Datos inválidos y método no permitido
    // ============================================================
    $invalido = test_comprador_controller()->procesar('POST', [],
        test_comprador_payload(test_document(), ['identificacion' => ['tipoCodigo' => 'INVENTADO']]));
    test_same(422, $invalido['status'], 'Rechaza tipo de identificación no admitido');

    $sinNombre = test_comprador_payload(test_document(), ['nombre' => 'AB']);
    test_same(422, test_comprador_controller()->procesar('POST', [], $sinNombre)['status'],
        'Rechaza nombre demasiado corto');

    $correoInvalido = test_comprador_payload(test_document(), ['correoElectronico' => 'no-es-correo']);
    test_same(422, test_comprador_controller()->procesar('POST', [], $correoInvalido)['status'],
        'Rechaza correo electrónico inválido');

    $sinId = test_comprador_controller()->procesar('GET', ['identificacionNumero' => 'NOEXISTE999'], []);
    test_same(404, $sinId['status'], 'Identificación inexistente');

    $metodo = test_comprador_controller()->procesar('TRACE', [], []);
    test_same(405, $metodo['status'], 'Método no permitido');

    // ============================================================
    // Bitácora: verifica que la entidad quede correctamente etiquetada
    // (y no como PRODUCTOR, el bug que hubiéramos tenido sin la Opción A).
    // ============================================================
    $bitacora = test_db()->prepare(
        'SELECT tbbitacoraentidad, tbbitacoraorigen FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id AND tbbitacoraaccion = :accion'
    );
    $bitacora->execute(['id' => $creado['identificacionNumero'], 'accion' => 'CREAR']);
    $filaBitacora = $bitacora->fetch();
    test_assert($filaBitacora !== false, 'Debe existir un registro de bitácora para la creación del comprador');
    test_same('COMPRADOR', $filaBitacora['tbbitacoraentidad'], 'La bitácora debe etiquetar la entidad como COMPRADOR');
    test_same('API_COMPRADORES', $filaBitacora['tbbitacoraorigen'], 'La bitácora debe etiquetar el origen como API_COMPRADORES');
} finally {
    test_cleanup_compradores($ids);
}

echo "OK comprador_test: CRUD de comprador, duplicados, desactivación/reactivación, "
    . "campos ajenos rechazados y bitácora correctamente etiquetada.\n";