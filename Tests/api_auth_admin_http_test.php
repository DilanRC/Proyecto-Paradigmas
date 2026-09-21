<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Tramo 2 (DEC-30): la demo local sin sesión solo ve la superficie pública.
 * Cada endpoint de administración exige actor autenticado en TODOS sus verbos
 * (lecturas y escrituras); los endpoints públicos se consultan sin sesión.
 *
 * El flujo "con JWT válido" se demuestra por unidad (auth_guard_test.php y
 * auth_actor_test.php con transport simulado), porque el sidecar local no firma
 * JWT reales. Aquí se fija el contrato determinista sin dependencia de red.
 */

function test_http_raw_status(string $method, string $url, ?string $body = null): int
{
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body ?? '',
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    file_get_contents($url, false, $context);
    preg_match('/\s(\d{3})\s/', ($http_response_header[0] ?? ''), $match);

    return (int) ($match[1] ?? 0);
}

$base = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1'), '/') . '/api';
$admins = [
    'productores.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], 'prohibido' => null],
    'productores-direccion.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE'], 'prohibido' => 'PATCH'],
    'transportistas.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], 'prohibido' => null],
    'vehiculos.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], 'prohibido' => null],
    'pagometodos.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], 'prohibido' => null],
    'compradores.php' => ['verbos' => ['GET', 'POST', 'DELETE', 'PATCH'], 'prohibido' => 'PUT'],
    'fincas-direccion.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE'], 'prohibido' => 'PATCH'],
    'transportistas-vehiculos.php' => ['verbos' => ['GET', 'POST', 'PUT', 'DELETE'], 'prohibido' => 'PATCH'],
];

foreach ($admins as $archivo => $endpoint) {
    $url = "{$base}/{$archivo}";

    foreach ($endpoint['verbos'] as $verbo) {
        $respuesta = test_http_json(
            $verbo,
            $verbo === 'GET' ? null : '{"a":1}',
            'application/json',
            $url,
        );
        test_same(401, $respuesta['status'],
            "{$archivo} {$verbo} sin sesión debe responder 401");
        if ($verbo === 'POST') {
            test_same(['auth' => 'SIN_SESION'], $respuesta['body']['errors'] ?? null,
                "{$archivo} POST 401 debe llevar el código SIN_SESION");
            test_assert(is_string($respuesta['body']['message'] ?? null)
                && str_contains($respuesta['body']['message'], 'iniciar sesión'),
                "{$archivo} POST 401 debe explicar que hay que iniciar sesión");
        }
    }

    test_same(204, test_http_raw_status('OPTIONS', $url),
        "{$archivo} OPTIONS no exige sesión (preflight CORS)");

    if ($endpoint['prohibido'] !== null) {
        test_same(405, test_http_json($endpoint['prohibido'], null, 'application/json', $url)['status'],
            "{$archivo} {$endpoint['prohibido']} es verbo prohibido y responde 405 antes de la sesión");
    }
}

// Un encabezado Authorization mal formado es rechazado por el resolutor con 401
// sin necesidad de contactar al sidecar (validación de formato local).
$malformado = test_http_json(
    'GET', null, 'application/json', "{$base}/productores.php",
    ['Authorization: Basic abc'],
);
test_same(401, $malformado['status'], 'Authorization mal formado → 401 sin red');

// Superficie pública (GET api/identidad.php): sin sesión se consulta y se
// responde que el actor no es productor, sin exigir inicio de sesión.
$identidad = test_http_json('GET', null, 'application/json', "{$base}/identidad.php");
test_same(200, $identidad['status'], 'api/identidad.php se consulta sin sesión');
test_same(false, $identidad['body']['data']['esProductor'], 'Sin sesión el actor no es productor');

// Superficie pública: el listado de publicaciones se consulta sin sesión.
$publicaciones = test_http_json('GET', null, 'application/json', "{$base}/publicaciones.php");
test_same(200, $publicaciones['status'], 'api/publicaciones.php se consulta sin sesión');

// Superficie pública: el histórico append-only de ubicación se consulta sin
// sesión para el productor de semilla.
$ubicacion = test_http_json('GET', null, 'application/json', "{$base}/productores-ubicacion.php?productorId=1");
test_same(200, $ubicacion['status'], 'api/productores-ubicacion.php se consulta sin sesión');

echo "OK api_auth_admin_http_test: superficie admin 401 sin sesión, preflight 204, verbos prohibidos 405 y superficie pública abierta.\n";
