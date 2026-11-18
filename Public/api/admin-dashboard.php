<?php

declare(strict_types=1);

use Application\Auth\AdminAuthorization;
use Application\Auth\SupabaseActorResolver;
use Application\Controller\AdminDashboardController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/AdminAuthorization.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
foreach (['NamedLock', 'PersonaTelefonoHistorico', 'Persona', 'Comprador', 'AnimalComercial', 'Notificacion', 'Bitacora', 'Subasta', 'AdminDashboard'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
require_once $raiz . '/Application/Service/AuthGuard.php';
require_once $raiz . '/Application/Controller/AdminDashboardController.php';

header('Cache-Control: no-store, private');

// Centro de inteligencia del administrador (DEC-DASHBOARD-002). Solo lectura. Los filtros pueden viajar en
// POST { consulta } para no dejar nombres buscados en la URL ni en los registros del servidor.
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, POST, OPTIONS');
    http_response_code(204);
    exit;
}
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

$tipoContenido = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($metodo === 'POST' && $tipoContenido !== 'application/json') {
    sendJsonResponse(['success' => false, 'message' => 'El cuerpo debe usar Content-Type: application/json.', 'data' => null], 415);
}

try {
    $cuerpo = $metodo === 'POST' ? readJsonBody() : [];
    $conexion = Database::getConnection();
    $actor = SupabaseActorResolver::fromGlobalsPermitiendoPersonaNoVinculada($conexion);
    Application\Service\AuthGuard::requerirAutenticado($actor);
    AdminAuthorization::require($actor, $conexion);
    // Primero la autorización: sin sesión se responde 401, nunca un 422 que
    // le cuente a cualquiera cómo se forma una consulta.
    $consulta = $_GET;
    if ($metodo === 'POST') {
        if (!is_array($cuerpo['consulta'] ?? null)) {
            sendJsonResponse(['success' => false, 'message' => 'Envíe los filtros en { "consulta": { … } }.', 'data' => null], 422);
        }
        $consulta = $cuerpo['consulta'];
    }
    $respuesta = (new AdminDashboardController($conexion))->procesar('GET', $consulta);
    sendJsonResponse($respuesta['body'], $respuesta['status']);
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
    sendJsonResponse(['success' => false, 'message' => 'No fue posible completar la solicitud.', 'data' => null], 500);
}
