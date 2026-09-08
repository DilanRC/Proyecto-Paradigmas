<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once "{$root}/Tools/schema-manifest.php";

$required = [
    'Database/SqlScripts/000instalacioncompleta.sql',
    'Database/Migrations/007personaaliastelefonohistorico.sql',
    'Application/Model/Persona.php',
    'Application/Model/PersonaTelefonoHistorico.php',
    'Application/Model/Productor.php',
    'Public/api/productores.php',
    'Public/api/transportistas.php',
];
foreach ($required as $file) {
    if (!is_file("{$root}/{$file}")) {
        throw new RuntimeException("Falta {$file}");
    }
}

$sql = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$manifest = schema_manifest();

$requiredTables = [
    'tbpersona',
    'tbproductor',
    'tbcomprador',
    'tbproductorpersonatelefonohistorico',
    'tbcompradorpersonatelefonohistorico',
];
foreach ($requiredTables as $table) {
    if (!in_array($table, $manifest['tables_sorted'], true)) {
        throw new RuntimeException("Falta tabla requerida por Calidad: {$table}");
    }
}

foreach (['PRIMARY KEY', 'FOREIGN KEY', 'CHECK (', 'CONSTRAINT ', 'REFERENCES ',
    'AUTO_INCREMENT', 'CREATE INDEX', 'CREATE UNIQUE INDEX', 'UNIQUE KEY', 'UNIQUE ('] as $forbiddenSql) {
    if (str_contains($sql, $forbiddenSql)) {
        throw new RuntimeException("El esquema no puede contener {$forbiddenSql}");
    }
}
foreach (['DEFAULT ', 'CURRENT_TIMESTAMP', 'CREATE TRIGGER', 'CREATE PROCEDURE',
    'CREATE FUNCTION', 'CREATE EVENT'] as $engineLogic) {
    if (str_contains($sql, $engineLogic)) {
        throw new RuntimeException("La lógica no puede delegarse al motor mediante {$engineLogic}");
    }
}

foreach ([
    'tbpersonaalias VARCHAR(150) NULL' => 'Persona debe registrar alias opcional',
    'tbproductorpersonatelefonohistoricoid INT NOT NULL' => 'histórico de teléfono del Productor',
    'tbproductorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL' => 'nuevo teléfono del Productor',
    'tbproductorpersonatelefonohistoricofecha DATETIME NOT NULL' => 'fecha del histórico del Productor',
    'tbcompradorpersonatelefonohistoricoid INT NOT NULL' => 'histórico de teléfono del Comprador',
    'tbcompradorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL' => 'nuevo teléfono del Comprador',
    'tbcompradorpersonatelefonohistoricofecha DATETIME NOT NULL' => 'fecha del histórico del Comprador',
] as $fragment => $reason) {
    if (!str_contains($sql, $fragment)) {
        throw new RuntimeException("Falta {$fragment}: {$reason}");
    }
}

foreach ([
    'tbproductorpersonatelefonohistoricoestado',
    'tbcompradorpersonatelefonohistoricoestado',
    'tbproductorpersonatelefonohistoricofechafin',
    'tbcompradorpersonatelefonohistoricofechafin',
] as $forbiddenHistoricalField) {
    if (str_contains($sql, $forbiddenHistoricalField)) {
        throw new RuntimeException("El histórico de teléfono no debe contener {$forbiddenHistoricalField}");
    }
}

$persona = file_get_contents("{$root}/Application/Model/Persona.php");
$historico = file_get_contents("{$root}/Application/Model/PersonaTelefonoHistorico.php");
foreach (['tbpersonaalias', 'registrarCambio', "gmdate('Y-m-d H:i:s')"] as $fragment) {
    if (!str_contains($persona, $fragment)) {
        throw new RuntimeException("Persona no aplica el contrato nuevo: falta {$fragment}");
    }
}
foreach (['tbproductorpersonatelefonohistorico', 'tbcompradorpersonatelefonohistorico',
    '->prepare(', 'MAX(tbproductorpersonatelefonohistoricoid)',
    'MAX(tbcompradorpersonatelefonohistoricoid)'] as $fragment) {
    if (!str_contains($historico, $fragment)) {
        throw new RuntimeException("El modelo histórico no aplica el contrato: falta {$fragment}");
    }
}
if (str_contains($historico, '->query(') || str_contains($historico, '->exec(')) {
    throw new RuntimeException('El histórico de teléfono debe usar sentencias preparadas.');
}

foreach (['Public/api/productores.php', 'Public/api/transportistas.php'] as $api) {
    $content = file_get_contents("{$root}/{$api}");
    if (!str_contains($content, 'PersonaTelefonoHistorico')) {
        throw new RuntimeException("{$api} debe cargar PersonaTelefonoHistorico antes de Persona.");
    }
}

preg_match_all('/(?:CREATE DATABASE(?: IF NOT EXISTS)?|ALTER DATABASE|USE)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $bases);
$nombresBase = array_values(array_unique($bases[1]));
if ($nombresBase !== ['bdmercadoganadero']) {
    throw new RuntimeException('CREATE, ALTER y USE deben referirse únicamente a bdmercadoganadero.');
}

if (preg_match('/\btb[a-z0-9]*[A-Z][A-Za-z0-9]*/', $sql, $coincidencia)) {
    throw new RuntimeException("Identificador camelCase prohibido en SQL: {$coincidencia[0]}");
}

echo "OK naming_gate: persona con alias, históricos de teléfono por contexto, cero lógica de motor y sentencias preparadas.\n";
