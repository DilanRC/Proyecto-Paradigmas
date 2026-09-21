<?php

declare(strict_types=1);

use Application\Auth\SupabaseActorResolver;
use Application\Controller\PublicacionInteraccionController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
foreach (['NamedLock', 'Bitacora', 'PublicacionInteraccion'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
require_once $raiz . '/Application/Controller/PublicacionInteraccionController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: POST, OPTIONS');
    http_response_code(204);
    exit;
}
if ($metodo !== 'POST') {
    header('Allow: POST, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}
$tipoContenido = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($tipoContenido !== 'application/json') {
    sendJsonResponse(['success' => false, 'message' => 'El cuerpo debe usar Content-Type: application/json.', 'data' => null], 415);
}

try {
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobals($conexion);
    $controlador = new PublicacionInteraccionController(
        $conexion,
        $actor,
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
    );
    $respuesta = $controlador->procesar($metodo, readJsonBody());
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
} catch (Application\HttpException $excepcion) {
    $body = ['success' => false, 'message' => $excepcion->getMessage(), 'data' => $excepcion->datos];
    if ($excepcion->errores !== []) $body['errors'] = $excepcion->errores;
    sendJsonResponse($body, $excepcion->estadoHttp);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse(['success' => false, 'message' => 'No fue posible guardar la interacción.', 'data' => null], 500);
}
