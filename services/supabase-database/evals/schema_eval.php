<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents("{$root}/schema.sql");
$migration = file_get_contents("{$root}/migrate.php");
$entrypoint = file_get_contents(dirname(__DIR__, 3) . '/docker/apache/container-entrypoint.sh');
$checks = [
    'veintisiete_tablas' => substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 27,
    'persona_compartida' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbpersona')
        && !preg_match('/tb(productor|comprador|transportista)identificacionnumero/', $schema)
        && str_contains($migration, 'normalizePersonCapabilities($connection)'),
    'direccion_centralizada' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbdireccion')
        && !str_contains($schema, 'tbproductordireccionprovincia')
        && str_contains($migration, 'normalizeProductorAddress($connection)'),
    'transporte_y_pago' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbtransportistavehiculo')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbpagometodo')
        && str_contains($migration, "'Efectivo', 'Pago realizado en efectivo', 1"),
    'incluye_tbfinca' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbfinca'),
    'incluye_tbcomprador' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbcomprador')
        && str_contains($migration, "'tbcomprador' => ["),
    'clasificacion_productor' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbproductorclasificacionperiodo')
        && str_contains($schema, 'tbproductorclasificacionperiodotipo VARCHAR(30) NOT NULL'),
    'animal_observacion' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbanimal')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbanimalobservacion')
        && !str_contains($schema, 'tbanimalfechanacimiento'),
    'compra_venta_hechos' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbcompra')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbventa')
        && str_contains($schema, 'tbcompraid INTEGER NULL'),
    'funnel_carrito' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbanimalinteraccion')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbcarritoanimal'),
    'transporte_historico' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbtransportistaestadoperiodo')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbtransportistaflete')
        && str_contains($schema, 'CREATE TABLE IF NOT EXISTS public.tbtransportistaresena'),
    'sin_tbvendedor' => !str_contains($schema, 'tbvendedor'),
    'sin_automatismos' => !preg_match('/PRIMARY KEY|FOREIGN KEY|DEFAULT |CREATE INDEX|UNIQUE/', $schema),
    'rest_bloqueado_por_rls' => substr_count($schema, 'ENABLE ROW LEVEL SECURITY') === 27,
    'migracion_serializada' => str_contains($migration, 'pg_advisory_xact_lock'),
    'validacion_posterior' => str_contains($migration, 'validateSchema($connection)'),
    'diagnostico_columnas' => str_contains($migration, 'esperado=[%s] actual=[%s]'),
    'orden_columnas_neutro' => substr_count($migration, 'sort($columns)') === 2,
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
