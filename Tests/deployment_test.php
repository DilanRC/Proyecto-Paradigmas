<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$dockerfile = file_get_contents("{$root}/Dockerfile");
$vercelDockerfile = file_get_contents("{$root}/Dockerfile.vercel");
$entrypoint = file_get_contents("{$root}/docker/apache/container-entrypoint.sh");
$databaseSchema = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$vercelConfiguration = json_decode(file_get_contents("{$root}/vercel.json"), true, 512, JSON_THROW_ON_ERROR);

foreach ([$dockerfile, $vercelDockerfile] as $definition) {
    test_assert(str_contains($definition, 'COPY Application /var/www/html/Application'),
        'La imagen debe incluir la aplicación');
    test_assert(str_contains($definition, 'COPY Configuration /var/www/html/Configuration'),
        'La imagen debe incluir la configuración');
    test_assert(str_contains($definition, 'COPY Public /var/www/html/Public'),
        'La imagen debe incluir el document root');
    test_assert(str_contains($definition, 'pdo_pgsql'), 'La imagen debe incluir el controlador PostgreSQL');
    test_assert(str_contains($definition, 'COPY services/supabase-database'),
        'La imagen debe incluir la migración Supabase');
    test_assert(str_contains($definition, 'a2enconf servername'),
        'Apache debe tener ServerName global para evitar advertencias operativas');
    test_assert(str_contains($definition, 'CMD ["tindercows-entrypoint"]'),
        'La imagen debe iniciar mediante el adaptador de puerto');
}

test_assert(str_contains($entrypoint, '${PORT:-80}'), 'El contenedor debe respetar PORT y usar 80 localmente');
test_assert(str_contains($entrypoint, 'exec apache2-foreground'), 'Apache debe quedar como proceso principal');
test_assert(str_contains($entrypoint, 'services/supabase-database/migrate.php'),
    'El arranque debe validar el esquema Supabase');
test_assert(str_contains($databaseSchema, 'CREATE TABLE IF NOT EXISTS tbfinca'),
    'El esquema debe crear tbfinca de forma idempotente');
test_assert(str_contains($databaseSchema, 'CREATE TABLE IF NOT EXISTS tbcomprador'),
    'El esquema debe crear tbcomprador de forma idempotente');
test_same('Dockerfile.vercel', $vercelConfiguration['services']['app']['entrypoint'] ?? null,
    'Vercel debe construir explícitamente el contenedor');
test_same(['service' => 'app'], $vercelConfiguration['rewrites'][0]['destination'] ?? null,
    'Vercel debe dirigir todo el tráfico al contenedor');

$vercelIgnoreBuild = file_get_contents("{$root}/Tools/vercel-ignore-build.sh");

test_same(true, $vercelConfiguration['git']['deploymentEnabled']['dev'] ?? null,
    'Vercel debe crear previews automáticos únicamente desde dev');
test_same(true, $vercelConfiguration['git']['deploymentEnabled']['main'] ?? null,
    'Vercel debe conservar los despliegues de producción desde main');
test_assert(!array_key_exists('*', $vercelConfiguration['git']['deploymentEnabled'] ?? []),
    'deploymentEnabled no admite comodines: "*" no bloquea nada y deja construir cualquier rama');
test_same('bash Tools/vercel-ignore-build.sh', $vercelConfiguration['ignoreCommand'] ?? null,
    'ignoreCommand debe ser global: dentro de services Vercel no lo ejecuta y toda rama empuja imagen');
test_assert(!array_key_exists('ignoreCommand', $vercelConfiguration['services']['app'] ?? []),
    'El servicio no debe declarar ignoreCommand propio');
test_assert(str_contains($vercelIgnoreBuild, '"${VERCEL_ENV:-}" == "production"'),
    'La política debe conservar los despliegues de producción');
test_assert(str_contains($vercelIgnoreBuild, '"${VERCEL_GIT_COMMIT_REF:-}" == "dev"'),
    'La política debe permitir previews únicamente desde dev');

// La política de ramas es determinista: se ejecuta, no se infiere del texto.
$politica = static function (array $entorno) use ($root): int {
    $asignaciones = '';
    foreach ($entorno as $clave => $valor) {
        $asignaciones .= sprintf('%s=%s ', $clave, escapeshellarg($valor));
    }
    exec(sprintf('cd %s && %sbash Tools/vercel-ignore-build.sh > /dev/null 2>&1',
        escapeshellarg($root), $asignaciones), $salida, $codigo);

    return $codigo;
};

test_same(1, $politica(['VERCEL_ENV' => 'production', 'VERCEL_GIT_COMMIT_REF' => 'main']),
    'main en producción debe construir');
test_same(1, $politica(['VERCEL_ENV' => 'preview', 'VERCEL_GIT_COMMIT_REF' => 'dev']),
    'dev debe construir su preview');
test_same(0, $politica(['VERCEL_ENV' => 'preview', 'VERCEL_GIT_COMMIT_REF' => 'feat/explore-mode']),
    'una rama de trabajo no debe construir ni empujar imagen al registro');
test_same(0, $politica([]), 'sin entorno Vercel la política debe omitir la construcción');

echo "OK deployment_test: imágenes autocontenidas, puerto configurable y tablas idempotentes.\n";