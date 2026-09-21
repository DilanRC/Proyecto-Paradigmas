<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Service\ValidacionService;

/**
 * Política de duplicados DEC-31 demostrada por HTTP.
 *
 * La regla de negocio se prueba a nivel de controlador en
 * `Tests/duplicados_test.php` (persona 409, finca advertencia sin bloquear).
 * En la demo local no existe sesión real (Supabase no firma), así que toda
 * escritura admin anónima responde 401 SIN_SESION y la superficie pública
 * conserva el sobre {success,message,data,errors} intacto; este test fija
 * ese contrato HTTP para que la política no se enrute por el agujero de la
 * ausencia de sesión.
 */

$baseAdmin = [
    'productores.php' => ['identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => 'X-PRUEBA-0001'], 'nombre' => 'X'],
    'compradores.php' => ['identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => 'X-PRUEBA-0002'], 'nombre' => 'X'],
    'transportistas.php' => ['identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => 'X-PRUEBA-0003'], 'nombre' => 'X'],
    'vehiculos.php' => ['placa' => 'AAA-000', 'vin' => 'VIN-PRUEBA-000', 'modelo' => 'X'],
    'pagometodos.php' => ['nombre' => 'X'],
    'transportistas-vehiculos.php' => ['identificacionNumero' => 'X-PRUEBA-0003', 'vehiculoId' => 999],
    'fincas-direccion.php' => [
        'identificacionNumero' => 'X-PRUEBA-0001',
        'nombreFinca' => 'Finca X',
        'direccionFinca' => ['provincia' => 'A', 'canton' => 'B', 'distrito' => 'C'],
    ],
    'productores-direccion.php' => [
        'identificacionNumero' => 'X-PRUEBA-0001',
        'direccionPrincipal' => ['provincia' => 'A', 'canton' => 'B', 'distrito' => 'C'],
    ],
    'capacidades.php' => ['accion' => 'inscribir', 'contexto' => 'comprador', 'identificacionNumero' => 'X-PRUEBA-0002'],
];

// -----------------------------------------------
// 1. Escrituras admin anónimas → 401 SIN_SESION.
// -----------------------------------------------
foreach ($baseAdmin as $endpoint => $cuerpo) {
    $respuesta = test_http_json('POST', json_encode($cuerpo, JSON_UNESCAPED_UNICODE), 'application/json', "http://127.0.0.1/api/{$endpoint}");
    test_same(401, $respuesta['status'], "POST anónimo a {$endpoint} responde 401");
    test_same(false, $respuesta['body']['success'], "{$endpoint}: el sobre declara success false");
    test_assert(is_string($respuesta['body']['message'] ?? null) && $respuesta['body']['message'] !== '',
        "{$endpoint}: el sobre conserva message");
    test_same(null, $respuesta['body']['data'] ?? null, "{$endpoint}: data null en modo público");
    test_same('SIN_SESION', $respuesta['body']['errors']['auth'] ?? null,
        "{$endpoint}: errors.auth es SIN_SESION");
}

// -----------------------------------------------
// 2. Superficie pública preserva el sobre.
// -----------------------------------------------
$publicaciones = test_http_json('GET', null, 'application/json', 'http://127.0.0.1/api/publicaciones.php');
test_same(200, $publicaciones['status'], 'GET público de publicaciones responde 200');
test_same(true, $publicaciones['body']['success'], 'La superficie pública devuelve success true');
test_assert(is_array($publicaciones['body']['data']['publicaciones'] ?? null),
    'El sobre público de publicaciones conserva data.publicaciones');

$identidad = test_http_json('GET', null, 'application/json', 'http://127.0.0.1/api/identidad.php');
test_same(200, $identidad['status'], 'GET público de identidad responde 200');
test_same(false, $identidad['body']['data']['esProductor'], 'Identidad pública → esProductor false');
test_same(true, $identidad['body']['success'], 'Identidad pública es exitosa');

// productores-ubicacion necesita un productorId real; si no existe ninguna
// fila (BD vacía antes de la semilla) se omite sin romper el test.
$productorId = test_db()->query('SELECT tbproductorid FROM tbproductor ORDER BY tbproductorid LIMIT 1')->fetchColumn();
if ($productorId !== false) {
    $ubicacion = test_http_json('GET', null, 'application/json',
        'http://127.0.0.1/api/productores-ubicacion.php?productorId=' . (int) $productorId);
    test_same(200, $ubicacion['status'], 'GET público de ubicación responde 200');
    test_same(true, $ubicacion['body']['success'], 'La lectura pública de ubicación es exitosa');
}

echo "OK duplicados_api_http_test: escritura admin anónima 401 SIN_SESION y superficie pública "
    . "conserva el sobre {success,message,data,errors}.\n";