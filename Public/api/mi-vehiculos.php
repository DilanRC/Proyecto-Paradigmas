<?php

declare(strict_types=1);

use Application\Auth\SupabaseActorResolver;
use Application\Controller\MiVehiculosController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
require_once $raiz . '/Application/Service/AuthGuard.php';
foreach (['NamedLock', 'Persona', 'Vehiculo', 'TransportistaVehiculo', 'Transportista', 'Bitacora'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Controller/MiVehiculosController.php';

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

$conCuerpo = $metodo !== 'GET';
if ($conCuerpo && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    sendJsonResponse(['success' => false, 'message' => 'El cuerpo debe usar Content-Type: application/json.', 'data' => null], 415);
}

try {
    $cuerpo = $conCuerpo ? readJsonBody() : [];
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobals($conexion);
    Application\Service\AuthGuard::requerirAutenticado($actor);
    $controlador = new MiVehiculosController(
        $conexion,
        $actor,
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
    );
    $respuesta = $controlador->procesar($metodo, $cuerpo);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
} catch (Application\HttpException $excepcion) {
    sendJsonResponse([
        'success' => false,
        'message' => $excepcion->getMessage(),
        'data' => $excepcion->datos,
        'errors' => $excepcion->errores,
    ], $excepcion->estadoHttp);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse(['success' => false, 'message' => 'No fue posible completar la solicitud.', 'data' => null], 500);
}
