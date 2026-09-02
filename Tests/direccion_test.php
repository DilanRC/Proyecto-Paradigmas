<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\ProductorDireccion;
use Application\Model\Direccion;

$id = test_document();

// NOTA: hay DOS guardas de duplicados independientes (Productor::buscar() y
// ProductorDireccion::buscar()), cada una con su propio mensaje. En el flujo de
// consultarDireccion(), Productor::buscar() se ejecuta primero, así que su
// RuntimeException es el que se observa aquí — el de ProductorDireccion::buscar()
// solo se alcanzaría si se llamara a ese método de forma aislada.

try {
    // ============================================================
    // Fixture: productor activo con dirección vacía (creada automáticamente por POST)
    // ============================================================
    $productor = test_create([], $id);
    $productorId = $productor['productorId'];
    $db = test_db();
    $modeloDireccion = new ProductorDireccion($db, new Direccion($db));

    // ============================================================
    // GET /productores-direccion — consultarDireccion()
    // ============================================================

    $sinParametro = test_controller()->procesarDireccion('GET', [], []);
    test_same(422, $sinParametro['status'], 'GET sin identificacionNumero debe rechazarse');

    $formatoInvalido = test_controller()->procesarDireccion('GET', ['identificacionNumero' => '   '], []);
    test_same(422, $formatoInvalido['status'], 'GET con identificación vacía/inválida debe rechazarse');

    $noExiste = test_controller()->procesarDireccion('GET', ['identificacionNumero' => 'NOEXISTE999'], []);
    test_same(404, $noExiste['status'], 'GET de productor inexistente debe responder 404');
    test_same(
        'Productor no encontrado.',
        $noExiste['body']['message'],
        'El mensaje de "no encontrado" debe ser distinguible del de "sin dirección"'
    );

    $consultaValida = test_controller()->procesarDireccion('GET', ['identificacionNumero' => $id], []);
    test_same(200, $consultaValida['status'], 'GET de productor existente con dirección debe responder 200');
    test_same($id, $consultaValida['body']['data']['identificacionNumero'], 'GET debe devolver la identificación consultada');
    test_assert(is_array($consultaValida['body']['data']['direccionPrincipal']), 'GET debe devolver la dirección');

    // Productor existente SIN fila de dirección (la política de aplicación no permite este
    // estado en un flujo normal; lo forzamos para probar la rama de código).
    $db->prepare('DELETE FROM tbproductordireccion WHERE tbproductorid = :id')->execute(['id' => $productorId]);

    $sinDireccion = test_controller()->procesarDireccion('GET', ['identificacionNumero' => $id], []);
    test_same(404, $sinDireccion['status'], 'GET de productor sin dirección debe responder 404');
    test_same(
        'El productor no tiene una dirección registrada.',
        $sinDireccion['body']['message'],
        'El mensaje de "sin dirección" debe ser distinguible del de "no encontrado"'
    );

    // Restauramos la fila vacía usando el modelo real (mismo camino que usa el POST de alta).
    // CORRECCIÓN: crearVacia() requiere ejecutarConBloqueoAlta() porque internamente
    // llama a insertarEnlace() que calcula MAX(id)+1 y crea una dirección.
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->crearVacia($productorId)
    );

    // ============================================================
    // PUT /productores-direccion — actualizarDireccion()
    // ============================================================

    $putSinDireccion = test_controller()->procesarDireccion('PUT', [], ['identificacionNumero' => $id]);
    test_same(422, $putSinDireccion['status'], 'PUT sin direccionPrincipal debe rechazarse');

    $putCampoDesconocido = test_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id,
        'direccionPrincipal' => test_direccion_payload(['campoInventado' => 'x']),
    ]);
    test_same(422, $putCampoDesconocido['status'], 'PUT con campo desconocido dentro de direccionPrincipal debe rechazarse');

    $putNoExiste = test_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => 'NOEXISTE999',
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    test_same(404, $putNoExiste['status'], 'PUT de productor inexistente debe responder 404');

    $putValido = test_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id,
        'direccionPrincipal' => test_direccion_payload(['provincia' => 'Limón']),
    ]);
    test_same(200, $putValido['status'], 'PUT válido debe responder 200');
    test_same('Limón', $putValido['body']['data']['direccionPrincipal']['provincia'], 'PUT debe persistir el nuevo valor');

    // Productor inactivo (se desactiva/reactiva vía el endpoint de productor, no el de dirección).
    test_controller()->procesar('DELETE', [], ['identificacionNumero' => $id]);
    $putInactivo = test_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id,
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    test_same(409, $putInactivo['status'], 'PUT sobre productor inactivo debe responder 409');
    test_controller()->procesar('PATCH', [], ['identificacionNumero' => $id]); // reactivar

    // Productor existente SIN fila de dirección.
    $db->prepare('DELETE FROM tbproductordireccion WHERE tbproductorid = :id')->execute(['id' => $productorId]);
    $putSinFila = test_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id,
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    test_same(404, $putSinFila['status'], 'PUT sobre productor sin fila de dirección debe responder 404');
    test_same(
        'El productor no tiene una dirección registrada; use POST para crearla.',
        $putSinFila['body']['message'],
        'El mensaje debe orientar a usar POST'
    );

    // Restauramos la fila (con valores) para las pruebas de DELETE, usando el modelo real.
    // CORRECCIÓN: crear() también requiere ejecutarConBloqueoAlta().
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->crear($productorId, test_direccion_payload(['provincia' => 'Limón']))
    );

    // ============================================================
    // DELETE /productores-direccion — eliminarDireccion()
    // ============================================================

    $delCampoDesconocido = test_controller()->procesarDireccion('DELETE', [], [
        'identificacionNumero' => $id,
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    test_same(422, $delCampoDesconocido['status'], 'DELETE con campo desconocido en el cuerpo debe rechazarse');
    test_same(
        'Campo no permitido.',
        $delCampoDesconocido['body']['errors']['direccionPrincipal'],
        'rechazarCamposDesconocidos debe reportar direccionPrincipal como campo no permitido en DELETE'
    );

    $delSinIdentificacion = test_controller()->procesarDireccion('DELETE', [], ['identificacionNumero' => '']);
    test_same(422, $delSinIdentificacion['status'], 'DELETE con identificación vacía debe rechazarse');
    test_same(
        'La identificación es obligatoria.',
        $delSinIdentificacion['body']['errors']['identificacionNumero'],
        'El error debe venir en errors.identificacionNumero, no en message'
    );

    $delNoExiste = test_controller()->procesarDireccion('DELETE', [], ['identificacionNumero' => 'NOEXISTE999']);
    test_same(404, $delNoExiste['status'], 'DELETE de productor inexistente debe responder 404');

    $delValido = test_controller()->procesarDireccion('DELETE', [], ['identificacionNumero' => $id]);
    test_same(200, $delValido['status'], 'DELETE válido debe responder 200');
    test_same('', $delValido['body']['data']['direccionPrincipal']['provincia'], 'DELETE debe vaciar provincia');
    test_same('', $delValido['body']['data']['direccionPrincipal']['canton'], 'DELETE debe vaciar cantón');
    test_same(null, $delValido['body']['data']['direccionPrincipal']['pueblo'], 'DELETE debe vaciar pueblo (NULL)');

    $abiertos = $db->prepare('SELECT COUNT(*) FROM tbproductordireccion
        WHERE tbproductorid = :id AND tbproductordireccionfechafin IS NULL');
    $abiertos->execute(['id' => $productorId]);
    test_same(
        1,
        (int) $abiertos->fetchColumn(),
        'DELETE/vaciar conserva exactamente un periodo de dirección abierto (vacío), nunca más'
    );

    // Productor inactivo.
    test_controller()->procesar('DELETE', [], ['identificacionNumero' => $id]); // desactiva el productor
    $delInactivo = test_controller()->procesarDireccion('DELETE', [], ['identificacionNumero' => $id]);
    test_same(409, $delInactivo['status'], 'DELETE sobre productor inactivo debe responder 409');
    test_controller()->procesar('PATCH', [], ['identificacionNumero' => $id]); // reactivar

    // Productor existente SIN fila de dirección: "no hay nada que eliminar".
    $db->prepare('DELETE FROM tbproductordireccion WHERE tbproductorid = :id')->execute(['id' => $productorId]);
    $delSinFila = test_controller()->procesarDireccion('DELETE', [], ['identificacionNumero' => $id]);
    test_same(404, $delSinFila['status'], 'DELETE sobre productor sin fila de dirección debe responder 404');
    test_same(
        'El productor no tiene una dirección registrada; no hay nada que eliminar.',
        $delSinFila['body']['message'],
        'El mensaje debe distinguir este caso del de "no encontrado"'
    );

    // Restauramos la fila vacía para la prueba de integridad final.
    // CORRECCIÓN: crearVacia() requiere ejecutarConBloqueoAlta().
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->crearVacia($productorId)
    );

    // ============================================================
    // buscar() — no debe ocultar más de una dirección por productor
    // ============================================================
    // crear()/crearVacia() rechazan insertar si ya existe una fila (validación de negocio
    // real), así que para forzar el duplicado usamos SQL directo. Este bloque es
    // intencionalmente fuera del lock porque simula corrupción de datos preexistente,
    // no una operación concurrente legítima.
    $direccionDuplicadaId = (int) $db->query(
        'SELECT COALESCE(MAX(tbdireccionid), 0) + 1 FROM tbdireccion'
    )->fetchColumn();
    $db->prepare('INSERT INTO tbdireccion
            (tbdireccionid, tbdireccionprovincia, tbdireccioncanton, tbdirecciondistrito,
             tbdireccionpueblo, tbdireccionsenas)
        VALUES (:direccionId, \'Duplicada\', \'Duplicada\', \'Duplicada\', NULL, NULL)')
        ->execute(['direccionId' => $direccionDuplicadaId]);

    $enlaceDuplicadoId = (int) $db->query(
        'SELECT COALESCE(MAX(tbproductordireccionid), 0) + 1 FROM tbproductordireccion'
    )->fetchColumn();
    $db->prepare('INSERT INTO tbproductordireccion
            (tbproductordireccionid, tbproductorid, tbdireccionid)
        VALUES (:enlaceId, :productorId, :direccionId)')
        ->execute(['enlaceId' => $enlaceDuplicadoId, 'productorId' => $productorId, 'direccionId' => $direccionDuplicadaId]);

    $lanzoExcepcion = false;
    try {
        test_controller()->procesarDireccion('GET', ['identificacionNumero' => $id], []);
    } catch (RuntimeException $excepcion) {
        $lanzoExcepcion = true;
        test_same(
            'La identificación no conserva un único productor y una única dirección.',
            $excepcion->getMessage(),
            'Con un duplicado, Productor::buscar() debe detectarlo antes de llegar a ProductorDireccion::buscar()'
        );
    }
    test_assert(
        $lanzoExcepcion,
        'Un duplicado de dirección debe hacer explotar la consulta, no devolver la primera fila silenciosamente'
    );
    // Limpiamos el duplicado que insertamos, dejando la fila original.
    $db->prepare('DELETE FROM tbproductordireccion WHERE tbproductordireccionid = :enlaceId')
        ->execute(['enlaceId' => $enlaceDuplicadoId]);
    $db->prepare('DELETE FROM tbdireccion WHERE tbdireccionid = :direccionId')
        ->execute(['direccionId' => $direccionDuplicadaId]);
    $conteoFinal = $db->prepare('SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :id');
    $conteoFinal->execute(['id' => $productorId]);
    test_same(1, (int) $conteoFinal->fetchColumn(), 'Debe quedar exactamente una fila tras la limpieza del duplicado');
    // ============================================================
    // Histórico de dirección: periodos con vigencia (tramo 3b)
    // ============================================================
    // Estado de partida: un único enlace abierto creado por crearVacia().
    $periodoInicial = $modeloDireccion->consultarPeriodoAbierto($productorId);
    test_assert($periodoInicial !== null, 'El productor conserva su enlace de dirección abierto');
    test_assert($periodoInicial['tbproductordireccionfechafin'] === null,
        'El enlace vigente tiene fechafin NULL');
    $direccionInicialId = $periodoInicial['tbdireccionid'];

    // crear() se rechaza únicamente porque hay un periodo abierto.
    $rechazaConAbierto = false;
    try {
        $modeloDireccion->ejecutarConBloqueoAlta(
            fn () => $modeloDireccion->crear($productorId, test_direccion_payload())
        );
    } catch (RuntimeException $excepcion) {
        $rechazaConAbierto = str_contains($excepcion->getMessage(), 'ya tiene una dirección registrada');
    }
    test_assert($rechazaConAbierto, 'crear() con periodo abierto sigue orientando a actualizar()');

    // Cerrar el periodo: la dirección del pasado queda intocable.
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->cerrarPeriodo($productorId)
    );
    test_same(null, $modeloDireccion->consultarPeriodoAbierto($productorId),
        'Tras cerrar el periodo no hay enlace abierto');
    test_same(null, $modeloDireccion->buscar($productorId),
        'buscar() devuelve null cuando no existe periodo abierto');

    // Abrir un periodo nuevo: el histórico crece, nada se sobrescribe.
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->crear($productorId, test_direccion_payload(['provincia' => 'Limón']))
    );
    test_same('Limón', $modeloDireccion->buscar($productorId)['provincia'],
        'buscar() lee el periodo abierto nuevo');

    $direccionInicial = $db->prepare('SELECT tbdireccionprovincia FROM tbdireccion WHERE tbdireccionid = :id');
    $direccionInicial->execute(['id' => $direccionInicialId]);
    test_same('', $direccionInicial->fetchColumn(),
        'La primera dirección permanece almacenada sin modificaciones');

    // Segundo cambio: tres periodos consultables, dos cerrados y uno abierto.
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->cerrarPeriodo($productorId)
    );
    $modeloDireccion->ejecutarConBloqueoAlta(
        fn () => $modeloDireccion->crear($productorId, test_direccion_payload(['provincia' => 'Heredia']))
    );
    $conteoHistorico = $db->prepare('SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :id');
    $conteoHistorico->execute(['id' => $productorId]);
    test_same(3, (int) $conteoHistorico->fetchColumn(),
        'Dos cambios dejan tres periodos consultables');
    test_same('Heredia', $modeloDireccion->buscar($productorId)['provincia'],
        'El periodo abierto es el último cambio');

    // Vigencia por fecha sobre el histórico (fechas ajustadas con SQL directo
    // para límites deterministas).
    $enlaces = $db->prepare(
        'SELECT tbproductordireccionid, d.tbdireccionprovincia AS provincia
         FROM tbproductordireccion pd
         INNER JOIN tbdireccion d ON d.tbdireccionid = pd.tbdireccionid
         WHERE pd.tbproductorid = :id ORDER BY pd.tbproductordireccionid ASC'
    );
    $enlaces->execute(['id' => $productorId]);
    $historico = $enlaces->fetchAll();
    $db->prepare(
        'UPDATE tbproductordireccion
         SET tbproductordireccionfechainicio = :inicio, tbproductordireccionfechafin = :fin
         WHERE tbproductordireccionid = :id'
    )->execute(['inicio' => '2020-01-01 00:00:00', 'fin' => '2021-01-01 00:00:00',
        'id' => $historico[0]['tbproductordireccionid']]);
    $db->prepare(
        'UPDATE tbproductordireccion
         SET tbproductordireccionfechainicio = :inicio, tbproductordireccionfechafin = :fin
         WHERE tbproductordireccionid = :id'
    )->execute(['inicio' => '2021-01-01 00:00:00', 'fin' => '2022-01-01 00:00:00',
        'id' => $historico[1]['tbproductordireccionid']]);

    test_same('', $modeloDireccion->consultarVigenteEn($productorId, '2020-06-01 00:00:00')['provincia'],
        'En 2020 la residencia vigente era la dirección original');
    test_same('Limón', $modeloDireccion->consultarVigenteEn($productorId, '2021-06-01 00:00:00')['provincia'],
        'En 2021 la residencia vigente era la segunda dirección');
    test_same('Heredia', $modeloDireccion->consultarVigenteEn($productorId, '2999-01-01 00:00:00')['provincia'],
        'Una fecha futura resuelve al periodo abierto');

    // Invariante: nunca dos enlaces abiertos para el mismo productor.
    $segundoAbiertoId = (int) $db->query(
        'SELECT COALESCE(MAX(tbproductordireccionid), 0) + 1 FROM tbproductordireccion'
    )->fetchColumn();
    $db->prepare('INSERT INTO tbproductordireccion
            (tbproductordireccionid, tbproductorid, tbdireccionid)
        VALUES (:enlaceId, :productorId, :direccionId)')
        ->execute(['enlaceId' => $segundoAbiertoId, 'productorId' => $productorId,
            'direccionId' => $direccionInicialId]);
    $detectaDobleAbierto = false;
    try {
        $modeloDireccion->consultarPeriodoAbierto($productorId);
    } catch (RuntimeException $excepcion) {
        $detectaDobleAbierto = str_contains($excepcion->getMessage(), 'más de un periodo de dirección abierto');
    }
    test_assert($detectaDobleAbierto, 'Dos enlaces abiertos deben detectarse como integridad rota');
    $db->prepare('DELETE FROM tbproductordireccion WHERE tbproductordireccionid = :id')
        ->execute(['id' => $segundoAbiertoId]);
} finally {
    test_cleanup_productores([$id]);
}

echo "OK direccion_test: GET/PUT/DELETE de dirección — casos válidos, productor inexistente, "
    . "productor inactivo, sin dirección, datos inválidos y detección de duplicados.\n";