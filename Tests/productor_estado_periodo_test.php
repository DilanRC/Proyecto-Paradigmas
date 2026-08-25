<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\ProductorEstadoPeriodo;

$id = test_document();

try {
    // ============================================================
    // Fixture: productor activo de prueba.
    // ============================================================
    $productor = test_create([], $id);
    $productorId = (int) $productor['productorId'];
    $db = test_db();
    $modelo = new ProductorEstadoPeriodo($db);

    // ============================================================
    // abrir() exige que la conexión posea el lock del productor.
    // ============================================================
    $lanzoLogicException = false;
    try {
        $modelo->abrir($productorId, 1, 'Alta inicial');
    } catch (LogicException) {
        $lanzoLogicException = true;
    }
    test_assert($lanzoLogicException, 'abrir() sin el lock del productor debe lanzar LogicException');

    $lanzoLogicException = false;
    try {
        $modelo->cerrar($productorId);
    } catch (LogicException) {
        $lanzoLogicException = true;
    }
    test_assert($lanzoLogicException, 'cerrar() sin el lock del productor debe lanzar LogicException');

    // ============================================================
    // Alta inicial: abrir ACTIVO bajo lock; consultarAbierto lo ve abierto.
    // ============================================================
    $primerPeriodoId = $modelo->ejecutarConBloqueo(
        $productorId,
        fn (): int => $modelo->abrir($productorId, 1, 'Alta inicial'),
    );
    test_assert($primerPeriodoId > 0, 'abrir() devuelve un identificador positivo');

    $abierto = $modelo->consultarAbierto($productorId);
    test_assert($abierto !== null, 'Tras abrir hay exactamente un periodo abierto');
    test_same(1, $abierto['tbproductorestadoperiodoestado'], 'El periodo abierto registra ACTIVO (1)');
    test_same(null, $abierto['tbproductorestadoperiodofechafin'], 'El periodo abierto tiene fechafin NULL');
    test_same('Alta inicial', $abierto['tbproductorestadoperiodomotivo'], 'El periodo conserva el motivo');
    test_assert(abs(strtotime((string) $abierto['tbproductorestadoperiodofechainicio']) - time()) <= 120,
        'La fecha de inicio la asigna PHP con su reloj');

    // ============================================================
    // Invariante: máximo un periodo abierto por productor.
    // ============================================================
    $rechazaSegundoAbierto = false;
    try {
        $modelo->ejecutarConBloqueo(
            $productorId,
            fn (): int => $modelo->abrir($productorId, 0, 'Intento duplicado'),
        );
    } catch (RuntimeException $excepcion) {
        $rechazaSegundoAbierto = str_contains($excepcion->getMessage(), 'periodo de estado abierto');
    }
    test_assert($rechazaSegundoAbierto, 'abrir() con otro periodo aún abierto debe rechazarse');

    // ============================================================
    // Cierre: fechafin asignada por PHP; la fila cerrada nunca se borra ni edita.
    // ============================================================
    $modelo->ejecutarConBloqueo($productorId, fn () => $modelo->cerrar($productorId));
    test_same(null, $modelo->consultarAbierto($productorId), 'Tras cerrar no queda periodo abierto');

    $filaCerrada = $db->prepare('SELECT * FROM tbproductorestadoperiodo WHERE tbproductorestadoperiodoid = :id');
    $filaCerrada->execute(['id' => $primerPeriodoId]);
    $datos = $filaCerrada->fetch();
    test_assert($datos !== false, 'Cerrar no elimina la fila: el histórico se conserva');
    test_assert($datos['tbproductorestadoperiodofechafin'] !== null, 'La fila cerrada quedó con fechafin');

    // ============================================================
    // Secuencia completa desactivar/reactivar: los periodos quedan como hechos.
    // ACTIVO → INACTIVO → ACTIVO (abierto): 2 cerrados + 1 abierto.
    // ============================================================
    $modelo->ejecutarConBloqueo($productorId, function () use ($modelo, $productorId): void {
        $modelo->abrir($productorId, 0, 'Desactivación');
        $modelo->cerrar($productorId);
        $modelo->abrir($productorId, 1, 'Reactivación');
        $modelo->cerrar($productorId);
        $modelo->abrir($productorId, 1, null);
    });

    $conteo = $db->prepare('SELECT COUNT(*) FROM tbproductorestadoperiodo WHERE tbproductorid = :id');
    $conteo->execute(['id' => $productorId]);
    test_same(4, (int) $conteo->fetchColumn(), 'La secuencia deja cuatro periodos en total');

    $estados = $db->prepare(
        'SELECT tbproductorestadoperiodoestado FROM tbproductorestadoperiodo
         WHERE tbproductorid = :id ORDER BY tbproductorestadoperiodoid ASC'
    );
    $estados->execute(['id' => $productorId]);
    test_same([1, 0, 1, 1], array_map('intval', array_column($estados->fetchAll(), 'tbproductorestadoperiodoestado')),
        'Los periodos registran la secuencia ACTIVO, INACTIVO, ACTIVO, ACTIVO');

    $abiertoFinal = $modelo->consultarAbierto($productorId);
    test_assert($abiertoFinal !== null && $abiertoFinal['tbproductorestadoperiodomotivo'] === null,
        'El último motivo opcional admite NULL');

    // ============================================================
    // consultarVigenteEn: vigencia por fecha sobre el histórico real.
    // Se ajustan las fechas del histórico con SQL directo para obtener
    // límites deterministas (la manipulación de datos es válida en pruebas).
    // ============================================================
    $db->prepare(
        'UPDATE tbproductorestadoperiodo
         SET tbproductorestadoperiodofechainicio = :inicio, tbproductorestadoperiodofechafin = :fin
         WHERE tbproductorestadoperiodoid = :id'
    )->execute([
        'inicio' => '2024-01-01 00:00:00',
        'fin' => '2024-06-01 00:00:00',
        'id' => $primerPeriodoId,
    ]);

    test_same(null, $modelo->consultarVigenteEn($productorId, '2023-12-31 23:59:59'),
        'Una fecha anterior al primer periodo no tiene vigencia');
    test_same(null, $modelo->consultarVigenteEn($productorId, '2024-06-01 00:00:00'),
        'El instante exacto de fechafin ya pertenece al periodo siguiente, no al cerrado');

    $vigentePasado = $modelo->consultarVigenteEn($productorId, '2024-03-01 12:00:00');
    test_assert($vigentePasado !== null
        && $vigentePasado['tbproductorestadoperiodoid'] === $primerPeriodoId,
        'Una fecha dentro del primer periodo devuelve ese periodo cerrado');
    test_same(1, $vigentePasado['tbproductorestadoperiodoestado'],
        'El periodo vigente en el pasado era ACTIVO');

    $vigenteActual = $modelo->consultarVigenteEn($productorId, gmdate('Y-m-d H:i:s', time() + 3600));
    test_assert($vigenteActual !== null && $vigenteActual === $abiertoFinal,
        'Una fecha futura inmediata resuelve al periodo abierto actual');
} finally {
    test_cleanup_estado_periodos([(int) ($productor['productorId'] ?? 0)]);
    test_cleanup_productores([$id]);
}

echo "OK productor_estado_periodo_test: apertura y cierre con lock por productor, "
    . "máximo un abierto, cierre inmutable y vigencia por fecha.\n";
