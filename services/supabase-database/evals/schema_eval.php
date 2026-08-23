<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents("{$root}/schema.sql");
$migration = file_get_contents("{$root}/migrate.php");
$entrypoint = file_get_contents(dirname(__DIR__, 3) . '/docker/apache/container-entrypoint.sh');
$checks = [
    'catorce_tablas' => substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 14,
    'direccion_centralizada' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbdireccion')
        && !str_contains($schema, 'tbproductordireccionprovincia')
        && str_contains($migration, 'normalizeProductorAddress($connection)'),
    'transporte_y_pago' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbtransportistavehiculo')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbpagometodo')
        && str_contains($migration, "'Efectivo', 'Pago realizado en efectivo', 1"),
    'incluye_tbfinca' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbfinca'),
    'incluye_tbcomprador' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbcomprador')
        && str_contains($migration, "'tbcomprador' => ["),
    'sin_automatismos' => !preg_match('/PRIMARY KEY|FOREIGN KEY|DEFAULT |CREATE INDEX|UNIQUE/', $schema),
    'rest_bloqueado_por_rls' => substr_count($schema, 'ENABLE ROW LEVEL SECURITY') === 14,
    'migracion_serializada' => str_contains($migration, 'pg_advisory_xact_lock'),
    'validacion_posterior' => str_contains($migration, 'validateSchema($connection)'),
    'recarga_postgrest' => str_contains($migration, "NOTIFY pgrst, 'reload schema'"),
    'traza_operativa' => str_contains($migration, 'supabase_schema_status=ready'),
    'tls_desde_url' => str_contains($migration, "\$query['sslmode']") && str_contains($migration, "'require'"),
    'arranque_vercel' => str_contains($entrypoint, 'services/supabase-database/migrate.php'),
];
$score = (int) round(100 * count(array_filter($checks)) / count($checks));
echo json_encode(['eval' => 'supabase_database_schema', 'score' => $score, 'threshold' => 100,
    'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if ($score < 100) {
    exit(1);
}
