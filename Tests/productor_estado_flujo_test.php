<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\ProductorEstadoPeriodo;

$id = test_document();

try {
    // ============================================================
    // Fixture: productor activo de prueba. El POST de alta ahora abre
    // un periodo ACTIVO inicial automáticamente.
    // ============================================================
    $productor = test_create([], $id);
    $productorId = (int) $productor['productorId'];
    $identificacion = $productor['identificacionNumero'];
    $db = test_db();
    $estadoModelo = new ProductorEstadoPeriodo($db);

    test_same('ACTIVO', $productor['estado'],
        'POST de alta abre periodo ACTIVO y el get lo devuelve como ACTIVO');

    // ============================================================
    // Desactivar → reactivar → desactivar → reactivar → desactivar.
    // Quedan: 3 inactivos, 2 activos cerrados, 1 inactivo abierto.
    // ============================================================
    test_same(200, test_controller()->procesar('DELETE', [], [
        'identificacionNumero' => $identificacion,
    ])['status'], 'Primera desactivación 200');

    $inactivo = test_controller()->procesar('GET', [
        'identificacionNumero' => $identificacion,
    ], []);
    test_same('INACTIVO', $inactivo['body']['data']['estado'],
        'Tras desactivar el productor tiene estado INACTIVO');

    test_same(200, test_controller()->procesar('PATCH', [], [
        'identificacionNumero' => $identificacion,
    ])['status'], 'Reactivación 200');

    $activo = test_controller()->procesar('GET', ['identificacionNumero' => $identificacion], []);
    test_same('ACTIVO', $activo['body']['data']['estado'], 'Tras reactivar es ACTIVO');

    test_same(200, test_controller()->procesar('DELETE', [], [
        'identificacionNumero' => $identificacion,
    ])['status'], 'Segunda desactivación 200');

    test_same(200, test_controller()->procesar('PATCH', [], [
        'identificacionNumero' => $identificacion,
    ])['status'], 'Segunda reactivación 200');

    test_same(200, test_controller()->procesar('DELETE', [], [
        'identificacionNumero' => $identificacion,
    ])['status'], 'Tercera desactivación 200');

    $conteoPeriodos = $db->prepare('SELECT COUNT(*) FROM tbproductorestadoperiodo WHERE tbproductorid = :id');
    $conteoPeriodos->execute(['id' => $productorId]);
    test_same(6, (int) $conteoPeriodos->fetchColumn(),
        'Los tres ciclos dejan seis periodos (3 cerrados ACTIVO, 2 cerrados INACTIVO, 1 abierto INACTIVO)');

    $abierto = $estadoModelo->consultarAbierto($productorId);
    test_assert($abierto !== null && (int) $abierto['tbproductorestadoperiodoestado'] === 0,
        'El periodo abierto final es INACTIVO');

    // ============================================================
    // Idempotencia: desactivar dos veces seguidas no duplica periodos.
    // ============================================================
    test_same(200, test_controller()->procesar('DELETE', [], [
        'identificacionNumero' => $identificacion,
    ])['status'], 'Idempotente: desactivar de nuevo 200');

    $conteoIdempotencia = $db->prepare('SELECT COUNT(*) FROM tbproductorestadoperiodo WHERE tbproductorid = :id');
    $conteoIdempotencia->execute(['id' => $productorId]);
    test_same(6, (int) $conteoIdempotencia->fetchColumn(),
        'La desactivación idempotente no genera periodos adicionales');

    $inactivoFinal = test_controller()->procesar('GET', ['identificacionNumero' => $identificacion], []);
    test_same('INACTIVO', $inactivoFinal['body']['data']['estado'],
        'El productor sigue INACTIVO tras la segunda desactivación');

    // Reactivamos para la prueba de concurrencia.
    test_controller()->procesar('PATCH', [], ['identificacionNumero' => $identificacion]);
} finally {
    test_cleanup_estado_periodos([(int) ($productor['productorId'] ?? 0)]);
    test_cleanup_productores([$id]);
}

// ============================================================
// Concurrencia: dos DELETE simultáneos del mismo productor
// serializados por el lock por productor. Fuera del try para no
// afectar el cleanup.
// ============================================================
$identificacionConcurrente = test_document();
$productorConcurrente = test_create([], $identificacionConcurrente);
$productorIdConc = (int) $productorConcurrente['productorId'];

try {
    $rafaga = 2;
    $codigoWorker = static fn (string $ident, string $testRoot): string => sprintf(
        "require %s;\n"
        . "\$ctrl = test_controller();\n"
        . "\$ctrl->procesar('DELETE', [], ['identificacionNumero' => %s]);\n"
        . "echo 'OK';",
        var_export($testRoot . '/bootstrap.php', true),
        var_export($ident, true),
    );

    $procesos = [];
    for ($i = 0; $i < $rafaga; $i++) {
        $tuberias = [];
        $proceso = proc_open(
            [PHP_BINARY, '-r', $codigoWorker($identificacionConcurrente, __DIR__)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
        );
        test_assert(is_resource($proceso), 'Cada worker debe iniciarse');
        $procesos[] = [$proceso, $tuberias];
    }

    foreach ($procesos as [$proceso, $tuberias]) {
        $error = stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        test_same(0, proc_close($proceso), 'El worker no debe fallar' . ($error !== '' ? ": {$error}" : ''));
    }

    $dbConc = test_db();
    $conteoConc = $dbConc->prepare('SELECT COUNT(*) FROM tbproductorestadoperiodo WHERE tbproductorid = :id');
    $conteoConc->execute(['id' => $productorIdConc]);
    test_same(2, (int) $conteoConc->fetchColumn(),
        'Dos DELETE simultáneos serializados dejan exactamente 2 periodos: 1 cerrado INACTIVO + 1 abierto INACTIVO');
} finally {
    test_cleanup_estado_periodos([$productorIdConc]);
    test_cleanup_productores([$identificacionConcurrente]);
}

echo "OK productor_estado_flujo_test: desactivar/reactivar por periodos, idempotencia "
    . "y concurrencia bajo lock serializados.\n";
