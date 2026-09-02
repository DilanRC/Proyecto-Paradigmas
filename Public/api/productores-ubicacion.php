<?php

declare(strict_types=1);

use Application\Controller\ProductorUbicacionController;
use Application\Auth\SupabaseActorResolver;
use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Model\ProductorUbicacion;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
foreach (['NamedLock', 'Persona', 'ProductorFinca', 'Productor', 'Bitacora', 'ProductorUbicacion'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Controller/ProductorUbicacionController.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$permitidos = ['GET', 'POST'];
if ($metodo === 'OPTIONS') {
    header('Allow: GET, POST, OPTIONS');
    http_response_code(204);
    exit;
}
if (!in_array($metodo, $permitidos, true)) {
    header('Allow: GET, POST, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

// Solo POST transporta cuerpo; el listado por GET va completo en la query.
$metodosConCuerpo = ['POST'];
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
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobals($conexion);
    $controlador = new ProductorUbicacionController(
        $conexion,
        new Productor($conexion, new ProductorFinca($conexion)),
        new ProductorUbicacion($conexion),
        new Bitacora($conexion, $actor),
        is_string($_SERVER['HTTP_X_REQUEST_ID'] ?? null) ? $_SERVER['HTTP_X_REQUEST_ID'] : null,
    );
    $respuesta = $controlador->procesar($metodo, $_GET, $cuerpo);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
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
