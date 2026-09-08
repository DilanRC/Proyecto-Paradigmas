<?php

declare(strict_types=1);

/**
 * Consulta de contextos Comprador relacionados con Persona. Solo lectura.
 *
 * No existe un CRUD administrativo de roles: las filas de tbcomprador deben
 * originarse en el proceso de negocio que corresponda. La API expone únicamente
 * consulta durante Avance 2.
 */

use Application\Controller\CompradorConsultaController;
use Configuration\Database;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Model/Comprador.php';
require_once $raiz . '/Application/Controller/CompradorConsultaController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, OPTIONS');
    http_response_code(204);
    exit;
}
if ($metodo !== 'GET') {
    header('Allow: GET, OPTIONS');
    sendJsonResponse([
        'success' => false,
        'message' => 'Comprador no se administra como un rol manual; su contexto se genera desde el proceso de negocio.',
        'data' => null,
    ], 405);
}

try {
    $controlador = new CompradorConsultaController(Database::getConnection());
    $respuesta = $controlador->procesar('GET', $_GET);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (Application\HttpException $excepcion) {
    sendJsonResponse([
        'success' => false,
        'message' => $excepcion->getMessage(),
        'data' => $excepcion->datos,
    ], $excepcion->estadoHttp);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(),
        $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible completar la solicitud.',
        'data' => null,
    ], 500);
}
