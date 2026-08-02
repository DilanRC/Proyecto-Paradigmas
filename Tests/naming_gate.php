<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function gate(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function contents(string $root, string $path): string
{
    gate(is_file($root . '/' . $path), "Falta el archivo requerido: {$path}");
    $value = file_get_contents($root . '/' . $path);
    gate(is_string($value), "No fue posible leer: {$path}");

    return $value;
}

$required = [
    'Application/Controller/ProductorController.php',
    'Application/Model/Participante.php',
    'Application/Model/ParticipanteIdentificacion.php',
    'Application/Model/ParticipanteDireccion.php',
    'Application/Model/ParticipanteRol.php',
    'Application/Model/ProductorFinca.php',
    'Application/Model/Bitacora.php',
    'Application/View/productores/index.php',
    'Public/api/productores.php',
    'Public/js/productores.js',
];
foreach ($required as $path) {
    gate(is_file($root . '/' . $path), "Falta el archivo oficial: {$path}");
}

$legacy = [
    'Application/Controller/ProducerController.php',
    'Application/Model/Producer.php',
    'Application/View/producers',
    'Public/api/producers.php',
    'Public/js/producers.js',
    'Database/SqlScripts/001_create_producers.sql',
];
foreach ($legacy as $path) {
    $fullPath = $root . '/' . $path;
    $hasTrackedContent = is_file($fullPath)
        || (is_dir($fullPath) && count(glob($fullPath . '/*') ?: []) > 0);
    gate(!$hasTrackedContent, "Permanece una ruta obsoleta con contenido: {$path}");
}

$controller = contents($root, 'Application/Controller/ProductorController.php');
$endpoint = contents($root, 'Public/api/productores.php');
$javascript = contents($root, 'Public/js/productores.js');
$configuration = contents($root, 'Configuration/Configuration.php');
$database = contents($root, 'Configuration/Database.php');
$compose = contents($root, 'compose.yaml');
$schema = implode("\n", array_map(
    static fn (string $path): string => contents($root, 'Database/SqlScripts/' . basename($path)),
    glob($root . '/Database/SqlScripts/*.sql') ?: [],
));
$seeds = implode("\n", array_map(
    static fn (string $path): string => contents($root, 'Database/SeedData/' . basename($path)),
    glob($root . '/Database/SeedData/*.sql') ?: [],
));

foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
    gate(str_contains($controller, "'{$method}'"), "ProductorController no implementa {$method}.");
}
foreach (['success', 'message', 'data'] as $key) {
    gate(str_contains($controller, "'{$key}'"), "El contrato JSON no contiene {$key}.");
}
gate(str_contains($endpoint, "'application/json'"), 'El endpoint no exige application/json.');
gate(str_contains($configuration, 'Content-Type: application/json; charset=utf-8'), 'Las respuestas no fijan JSON UTF-8.');
gate(str_contains($configuration, 'JSON_THROW_ON_ERROR'), 'La lectura de JSON no rechaza sintaxis inválida de forma determinista.');
gate(str_contains($javascript, "const API_URL = 'api/productores.php'"), 'JavaScript no usa el endpoint oficial en español.');
gate(!preg_match('/\.innerHTML\s*=/u', $javascript), 'JavaScript inserta contenido mediante innerHTML.');
gate(str_contains($javascript, '.textContent'), 'JavaScript no evidencia inserción segura mediante textContent.');
gate(str_contains($database, 'PDO::ATTR_EMULATE_PREPARES => false'), 'PDO debe desactivar consultas preparadas emuladas.');
gate(!preg_match('/DB_(?:PASS|ROOT_PASS)\s*=\s*["\'][^"\']+["\']/u', $database), 'Hay una credencial de base de datos incrustada en PHP.');

$tables = ['tbparticipante', 'tbrol', 'tbparticipanterol', 'tbidentificaciontipo',
    'tbparticipanteidentificacion', 'tbparticipantedireccion', 'tbfinca', 'tbproductorfinca', 'tbbitacora'];
foreach ($tables as $table) {
    gate(preg_match('/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\b/u', $schema) === 1, "Falta la tabla {$table}.");
}
gate(!preg_match('/CREATE TABLE IF NOT EXISTS\s+(?:producers|productores)\b/iu', $schema), 'El esquema conserva una tabla personal de productores.');
gate(!preg_match('/\b(?:farm_name|created_at|updated_at)\b/iu', $schema), 'El esquema conserva atributos obsoletos del participante.');
gate(substr_count(strtoupper($seeds), 'SET NAMES UTF8MB4') >= 3, 'Cada seed debe declarar SET NAMES utf8mb4 para conservar tildes.');
gate(str_contains($compose, 'mysqladmin ping -h 127.0.0.1'), 'El healthcheck debe usar TCP para no aceptar el servidor temporal de inicialización.');

echo "OK naming_gate: nomenclatura, contrato JSON y controles estáticos de seguridad.\n";
