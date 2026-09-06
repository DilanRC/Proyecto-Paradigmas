<?php

declare(strict_types=1);

use Application\Controller\AnimalPublicacionController;
use Configuration\Database;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
foreach (['NamedLock', 'AnimalComercial'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Controller/AnimalPublicacionController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, OPTIONS');
    http_response_code(204);
    exit;
}
// Solo lectura: el catálogo se publica desde los flujos de AnimalComercial,
// que escriben bajo lock y transacción, no por este endpoint.
if ($metodo !== 'GET') {
    header('Allow: GET, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

try {
    $conexion = Database::getConnection();
    $controlador = new AnimalPublicacionController(
        $conexion,
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
    );
    $respuesta = $controlador->procesar($metodo, $_GET, []);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
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
