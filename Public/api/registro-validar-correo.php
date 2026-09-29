<?php

declare(strict_types=1);

use Application\Controller\RegistroCorreoController;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/Controller/RegistroCorreoController.php';

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
    if ($conexion->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
        sendJsonResponse([
            'success' => false,
            'message' => 'La verificación de correo no está disponible en este entorno.',
            'data' => null,
        ], 503);
    }

    // Auth puede tener una cuenta aunque su fila tbpersona nunca se haya creado.
    // Esta consulta privilegiada permanece en el servidor y devuelve solo disponibilidad.
    $respuesta = (new RegistroCorreoController(static function (string $correo) use ($conexion): bool {
        $consulta = $conexion->prepare(
            'SELECT EXISTS (SELECT 1 FROM auth.users WHERE LOWER(email) = :correo)'
        );
        $consulta->execute(['correo' => $correo]);
        return (bool) $consulta->fetchColumn();
    }))->procesar($metodo, readJsonBody());
    sendJsonResponse($respuesta['body'], $respuesta['status']);
} catch (UnexpectedValueException $excepcion) {
    sendJsonResponse(['success' => false, 'message' => $excepcion->getMessage(), 'data' => null], 400);
} catch (Throwable $excepcion) {
    error_log(sprintf('[TinderCows] %s en %s:%d', $excepcion->getMessage(), $excepcion->getFile(), $excepcion->getLine()));
    sendJsonResponse([
        'success' => false,
        'message' => 'No se pudo verificar el correo. Intenta de nuevo.',
        'data' => null,
    ], 503);
}
