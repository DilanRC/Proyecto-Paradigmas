<?php

declare(strict_types=1);

use Application\Auth\SupabaseActorResolver;
use Application\Controller\MiActividadController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';

// Dependencias de lectura de contextos y de las transiciones ya aprobadas de
// Productor/Transportista. Mi actividad NO crea un segundo modelo de estados.
foreach ([
    'NamedLock',
    'PersonaTelefonoHistorico',
    'Persona',
    'ProductorFinca',
    'Direccion',
    'ProductorDireccion',
    'FincaDireccion',
    'Bitacora',
    'Productor',
    'ProductorEstadoPeriodo',
    'TransportistaVehiculo',
    'Transportista',
    'Comprador',
] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach ([
    'ProductorDireccionService',
    'ProductorEstadoService',
    'ValidacionService',
    'EstadoService',
    'CompradorClasificacionService',
    'CapacidadService',
] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
require_once $raiz . '/Application/Controller/ProductorController.php';
require_once $raiz . '/Application/Controller/TransportistaController.php';
require_once $raiz . '/Application/Controller/MiActividadController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, PATCH, OPTIONS');
    http_response_code(204);
    exit;
}
if (!in_array($metodo, ['GET', 'PATCH'], true)) {
    header('Allow: GET, PATCH, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

if ($metodo === 'PATCH') {
    $tipoContenido = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($tipoContenido !== 'application/json') {
        sendJsonResponse([
            'success' => false,
            'message' => 'El cuerpo debe usar Content-Type: application/json.',
            'data' => null,
        ], 415);
    }
}

try {
    $cuerpo = $metodo === 'PATCH' ? readJsonBody() : [];
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobals($conexion);
    $controlador = new MiActividadController(
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
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible completar la solicitud.',
        'data' => null,
    ], 500);
}
