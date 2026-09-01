<?php

declare(strict_types=1);

use Application\Controller\CompradorController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
foreach (['NamedLock', 'Persona', 'Bitacora', 'Comprador'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach (['ValidacionService', 'EstadoService'] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
require_once $raiz . '/Application/Controller/CompradorController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$permitidos = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
if ($metodo === 'OPTIONS') {
    header('Allow: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    http_response_code(204);
    exit;
}
if (!in_array($metodo, $permitidos, true)) {
    header('Allow: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

$metodosConCuerpo = ['POST', 'PUT', 'DELETE', 'PATCH'];
$tipoContenido = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if (in_array($metodo, $metodosConCuerpo, true) && $tipoContenido !== 'application/json') {
    sendJsonResponse([
        'success' => false,
        'message' => 'El cuerpo debe usar Content-Type: application/json.',
        'data' => null,
    ], 415);
}

try {
    $cuerpo = in_array($metodo, $metodosConCuerpo, true) ? readJsonBody() : [];
    $controlador = new CompradorController(
        Database::getConnection(),
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
    );
    $respuesta = $controlador->procesar($metodo, $_GET, $cuerpo);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible completar la solicitud.',
        'data' => null,
    ], 500);
}
