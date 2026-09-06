<?php

declare(strict_types=1);

use Application\Auth\SupabaseActorResolver;
use Application\Controller\IdentidadController;
use Configuration\Database;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
require_once $raiz . '/Application/Controller/IdentidadController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, OPTIONS');
    http_response_code(204);
    exit;
}
if ($metodo !== 'GET') {
    header('Allow: GET, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

try {
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobals($conexion);
    $controlador = new IdentidadController($conexion, $actor);
    $respuesta = $controlador->procesar();
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (Application\HttpException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => $excepcion->datos], $excepcion->estadoHttp);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible completar la solicitud.',
        'data' => null,
    ], 500);
}
