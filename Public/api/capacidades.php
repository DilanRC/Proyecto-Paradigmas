<?php


declare(strict_types=1);

use Application\Auth\SupabaseActorResolver;
use Application\Controller\CapacidadController;
use Application\Service\ValidacionException;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
foreach (['NamedLock', 'PersonaTelefonoHistorico', 'Persona', 'Comprador', 'Bitacora', 'Transportista', 'TransportistaVehiculo', 'Productor', 'ProductorFinca', 'ProductorEstadoPeriodo'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach (['ValidacionService', 'CompradorClasificacionService', 'AuthGuard', 'ProductorEstadoService', 'EstadoService', 'CapacidadService'] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
require_once $raiz . '/Application/Controller/CapacidadController.php';

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
    sendJsonResponse([
        'success' => false,
        'message' => 'El cuerpo debe usar Content-Type: application/json.',
        'data' => null,
    ], 415);
}

try {
    $cuerpo = readJsonBody();
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobals($conexion);
    Application\Service\AuthGuard::requerirAutenticado($actor);
    $controlador = new CapacidadController(
        $conexion,
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
        $actor,
    );
    $respuesta = $controlador->procesar($cuerpo);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (ValidacionException $excepcion) {
    sendJsonResponse([
        'success' => false,
        'message' => $excepcion->getMessage(),
        'data' => null,
        'errors' => $excepcion->errores,
    ], 400);
} catch (\Application\Model\PersonaConflictException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null, 'errors' => [
        'identificacion.numero' => $excepcion->getMessage(),
    ]], 409);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
} catch (Application\HttpException $excepcion) {
    $cuerpoError = ['success' => false, 'message' => $excepcion->getMessage(), 'data' => $excepcion->datos];
    if ($excepcion->errores !== []) {
        $cuerpoError['errors'] = $excepcion->errores;
    }
    sendJsonResponse($cuerpoError, $excepcion->estadoHttp);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible completar la solicitud.',
        'data' => null,
    ], 500);
}