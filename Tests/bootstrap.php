<?php

declare(strict_types=1);

use Application\Controller\FincaController;
use Application\Controller\ProductorController;
use Application\Controller\ProductorUbicacionController;
use Application\Model\Bitacora;
use Application\Model\ProductorFinca;
use Application\Model\ProductorUbicacion;
use Configuration\Database;

$testRoot = dirname(__DIR__);
require_once $testRoot . '/Configuration/Configuration.php';
require_once $testRoot . '/Configuration/Database.php';
foreach (['NamedLock', 'Persona', 'ProductorFinca', 'Direccion', 'ProductorDireccion', 'FincaDireccion', 'Bitacora', 'Productor', 'ProductorUbicacion', 'ProductorEstadoPeriodo'] as $testModel) {
    require_once $testRoot . "/Application/Model/{$testModel}.php";
}
require_once $testRoot . '/Application/Controller/ProductorController.php';
require_once $testRoot . '/Application/Controller/FincaController.php';
require_once $testRoot . '/Application/Controller/ProductorUbicacionController.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf('%s. Esperado: %s; recibido: %s', $message,
            var_export($expected, true), var_export($actual, true)));
    }
}

function test_db(): PDO
{
    return Database::getConnection();
}

function test_new_db(): PDO
{
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'dbmercadoganadero';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function test_controller(?string $requestId = null): ProductorController
{
    return new ProductorController(test_db(), $requestId ?? test_token('request'));
}

function test_finca_controller(?string $requestId = null): FincaController
{
    return new FincaController(test_db(), $requestId ?? test_token('request'));
}

function test_ubicacion_controller(?string $requestId = null): ProductorUbicacionController
{
    $db = test_db();

    return new ProductorUbicacionController(
        $db,
        new Productor($db, new ProductorFinca($db)),
        new ProductorUbicacion($db),
        new Bitacora($db),
        $requestId ?? test_token('request'),
    );
}

/**
 * Limpia las filas de ubicación append-only de los productores indicados.
 * El borrado directo es válido en pruebas: la política append-only aplica a
 * la API y al modelo, no al mantenimiento del banco de pruebas. Los eventos
 * de bitácora asociados se retiran con test_cleanup_productores().
 */
function test_cleanup_ubicaciones(array $productorIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $productorIds))));
    if ($ids === []) return;
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    test_db()->prepare("DELETE FROM tbproductorubicacion WHERE tbproductorid IN ({$marcadores})")->execute($ids);
}

/**
 * Retira los periodos de estado y actividad del productor de prueba; el
 * productor, su dirección y su bitácora se retiran con test_cleanup_productores().
 */
function test_cleanup_estado_periodos(array $productorIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $productorIds))));
    if ($ids === []) return;
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    test_db()->prepare("DELETE FROM tbproductorestadoperiodo WHERE tbproductorid IN ({$marcadores})")->execute($ids);
    test_db()->prepare("DELETE FROM tbproductoractividad WHERE tbproductorid IN ({$marcadores})")->execute($ids);
}

function test_token(string $label): string
{
    static $sequence = 0;
    return 'TC_TEST_' . strtoupper($label) . '_' . getmypid() . '_' . ++$sequence . '_' . bin2hex(random_bytes(4));
}

function test_document(): string
{
    return 'TST' . strtoupper(bin2hex(random_bytes(8)));
}

function test_direccion_payload(array $overrides = []): array
{
    return array_replace([
        'provincia' => 'Provincia Prueba', 'canton' => 'Cantón Prueba',
        'distrito' => 'Distrito Prueba', 'pueblo' => null,
        'senas' => 'Registro ficticio generado por Tests.',
    ], $overrides);
}

function test_payload(?string $number = null, array $overrides = []): array
{
    $base = [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $number ?? test_document()],
        'nombre' => 'Productor Ficticio de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'crud.tests@example.test',
        'fincas' => [],
    ];
    return array_replace_recursive($base, $overrides);
}

function test_create(array $overrides = [], ?string $number = null): array
{
    $response = test_controller()->procesar('POST', [], test_payload($number, $overrides));
    test_same(201, $response['status'], 'La fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La fixture debe ser exitosa.');
    test_assert(is_string($response['body']['data']['identificacionNumero'] ?? null), 'La API debe devolver identificación textual.');
    return $response['body']['data'];
}

function test_create_completo(array $overrides = [], array $direccionOverrides = [], ?string $number = null): array
{
    $creado = test_create($overrides, $number);
    $payloadActualizacion = test_payload($creado['identificacionNumero'], array_replace_recursive($overrides, [
        'direccionPrincipal' => test_direccion_payload($direccionOverrides),
        'identificacionNumeroOriginal' => $creado['identificacionNumero'],
    ]));
    $response = test_controller()->procesar('PUT', [], $payloadActualizacion);
    test_same(200, $response['status'], 'La fixture debe poder completar la dirección con PUT');

    return $response['body']['data'];
}

function test_http_json(
    string $method,
    ?string $body = null,
    string $contentType = 'application/json',
    string $url = 'http://127.0.0.1/api/productores.php',
): array {
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
    $responseBody = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    test_assert($responseBody !== false, "No fue posible ejecutar HTTP {$method}.");
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    $contentHeader = current(array_filter($responseHeaders,
        static fn (string $header): bool => str_starts_with(strtolower($header), 'content-type:')));
    test_assert(is_string($contentHeader) && str_contains(strtolower($contentHeader), 'application/json'),
        "HTTP {$method} debe responder application/json.");
    $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    test_assert(is_array($decoded), "HTTP {$method} debe responder un objeto JSON.");
    return ['status' => (int) ($statusMatch[1] ?? 0), 'body' => $decoded];
}

/**
 * Limpia productor, fincas, enlaces de dirección (productor y finca) y las
 * filas de tbdireccion que quedaron huérfanas por la prueba. Con el esquema
 * normalizado (DEC-13) tbdireccion es independiente y nadie más la borra
 * automáticamente, así que esta limpieza es la única forma de no dejar
 * basura acumulándose entre corridas de test.
 */
function test_cleanup_productores(array $identificaciones): void
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        $buscarProductorIds = $db->prepare("SELECT p.tbproductorid FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero IN ({$marcadores})");
        $buscarProductorIds->execute($ids);
        $productorIds = array_map('intval', $buscarProductorIds->fetchAll(PDO::FETCH_COLUMN));
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadores})")->execute($ids);

        if ($productorIds !== []) {
            $marcadoresProductor = implode(',', array_fill(0, count($productorIds), '?'));
            $db->prepare("DELETE FROM tbproductorestadoperiodo WHERE tbproductorid IN ({$marcadoresProductor})")->execute($productorIds);
            $db->prepare("DELETE FROM tbproductoractividad WHERE tbproductorid IN ({$marcadoresProductor})")->execute($productorIds);

            $buscarFincaIds = $db->prepare("SELECT tbfincaid FROM tbfinca WHERE tbproductorid IN ({$marcadoresProductor})");
            $buscarFincaIds->execute($productorIds);
            $fincaIds = array_map('intval', $buscarFincaIds->fetchAll(PDO::FETCH_COLUMN));

            $direccionIds = [];
            $buscarDireccionProductor = $db->prepare("SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid IN ({$marcadoresProductor})");
            $buscarDireccionProductor->execute($productorIds);
            $direccionIds = array_merge($direccionIds, array_map('intval', $buscarDireccionProductor->fetchAll(PDO::FETCH_COLUMN)));

            if ($fincaIds !== []) {
                $marcadoresFinca = implode(',', array_fill(0, count($fincaIds), '?'));
                $buscarDireccionFinca = $db->prepare("SELECT tbdireccionid FROM tbfincadireccion WHERE tbfincaid IN ({$marcadoresFinca})");
                $buscarDireccionFinca->execute($fincaIds);
                $direccionIds = array_merge($direccionIds, array_map('intval', $buscarDireccionFinca->fetchAll(PDO::FETCH_COLUMN)));

                $db->prepare("DELETE FROM tbfincadireccion WHERE tbfincaid IN ({$marcadoresFinca})")->execute($fincaIds);
            }

            $db->prepare("DELETE FROM tbfinca WHERE tbproductorid IN ({$marcadoresProductor})")->execute($productorIds);
            $db->prepare("DELETE FROM tbproductordireccion WHERE tbproductorid IN ({$marcadoresProductor})")->execute($productorIds);

            $direccionIds = array_values(array_unique($direccionIds));
            if ($direccionIds !== []) {
                $marcadoresDireccion = implode(',', array_fill(0, count($direccionIds), '?'));
                $db->prepare("DELETE FROM tbdireccion WHERE tbdireccionid IN ({$marcadoresDireccion})")->execute($direccionIds);
            }
        }
        $db->prepare("DELETE p FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid WHERE pe.tbpersonaidentificacionnumero IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marcadores})")->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}
