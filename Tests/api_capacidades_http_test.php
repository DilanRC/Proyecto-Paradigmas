<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

/**
 * Contrato HTTP de /api/capacidades.php (Tramo 4, superficie admin):
 * sin sesión todo se rechaza con 401/403 y la precedencia de errores
 * estructurales (405/415/OPTIONS) ocurre ANTES del guard, igual que en las
 * superficies autenticadas de DEC-30.
 */

$url = 'http://127.0.0.1/api/capacidades.php';

function test_http_raw_status(string $method, ?string $body, string $target, string $contentType = 'application/json'): int
{
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = "Content-Type: {$contentType}";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $responseBody = file_get_contents($target, false, $context);
    test_assert($responseBody !== false, "No fue posible ejecutar HTTP {$method}.");
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    return (int) ($statusMatch[1] ?? 0);
}

$anexo = (string) (($_SERVER['argv'][1] ?? '') !== '' ? " {$argv[1]}" : '');
echo "\n== capacidades HTTP {$anexo} ==\n";

// ============================================================
// 401 SIN_SESION para el verbo de escritura.
// ============================================================
$inscribir = test_http_json('POST', '{"accion":"inscribir"}', 'application/json', $url);
test_same(401, $inscribir['status'], 'POST sin sesión responde 401');
test_same('SIN_SESION', $inscribir['body']['errors']['auth'] ?? null,
    'El 401 lleva errors.auth = SIN_SESION');
test_assert(str_contains((string) $inscribir['body']['message'], 'iniciar sesión'),
    'El mensaje del 401 orienta a iniciar sesión');

// ============================================================
// Precedencia estructural ANTES de la sesión (DEC-30).
// ============================================================
$opciones = test_http_raw_status('OPTIONS', null, $url);
test_same(204, $opciones, 'OPTIONS precede al guard con 204');

$get = test_http_json('GET', null, 'application/json', $url);
test_same(405, $get['status'], 'GET no permitido responde 405 aunque no haya sesión');

$noJson = test_http_json('POST', '{"a":1}', 'text/plain', $url);
test_same(415, $noJson['status'], 'Content-Type distinto de application/json responde 415 sin sesión');

$basic = test_http_json('POST', '{"accion":"inscribir"}', 'application/json', $url, ['Authorization: Basic YWRtaW46c2VjcmV0']);
test_same(401, $basic['status'], 'Autorización Basic sin sesión activa responde 401');

// ============================================================
// La acción/motivo se validan con éxito autenticado: sin sesión jamás
// se llega a ellos (el guard es la primera barrera).
// ============================================================
$sinCuerpo = test_http_raw_status('POST', '{}', $url);
test_same(400, $sinCuerpo, 'Cuerpo JSON vacío responde 400 antes del guard');

echo "OK capacidades HTTP: 401 SIN_SESION, OPTIONS 204, 405/415/400 preceden al guard.\n";