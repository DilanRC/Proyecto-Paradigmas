<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Tools/schema-manifest.php';

$root = dirname(__DIR__);
$schema = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$migration = file_get_contents("{$root}/Database/Migrations/006estructuracomercialhistorica.sql");
$diagnostico = file_get_contents("{$root}/Database/Tests/diagnostico.sql");
$decisiones = file_get_contents("{$root}/Documentation/Decisiones.md");
$diccionario = file_get_contents("{$root}/Documentation/DiccionarioDatos.md");
$der = file_get_contents("{$root}/Documentation/DER.md");
$manifest = schema_manifest();

test_same('bdmercadoganadero', $manifest['database'], 'La base activa debe ser bdmercadoganadero');
test_same(27, $manifest['table_count'], 'La capa DB ready debe contener 27 tablas');

foreach (['tbproductorclasificacionperiodo', 'tbanimal', 'tbanimalobservacion', 'tbanimalpublicacion',
    'tbcompra', 'tbventa', 'tbanimalinteraccion', 'tbcarrito', 'tbcarritoanimal',
    'tbtransportistaestadoperiodo', 'tbtransportistaflete', 'tbtransportistaresena'] as $table) {
    test_assert(in_array($table, $manifest['tables_sorted'], true), "Falta {$table} en el SQL canónico");
    test_assert(str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"), "Falta {$table} en la migración");
    test_assert(str_contains($diagnostico, $table), "Falta diagnóstico para {$table}");
}

foreach (['INSERT INTO', 'UPDATE ', 'DELETE FROM', 'ALTER TABLE tbcomprador'] as $forbidden) {
    test_assert(!str_contains($migration, $forbidden), "La migración 006 no debe ejecutar {$forbidden}");
}

foreach (['CREATE TABLE IF NOT EXISTS tbvendedor', 'tbcompradorestadoperiodo',
    'tbvendedorestadoperiodo', 'tbvendedoractividad', 'tbanimalfechanacimiento',
    'tbcompraestado', 'cantidadfletessemanales', 'metodopagofrecuente',
    'calificacionpromedio'] as $forbidden) {
    test_assert(!str_contains($schema, $forbidden), "El esquema no debe contener {$forbidden}");
}

test_assert(str_contains($schema, 'tbcompraid INT NULL'), 'tbventa debe permitir tbcompraid NULL');
test_assert(substr_count($schema, 'CREATE TABLE IF NOT EXISTS tbcomprador') === 1,
    'tbcomprador se conserva legacy sin tablas satélite');
test_assert(str_contains($decisiones, 'tbcomprador` se conserva solo como legacy')
    && str_contains($diccionario, 'Estructura legacy de compatibilidad')
    && str_contains($der, 'tbcomprador : "legacy por tbpersonaid"'),
    'Documentación debe marcar tbcomprador como legacy');
test_assert(str_contains($decisiones, 'locks en Backend')
    && str_contains($decisiones, 'MAX(id)+1')
    && str_contains($decisiones, 'lock global'),
    'Decisiones debe dejar contrato de IDs y locks para Backend');

echo "OK db_ready_test: esquema comercial histórico preparado sin pasado inventado ni tbvendedor.\n";
