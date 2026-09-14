<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$ids = [];
try {
    $visible = 'AB-00-' . strtoupper(bin2hex(random_bytes(4)));
    $direccionFincaUno = array_replace(
        test_direccion_payload(['provincia' => 'Guanacaste', 'canton' => 'Liberia']),
        ['latitud' => '10.6345960', 'longitud' => '-85.4406740'],
    );
    $creado = test_create([
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Alajuela']),
        'fincas' => [
            ['nombre' => 'Finca Uno', 'direccion' => $direccionFincaUno],
            ['nombre' => 'Finca Dos'],
        ],
    ], $visible);
    $ids[] = $creado['identificacionNumero'];
    test_same(str_replace('-', '', $visible), $creado['identificacionNumero'], 'La identificación debe almacenarse canónica');
    test_assert(is_int($creado['productorId']) && $creado['productorId'] > 0,
        'PHP debe asignar tbproductorid sin AUTO_INCREMENT en MySQL');
    test_same('Alajuela', $creado['direccionPrincipal']['provincia'],
        'POST debe aceptar y persistir la dirección enviada por la UI');
    test_same(2, count($creado['fincas']), 'Debe admitir varias fincas en tbfinca');

    $fincaUno = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $creado['identificacionNumero'],
        'nombreFinca' => 'Finca Uno',
    ], []);
    test_same(200, $fincaUno['status'],
        'POST Productor debe guardar la dirección opcional de finca en la misma operación');
    test_same('Guanacaste', $fincaUno['body']['data']['direccionFinca']['provincia'],
        'La dirección debe quedar asociada a la finca correcta');
    test_same('10.6345960', $fincaUno['body']['data']['direccionFinca']['latitud'],
        'La coordenada de finca debe conservar precisión de siete decimales');

    $consulta = test_controller()->procesar('GET', ['identificacionNumero' => $visible], []);
    test_same(200, $consulta['status'], 'Consulta por identificación');
    $lista = test_controller()->procesar('GET', ['q' => $visible, 'estado' => 'ACTIVO'], []);
    test_same(1, $lista['body']['data']['total'], 'Búsqueda por identificación');

    $actualizadoPayload = test_payload($visible, [
        'telefono' => '+506 2222-3333',
        'correoElectronico' => 'actualizado@example.test',
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Heredia']),
        'fincas' => [[
            'nombre' => 'Finca Tres',
            'direccion' => array_replace(
                test_direccion_payload(['provincia' => 'Heredia', 'canton' => 'Barva']),
                ['latitud' => '10.0190000', 'longitud' => '-84.1230000'],
            ),
        ]],
    ]);
    $actualizadoPayload['identificacionNumeroOriginal'] = $creado['identificacionNumero'];
    $actualizado = test_controller()->procesar('PUT', [], $actualizadoPayload);
    test_same(200, $actualizado['status'], 'Actualización por identificación inmutable');
    test_same('Heredia', $actualizado['body']['data']['direccionPrincipal']['provincia'], 'Actualiza dirección');
    test_same([['nombre' => 'Finca Tres']], $actualizado['body']['data']['fincas'], 'Sincroniza fincas');

    $fincaTres = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $creado['identificacionNumero'],
        'nombreFinca' => 'Finca Tres',
    ], []);
    test_same(200, $fincaTres['status'],
        'PUT Productor debe crear/actualizar la dirección de finca dentro de la misma transacción');
    test_same('Barva', $fincaTres['body']['data']['direccionFinca']['canton'],
        'PUT debe persistir la dirección estructurada de la finca activa');

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
        'La política vigente mantiene nombre de finca único mientras sea su identidad conceptual');

    $coordenadaIncompleta = test_payload(test_document(), [
        'fincas' => [[
            'nombre' => 'Finca Coordenada Incompleta',
            'direccion' => array_replace(test_direccion_payload(), ['latitud' => '10.5000000']),
        ]],
    ]);
    test_same(422, test_controller()->procesar('POST', [], $coordenadaIncompleta)['status'],
        'PHP debe rechazar latitud sin longitud; la base no debe imponer esa política');

    // Regresión de atomicidad: se introduce deliberadamente una inconsistencia
    // en una finca YA creada para forzar un fallo al final del PUT. El cambio de
    // teléfono y el nuevo periodo de dirección principal ocurren antes del fallo;
    // ambos deben quedar revertidos por la misma transacción.
    $rollbackCreado = test_create([
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Puntarenas']),
        'fincas' => [[
            'nombre' => 'Finca Rollback',
            'direccion' => array_replace(
                test_direccion_payload(['provincia' => 'Puntarenas']),
                ['latitud' => '9.9763000', 'longitud' => '-84.8384000'],
            ),
        ]],
    ]);
    $ids[] = $rollbackCreado['identificacionNumero'];
    $antesRollback = test_controller()->procesar('GET', [
        'identificacionNumero' => $rollbackCreado['identificacionNumero'],
    ], [])['body']['data'];

    $buscarEnlace = test_db()->prepare(
        'SELECT f.tbfincaid, fd.tbdireccionid
         FROM tbfinca f
         INNER JOIN tbfincadireccion fd ON fd.tbfincaid = f.tbfincaid
         WHERE f.tbproductorid = :productorId
           AND f.tbfincanombre = :nombre
           AND f.tbfincaestado = 1'
    );
    $buscarEnlace->execute([
        'productorId' => $rollbackCreado['productorId'],
        'nombre' => 'Finca Rollback',
    ]);
    $enlaceBase = $buscarEnlace->fetch();
    test_assert(is_array($enlaceBase), 'La fixture de rollback debe tener una dirección de finca');
    $siguienteEnlace = (int) test_db()->query(
        'SELECT COALESCE(MAX(tbfincadireccionid), 0) + 1 FROM tbfincadireccion'
    )->fetchColumn();
    test_db()->prepare(
        'INSERT INTO tbfincadireccion (tbfincadireccionid, tbfincaid, tbdireccionid)
         VALUES (:id, :fincaId, :direccionId)'
    )->execute([
        'id' => $siguienteEnlace,
        'fincaId' => (int) $enlaceBase['tbfincaid'],
        'direccionId' => (int) $enlaceBase['tbdireccionid'],
    ]);

    $payloadRollback = test_payload($rollbackCreado['identificacionNumero'], [
        'telefono' => '+506 2111-9999',
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Cartago']),
        'fincas' => [[
            'nombre' => 'Finca Rollback',
            'direccion' => array_replace(
                test_direccion_payload(['provincia' => 'Cartago']),
                ['latitud' => '9.8644000', 'longitud' => '-83.9194000'],
            ),
        ]],
    ]);
    $payloadRollback['identificacionNumeroOriginal'] = $rollbackCreado['identificacionNumero'];
    $falloRollback = test_controller()->procesar('PUT', [], $payloadRollback);
    test_same(409, $falloRollback['status'],
        'Una inconsistencia al guardar una finca debe abortar todo el PUT de Productor');

    $despuesRollback = test_controller()->procesar('GET', [
        'identificacionNumero' => $rollbackCreado['identificacionNumero'],
    ], [])['body']['data'];
    test_same($antesRollback['telefono'], $despuesRollback['telefono'],
        'El teléfono debe volver al valor previo si falla una dirección de finca');
    test_same($antesRollback['direccionPrincipal']['provincia'], $despuesRollback['direccionPrincipal']['provincia'],
        'El periodo de dirección principal debe revertirse si falla una dirección de finca');

    $periodosAbiertos = test_db()->prepare(
        'SELECT COUNT(*) FROM tbproductordireccion
         WHERE tbproductorid = :productorId AND tbproductordireccionfechafin IS NULL'
    );
    $periodosAbiertos->execute(['productorId' => $rollbackCreado['productorId']]);
    test_same(1, (int) $periodosAbiertos->fetchColumn(),
        'El rollback debe conservar exactamente un periodo vigente de dirección principal');

    $physicalCounts = [];
    foreach (['tbproductor', 'tbproductordireccion', 'tbfinca'] as $tabla) {
        $valor = $tabla === 'tbproductor' ? $creado['identificacionNumero'] : $creado['productorId'];
        $sql = $tabla === 'tbproductor'
            ? 'SELECT COUNT(*) FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid WHERE pe.tbpersonaidentificacionnumero=:valor'
            : "SELECT COUNT(*) FROM {$tabla} WHERE tbproductorid = :valor";
        $statement = test_db()->prepare($sql);
        $statement->execute(['valor' => $valor]);
        $physicalCounts[$tabla] = (int) $statement->fetchColumn();
    }
    $desactivado = test_controller()->procesar('DELETE', [], ['identificacionNumero' => $visible]);
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica');
    foreach (['tbproductor', 'tbproductordireccion', 'tbfinca'] as $tabla) {
        $valor = $tabla === 'tbproductor' ? $creado['identificacionNumero'] : $creado['productorId'];
        $sql = $tabla === 'tbproductor'
            ? 'SELECT COUNT(*) FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid WHERE pe.tbpersonaidentificacionnumero=:valor'
            : "SELECT COUNT(*) FROM {$tabla} WHERE tbproductorid = :valor";
        $statement = test_db()->prepare($sql);
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
        $valor = $tabla === 'tbproductor' ? $creado['identificacionNumero'] : $creado['productorId'];
        $sql = $tabla === 'tbproductor'
            ? 'SELECT COUNT(*) FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid WHERE pe.tbpersonaidentificacionnumero=:valor'
            : "SELECT COUNT(*) FROM {$tabla} WHERE tbproductorid = :valor";
        $statement = test_db()->prepare($sql);
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

echo "OK api_productores_test: CRUD JSON, fincas/direcciones atómicas, rollback y reactivación.\n";
