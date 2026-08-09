<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$ids = [];
try {
    $visible = 'AB-00-' . strtoupper(bin2hex(random_bytes(4)));
    $creado = test_create(['fincas' => [['nombre' => 'Finca Uno'], ['nombre' => 'Finca Dos']]], $visible);
    $ids[] = $creado['identificacionNumero'];
    test_same(str_replace('-', '', $visible), $creado['identificacionNumero'], 'La identificación debe almacenarse canónica');
    test_assert(is_int($creado['productorId']) && $creado['productorId'] > 0,
        'PHP debe asignar tbproductorid sin AUTO_INCREMENT en MySQL');
    test_same(2, count($creado['fincas']), 'Debe admitir varias fincas en tbfinca');

    $consulta = test_controller()->procesar('GET', ['identificacionNumero' => $visible], []);
    test_same(200, $consulta['status'], 'Consulta por identificación');
    $lista = test_controller()->procesar('GET', ['q' => $visible, 'estado' => 'ACTIVO'], []);
    test_same(1, $lista['body']['data']['total'], 'Búsqueda por identificación');

    $actualizadoPayload = test_payload($visible, [
        'telefono' => '+506 2222-3333',
        'correoElectronico' => 'actualizado@example.test',
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Heredia']),
        'fincas' => [['nombre' => 'Finca Tres']],
    ]);
    $actualizadoPayload['identificacionNumeroOriginal'] = $creado['identificacionNumero'];
    $actualizado = test_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $actualizado['status'], 'Actualización por identificación inmutable');
    test_same('Heredia', $actualizado['body']['data']['direccionPrincipal']['provincia'], 'Actualiza dirección');
    test_same([['nombre' => 'Finca Tres']], $actualizado['body']['data']['fincas'], 'Sincroniza fincas');

    $repetido = test_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $repetido['status'], 'PUT repetido debe ser idempotente');
    $conteoFincas = test_db()->prepare('SELECT COUNT(*) FROM tbfinca
        WHERE tbproductorid = :id');
    $conteoFincas->execute(['id' => $creado['productorId']]);
    test_same(3, (int) $conteoFincas->fetchColumn(), 'PUT repetido no debe duplicar fincas sin depender de una PK compuesta');

    $identificacionModificada = $actualizadoPayload;
    $identificacionModificada['identificacion']['numero'] = test_document();
    test_same(422, test_controller()->procesar('PUT', [], $identificacionModificada)['status'],
        'PUT debe rechazar cambios de identificación');

    $fincaDuplicada = test_payload(test_document(), [
        'fincas' => [['nombre' => 'Finca Repetida'], ['nombre' => 'finca repetida']],
    ]);
    test_same(422, test_controller()->procesar('POST', [], $fincaDuplicada)['status'],
        'La misma finca no puede repetirse para un productor');

    $physicalCounts = [];
    foreach (['tbproductor', 'tbproductordireccion', 'tbfinca'] as $tabla) {
        $columna = $tabla === 'tbproductor' ? 'tbproductoridentificacionnumero' : 'tbproductorid';
        $valor = $tabla === 'tbproductor' ? $creado['identificacionNumero'] : $creado['productorId'];
        $statement = test_db()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :valor");
        $statement->execute(['valor' => $valor]);
        $physicalCounts[$tabla] = (int) $statement->fetchColumn();
    }
    $desactivado = test_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica');
    foreach (['tbproductor', 'tbproductordireccion', 'tbfinca'] as $tabla) {
        $columna = $tabla === 'tbproductor' ? 'tbproductoridentificacionnumero' : 'tbproductorid';
        $valor = $tabla === 'tbproductor' ? $creado['identificacionNumero'] : $creado['productorId'];
        $statement = test_db()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :valor");
        $statement->execute(['valor' => $valor]);
        test_same($physicalCounts[$tabla], (int) $statement->fetchColumn(),
            "Desactivar no debe borrar físicamente {$tabla}");
    }
    $conflicto = test_controller()->procesar('POST', [], test_payload($visible));
    test_same(409, $conflicto['status'], 'Una identificación inactiva permanece reservada por la aplicación');
    test_same($creado['identificacionNumero'], $conflicto['body']['data']['reactivacion']['identificacionNumero'], 'Indica la identificación para reactivar');
    $reactivado = test_controller()->procesar('PATCH', [], ['identificacionNumero' => $visible]);
    test_same('ACTIVO', $reactivado['body']['data']['estado'], 'Reactiva la misma fila');
    test_same($creado['identificacionNumero'], $reactivado['body']['data']['identificacionNumero'],
        'La reactivación debe conservar la identificación');
    foreach ($physicalCounts as $tabla => $expectedCount) {
        $columna = $tabla === 'tbproductor' ? 'tbproductoridentificacionnumero' : 'tbproductorid';
        $valor = $tabla === 'tbproductor' ? $creado['identificacionNumero'] : $creado['productorId'];
        $statement = test_db()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :valor");
        $statement->execute(['valor' => $valor]);
        test_same($expectedCount, (int) $statement->fetchColumn(),
            "Reactivar no debe crear ni borrar filas físicas en {$tabla}");
    }

    $invalido = test_controller()->procesar('POST', [], test_payload(test_document(), ['identificacion' => ['tipoCodigo' => 'INVENTADO']]));
    test_same(422, $invalido['status'], 'Rechaza tipo no admitido');
    $sinId = test_controller()->procesar('GET', ['identificacionNumero' => 'NOEXISTE'], []);
    test_same(404, $sinId['status'], 'Identificación inexistente');
    $metodo = test_controller()->procesar('TRACE', [], []);
    test_same(405, $metodo['status'], 'Método no permitido');

    test_same(405, test_http_json('TRACE')['status'], 'HTTP 405 debe ser JSON');
    test_same(415, test_http_json('POST', '{}', 'text/plain')['status'], 'HTTP 415 debe ser JSON');
    test_same(400, test_http_json('POST', '{')['status'], 'HTTP 400 debe ser JSON');
} finally {
    test_cleanup_productores($ids);
}

echo "OK api_productores_test: CRUD JSON por identificación, fincas, búsqueda y reactivación.\n";
