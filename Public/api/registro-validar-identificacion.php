<?php

declare(strict_types=1);

use Application\Controller\RegistroIdentificacionController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
foreach (['NamedLock', 'Persona', 'RegistroConsulta'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach (['ValidacionService'] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
require_once $raiz . '/Application/Controller/RegistroIdentificacionController.php';

header('Cache-Control: no-store, private');

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
    $conexion = Database::getConnection();
    $limite = new Application\Model\RegistroConsulta($conexion);
    if (!$limite->permitir(Application\Model\RegistroConsulta::ipCliente($_SERVER))) {
        header('Retry-After: ' . Application\Model\RegistroConsulta::VENTANA_SEGUNDOS);
        sendJsonResponse([
            'success' => false,
            'message' => 'Demasiadas consultas seguidas. Espera un minuto e intenta de nuevo.',
            'data' => null,
        ], 429);
    }
    $controlador = new RegistroIdentificacionController($conexion);
    $respuesta = $controlador->procesar($metodo, readJsonBody());
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No fue posible verificar la identificación.',
        'data' => null,
    ], 500);
}
