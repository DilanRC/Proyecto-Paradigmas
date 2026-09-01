<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$dockerfile = file_get_contents("{$root}/Dockerfile");
$vercelDockerfile = file_get_contents("{$root}/Dockerfile.vercel");
$entrypoint = file_get_contents("{$root}/docker/apache/container-entrypoint.sh");
$databaseSchema = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$compose = file_get_contents("{$root}/compose.yaml");
$environmentExample = file_get_contents("{$root}/.env.example");
$databaseConfiguration = file_get_contents("{$root}/Configuration/Database.php");
$vercelIgnoreBuild = file_get_contents("{$root}/Tools/vercel-ignore-build.sh");
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
test_same(false, $vercelConfiguration['git']['deploymentEnabled']['*'] ?? null,
    'Vercel debe bloquear automáticamente cualquier rama no autorizada');
test_same(true, $vercelConfiguration['git']['deploymentEnabled']['dev'] ?? null,
    'Vercel debe crear previews automáticos únicamente desde dev');
test_same(true, $vercelConfiguration['git']['deploymentEnabled']['main'] ?? null,
    'Vercel debe conservar los despliegues de producción desde main');
test_same('bash Tools/vercel-ignore-build.sh', $vercelConfiguration['services']['app']['ignoreCommand'] ?? null,
    'El servicio Vercel debe aplicar la política de ramas antes de construir');
test_assert(!array_key_exists('ignoreCommand', $vercelConfiguration),
    'ignoreCommand no puede ser global cuando vercel.json declara services');
test_assert(str_contains($vercelIgnoreBuild, '"${VERCEL_ENV:-}" == "production"'),
    'La política debe conservar los despliegues de producción');
test_assert(str_contains($vercelIgnoreBuild, '"${VERCEL_GIT_COMMIT_REF:-}" == "dev"'),
    'La política debe permitir previews únicamente desde dev');
test_assert(str_contains($compose, 'phpmyadmin:5.2.2-apache'),
    'Compose debe ofrecer phpMyAdmin para inspeccionar MySQL');
test_assert(str_contains($compose, 'PMA_HOST: db'),
    'phpMyAdmin debe apuntar al servicio MySQL interno');
test_assert(!str_contains($compose, 'adminer:'), 'Adminer debe ser reemplazado por phpMyAdmin');
test_assert(str_contains($environmentExample, 'DB_NAME=bdmercadoganadero'),
    'El entorno de ejemplo debe usar el nuevo nombre de base');
test_assert(str_contains($environmentExample, 'DB_HOST_PORT=3309'),
    'MySQL debe usar el puerto local documentado');
test_assert(str_contains($databaseConfiguration, "'bdmercadoganadero'"),
    'El fallback de conexión debe usar el nuevo nombre de base');

echo "OK deployment_test: despliegue por rama, phpMyAdmin y base bdmercadoganadero configurados.\n";
