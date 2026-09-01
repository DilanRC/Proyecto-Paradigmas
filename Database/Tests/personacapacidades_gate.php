<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/SqlScripts/000instalacioncompleta.sql');
$migration = file_get_contents($root . '/Migrations/003personacapacidades.sql');
$seed = file_get_contents($root . '/SeedData/103exampleproductores.sql');
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$check($schema !== false && $migration !== false && $seed !== false, 'No se pudieron leer los artefactos SQL.');
$check(substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 27, 'El esquema debe crear 27 tablas.');
$check(str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbpersona'), 'Falta tbpersona.');
foreach (['productor', 'comprador', 'transportista'] as $profile) {
    // tbproductor cierra en tbpersonaid: su estado vive en el histórico de periodos.
    $check((bool) preg_match("/tb{$profile}id INT NOT NULL,\\s*\\n\\s*tbpersonaid INT NOT NULL/", $schema),
        "El perfil {$profile} no referencia tbpersona.");
    $check(!str_contains($schema, "tb{$profile}identificacionnumero"),
        "El perfil {$profile} todavía duplica identificación.");
}
$check(strpos($migration, 'MIGRACION_ABORTADA_CAPACIDAD_DUPLICADA')
    < strpos($migration, 'CREATE TABLE tbpersona'), 'La validación de duplicados debe ocurrir antes del DDL.');
$check(str_contains($migration, "GET_LOCK('bdmercadoganadero:migrar-persona-capacidades'"), 'Falta el lock de migración.');
$check(str_contains($migration, 'IF(@migracion_lock = 1'), 'La migración debe comprobar que adquirió el lock.');
$check(!preg_match('/CREATE\s+(PROCEDURE|FUNCTION|TRIGGER|EVENT)/i', $migration), 'La migración no debe crear objetos programables.');
$check(str_contains($migration, 'PREPARE preflight FROM @preflight_sql'), 'Falta el aborto pre-DDL de sesión.');
$check(strpos($migration, 'ALTER TABLE tbproductor ADD COLUMN tbpersonaid')
    > strpos($migration, 'MIGRACION_ABORTADA_DATOS_PERSONALES_INCOMPATIBLES'), 'El ALTER ocurre antes de validar datos personales.');
$check(str_contains($seed, 'INSERT INTO tbpersona'), 'El seed debe poblar tbpersona.');
foreach (['PRIMARY KEY', 'FOREIGN KEY', 'CREATE INDEX', ' UNIQUE ', ' DEFAULT ', 'CREATE TRIGGER'] as $forbidden) {
    $check(!str_contains(strtoupper($schema), $forbidden), "El esquema contiene {$forbidden}.");
}
echo "OK persona capacidades MySQL: conflicto pre-DDL, cero rutinas y columnas intactas.\n";
