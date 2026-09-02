<?php

declare(strict_types=1);

/**
 * T2 - Generacion de IDs bajo concurrencia real (plan seccion 11).
 *
 * ProductorEstadoPeriodo calcula MAX(tbproductorestadoperiodoid)+1 sobre TODA
 * la tabla, pero ejecutarConBloqueo toma un lock POR PRODUCTOR. Dos productores
 * distintos sostienen locks distintos, asi que nada serializa el calculo del
 * consecutivo: ambos leen el mismo MAX y escriben el mismo id.
 *
 * La prueba levanta procesos PHP reales, no hilos simulados: el defecto solo
 * aparece con conexiones que compiten de verdad. Cada trabajador alterna el
 * estado de su propio productor, de modo que jamas compiten por el lock de
 * entidad y toda la colision proviene del consecutivo global.
 */

use Application\Model\ProductorEstadoPeriodo;

require __DIR__ . '/bootstrap.php';

const IDS_TRABAJADORES = 6;
const IDS_TRANSICIONES = 18; // 6 x 18 = 108 transiciones cruzadas

// ---------------------------------------------------------------- trabajador
if (($argv[1] ?? '') === 'trabajador') {
    $productorId = (int) $argv[2];
    $db = test_new_db();
    $periodos = new ProductorEstadoPeriodo($db);
    for ($i = 0; $i < IDS_TRANSICIONES; $i++) {
        $estado = $i % 2 === 0 ? 0 : 1;
        try {
            $db->beginTransaction();
            $periodos->ejecutarConBloqueo($productorId, function () use ($periodos, $productorId, $estado): void {
                if ($periodos->consultarAbierto($productorId) !== null) {
                    $periodos->cerrar($productorId);
                }
                $periodos->abrir($productorId, $estado, 'T2 concurrencia');
            });
            $db->commit();
        } catch (Throwable $excepcion) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Un id duplicado no revienta aqui porque la tabla no declara UNIQUE
            // (regla del curso): el fallo se detecta despues, contando.
            fwrite(STDERR, $excepcion->getMessage() . "\n");
        }
    }
    exit(0);
}

// -------------------------------------------------------------------- padre
$identificaciones = [];
$productorIds = [];
try {
    for ($i = 0; $i < IDS_TRABAJADORES; $i++) {
        $creado = test_create();
        $identificaciones[] = $creado['identificacionNumero'];
        $buscar = test_db()->prepare(
            'SELECT p.tbproductorid FROM tbproductor p
             INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
             WHERE pe.tbpersonaidentificacionnumero = :numero'
        );
        $buscar->execute(['numero' => $creado['identificacionNumero']]);
        $productorIds[] = (int) $buscar->fetchColumn();
    }

    $procesos = [];
    foreach ($productorIds as $productorId) {
        $comando = sprintf('php %s trabajador %d', escapeshellarg(__FILE__), $productorId);
        $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias);
        test_assert(is_resource($proceso), 'No fue posible lanzar el proceso concurrente.');
        $procesos[] = ['proceso' => $proceso, 'tuberias' => $tuberias];
    }
    foreach ($procesos as $entrada) {
        stream_get_contents($entrada['tuberias'][1]);
        stream_get_contents($entrada['tuberias'][2]);
        fclose($entrada['tuberias'][1]);
        fclose($entrada['tuberias'][2]);
        proc_close($entrada['proceso']);
    }

    $marcadores = implode(',', array_fill(0, count($productorIds), '?'));

    $duplicados = test_db()->prepare(
        "SELECT tbproductorestadoperiodoid, COUNT(*) AS repeticiones
         FROM tbproductorestadoperiodo
         WHERE tbproductorid IN ({$marcadores})
         GROUP BY tbproductorestadoperiodoid
         HAVING COUNT(*) > 1"
    );
    $duplicados->execute($productorIds);
    $filasDuplicadas = $duplicados->fetchAll();
    test_same(0, count($filasDuplicadas), sprintf(
        'MAX(id)+1 genero %d identificadores repetidos bajo concurrencia; el lock por productor no protege el consecutivo global',
        count($filasDuplicadas)
    ));

    $abiertos = test_db()->prepare(
        "SELECT tbproductorid, COUNT(*) AS abiertos
         FROM tbproductorestadoperiodo
         WHERE tbproductorid IN ({$marcadores}) AND tbproductorestadoperiodofechafin IS NULL
         GROUP BY tbproductorid
         HAVING COUNT(*) <> 1"
    );
    $abiertos->execute($productorIds);
    test_same(0, count($abiertos->fetchAll()),
        'Cada productor debe conservar exactamente un periodo de estado abierto.');

    echo "OK concurrencia_ids: " . (IDS_TRABAJADORES * IDS_TRANSICIONES)
        . " transiciones cruzadas, cero identificadores repetidos, un periodo abierto por productor.\n";
} finally {
    test_cleanup_estado_periodos($productorIds);
    test_cleanup_productores($identificaciones);
}
