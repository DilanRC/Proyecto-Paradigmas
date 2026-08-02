<?php

declare(strict_types=1);

use Application\Controller\ProductorController;
use Configuration\Database;

$testRoot = dirname(__DIR__);
require_once $testRoot . '/Configuration/Configuration.php';
require_once $testRoot . '/Configuration/Database.php';
foreach (['ProductorFinca', 'ProductorDireccion', 'Bitacora', 'Productor'] as $testModel) {
    require_once $testRoot . "/Application/Model/{$testModel}.php";
}
require_once $testRoot . '/Application/Controller/ProductorController.php';

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
    $name = getenv('DB_NAME') ?: 'dbtindercows';
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

function test_token(string $label): string
{
    static $sequence = 0;
    return 'TC_TEST_' . strtoupper($label) . '_' . getmypid() . '_' . ++$sequence . '_' . bin2hex(random_bytes(4));
}

function test_document(): string
{
    return 'TST' . strtoupper(bin2hex(random_bytes(8)));
}

function test_payload(?string $number = null, array $overrides = []): array
{
    $base = [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $number ?? test_document()],
        'nombre' => 'Productor Ficticio de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'crud.tests@example.test',
        'direccionPrincipal' => [
            'provincia' => 'Provincia Prueba', 'canton' => 'Cantón Prueba',
            'distrito' => 'Distrito Prueba', 'pueblo' => null,
            'senas' => 'Registro ficticio generado por Tests.',
        ],
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

function test_http_json(string $method, ?string $body = null, string $contentType = 'application/json'): array
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
    $responseBody = file_get_contents('http://127.0.0.1/api/productores.php', false, $context);
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

function test_cleanup_productores(array $identificaciones): void
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        $buscarProductorIds = $db->prepare("SELECT tbproductorId FROM tbproductor
            WHERE tbproductorIdentificacionNumero IN ({$marcadores})");
        $buscarProductorIds->execute($ids);
        $productorIds = array_map('intval', $buscarProductorIds->fetchAll(PDO::FETCH_COLUMN));
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraRegistroIdentificacionNumero IN ({$marcadores})")->execute($ids);
        if ($productorIds !== []) {
            $marcadoresProductor = implode(',', array_fill(0, count($productorIds), '?'));
            $db->prepare("DELETE FROM tbproductorfinca WHERE tbproductorId IN ({$marcadoresProductor})")->execute($productorIds);
            $db->prepare("DELETE FROM tbproductordireccion WHERE tbproductorId IN ({$marcadoresProductor})")->execute($productorIds);
        }
        $db->prepare("DELETE FROM tbproductor WHERE tbproductorIdentificacionNumero IN ({$marcadores})")->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}
