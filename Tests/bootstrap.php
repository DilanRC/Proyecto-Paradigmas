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

function test_cleanup_productores(array $identificaciones): void
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraRegistroIdentificacionNumero IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbproductoresfinca WHERE tbproductoresIdentificacionNumero IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbproductores WHERE tbproductoresIdentificacionNumero IN ({$marcadores})")->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}
