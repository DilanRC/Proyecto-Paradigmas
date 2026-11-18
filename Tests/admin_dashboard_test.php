<?php

declare(strict_types=1);

/**
 * Dashboard administrativo (DEC-DASHBOARD-002): cada número del resumen se concilia contra una consulta SQL
 * independiente sobre los hechos guardados, sin importar qué datos tenga la base local (más útil con
 * Tools/seed-dashboard-demo.php). Además: validación de la consulta, paginación, privacidad del expediente y que los
 * viajes sin flujo de registro se declaren "sin instrumentar".
 */

require __DIR__ . '/bootstrap.php';
foreach (['Notificacion', 'Subasta', 'AdminDashboard'] as $modelo) require_once dirname(__DIR__) . "/Application/Model/{$modelo}.php";
require_once dirname(__DIR__) . '/Application/Controller/AdminDashboardController.php';

use Application\Controller\AdminDashboardController;
use Application\Service\TiempoReal;

$db = test_db();
$controlador = new AdminDashboardController($db, new TiempoReal(static function (): void {}));
$uno = static function (string $sql) use ($db): float {
    return (float) $db->query($sql)->fetchColumn();
};
$ok = static function (array $r): array {
    test_assert($r['status'] === 200, 'respuesta 200: ' . json_encode($r['body']['errors'] ?? $r['body']['message']));
    return $r['body']['data'];
};

// ───── Resumen sin periodo: conciliación ─────
$d = $ok($controlador->procesar('GET', []));
// El dashboard cierra antes las vencidas: después de consultarlo no queda ninguna ACTIVA con el fin en el pasado.
test_assert((int) $uno("SELECT COUNT(*) FROM tbsubasta WHERE tbsubastaestado = 'ACTIVA' AND tbsubastafechafin <= '" . gmdate('Y-m-d H:i:s') . "'") === 0,
    'no se cuentan como activas subastas ya vencidas');

test_assert($d['personas']['personas'] === (int) $uno('SELECT COUNT(*) FROM tbpersona'), 'personas únicas = filas de tbpersona');
test_assert(array_sum($d['personas']['porNumeroDeActividades']) === $d['personas']['personas'],
    'cada persona cae en un solo grupo de actividades (no se suman actividades como personas)');

$estados = [];
foreach ($db->query('SELECT tbsubastaestado AS e, COUNT(*) AS n FROM tbsubasta GROUP BY tbsubastaestado') as $f) $estados[$f['e']] = (int) $f['n'];
$s = $d['subastas'];
test_assert($s['estados']['activas']['total'] === ($estados['ACTIVA'] ?? 0), 'activas');
test_assert($s['estados']['adjudicadas']['total'] === ($estados['FINALIZADA'] ?? 0), 'adjudicadas = FINALIZADA');
test_assert($s['estados']['desiertas']['total'] === ($estados['DESIERTA'] ?? 0), 'desiertas');
test_assert($s['estados']['canceladas']['total'] === ($estados['CANCELADA'] ?? 0), 'canceladas aparte');
test_assert(!isset($s['estados']['activas']['ids']), 'el resumen no manda ids: la evidencia va paginada');

$montoSql = round($uno("SELECT COALESCE(SUM(pg.tbsubastapujamonto), 0) FROM tbsubasta s
    INNER JOIN tbsubastapuja pg ON pg.tbsubastapujaid = s.tbsubastapujaganadoraid WHERE s.tbsubastaestado = 'FINALIZADA'"), 2);
test_assert(abs($s['precioPromedio']['montoTotal'] - $montoSql) < 0.01, "monto adjudicado {$s['precioPromedio']['montoTotal']} = {$montoSql}");
test_assert($s['pujas'] === (int) $uno('SELECT COUNT(*) FROM tbsubastapuja'), 'pujas válidas guardadas');
test_assert($s['compradores']['total'] === (int) $uno('SELECT COUNT(DISTINCT tbpersonaid) FROM tbsubastapuja'), 'compradores distintos');

// Sin doble conteo: una adjudicación produce una compra y una venta, pero el canal SUBASTA cuenta solo tbventa.
$canal = $d['comercializacion']['ventasPorCanal']['SUBASTA'] ?? ['animales' => 0, 'monto' => 0.0];
$ventasSubasta = (int) $uno("SELECT COUNT(*) FROM tbventa WHERE tbventaorigen = 'API_SUBASTAS' AND tbcomprasolicitudid IS NULL");
test_assert($canal['animales'] === $ventasSubasta, 'ventas por subasta = filas de tbventa (sin la compra espejo)');
test_assert($canal['animales'] === $s['estados']['adjudicadas']['total'], 'cada adjudicación es exactamente una venta');
test_assert($d['control']['incompletos']['adjudicadasSinVenta'] === 0, 'el cierre siempre deja la venta: ninguna adjudicada sin tbventa');
test_assert(abs($canal['monto'] - $montoSql) < 0.01, 'el monto de ventas por subasta coincide con el adjudicado');

$solicitud = $d['comercializacion']['ventasPorCanal']['SOLICITUD'] ?? null;
if ($solicitud !== null) {
    test_assert($solicitud['operaciones'] === (int) $uno('SELECT COUNT(DISTINCT tbcomprasolicitudid) FROM tbventa WHERE tbcomprasolicitudid IS NOT NULL'),
        'operaciones por solicitud = solicitudes distintas (un lote no cuenta N veces)');
}

// Transporte: un flete aceptado nunca aparece como viaje.
$t = $d['transporte'];
test_assert($t['viajesInstrumentados'] === false, 'viajes realizados declarados sin instrumentar');
test_assert($t['viajesRegistrados']['total'] === (int) $uno('SELECT COUNT(*) FROM tbtransportistaflete'), 'viajes = solo tbtransportistaflete');
test_assert(($t['solicitudesFletePorEstado']['ACEPTADA'] ?? 0) === (int) $uno(
    "SELECT COUNT(*) FROM tbcomprasolicitud WHERE tbtransportistaofertaid IS NOT NULL AND tbcomprasolicitudfleteestado = 'ACEPTADA'"),
    'fletes aceptados vienen de la solicitud, no de los viajes');
test_assert(isset($d['instrumentacion']['viajesRealizados']['motivo']), 'el motivo de lo no instrumentado viaja con los datos');

// ───── Periodo vacío: el pasado lejano no tiene hechos y los porcentajes quedan en null, no en 0 ─────
$vacio = $ok($controlador->procesar('GET', ['desde' => '2001-01-01', 'hasta' => '2001-01-31']));
test_assert($vacio['subastas']['exito']['porcentaje'] === null, 'sin cerradas no hay % de éxito');
test_assert($vacio['subastas']['precioPromedio']['valor'] === null, 'sin adjudicadas no hay precio promedio');
test_assert($vacio['comercializacion']['ventasPorCanal'] === [], 'sin ventas en el periodo');
test_assert($vacio['personas']['personas'] === $d['personas']['personas'], 'la red es una foto actual: no depende del periodo');

// ───── Validación ─────
foreach ([
    [['vista' => 'TODO'], 'vista'],
    [['desde' => '2026-02-30'], 'desde'],
    [['desde' => '2026-11-10', 'hasta' => '2026-11-01'], 'hasta'],
    [['vista' => 'EVIDENCIA', 'nodo' => 'tbpersona'], 'nodo'],
    [['vista' => 'EVIDENCIA', 'nodo' => 'subastas', 'estado' => 'PAGADA'], 'estado'],
    [['vista' => 'EVIDENCIA', 'nodo' => 'subastas', 'tamanoPagina' => '500'], 'tamanoPagina'],
    [['vista' => 'EVIDENCIA', 'nodo' => 'subastas', 'pagina' => true], 'pagina'],
    [['vista' => 'PERSONA', 'personaId' => '-3'], 'personaId'],
    [['vista' => 'PERSONA', 'q' => 'a'], 'q'],
] as [$consulta, $campo]) {
    $r = $controlador->procesar('GET', $consulta);
    test_assert($r['status'] === 422 && isset($r['body']['errors'][$campo]), "422 por {$campo}: " . json_encode($consulta));
}
test_assert($controlador->procesar('POST', [])['status'] === 405, 'solo lectura');

// ───── Evidencia paginada: total igual al indicador y páginas acotadas ─────
$adjudicadas = $s['estados']['adjudicadas']['total'];
$e = $ok($controlador->procesar('GET', ['vista' => 'EVIDENCIA', 'nodo' => 'subastas', 'estado' => 'FINALIZADA', 'tamanoPagina' => '2']));
test_assert($e['total'] === $adjudicadas, 'la evidencia cuenta lo mismo que el indicador');
test_assert(count($e['items']) === min(2, $adjudicadas), 'página de 2');
foreach ($e['items'] as $item) {
    test_assert($item['estado'] === 'FINALIZADA' && $item['monto'] !== null, 'cada fila es una adjudicada con su monto');
    test_assert(array_diff(array_keys($item), ['id', 'titulo', 'estado', 'fecha', 'imagenUrl', 'precioBase', 'monto', 'cantidad', 'personaId', 'persona']) === [],
        'la evidencia no trae columnas extra');
}
foreach (array_keys(AdminDashboardController::NODOS) as $nodo) {
    $ok($controlador->procesar('GET', ['vista' => 'EVIDENCIA', 'nodo' => $nodo]));
}
$tr = $ok($controlador->procesar('GET', ['vista' => 'TRANSPORTISTAS', 'tamanoPagina' => '5']));
test_assert($tr['total'] === (int) $uno('SELECT COUNT(*) FROM tbtransportista'), 'transportistas paginados');
foreach ($tr['items'] as $fila) {
    test_assert($fila['viajesRegistrados'] === 0 || $fila['promedioPorViaje'] !== null, 'promedio por viaje solo con viajes registrados');
    test_assert($fila['viajesRegistrados'] > 0 || $fila['promedioPorViaje'] === null, 'sin viajes no hay promedio inventado');
}

// ───── Expediente: una Persona con varias actividades, sin datos sensibles ─────
$personaId = (int) $uno('SELECT MIN(tbpersonaid) FROM tbsubastapuja');
if ($personaId > 0) {
    $x = $ok($controlador->procesar('GET', ['vista' => 'PERSONA', 'personaId' => (string) $personaId]))['expediente'];
    $plano = json_encode($x);
    foreach (['correo', 'telefono', 'identificacion', 'documentoruta', 'Ruta'] as $sensible) {
        test_assert(stripos($plano, $sensible) === false, "el expediente no expone {$sensible}");
    }
    test_assert($x['comprador'] === null || $x['comprador']['pujas'] === (int) $uno("SELECT COUNT(*) FROM tbsubastapuja WHERE tbpersonaid = {$personaId}"),
        'pujas del expediente');
}
test_assert($controlador->procesar('GET', ['vista' => 'PERSONA', 'personaId' => '2147483647'])['status'] === 404, 'persona inexistente → 404');
$busqueda = $ok($controlador->procesar('GET', ['vista' => 'PERSONA', 'q' => 'zz-nadie-se-llama-asi']));
test_assert($busqueda['personas'] === [], 'búsqueda sin resultados');

echo "OK admin_dashboard_test\n";
