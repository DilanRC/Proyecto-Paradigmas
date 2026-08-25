<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents("{$root}/schema.sql");
$migration = file_get_contents("{$root}/migrate.php");
if ($schema === false || $migration === false) {
    throw new RuntimeException('Faltan archivos del servicio Supabase.');
}

$tables = ['tbproductor', 'tbproductordireccion', 'tbdireccion', 'tbfinca', 'tbfincadireccion',
    'tbpagometodo', 'tbtransportista', 'tbvehiculo', 'tbtransportistavehiculo', 'tbbitacora',
    'tbcomprador', 'tbproductorestadoperiodo', 'tbproductorubicacion', 'tbproductoractividad'];
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
foreach ($tables as $table) {
    $check(str_contains($schema, "CREATE TABLE IF NOT EXISTS public.{$table}"), "Falta {$table}");
    $check(str_contains($schema, "ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY"), "Falta RLS en {$table}");
}
$check(substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 14, 'Deben existir exactamente catorce CREATE TABLE');
$check(!str_contains($schema, 'tbproductordireccionprovincia'),
    'La ubicación vive solo en tbdireccion, también en el espejo PostgreSQL');
$check(str_contains($migration, 'normalizeProductorAddress($connection)')
    && str_contains($migration, 'ALTER COLUMN tbdireccionid SET NOT NULL'),
    'La migración normaliza tbproductordireccion y deja el enlace obligatorio');
$check(str_contains($migration, "'Efectivo', 'Pago realizado en efectivo', 1"),
    'La migración registra el método de pago inicial');
foreach (['PRIMARY KEY', 'FOREIGN KEY', 'DEFAULT ', 'CREATE INDEX', 'UNIQUE'] as $forbidden) {
    $check(!str_contains($schema, $forbidden), "Constructo SQL prohibido: {$forbidden}");
}
$check(str_contains($migration, 'pg_advisory_xact_lock'), 'Falta serialización de migración');
$check(str_contains($migration, 'validateSchema($connection)'), 'Falta validación posterior');
$check(str_contains($migration, "NOTIFY pgrst, 'reload schema'"), 'Falta recargar el esquema REST de PostgREST');
$check(str_contains($migration, 'supabase_schema_status=ready tables=14 migration=v5'), 'Falta traza operativa');
$check(!str_contains($schema, 'tbproductorestado SMALLINT'),
    'La columna tbproductorestado fue retirada de tbproductor en v5');
$check(str_contains($migration, "'tbcomprador' => ["), 'Falta validar las columnas de tbcomprador');
$check(str_contains($migration, "\$query['sslmode']"), 'La migración debe leer sslmode desde la URL');
$check(str_contains($migration, "? \$query['sslmode'] : 'require'"), 'TLS debe ser obligatorio por defecto');

echo "OK supabase schema_test: catorce tablas idempotentes, RLS, normalización y validación estricta.\n";
