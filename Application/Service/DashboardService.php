<?php

declare(strict_types=1);

namespace Application\Service;

/**
 * Indicadores de los dashboards de subastas (DEC-DASHBOARD-001). Funciones puras: reciben las filas que entrega el
 * modelo (Subasta::paraDashboardVendedor) y devuelven los números. Cada indicador trae `ids`: las subastas que lo
 * forman, para que la pantalla muestre la foto de cada ejemplar.
 *
 * Definiciones:
 *  - Cerradas = FINALIZADA (con ganador) + DESIERTA (sin pujas). Son las "finalizadas" de los porcentajes; las
 *    CANCELADAS no cuentan como finalizadas.
 *  - Exitosas = adjudicadas (FINALIZADA) / cerradas × 100.
 *  - Precio promedio de venta = suma de montos adjudicados / animales adjudicados (una subasta = un animal).
 *  - Incremento promedio = promedio de (precio final − precio base) / precio base × 100 de las adjudicadas.
 *  - Compradores participantes = personas distintas que pujaron (toda puja guardada ya pasó las reglas).
 *  - Tiempo promedio de adjudicación = promedio de (cierre − inicio) de las adjudicadas, en minutos.
 *  - Animales sin ofertas = DESIERTA.
 */
final class DashboardService
{
    /**
     * @param array<int,array<string,mixed>> $subastas filas de Subasta::paraDashboardVendedor
     * @param array<int,array{subastaId:int,personaId:int}> $postores pares distintos (subasta, persona)
     */
    public static function indicadoresVendedor(array $subastas, array $postores): array
    {
        $ids = static fn (array $lista): array => array_values(array_map(static fn (array $s): int => (int) $s['subastaId'], $lista));
        $porEstado = static fn (array $estados): array => array_values(array_filter($subastas, static fn (array $s): bool => in_array($s['estado'], $estados, true)));

        $activas = $porEstado(['ACTIVA']);
        $cerradas = $porEstado(['FINALIZADA', 'DESIERTA']);
        $canceladas = $porEstado(['CANCELADA']);
        $adjudicadas = array_values(array_filter($porEstado(['FINALIZADA']), static fn (array $s): bool => $s['montoAdjudicado'] !== null));
        $desiertas = $porEstado(['DESIERTA']);

        $montoTotal = array_sum(array_map(static fn (array $s): float => (float) $s['montoAdjudicado'], $adjudicadas));

        $incrementos = [];
        foreach ($adjudicadas as $s) {
            if ((float) $s['precioBase'] > 0) {
                $incrementos[] = ((float) $s['montoAdjudicado'] - (float) $s['precioBase']) / (float) $s['precioBase'] * 100;
            }
        }

        $duraciones = [];
        foreach ($adjudicadas as $s) {
            $minutos = self::minutosEntre($s['fechaInicio'] ?? null, $s['fechaCierre'] ?? $s['fechaFin'] ?? null);
            if ($minutos !== null) $duraciones[] = $minutos;
        }

        $personas = [];
        $conPujas = [];
        foreach ($postores as $par) {
            $personas[$par['personaId']] = true;
            $conPujas[$par['subastaId']] = true;
        }

        return [
            'estados' => [
                'activas' => ['total' => count($activas), 'ids' => $ids($activas)],
                'cerradas' => ['total' => count($cerradas), 'ids' => $ids($cerradas)],
                'canceladas' => ['total' => count($canceladas), 'ids' => $ids($canceladas)],
            ],
            'exito' => [
                'porcentaje' => self::porcentaje(count($adjudicadas), count($cerradas)),
                'adjudicadas' => count($adjudicadas),
                'finalizadas' => count($cerradas),
                'ids' => $ids($adjudicadas),
            ],
            'precioPromedio' => [
                'valor' => $adjudicadas === [] ? null : round($montoTotal / count($adjudicadas), 2),
                'montoTotal' => round($montoTotal, 2),
                'animales' => count($adjudicadas),
                'ids' => $ids($adjudicadas),
            ],
            'incrementoPromedio' => [
                'porcentaje' => $incrementos === [] ? null : round(array_sum($incrementos) / count($incrementos), 1),
                'ids' => $ids($adjudicadas),
            ],
            'compradores' => [
                'total' => count($personas),
                'ids' => array_values(array_filter($ids($subastas), static fn (int $id): bool => isset($conPujas[$id]))),
            ],
            'tiempoAdjudicacion' => [
                'minutos' => $duraciones === [] ? null : (int) round(array_sum($duraciones) / count($duraciones)),
                'ids' => $ids($adjudicadas),
            ],
            'sinOfertas' => ['total' => count($desiertas), 'ids' => $ids($desiertas)],
        ];
    }

    /**
     * Dashboard del comprador. $participaciones = filas de Subasta::paraDashboardComprador ya enriquecidas por el
     * controlador con `comparacion` (ver promedioComparable) y, en las activas, `costoFlete`; $oportunidades = activas
     * de otros dentro del radio, o null si la persona no compartió su ubicación.
     *
     * Definiciones:
     *  - Pujas activas = subastas ACTIVA donde pujó. Superadas = de esas, donde la puja más alta no es la suya.
     *  - Adjudicación = ganadas / FINALIZADAS donde pujó × 100 (con una puja suya no puede quedar DESIERTA ni CANCELADA).
     *  - Monto adjudicado = suma de lo que pagó en las ganadas.
     *  - Comparación = promedio de (precio actual − promedio histórico comparable) / promedio histórico, en las activas
     *    donde puja y en las oportunidades. Negativo = está más barato que lo que se ha pagado antes.
     *  - Costo estimado = su puja + el flete más barato con precio que cubre la finca, en las activas donde puja.
     */
    public static function indicadoresComprador(array $participaciones, int $personaId, ?array $oportunidades, int $radioKm): array
    {
        $ids = static fn (array $lista): array => array_values(array_map(static fn (array $s): int => (int) $s['subastaId'], $lista));
        $filtrar = static fn (array $lista, callable $condicion): array => array_values(array_filter($lista, $condicion));

        $activas = $filtrar($participaciones, static fn (array $s): bool => $s['estado'] === 'ACTIVA');
        $superadas = $filtrar($activas, static fn (array $s): bool => $s['miPuja'] !== null && $s['precioActual'] !== null
            && (float) $s['precioActual'] > (float) $s['miPuja']);
        $finalizadas = $filtrar($participaciones, static fn (array $s): bool => $s['estado'] === 'FINALIZADA');
        $ganadas = $filtrar($finalizadas, static fn (array $s): bool => $s['ganadorPersonaId'] === $personaId);
        $monto = array_sum(array_map(static fn (array $s): float => (float) $s['montoAdjudicado'], $ganadas));

        // Comparación de precios: activas donde puja + oportunidades, sin repetir.
        $comparables = [];
        foreach (array_merge($activas, $oportunidades ?? []) as $s) {
            if (($s['comparacion']['diferenciaPorcentaje'] ?? null) !== null) $comparables[$s['subastaId']] = $s;
        }
        $diferencias = array_map(static fn (array $s): float => (float) $s['comparacion']['diferenciaPorcentaje'], $comparables);

        $costos = $filtrar($activas, static fn (array $s): bool => $s['miPuja'] !== null);
        $total = 0.0;
        $sinFlete = 0;
        foreach ($costos as $s) {
            $total += (float) $s['miPuja'] + (float) ($s['costoFlete'] ?? 0);
            if (($s['costoFlete'] ?? null) === null) $sinFlete++;
        }

        return [
            'pujasActivas' => ['total' => count($activas), 'ids' => $ids($activas)],
            'pujasSuperadas' => ['total' => count($superadas), 'ids' => $ids($superadas)],
            'adjudicacion' => [
                'porcentaje' => self::porcentaje(count($ganadas), count($finalizadas)),
                'ganadas' => count($ganadas),
                'finalizadas' => count($finalizadas),
                'ids' => $ids($ganadas),
            ],
            'montoAdjudicado' => ['valor' => round($monto, 2), 'animales' => count($ganadas), 'ids' => $ids($ganadas)],
            'oportunidades' => [
                'total' => $oportunidades === null ? null : count($oportunidades),
                'radioKm' => $radioKm,
                'requiereUbicacion' => $oportunidades === null,
                'ids' => $oportunidades === null ? [] : $ids($oportunidades),
            ],
            'comparacion' => [
                'porcentaje' => $diferencias === [] ? null : round(array_sum($diferencias) / count($diferencias), 1),
                'ids' => array_map('intval', array_keys($comparables)),
            ],
            'costoAdquisicion' => [
                'total' => $costos === [] ? null : round($total, 2),
                'subastas' => count($costos),
                'sinFlete' => $sinFlete,
                'ids' => $ids($costos),
            ],
        ];
    }

    /**
     * Promedio histórico de animales comparables, del grupo más parecido que tenga datos: mismo tipo y raza, si no
     * mismo tipo, si no misma especie. $precio = el precio actual (la puja más alta o, sin pujas, el precio base).
     *
     * @param array{especieId:?int,tipoId:?int,razaId:?int} $animal
     * @param array<int,array{especieId:?int,tipoId:?int,razaId:?int,monto:float}> $historicos
     * @return array{promedio:?float,muestras:int,nivel:?string,diferenciaPorcentaje:?float}
     */
    public static function promedioComparable(array $animal, array $historicos, float $precio): array
    {
        $grupos = [
            'TIPO_RAZA' => static fn (array $h): bool => $animal['tipoId'] !== null && $animal['razaId'] !== null
                && $h['tipoId'] === $animal['tipoId'] && $h['razaId'] === $animal['razaId'],
            'TIPO' => static fn (array $h): bool => $animal['tipoId'] !== null && $h['tipoId'] === $animal['tipoId'],
            'ESPECIE' => static fn (array $h): bool => $animal['especieId'] !== null && $h['especieId'] === $animal['especieId'],
        ];
        foreach ($grupos as $nivel => $coincide) {
            $montos = array_map(static fn (array $h): float => $h['monto'], array_values(array_filter($historicos, $coincide)));
            if ($montos === []) continue;
            $promedio = array_sum($montos) / count($montos);
            return [
                'promedio' => round($promedio, 2),
                'muestras' => count($montos),
                'nivel' => $nivel,
                'diferenciaPorcentaje' => $promedio > 0 ? round(($precio - $promedio) / $promedio * 100, 1) : null,
            ];
        }
        return ['promedio' => null, 'muestras' => 0, 'nivel' => null, 'diferenciaPorcentaje' => null];
    }

    /** Porcentaje con un decimal, o null si no hay base (no se inventa un 0 %). */
    public static function porcentaje(int $parte, int $total): ?float
    {
        return $total === 0 ? null : round($parte / $total * 100, 1);
    }

    /** Minutos entre dos fechas ISO (UTC con Z); null si falta alguna o el orden es imposible. */
    public static function minutosEntre(?string $desde, ?string $hasta): ?int
    {
        if ($desde === null || $hasta === null) return null;
        $inicio = strtotime($desde);
        $fin = strtotime($hasta);
        if ($inicio === false || $fin === false || $fin < $inicio) return null;
        return (int) round(($fin - $inicio) / 60);
    }
}
