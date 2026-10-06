<?php

declare(strict_types=1);

use Application\Controller\AnimalPublicacionController;
use Application\Auth\SupabaseActorResolver;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
foreach (['NamedLock', 'AnimalCatalogo', 'AnimalComercial', 'Bitacora', 'Persona', 'ProductorFinca', 'Productor'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Service/PublicacionCercaniaService.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
require_once $raiz . '/Application/Controller/AnimalPublicacionController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, POST, PATCH, OPTIONS');
    http_response_code(204);
    exit;
}
if (!in_array($metodo, ['GET', 'POST', 'PATCH'], true)) {
    header('Allow: GET, POST, PATCH, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

if ($metodo !== 'GET' && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    sendJsonResponse(['success' => false, 'message' => 'El cuerpo debe usar Content-Type: application/json.', 'data' => null], 415);
}

try {
    $conexion = Database::getConnection();
    $cuerpo = $metodo !== 'GET' ? readJsonBody() : [];
    $esConsultaPrivada = $metodo === 'POST' && array_key_exists('consulta', $cuerpo);
    if ($esConsultaPrivada && !is_array($cuerpo['consulta'])) {
        sendJsonResponse(['success' => false, 'message' => 'La consulta debe ser un objeto JSON.', 'data' => null], 400);
    }
    // La lectura pública no pide sesión; crear, editar y "mias" sí.
    $consultaPlana = $esConsultaPrivada ? $cuerpo['consulta'] : ($metodo === 'GET' ? $_GET : []);
    $pideMias = filter_var($consultaPlana['mias'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $requiereActor = $pideMias || $metodo === 'PATCH' || ($metodo === 'POST' && !$esConsultaPrivada);
    // En la lectura pública la sesión es opcional: si llega, la lista trae meInteresa.
    $hayToken = ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') !== '';
    $actor = null;
    if ($requiereActor || $hayToken) {
        try {
            $actor = SupabaseActorResolver::fromGlobals($conexion);
        } catch (Application\HttpException $sesion) {
            if ($requiereActor) throw $sesion; // lectura pública: un token vencido no la rompe
        }
    }
    $controlador = new AnimalPublicacionController(
        $conexion,
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
        $actor,
    );
    $respuesta = $controlador->procesar(
        $esConsultaPrivada ? 'GET' : $metodo,
        $esConsultaPrivada ? $cuerpo['consulta'] : $_GET,
        $esConsultaPrivada ? [] : $cuerpo,
    );
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
