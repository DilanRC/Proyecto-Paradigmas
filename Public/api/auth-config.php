<?php

declare(strict_types=1);

use function Configuration\sendJsonResponse;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'OPTIONS') {
    header('Allow: GET, OPTIONS');
    http_response_code(204);
    exit;
}
if ($metodo !== 'GET') {
    header('Allow: GET, OPTIONS');
    sendJsonResponse(['success' => false, 'message' => 'Método no permitido.', 'data' => null], 405);
}

$url = trim((string) (getenv('SUPABASE_URL') ?: ''));
$publishableKey = trim((string) (getenv('SUPABASE_PUBLISHABLE_KEY') ?: ''));
if ($url === '' || $publishableKey === '') {
    sendJsonResponse([
        'success' => false,
        'message' => 'La autenticación no está configurada en este entorno.',
        'data' => null,
    ], 503);
}
if (filter_var($url, FILTER_VALIDATE_URL) === false || !str_starts_with($url, 'https://')) {
    sendJsonResponse([
        'success' => false,
        'message' => 'La URL pública de autenticación no es válida.',
        'data' => null,
    ], 503);
}
if (!str_starts_with($publishableKey, 'sb_publishable_')) {
    sendJsonResponse([
        'success' => false,
        'message' => 'La clave pública de autenticación no es válida para el navegador.',
        'data' => null,
    ], 503);
}

sendJsonResponse([
    'success' => true,
    'message' => 'Configuración pública de autenticación.',
    'data' => [
        'url' => rtrim($url, '/'),
        'publishableKey' => $publishableKey,
    ],
]);
