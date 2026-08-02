<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$ids = [];
try {
    $visible = 'AB-00-' . strtoupper(bin2hex(random_bytes(4)));
    $creado = test_create(['fincas' => [['nombre' => 'Finca Uno'], ['nombre' => 'Finca Dos']]], $visible);
    $ids[] = $creado['identificacionNumero'];
    test_same(str_replace('-', '', $visible), $creado['identificacionNumero'], 'La PK debe almacenarse canónica');
    test_same(2, count($creado['fincas']), 'Debe admitir varias fincas sin tbfinca');

    $consulta = test_controller()->procesar('GET', ['identificacionNumero' => $visible], []);
    test_same(200, $consulta['status'], 'Consulta por PK');
    $lista = test_controller()->procesar('GET', ['q' => $visible, 'estado' => 'ACTIVO'], []);
    test_same(1, $lista['body']['data']['total'], 'Búsqueda por identificación');

    $actualizadoPayload = test_payload($visible, [
        'telefono' => '+506 2222-3333',
        'correoElectronico' => 'actualizado@example.test',
        'direccionPrincipal' => ['provincia' => 'Heredia'],
        'fincas' => [['nombre' => 'Finca Tres']],
    ]);
    $actualizadoPayload['identificacionNumeroOriginal'] = $creado['identificacionNumero'];
    $actualizado = test_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $actualizado['status'], 'Actualización por PK natural');
    test_same('Heredia', $actualizado['body']['data']['direccionPrincipal']['provincia'], 'Actualiza dirección');
    test_same([['nombre' => 'Finca Tres']], $actualizado['body']['data']['fincas'], 'Sincroniza fincas');

    $desactivado = test_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica');
    $conflicto = test_controller()->procesar('POST', [], test_payload($visible));
    test_same(409, $conflicto['status'], 'Una PK inactiva permanece reservada');
    test_same($creado['identificacionNumero'], $conflicto['body']['data']['reactivacion']['identificacionNumero'], 'Indica la PK para reactivar');
    $reactivado = test_controller()->procesar('PATCH', [], ['identificacionNumero' => $visible]);
    test_same('ACTIVO', $reactivado['body']['data']['estado'], 'Reactiva la misma fila');

    $invalido = test_controller()->procesar('POST', [], test_payload(test_document(), ['identificacion' => ['tipoCodigo' => 'INVENTADO']]));
    test_same(422, $invalido['status'], 'Rechaza tipo no admitido');
    $sinId = test_controller()->procesar('GET', ['identificacionNumero' => 'NOEXISTE'], []);
    test_same(404, $sinId['status'], 'PK inexistente');
    $metodo = test_controller()->procesar('TRACE', [], []);
    test_same(405, $metodo['status'], 'Método no permitido');
} finally {
    test_cleanup_productores($ids);
}

echo "OK api_productores_test: CRUD JSON por identificación, fincas, búsqueda y reactivación.\n";
