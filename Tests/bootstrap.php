<?php

declare(strict_types=1);

use Application\Controller\ProductorController;
use Configuration\Database;
$testRoot = dirname(__DIR__);
require_once $testRoot . '/Configuration/Configuration.php';
require_once $testRoot . '/Configuration/Database.php';
foreach (['Rol', 'TipoIdentificacion', 'ParticipanteRol', 'ParticipanteIdentificacion',
          'ParticipanteDireccion', 'Finca', 'ProductorFinca', 'Bitacora', 'Participante'] as $testModel) {
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
        throw new RuntimeException(sprintf(
            '%s. Esperado: %s; recibido: %s',
            $message,
            var_export($expected, true),
            var_export($actual, true),
        ));
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
    ++$sequence;

    return 'TC_TEST_' . strtoupper($label) . '_' . getmypid() . '_' . $sequence . '_' . bin2hex(random_bytes(4));
}

function test_document(): string
{
    return 'TST-' . strtoupper(bin2hex(random_bytes(8)));
}

function test_type_id(string $code = 'PASAPORTE'): int
{
    $statement = test_db()->prepare(
        'SELECT tbidentificaciontipoId FROM tbidentificaciontipo
         WHERE tbidentificaciontipoCodigo = :codigo AND tbidentificaciontipoEstado = 1'
    );
    $statement->execute(['codigo' => $code]);
    $id = $statement->fetchColumn();
    test_assert($id !== false, "No existe el tipo de identificación activo {$code}.");

    return (int) $id;
}

function test_role_id(string $code): int
{
    $statement = test_db()->prepare(
        'SELECT tbrolId FROM tbrol WHERE tbrolCodigo = :codigo AND tbrolEstado = 1'
    );
    $statement->execute(['codigo' => $code]);
    $id = $statement->fetchColumn();
    test_assert($id !== false, "No existe el rol activo {$code}.");

    return (int) $id;
}

function test_payload(?string $number = null, array $overrides = []): array
{
    $number ??= test_document();
    $base = [
        'identificacion' => ['tipoId' => test_type_id(), 'numero' => $number],
        'nombre' => 'Productor Ficticio de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'crud.tests@example.test',
        'direccionPrincipal' => [
            'provincia' => 'Provincia Prueba',
            'canton' => 'Cantón Prueba',
            'distrito' => 'Distrito Prueba',
            'pueblo' => null,
            'senas' => 'Registro ficticio generado por Tests.',
        ],
        'fincas' => [],
    ];

    return array_replace_recursive($base, $overrides);
}

function test_create(array $overrides = [], ?string $number = null): array
{
    $response = test_controller()->procesar('POST', [], test_payload($number, $overrides));
    test_same(201, $response['status'], 'La creación de la fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La creación de la fixture debe ser exitosa.');
    test_assert(is_int($response['body']['data']['participanteId'] ?? null), 'La API debe devolver participanteId entero.');

    return $response['body']['data'];
}

function test_cleanup_participants(array $participantIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $participantIds), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return;
    }
    $db = test_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        foreach (['tbbitacora' => 'tbbitacoraRegistroId', 'tbproductorfinca' => 'tbparticipanteId',
                  'tbparticipantedireccion' => 'tbparticipanteId', 'tbparticipanteidentificacion' => 'tbparticipanteId',
                  'tbparticipanterol' => 'tbparticipanteId'] as $table => $column) {
            $statement = $db->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})");
            $statement->execute($ids);
        }
        $statement = $db->prepare("DELETE FROM tbparticipante WHERE tbparticipanteId IN ({$placeholders})");
        $statement->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function test_cleanup_farms(array $farmIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $farmIds), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db = test_db();
    $db->beginTransaction();
    try {
        $statement = $db->prepare("DELETE FROM tbproductorfinca WHERE tbfincaId IN ({$placeholders})");
        $statement->execute($ids);
        $statement = $db->prepare("DELETE FROM tbfinca WHERE tbfincaId IN ({$placeholders})");
        $statement->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function test_create_farm(bool $active = true): int
{
    $statement = test_db()->prepare(
        'INSERT INTO tbfinca (tbfincaNombre, tbfincaEstado) VALUES (:nombre, :estado)'
    );
    $statement->execute(['nombre' => test_token('farm'), 'estado' => $active ? 1 : 0]);

    return (int) test_db()->lastInsertId();
}
