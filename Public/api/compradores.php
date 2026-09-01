<?php

declare(strict_types=1);

/**
 * Consulta de productores clasificados como COMPRADOR. Solo lectura.
 *
 * El CRUD legacy de comprador se retiró en el paso (d) (DEC-DBREADY-008):
 * Comprador es una clasificación derivada del comportamiento del Productor, por
 * lo que no existe alta, edición, baja ni reactivación administrativa por API.
 * Los clientes antiguos reciben 405 con una explicación explícita.
 */

use Application\Controller\CompradorConsultaController;
use Configuration\Database;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
foreach (['NamedLock', 'ProductorClasificacionPeriodo'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Service/CompradorClasificacionService.php';
require_once $raiz . '/Application/Controller/CompradorConsultaController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, OPTIONS');
    http_response_code(204);
    exit;
}
if ($metodo !== 'GET') {
    // Rechazo estructural antes de abrir conexión: una caída de MySQL no puede
    // convertir una escritura prohibida en 500 ni hacer parecer que el método
    // existe. El controlador conserva la misma defensa para llamadas directas.
    header('Allow: GET, OPTIONS');
    sendJsonResponse([
        'success' => false,
        'message' => 'La clasificación Comprador se deriva del comportamiento del productor y no se administra a mano.',
        'data' => null,
    ], 405);
}

try {
    $controlador = new CompradorConsultaController(Database::getConnection());
    $respuesta = $controlador->procesar('GET', $_GET);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (Application\HttpException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(),
        'data' => $excepcion->datos], $excepcion->estadoHttp);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(),
        $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible completar la solicitud.',
        'data' => null,
    ], 500);
}
