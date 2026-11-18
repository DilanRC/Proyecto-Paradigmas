<?php

declare(strict_types=1);

/**
 * Calidad de datos del panel admin (vista DIAGNOSTICO): cada uno de los seis diagnósticos distingue dato obligatorio,
 * opcional, desconocido, recuperable y de demostración. Los casos se escriben dentro de una transacción que se deshace
 * al final, así la base local queda igual. Los conteos se comparan contra el estado previo (la base puede traer datos).
 */

require __DIR__ . '/bootstrap.php';
foreach (['Notificacion', 'Subasta', 'AdminDiagnostico', 'AdminDashboard'] as $modelo) require_once dirname(__DIR__) . "/Application/Model/{$modelo}.php";
require_once dirname(__DIR__) . '/Application/Controller/AdminDashboardController.php';

use Application\Controller\AdminDashboardController;
use Application\Model\AdminDiagnostico;
use Application\Service\TiempoReal;

$db = test_db();
$diag = new AdminDiagnostico($db);
$controlador = new AdminDashboardController($db, new TiempoReal(static function (): void {}));
$clases = static fn (): array => array_map(static fn (array $d): array => $d['porClasificacion'], $diag->resumen());

// ───── Contrato: lista cerrada y paginada ─────
$r = $controlador->procesar('GET', ['vista' => 'DIAGNOSTICO', 'tipo' => 'tbventa; DROP TABLE tbventa']);
test_assert($r['status'] === 422 && isset($r['body']['errors']['tipo']), 'un tipo fuera de la lista es 422');
$r = $controlador->procesar('GET', ['vista' => 'DIAGNOSTICO', 'tipo' => ['adjudicadasSinVenta']]);
test_assert($r['status'] === 422, 'un tipo que no es texto es 422');
$r = $controlador->procesar('GET', ['vista' => 'DIAGNOSTICO', 'tipo' => 'ventasSinComprador', 'tamanoPagina' => '500']);
test_assert($r['status'] === 422 && isset($r['body']['errors']['tamanoPagina']), 'máximo 50 filas por página');
$r = $controlador->procesar('GET', ['vista' => 'DIAGNOSTICO']);
test_assert($r['status'] === 200 && count($r['body']['data']['diagnosticos']) === 6, 'el resumen trae los seis diagnósticos');
foreach ($r['body']['data']['diagnosticos'] as $d) {
    test_assert(!preg_match('/\btb[a-z]+/', $d['regla'] . $d['solucion'] . $d['titulo']), "{$d['tipo']}: la política no nombra tablas");
}
$antes = $clases();
$modelo = new Application\Model\AdminDashboard($db);
$resumenAntes = $modelo->control([])['incompletos'];

$max = static fn (string $tabla, string $id): int => (int) $db->query("SELECT COALESCE(MAX({$id}), 0) FROM {$tabla}")->fetchColumn() + 1000;
// Completa las columnas NOT NULL que el caso no usa con un valor neutro de su tipo (el caso solo fija lo que importa).
$ins = static function (string $tabla, array $fila) use ($db): void {
    $req = $db->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL");
    $req->execute(['t' => $tabla]);
    foreach ($req->fetchAll(PDO::FETCH_NUM) as [$col, $tipo]) {
        if (array_key_exists($col, $fila)) continue;
        $fila[$col] = match (true) {
            in_array($tipo, ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'double', 'float'], true) => 0,
            $tipo === 'date' => '2026-01-01', in_array($tipo, ['datetime', 'timestamp'], true) => '2026-01-01 00:00:00',
            $tipo === 'time' => '00:00:00', default => 'X',
        };
    }
    $columnas = implode(', ', array_keys($fila));
    $marcas = implode(', ', array_map(static fn (string $c): string => ":{$c}", array_keys($fila)));
    $db->prepare("INSERT INTO {$tabla} ({$columnas}) VALUES ({$marcas})")->execute($fila);
};

$db->beginTransaction();
try {
    $persona = $max('tbpersona', 'tbpersonaid');
    $fantasma = $persona + 1; // Persona que no existe (eliminada)
    $ins('tbpersona', ['tbpersonaid' => $persona, 'tbpersonanombre' => 'Diagnóstico Real', 'tbpersonaidentificacionnumero' => 'DIAG' . $persona, 'tbpersonaestado' => 1]);
    $demo = $persona + 2;
    $ins('tbpersona', ['tbpersonaid' => $demo, 'tbpersonanombre' => 'Diagnóstico Demo', 'tbpersonaidentificacionnumero' => 'DMDASH' . $demo, 'tbpersonaestado' => 1]);
    $productor = $max('tbproductor', 'tbproductorid');
    $ins('tbproductor', ['tbproductorid' => $productor, 'tbpersonaid' => $persona]);
    $ins('tbproductor', ['tbproductorid' => $productor + 1, 'tbpersonaid' => $fantasma]);
    $ins('tbproductor', ['tbproductorid' => $productor + 2, 'tbpersonaid' => $demo]);

    // Fincas: activa con dueño → REVISION; dueño inexistente → INCONSISTENCIA; inactiva → OPCIONAL.
    $finca = $max('tbfinca', 'tbfincaid');
    $ins('tbfinca', ['tbfincaid' => $finca, 'tbproductorid' => $productor, 'tbfincanombre' => 'Finca diag', 'tbfincaestado' => 1]);
    $ins('tbfinca', ['tbfincaid' => $finca + 1, 'tbproductorid' => $productor + 1, 'tbfincanombre' => 'Finca huérfana', 'tbfincaestado' => 1]);
    $ins('tbfinca', ['tbfincaid' => $finca + 2, 'tbproductorid' => $productor, 'tbfincanombre' => 'Finca cerrada', 'tbfincaestado' => 0]);

    // Animales sin especie: con dueño → REVISION; sin dueño → SIN_EVIDENCIA (no se deduce por la raza escrita).
    $animal = $max('tbanimal', 'tbanimalid');
    $ins('tbanimal', ['tbanimalid' => $animal, 'tbanimalraza' => 'Brahman', 'tbproductorid' => $productor]);
    $ins('tbanimal', ['tbanimalid' => $animal + 1, 'tbanimalraza' => 'Brahman', 'tbproductorid' => null]);
    foreach ([2, 3, 4] as $n) $ins('tbanimal', ['tbanimalid' => $animal + $n, 'tbanimalraza' => 'Gyr', 'tbespecieid' => 1, 'tbproductorid' => $productor]);

    // Subastas adjudicadas: una normal con su venta (no cuenta), una demo sin venta y una real sin venta.
    $subasta = $max('tbsubasta', 'tbsubastaid');
    $puja = $max('tbsubastapuja', 'tbsubastapujaid');
    $hace = gmdate('Y-m-d H:i:s', time() - 86400 * 3);
    foreach ([[2, $productor, 'Normal', $persona], [3, $productor + 2, 'Subasta de prueba [demo-dashboard]', $persona], [4, $productor, 'Real', $fantasma]] as $i => [$n, $prod, $desc, $ganador]) {
        $ins('tbsubastapuja', ['tbsubastapujaid' => $puja + $i, 'tbsubastaid' => $subasta + $i, 'tbpersonaid' => $ganador, 'tbsubastapujamonto' => 500000, 'tbsubastapujafecha' => $hace]);
        $ins('tbsubasta', ['tbsubastaid' => $subasta + $i, 'tbanimalid' => $animal + $n, 'tbproductorid' => $prod, 'tbfincaid' => $finca,
            'tbsubastatitulo' => "Diag {$i}", 'tbsubastadescripcion' => $desc, 'tbsubastaestado' => 'FINALIZADA',
            'tbsubastafechainicio' => $hace, 'tbsubastafechafin' => $hace, 'tbsubastapujaganadoraid' => $puja + $i]);
    }
    $venta = $max('tbventa', 'tbventaid');
    $compra = $max('tbcompra', 'tbcompraid');
    $comprador = $max('tbcomprador', 'tbcompradorid');
    $ins('tbcomprador', ['tbcompradorid' => $comprador, 'tbpersonaid' => $persona, 'tbcompradorestado' => 1]);
    $dia = substr($hace, 0, 10);
    // Venta normal de la subasta 0: con comprador; no aparece.
    $ins('tbcompra', ['tbcompraid' => $compra, 'tbanimalid' => $animal + 2, 'tbcompradorid' => $comprador, 'tbcomprafecha' => $dia, 'tbcompraprecio' => 500000, 'tbcompraorigen' => 'API_SUBASTAS']);
    $ins('tbventa', ['tbventaid' => $venta, 'tbanimalid' => $animal + 2, 'tbproductorvendedorid' => $productor, 'tbcompradorid' => $comprador,
        'tbcompraid' => $compra, 'tbventafecha' => $dia, 'tbventaprecio' => 500000, 'tbventaorigen' => 'API_SUBASTAS']);
    // Venta sin comprador cuya compra sí lo identifica → INCONSISTENCIA (recuperable).
    $ins('tbcompra', ['tbcompraid' => $compra + 1, 'tbanimalid' => $animal, 'tbcompradorid' => $comprador, 'tbcomprafecha' => $dia, 'tbcompraprecio' => 1, 'tbcompraorigen' => 'API_SOLICITUDES']);
    $ins('tbventa', ['tbventaid' => $venta + 1, 'tbanimalid' => $animal, 'tbproductorvendedorid' => $productor, 'tbcompraid' => $compra + 1,
        'tbventafecha' => $dia, 'tbventaprecio' => 1, 'tbventaorigen' => 'API_SOLICITUDES']);
    // Venta de una subasta cuyo ganador ya no existe → SIN_EVIDENCIA (comprador desconocido, no se inventa).
    $ins('tbventa', ['tbventaid' => $venta + 2, 'tbanimalid' => $animal + 4, 'tbproductorvendedorid' => $productor,
        'tbventafecha' => $dia, 'tbventaprecio' => 500000, 'tbventaorigen' => 'API_SUBASTAS']);
    // Venta histórica sin comprador ni compra → OPCIONAL.
    $ins('tbventa', ['tbventaid' => $venta + 3, 'tbanimalid' => $animal + 3, 'tbproductorvendedorid' => $productor,
        'tbventafecha' => $dia, 'tbventaprecio' => 2, 'tbventaorigen' => 'MANUAL']);

    $despues = $clases();
    $delta = static fn (string $tipo, string $clase): int => $despues[$tipo][$clase] - $antes[$tipo][$clase];
    test_assert($delta('fincasSinDireccion', 'REVISION') === 1, 'finca activa sin dirección: requiere revisión');
    test_assert($delta('fincasSinDireccion', 'INCONSISTENCIA') === 1, 'finca de un dueño inexistente: inconsistencia');
    test_assert($delta('fincasSinDireccion', 'OPCIONAL') === 1, 'finca inactiva sin dirección: opcional');
    test_assert($delta('animalesSinEspecie', 'REVISION') === 1 && $delta('animalesSinEspecie', 'SIN_EVIDENCIA') === 1,
        'animal sin especie: con dueño lo completa, sin dueño no hay evidencia');
    test_assert($delta('ventasSinComprador', 'INCONSISTENCIA') === 1, 'la compra identifica al comprador: vínculo perdido');
    test_assert($delta('ventasSinComprador', 'SIN_EVIDENCIA') === 1, 'ganador eliminado: comprador desconocido');
    test_assert($delta('ventasSinComprador', 'OPCIONAL') === 1, 'venta sin comprador ni compra: comprador no identificado');
    // La subasta 2 (animal + 4) tiene venta de subasta → no cuenta; la 0 tampoco. Solo la demo (animal + 3, venta MANUAL).
    test_assert($delta('adjudicadasSinVenta', 'INCONSISTENCIA') === 1, 'una venta de otro canal no cuenta como la venta de la subasta');

    $filas = $diag->filas('adjudicadasSinVenta', 1, 50)['items'];
    $demoFila = array_values(array_filter($filas, static fn (array $f): bool => $f['id'] === $subasta + 1))[0] ?? null;
    test_assert($demoFila !== null && $demoFila['origen'] === 'DEMO', 'la subasta con marca de demo se identifica como demo');
    test_assert(str_contains($demoFila['causa'], 'bitácora'), 'sin cierre en la bitácora se dice que se marcó fuera de la aplicación');
    $json = json_encode($diag->filas('ventasSinComprador', 1, 50));
    test_assert(!str_contains($json, 'DIAG' . $persona) && !str_contains($json, 'DMDASH'), 'las filas no traen números de identificación');
    $ventaRecuperable = array_values(array_filter($diag->filas('ventasSinComprador', 1, 50)['items'], static fn (array $f): bool => $f['id'] === $venta + 1))[0];
    test_assert($ventaRecuperable['clasificacion'] === 'INCONSISTENCIA', 'la fila trae su clasificación');

    $incompletos = $modelo->control([])['incompletos'];
    test_assert($incompletos['fincasSinDireccion'] === $resumenAntes['fincasSinDireccion'] + 3, 'el resumen usa el mismo conteo que el diagnóstico');
    test_assert($incompletos['ventasSinComprador'] === $resumenAntes['ventasSinComprador'] + 3, 'ventas sin comprador en el resumen');
} finally {
    $db->rollBack();
}
test_assert($clases() === $antes, 'la prueba no deja datos');
echo "OK admin_diagnostico_test\n";
