<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\ProductorUbicacion;

$id = test_document();

try {
    // ============================================================
    // Fixture: productor activo de prueba.
    // ============================================================
    $productor = test_create([], $id);
    $productorId = (int) $productor['productorId'];
    $db = test_db();
    $modelo = new ProductorUbicacion($db);

    // ============================================================
    // registrar() exige que la conexión posea el lock de alta.
    // ============================================================
    $lanzoLogicException = false;
    try {
        $modelo->registrar($productorId, '9.93', '-84.08', null, 'MANUAL');
    } catch (LogicException) {
        $lanzoLogicException = true;
    }
    test_assert($lanzoLogicException, 'registrar() sin el lock de alta debe lanzar LogicException');

    // ============================================================
    // Tres registros consecutivos bajo el lock.
    // ============================================================
    $base = (int) $db->query('SELECT COALESCE(MAX(tbproductorubicacionid), 0) FROM tbproductorubicacion')->fetchColumn();
    $instanteRegistro = time();
    $ids = [
        $modelo->ejecutarConBloqueoAlta(
            fn (): int => $modelo->registrar($productorId, '9.9345678', '-84.0876543', '12.5', 'NAVEGADOR'),
        ),
        $modelo->ejecutarConBloqueoAlta(
            fn (): int => $modelo->registrar($productorId, '9.94', '-84.09', null, 'MANUAL'),
        ),
        $modelo->ejecutarConBloqueoAlta(
            fn (): int => $modelo->registrar($productorId, '9.95', '-84.10', '0.00', 'NAVEGADOR'),
        ),
    ];

    test_same(range($base + 1, $base + 3), $ids,
        'Los IDs deben ser MAX+1 consecutivos sin huecos entre registros');

    $conteo = $db->prepare('SELECT COUNT(*) FROM tbproductorubicacion WHERE tbproductorid = :id');
    $conteo->execute(['id' => $productorId]);
    test_same(3, (int) $conteo->fetchColumn(), 'Deben existir exactamente tres filas para el productor');

    // ============================================================
    // La fila conserva el DECIMAL exacto y la fecha la asignó el servidor.
    // ============================================================
    $lectura = $db->prepare('SELECT * FROM tbproductorubicacion WHERE tbproductorubicacionid = :id');
    $lectura->execute(['id' => $ids[0]]);
    $fila = $lectura->fetch();
    test_same('9.9345678', $fila['tbproductorubicacionlatitud'], 'La latitud debe conservar los 7 decimales exactos');
    test_same('-84.0876543', $fila['tbproductorubicacionlongitud'], 'La longitud debe conservar los 7 decimales exactos');
    test_same('12.50', $fila['tbproductorubicacionprecision'], 'La precisión se normaliza a DECIMAL(10,2)');
    test_same('NAVEGADOR', $fila['tbproductorubicacionorigen'], 'El origen debe persistirse tal cual el catálogo');
    test_assert(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fila['tbproductorubicacionfecha']) === 1,
        'La fecha debe ser un DATETIME asignado por PHP');
    test_assert(abs(strtotime($fila['tbproductorubicacionfecha']) - $instanteRegistro) <= 5,
        'La fecha guardada debe coincidir con el reloj del servidor PHP, no con uno enviado por el cliente');

    $lectura->execute(['id' => $ids[1]]);
    test_same(null, $lectura->fetch()['tbproductorubicacionprecision'],
        'Sin precisión declarada la columna debe quedar NULL');

    // ============================================================
    // Append-only: releer las tres filas y verificar que nada cambió.
    // ============================================================
    $captura = [];
    foreach ($ids as $idUbicacion) {
        $lectura->execute(['id' => $idUbicacion]);
        $captura[$idUbicacion] = $lectura->fetch();
    }
    $modelo->listarPorProductor($productorId);
    $modelo->listarPorPeriodo($productorId, '2000-01-01 00:00:00', '2100-01-01 00:00:00');
    foreach ($captura as $idUbicacion => $original) {
        $lectura->execute(['id' => $idUbicacion]);
        test_same($original, $lectura->fetch(),
            "La fila {$idUbicacion} no debe cambiar por operaciones de lectura (append-only)");
    }

    // ============================================================
    // listarPorProductor(): total y paginado del más reciente al más antiguo.
    // ============================================================
    $paginaUno = $modelo->listarPorProductor($productorId, 1, 2);
    test_same(3, $paginaUno['total'], 'El total debe cubrir todo el histórico');
    test_same([$ids[2], $ids[1]], array_column($paginaUno['ubicaciones'], 'tbproductorubicacionid'),
        'La primera página devuelve las ubicaciones más recientes primero');
    $paginaDos = $modelo->listarPorProductor($productorId, 2, 2);
    test_same([$ids[0]], array_column($paginaDos['ubicaciones'], 'tbproductorubicacionid'),
        'La segunda página devuelve el resto del histórico');

    // ============================================================
    // listarPorPeriodo(): rango completo en orden cronológico y rango
    // vacío cuando no hay filas dentro del periodo.
    // ============================================================
    $rangoCompleto = $modelo->listarPorPeriodo($productorId, '2000-01-01 00:00:00', '2100-01-01 00:00:00');
    test_same(3, $rangoCompleto['total'], 'El histórico del productor debe contener exactamente tres filas');
    test_same($ids, array_column($rangoCompleto['ubicaciones'], 'tbproductorubicacionid'),
        'El rango completo debe devolver las tres filas en orden ascendente');
    $lectura->execute(['id' => $ids[1]]);
    $fechaCentral = $lectura->fetch()['tbproductorubicacionfecha'];
    $rangoCorto = $modelo->listarPorPeriodo($productorId, $fechaCentral, $fechaCentral);
    test_assert(in_array($ids[1], array_column($rangoCorto['ubicaciones'], 'tbproductorubicacionid'), true),
        'Un rango puntual sobre la fecha de una fila debe incluirla');
    $rangoVacio = $modelo->listarPorPeriodo($productorId, '2100-01-01 00:00:00', '2100-12-31 23:59:59');
    test_same([], $rangoVacio['ubicaciones'], 'Un rango futuro sin filas debe devolver una lista vacía');
} finally {
    test_cleanup_ubicaciones([$productorId ?? 0]);
    test_cleanup_productores([$id]);
}

echo "OK productor_ubicacion_test: append-only con IDs MAX+1 bajo lock, fecha del servidor, "
    . "DECIMAL exacto, paginación y rango de fechas.\n";
