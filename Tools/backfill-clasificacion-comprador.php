<?php

declare(strict_types=1);

/**
 * Backfill del paso (b) del retiro de la tabla legacy de comprador
 * (DEC-DBREADY-005/007): expandir -> migrar -> cortar.
 *
 * Precheck primero, backfill después. Nunca al revés: si primero se cambiara la
 * lectura del panel, los compradores legacy aparecerían como falso porque sus
 * periodos todavía no existen.
 *
 * Reglas que este script NO rompe:
 *   - No inventa `tbproductor`. Un comprador legacy sin productor se reporta y
 *     se salta; resolverlo es una decisión humana.
 *   - No inventa historia. Un comprador legacy inactivo no recibe un periodo
 *     cerrado falso: no se sabe cuándo dejó de serlo.
 *   - Es idempotente: correrlo dos veces no abre dos periodos COMPRADOR.
 *   - La fecha de inicio significa "desde aquí hay evidencia confiable", no que
 *     la persona empezó a ser compradora ese día. Por eso el motivo es
 *     explícito: MIGRACION_TBCOMPRADOR_LEGACY.
 *
 * Uso:
 *   php Tools/backfill-clasificacion-comprador.php --check   (solo audita)
 *   php Tools/backfill-clasificacion-comprador.php --apply   (audita y migra)
 */

require_once dirname(__DIR__) . '/Configuration/Configuration.php';
require_once dirname(__DIR__) . '/Configuration/Database.php';
require_once dirname(__DIR__) . '/Application/HttpException.php';
require_once dirname(__DIR__) . '/Application/Model/NamedLock.php';
require_once dirname(__DIR__) . '/Application/Model/ProductorClasificacionPeriodo.php';
require_once dirname(__DIR__) . '/Application/Service/CompradorClasificacionService.php';

use Application\Service\CompradorClasificacionService;

const BACKFILL_DIRECTORIO = '/tmp/backfill-clasificacion-comprador';

/**
 * Audita cada fila de tbcomprador contra tbproductor por tbpersonaid.
 *
 * @return array{filas: array<int,array<string,mixed>>, sin_productor: array<int,array<string,mixed>>,
 *               migrables: array<int,array<string,mixed>>, ya_migrados: array<int,array<string,mixed>>,
 *               inactivos: array<int,array<string,mixed>>}
 */
function backfill_auditar(PDO $conexion): array
{
    $sentencia = $conexion->prepare(
        'SELECT c.tbcompradorid, c.tbpersonaid, c.tbcompradorestado,
                pe.tbpersonaidentificacionnumero, pe.tbpersonanombre, pe.tbpersonaestado,
                p.tbproductorid,
                cp.tbproductorclasificacionperiodoid AS periodo_abierto
         FROM tbcomprador c
         INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
         LEFT JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
         LEFT JOIN tbproductorclasificacionperiodo cp
                ON cp.tbproductorid = p.tbproductorid
               AND cp.tbproductorclasificacionperiodotipo = :tipo
               AND cp.tbproductorclasificacionperiodofechafin IS NULL
         ORDER BY c.tbcompradorid'
    );
    $sentencia->execute(['tipo' => CompradorClasificacionService::TIPO]);
    $filas = $sentencia->fetchAll();

    $resultado = ['filas' => $filas, 'sin_productor' => [], 'migrables' => [],
        'ya_migrados' => [], 'inactivos' => []];
    foreach ($filas as $fila) {
        $activo = (int) $fila['tbcompradorestado'] === 1 && (int) $fila['tbpersonaestado'] === 1;
        if ($fila['tbproductorid'] === null) {
            $resultado['sin_productor'][] = $fila;
            continue;
        }
        if (!$activo) {
            $resultado['inactivos'][] = $fila;
            continue;
        }
        if ($fila['periodo_abierto'] !== null) {
            $resultado['ya_migrados'][] = $fila;
            continue;
        }
        $resultado['migrables'][] = $fila;
    }

    return $resultado;
}

function backfill_snapshot(array $auditoria, string $directorio): string
{
    if (!is_dir($directorio) && !mkdir($directorio, 0o755, true) && !is_dir($directorio)) {
        throw new RuntimeException("No fue posible crear {$directorio}");
    }
    $ruta = $directorio . '/snapshot-' . gmdate('Ymd-His') . '.csv';
    $manejador = fopen($ruta, 'w');
    if ($manejador === false) {
        throw new RuntimeException("No fue posible escribir {$ruta}");
    }
    fputcsv($manejador, ['tbcompradorid', 'tbpersonaid', 'identificacion', 'nombre',
        'tbcompradorestado', 'tbpersonaestado', 'tbproductorid', 'periodo_comprador_abierto_antes']);
    foreach ($auditoria['filas'] as $fila) {
        fputcsv($manejador, [$fila['tbcompradorid'], $fila['tbpersonaid'],
            $fila['tbpersonaidentificacionnumero'], $fila['tbpersonanombre'],
            $fila['tbcompradorestado'], $fila['tbpersonaestado'],
            $fila['tbproductorid'] ?? '', $fila['periodo_abierto'] ?? '']);
    }
    fclose($manejador);

    return $ruta;
}

/**
 * Abre el periodo COMPRADOR de cada comprador legacy activo y migrable.
 *
 * @return array{migrados: int, omitidos: int}
 */
function backfill_aplicar(PDO $conexion, array $auditoria, ?callable $progreso = null): array
{
    $servicio = new CompradorClasificacionService($conexion);
    $migrados = 0;
    $omitidos = 0;
    $total = count($auditoria['migrables']);
    foreach ($auditoria['migrables'] as $indice => $fila) {
        $productorId = (int) $fila['tbproductorid'];
        $conexion->beginTransaction();
        try {
            $abrio = $servicio->activar($productorId, CompradorClasificacionService::MOTIVO_MIGRACION);
            $conexion->commit();
        } catch (Throwable $error) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $error;
        }
        $abrio ? $migrados++ : $omitidos++;
        if ($progreso !== null) {
            $progreso($indice + 1, $total, $fila);
        }
    }

    return ['migrados' => $migrados, 'omitidos' => $omitidos];
}

function backfill_registrar(string $directorio, string $linea): void
{
    if (!is_dir($directorio) && !mkdir($directorio, 0o755, true) && !is_dir($directorio)) {
        return;
    }
    $marca = gmdate('Y-m-d H:i:s');
    file_put_contents($directorio . '/progress.log', "[{$marca}] {$linea}\n", FILE_APPEND);
    fwrite(STDOUT, "[{$marca}] {$linea}\n");
}

function backfill_main(array $argv): int
{
    $aplicar = in_array('--apply', $argv, true);
    if (!$aplicar && !in_array('--check', $argv, true)) {
        fwrite(STDERR, "Use --check (solo audita) o --apply (audita y migra).\n");

        return 2;
    }

    $conexion = Configuration\Database::getConnection();
    $auditoria = backfill_auditar($conexion);
    $directorio = BACKFILL_DIRECTORIO;
    $snapshot = backfill_snapshot($auditoria, $directorio);

    backfill_registrar($directorio, sprintf(
        'PRECHECK: %d compradores legacy | %d migrables | %d ya migrados | %d inactivos | %d sin productor. Snapshot: %s',
        count($auditoria['filas']), count($auditoria['migrables']), count($auditoria['ya_migrados']),
        count($auditoria['inactivos']), count($auditoria['sin_productor']), $snapshot,
    ));
    backfill_registrar($directorio, 'Seguimiento en vivo: tail -f ' . $directorio . '/progress.log');

    if ($auditoria['sin_productor'] !== []) {
        foreach ($auditoria['sin_productor'] as $fila) {
            backfill_registrar($directorio, sprintf(
                'INCOMPATIBLE: comprador %d (persona %d, identificación %s, nombre %s) no tiene productor. '
                . 'No se migra y no se inventa tbproductor.',
                $fila['tbcompradorid'], $fila['tbpersonaid'],
                $fila['tbpersonaidentificacionnumero'], $fila['tbpersonanombre'],
            ));
        }
        backfill_registrar($directorio, 'ABORTADO: resuelva los compradores sin productor antes del backfill.');

        return 1;
    }

    if (!$aplicar) {
        backfill_registrar($directorio, 'PRECHECK LIMPIO: sin compradores incompatibles. Ejecute --apply para migrar.');

        return 0;
    }

    $resultado = backfill_aplicar($conexion, $auditoria, static function (int $hechos, int $total) use ($directorio): void {
        if ($total > 0 && ($hechos === $total || $hechos % 50 === 0)) {
            backfill_registrar($directorio, sprintf('BACKFILL: %d/%d (%d%%)',
                $hechos, $total, (int) round(100 * $hechos / $total)));
        }
    });

    $verificacion = backfill_auditar($conexion);
    $pendientes = count($verificacion['migrables']);
    backfill_registrar($directorio, sprintf(
        'BACKFILL COMPLETO: %d periodos COMPRADOR abiertos, %d omitidos por idempotencia, %d pendientes.',
        $resultado['migrados'], $resultado['omitidos'], $pendientes,
    ));
    backfill_registrar($directorio, sprintf(
        'VERIFICACIÓN: %d activos con clasificación abierta | %d inactivos sin clasificación nueva | %d sin productor.',
        count($verificacion['ya_migrados']), count($verificacion['inactivos']),
        count($verificacion['sin_productor']),
    ));
    $reporte = backfill_snapshot($verificacion, $directorio);
    backfill_registrar($directorio, 'CSV después: ' . $reporte);

    return $pendientes === 0 ? 0 : 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(backfill_main($argv));
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
