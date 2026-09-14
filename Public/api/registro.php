<?php

declare(strict_types=1);

use Application\Auth\SupabaseActorResolver;
use Application\Controller\RegistroPublicoController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';

foreach ([
    'NamedLock',
    'PersonaTelefonoHistorico',
    'Persona',
    'ProductorFinca',
    'Direccion',
    'ProductorDireccion',
    'FincaDireccion',
    'ProductorEstadoPeriodo',
    'Productor',
    'TransportistaVehiculo',
    'Transportista',
    'Comprador',
    'Bitacora',
] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach (['ValidacionService', 'RegistroPublicoService'] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
require_once $raiz . '/Application/Controller/RegistroPublicoController.php';

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

    // ÚNICA excepción al resolver estricto: durante primera alta el JWT puede
    // ser válido aunque todavía no exista tbpersona. El controlador nunca toma
    // identidad de ese hecho; valida el correo del payload contra el JWT y la
    // creación/vinculación ocurre en una sola transacción.
    $actor = SupabaseActorResolver::fromGlobalsPermitiendoPersonaNoVinculada($conexion);
    $controlador = new RegistroPublicoController(
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
        'message' => 'No fue posible completar el registro.',
        'data' => null,
    ], 500);
}
