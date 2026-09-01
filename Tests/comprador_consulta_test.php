<?php

declare(strict_types=1);

/**
 * Paso (d) (DEC-DBREADY-008): el panel de compradores quedó de solo lectura.
 *
 * Lo que esta prueba fija no es solo que el listado funcione, sino que **no
 * exista** forma de clasificar a alguien desde la API. Si mañana alguien
 * reintroduce un alta, esto falla.
 */

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/CompradorConsultaController.php';

use Application\Controller\CompradorConsultaController;
use Application\Model\ProductorClasificacionPeriodo;
use Application\Service\CompradorClasificacionService;

$db = test_db();
$documentoClasificado = test_document();
$documentoSinClasificar = test_document();
$controlador = new CompradorConsultaController($db);
$clasificacion = new ProductorClasificacionPeriodo($db);
$servicio = new CompradorClasificacionService($db);

try {
    $clasificado = test_create([], $documentoClasificado);
    $sinClasificar = test_create([], $documentoSinClasificar);
    $productorClasificado = (int) $clasificado['productorId'];

    $db->beginTransaction();
    $servicio->activar($productorClasificado, CompradorClasificacionService::MOTIVO_MIGRACION);
    $db->commit();

    // --- Escrituras: no existen, y lo dicen ---------------------------------
    foreach (['POST', 'PUT', 'DELETE', 'PATCH'] as $metodo) {
        $respuesta = $controlador->procesar($metodo, []);
        test_same(405, $respuesta['status'], "{$metodo} sobre compradores debe responder 405.");
        test_assert(str_contains($respuesta['body']['message'], 'se deriva del comportamiento'),
            "{$metodo} debe explicar por qué no se administra a mano.");
    }

    // --- Consulta por identificación ----------------------------------------
    $ficha = $controlador->procesar('GET', ['identificacionNumero' => $clasificado['identificacionNumero']]);
    test_same(200, $ficha['status'], 'El productor clasificado se consulta por identificación.');
    test_same($productorClasificado, $ficha['body']['data']['productorId'],
        'La ficha identifica al productor, no a un comprador.');
    test_assert($ficha['body']['data']['clasificadoDesde'] !== null,
        'La ficha dice desde cuándo está clasificado.');
    test_same('ACTIVO', $ficha['body']['data']['estado'],
        'El estado del contrato de capacidades sale de la clasificación abierta.');

    $ausente = $controlador->procesar('GET',
        ['identificacionNumero' => $sinClasificar['identificacionNumero']]);
    test_same(404, $ausente['status'], 'Un productor sin clasificación abierta responde 404.');

    // --- Listado -------------------------------------------------------------
    $lista = $controlador->procesar('GET', ['q' => $clasificado['identificacionNumero']]);
    test_same(200, $lista['status'], 'El listado responde 200.');
    test_same('tbproductorclasificacionperiodo', $lista['body']['data']['fuente'],
        'El listado declara de dónde sale la verdad.');
    test_same(1, $lista['body']['data']['total'], 'La búsqueda encuentra al clasificado.');
    $fila = $lista['body']['data']['clasificados'][0];
    test_same($clasificado['identificacionNumero'], $fila['identificacionNumero'],
        'La fila corresponde al productor buscado.');
    test_same(CompradorClasificacionService::MOTIVO_MIGRACION, $fila['motivo'],
        'La fila conserva el origen de la clasificación.');

    // Al cerrar el periodo, desaparece del listado: la lista no puede mostrar
    // a nadie que la clasificación no respalde.
    $db->beginTransaction();
    $servicio->desactivar($productorClasificado);
    $db->commit();
    $listaTrasCierre = $controlador->procesar('GET', ['q' => $clasificado['identificacionNumero']]);
    test_same(0, $listaTrasCierre['body']['data']['total'],
        'Cerrada la clasificación, el productor sale del listado.');
    test_same(404, $controlador->procesar('GET',
        ['identificacionNumero' => $clasificado['identificacionNumero']])['status'],
        'Cerrada la clasificación, la ficha responde 404.');

    // El periodo cerrado sigue existiendo: la lista dejó de mostrarlo, la
    // historia no se borró.
    $historial = $db->prepare(
        'SELECT COUNT(*) FROM tbproductorclasificacionperiodo
         WHERE tbproductorid = :id AND tbproductorclasificacionperiodotipo = :tipo'
    );
    $historial->execute(['id' => $productorClasificado, 'tipo' => 'COMPRADOR']);
    test_same(1, (int) $historial->fetchColumn(), 'El periodo cerrado se conserva como historia.');

    // --- Validaciones de consulta -------------------------------------------
    test_same(422, $controlador->procesar('GET', ['pagina' => '0'])['status'],
        'La paginación se valida.');
    test_same(422, $controlador->procesar('GET', ['tamanoPagina' => '500'])['status'],
        'El tamaño de página se valida.');
    test_same(405, $controlador->procesar('OPTIONS', [])['status'], 'Otros métodos siguen sin permitirse.');
} finally {
    foreach ([$documentoClasificado, $documentoSinClasificar] as $documento) {
        $db->prepare('DELETE cp FROM tbproductorclasificacionperiodo cp
            INNER JOIN tbproductor p ON p.tbproductorid = cp.tbproductorid
            INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $documento]);
    }
    test_cleanup_productores([$documentoClasificado, $documentoSinClasificar]);
}

echo "OK comprador_consulta_test: la API de compradores solo lee la clasificación abierta y rechaza toda escritura.\n";
