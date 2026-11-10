<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Application/Service/DashboardService.php';

use Application\Service\DashboardService;

/** Indicadores del dashboard del vendedor (DEC-DASHBOARD-001): funciones puras, sin base de datos. */

function dash_igual(mixed $esperado, mixed $real, string $mensaje): void
{
    if ($esperado !== $real) {
        fwrite(STDERR, "FALLO: {$mensaje}\n  esperado: " . var_export($esperado, true) . "\n  real: " . var_export($real, true) . "\n");
        exit(1);
    }
}

function dash_subasta(int $id, string $estado, float $base, ?float $adjudicado = null, string $inicio = '2026-11-01T10:00:00Z', ?string $cierre = null): array
{
    return [
        'subastaId' => $id, 'estado' => $estado, 'precioBase' => $base, 'montoAdjudicado' => $adjudicado,
        'fechaInicio' => $inicio, 'fechaFin' => $cierre ?? '2026-11-02T10:00:00Z', 'fechaCierre' => $cierre,
    ];
}

// Sin subastas: todo en cero o null (no se inventa un 0 % ni un promedio).
$vacio = DashboardService::indicadoresVendedor([], []);
dash_igual(0, $vacio['estados']['activas']['total'], 'sin subastas no hay activas');
dash_igual(null, $vacio['exito']['porcentaje'], 'sin finalizadas el porcentaje es null');
dash_igual(null, $vacio['precioPromedio']['valor'], 'sin adjudicadas no hay precio promedio');
dash_igual(null, $vacio['incrementoPromedio']['porcentaje'], 'sin adjudicadas no hay incremento');
dash_igual(null, $vacio['tiempoAdjudicacion']['minutos'], 'sin adjudicadas no hay tiempo');
dash_igual(0, $vacio['compradores']['total'], 'sin pujas no hay compradores');

$subastas = [
    dash_subasta(1, 'ACTIVA', 100000),
    // Adjudicada en 2 h: base 100 000 → 150 000 (+50 %).
    dash_subasta(2, 'FINALIZADA', 100000, 150000, '2026-11-01T10:00:00Z', '2026-11-01T12:00:00Z'),
    // Adjudicada en 4 h: base 200 000 → 250 000 (+25 %).
    dash_subasta(3, 'FINALIZADA', 200000, 250000, '2026-11-01T10:00:00Z', '2026-11-01T14:00:00Z'),
    dash_subasta(4, 'DESIERTA', 80000, null, '2026-11-01T10:00:00Z', '2026-11-01T11:00:00Z'),
    dash_subasta(5, 'CANCELADA', 90000),
];
$postores = [
    ['subastaId' => 1, 'personaId' => 10],
    ['subastaId' => 2, 'personaId' => 10],
    ['subastaId' => 2, 'personaId' => 11],
    ['subastaId' => 3, 'personaId' => 12],
];
$r = DashboardService::indicadoresVendedor($subastas, $postores);

dash_igual(['total' => 1, 'ids' => [1]], $r['estados']['activas'], 'activas');
dash_igual(['total' => 3, 'ids' => [2, 3, 4]], $r['estados']['cerradas'], 'cerradas = finalizadas + desiertas');
dash_igual(['total' => 1, 'ids' => [5]], $r['estados']['canceladas'], 'canceladas');
// 2 adjudicadas de 3 finalizadas (la cancelada no cuenta como finalizada).
dash_igual(66.7, $r['exito']['porcentaje'], 'porcentaje de éxito');
dash_igual(2, $r['exito']['adjudicadas'], 'adjudicadas');
dash_igual(3, $r['exito']['finalizadas'], 'finalizadas');
dash_igual(200000.0, $r['precioPromedio']['valor'], 'precio promedio = 400 000 / 2 animales');
dash_igual(400000.0, $r['precioPromedio']['montoTotal'], 'monto total adjudicado');
dash_igual(37.5, $r['incrementoPromedio']['porcentaje'], 'incremento promedio = (50 % + 25 %) / 2');
dash_igual(3, $r['compradores']['total'], 'personas distintas que pujaron');
dash_igual([1, 2, 3], $r['compradores']['ids'], 'subastas con pujas');
dash_igual(180, $r['tiempoAdjudicacion']['minutos'], 'tiempo promedio = (2 h + 4 h) / 2');
dash_igual(['total' => 1, 'ids' => [4]], $r['sinOfertas'], 'sin ofertas = desiertas');

dash_igual(null, DashboardService::minutosEntre('2026-11-01T10:00:00Z', null), 'sin cierre no hay duración');
dash_igual(null, DashboardService::minutosEntre('2026-11-01T10:00:00Z', '2026-11-01T09:00:00Z'), 'un cierre anterior al inicio no cuenta');
dash_igual(90, DashboardService::minutosEntre('2026-11-01T10:00:00Z', '2026-11-01T11:30:00Z'), 'minutos entre fechas');

// ───── Comprador ─────
$historicos = [
    ['especieId' => 1, 'tipoId' => 6, 'razaId' => 3, 'monto' => 800000.0],
    ['especieId' => 1, 'tipoId' => 6, 'razaId' => 3, 'monto' => 1000000.0],
    ['especieId' => 1, 'tipoId' => 6, 'razaId' => 2, 'monto' => 1500000.0],
    ['especieId' => 1, 'tipoId' => 7, 'razaId' => 7, 'monto' => 2000000.0],
];
$jersey = DashboardService::promedioComparable(['especieId' => 1, 'tipoId' => 6, 'razaId' => 3], $historicos, 810000);
dash_igual(['promedio' => 900000.0, 'muestras' => 2, 'nivel' => 'TIPO_RAZA', 'diferenciaPorcentaje' => -10.0], $jersey, 'mismo tipo y raza primero');
$vacaOtra = DashboardService::promedioComparable(['especieId' => 1, 'tipoId' => 6, 'razaId' => 9], $historicos, 1100000);
dash_igual(['promedio' => 1100000.0, 'muestras' => 3, 'nivel' => 'TIPO', 'diferenciaPorcentaje' => 0.0], $vacaOtra, 'sin la raza, el mismo tipo');
$ternero = DashboardService::promedioComparable(['especieId' => 1, 'tipoId' => 1, 'razaId' => null], $historicos, 1325000);
dash_igual('ESPECIE', $ternero['nivel'], 'sin el tipo, la misma especie');
$cerdo = DashboardService::promedioComparable(['especieId' => 2, 'tipoId' => null, 'razaId' => null], $historicos, 100);
dash_igual(null, $cerdo['promedio'], 'sin comparables no hay promedio');

$yo = 50;
$part = static fn (int $id, string $estado, ?float $mia, ?float $actual, ?int $ganador = null, ?float $adjudicado = null, array $extra = []): array => $extra + [
    'subastaId' => $id, 'estado' => $estado, 'miPuja' => $mia, 'precioActual' => $actual, 'ganadorPersonaId' => $ganador,
    'montoAdjudicado' => $adjudicado,
];
$participaciones = [
    // Voy ganando: mi puja es la más alta. Flete de 50 000.
    $part(1, 'ACTIVA', 500000, 500000, null, null, ['costoFlete' => 50000.0, 'comparacion' => ['diferenciaPorcentaje' => -10.0]]),
    // Me superaron. Sin flete con precio.
    $part(2, 'ACTIVA', 300000, 350000, null, null, ['costoFlete' => null, 'comparacion' => ['diferenciaPorcentaje' => 20.0]]),
    $part(3, 'FINALIZADA', 700000, 700000, $yo, 700000),
    $part(4, 'FINALIZADA', 400000, 450000, 99, 450000),
    $part(5, 'FINALIZADA', 250000, 250000, $yo, 250000),
];
$oportunidad = ['subastaId' => 9, 'estado' => 'ACTIVA', 'comparacion' => ['diferenciaPorcentaje' => 5.0]];
$c = DashboardService::indicadoresComprador($participaciones, $yo, [$oportunidad], 50);
dash_igual(['total' => 2, 'ids' => [1, 2]], $c['pujasActivas'], 'pujas activas');
dash_igual(['total' => 1, 'ids' => [2]], $c['pujasSuperadas'], 'pujas superadas');
dash_igual(66.7, $c['adjudicacion']['porcentaje'], 'ganó 2 de 3 finalizadas');
dash_igual([3, 5], $c['adjudicacion']['ids'], 'las ganadas');
dash_igual(950000.0, $c['montoAdjudicado']['valor'], 'monto adjudicado = 700 000 + 250 000');
dash_igual(['total' => 1, 'radioKm' => 50, 'requiereUbicacion' => false, 'ids' => [9]], $c['oportunidades'], 'oportunidades');
dash_igual(5.0, $c['comparacion']['porcentaje'], 'comparación = (−10 + 20 + 5) / 3');
dash_igual([1, 2, 9], $c['comparacion']['ids'], 'activas y oportunidades comparadas');
dash_igual(850000.0, $c['costoAdquisicion']['total'], 'costo = 500 000 + 50 000 + 300 000 (sin flete)');
dash_igual(1, $c['costoAdquisicion']['sinFlete'], 'una sin precio de flete');

$sinUbicacion = DashboardService::indicadoresComprador([], $yo, null, 50);
dash_igual(true, $sinUbicacion['oportunidades']['requiereUbicacion'], 'sin ubicación se pide compartirla');
dash_igual(null, $sinUbicacion['oportunidades']['total'], 'sin ubicación no hay conteo');
dash_igual(null, $sinUbicacion['adjudicacion']['porcentaje'], 'sin finalizadas no hay porcentaje');
dash_igual(null, $sinUbicacion['costoAdquisicion']['total'], 'sin pujas activas no hay costo');

echo "OK dashboard_service_test\n";
