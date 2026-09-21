<?php

declare(strict_types=1);

/**
 * Verifica la mecánica del modelo analítico ProductorClasificacionPeriodo
 * (DEC-28/29): pregunta y respuesta por periodo, apertura y cierre
 * independientes por tipo y conservación del histórico. Es usada en
 * Tools/backfill-clasificacion-comprador.php.
 *
 * La pregunta "¿este productor es comprador?" ya NO rige el negocio: el
 * contexto Comprador se lee de tbcomprador + tbpersona. La clasificación
 * COMPRADOR queda como señal analítica del Productor y este test valida que
 * esa señal sigue calculable y cerrable sin romper el histórico.
 *
 * Los cuatro casos que fija Calidad para la señal:
 *   sin periodo COMPRADOR            -> false
 *   periodo COMPRADOR abierto        -> true
 *   periodo COMPRADOR cerrado        -> false
 *   COMPRADOR + VENDEDOR abiertos    -> true
 */

require __DIR__ . '/bootstrap.php';

use Application\Model\ProductorClasificacionPeriodo;

$db = test_db();
$documento = test_document();
$clasificacion = new ProductorClasificacionPeriodo($db);

$abrir = static function (int $productorId, string $tipo) use ($db, $clasificacion): int {
    return $clasificacion->ejecutarConBloqueo($productorId, $tipo, function () use ($db, $clasificacion, $productorId, $tipo): int {
        $db->beginTransaction();
        try {
            $id = $clasificacion->abrir($productorId, $tipo, 'Regresión paso (a)');
            $db->commit();

            return $id;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    });
};
$cerrar = static function (int $productorId, string $tipo) use ($db, $clasificacion): void {
    $clasificacion->ejecutarConBloqueo($productorId, $tipo, function () use ($db, $clasificacion, $productorId, $tipo): int {
        $db->beginTransaction();
        try {
            $clasificacion->cerrar($productorId, $tipo);
            $db->commit();

            return 0;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    });
};

try {
    $productor = test_create([], $documento);
    $productorId = (int) $productor['productorId'];

    // Caso 1: productor sin ningún periodo COMPRADOR.
    test_same(false, $clasificacion->esComprador($productorId),
        'Un productor sin periodo COMPRADOR no es comprador.');

    // Caso 2: periodo COMPRADOR abierto.
    $abrir($productorId, 'COMPRADOR');
    test_same(true, $clasificacion->esComprador($productorId),
        'Un productor con periodo COMPRADOR abierto es comprador.');

    // Caso 3: el mismo periodo, ya cerrado. Lo fue y dejó de serlo; el pasado
    // se conserva y la respuesta vuelve a false.
    $cerrar($productorId, 'COMPRADOR');
    test_same(false, $clasificacion->esComprador($productorId),
        'Un productor con periodo COMPRADOR cerrado ya no es comprador.');
    test_same(0, count($clasificacion->listarAbiertas($productorId)),
        'Cerrar la clasificación no deja periodos abiertos.');
    $historico = $db->prepare(
        'SELECT COUNT(*) FROM tbproductorclasificacionperiodo
         WHERE tbproductorid = :id AND tbproductorclasificacionperiodotipo = :tipo'
    );
    $historico->execute(['id' => $productorId, 'tipo' => 'COMPRADOR']);
    test_same(1, (int) $historico->fetchColumn(),
        'El periodo cerrado se conserva como historia, no se borra.');

    // Caso 4: COMPRADOR y VENDEDOR abiertos a la vez. Son independientes: la
    // clasificación vendedor no puede alterar la respuesta de comprador.
    $abrir($productorId, 'COMPRADOR');
    $abrir($productorId, 'VENDEDOR');
    test_same(true, $clasificacion->esComprador($productorId),
        'COMPRADOR y VENDEDOR abiertos a la vez mantienen comprador = true.');
    test_same(2, count($clasificacion->listarAbiertas($productorId)),
        'Ambas clasificaciones quedan abiertas simultáneamente.');

    // La señal analítica vive fuera del contexto: este productor nunca se
    // inscribió como comprador (no tiene fila en tbcomprador) y la señal aun
    // así se calcula y se cierra de forma independiente.
    $legacy = $db->prepare(
        'SELECT COUNT(*) FROM tbcomprador c
         INNER JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
         WHERE p.tbproductorid = :id'
    );
    $legacy->execute(['id' => $productorId]);
    test_same(0, (int) $legacy->fetchColumn(),
        'La señal analítica no depende de tbcomprador: no existe fila para este productor.');

    // Y al revés: cerrar VENDEDOR tampoco altera comprador.
    $cerrar($productorId, 'VENDEDOR');
    test_same(true, $clasificacion->esComprador($productorId),
        'Cerrar VENDEDOR no cierra COMPRADOR.');
} finally {
    if (isset($productorId)) {
        $db->prepare('DELETE FROM tbproductorclasificacionperiodo WHERE tbproductorid = :id')
            ->execute(['id' => $productorId]);
    }
    test_cleanup_productores([$documento]);
}

echo "OK comprador_clasificacion_test: la señal analítica COMPRADOR sobre el Productor sigue calculable (sin periodo, abierto, cerrado y COMPRADOR+VENDEDOR).\n";
