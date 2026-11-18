<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$dockerfile = file_get_contents("{$root}/Dockerfile");
$vercelDockerfile = file_get_contents("{$root}/Dockerfile.vercel");
$entrypoint = file_get_contents("{$root}/docker/apache/container-entrypoint.sh");
$phpConfiguration = file_get_contents("{$root}/docker/php-errores.ini");
$requestMigrator = file_get_contents("{$root}/services/supabase-database/migrate-request.php");
$supabaseContract = file_get_contents("{$root}/services/supabase-database/schema-contract.php");
$supabaseMigrator = ($supabaseContract ?: '') . "\n" . file_get_contents("{$root}/services/supabase-database/migrate.php");
$databaseSchema = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$compose = file_get_contents("{$root}/compose.yaml");
$environmentExample = file_get_contents("{$root}/.env.example");
$databaseConfiguration = file_get_contents("{$root}/Configuration/Database.php");
$vercelPruneWorkflow = file_get_contents("{$root}/.github/workflows/vercel-prune-registry.yml");
$publicHtaccess = file_get_contents("{$root}/Public/.htaccess");
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
test_assert(str_contains($phpConfiguration, 'auto_prepend_file = /var/www/html/services/supabase-database/migrate-request.php'),
    'Solo las solicitudes PHP deben validar el esquema Supabase antes de atenderlas');
test_assert(!str_contains($entrypoint, 'services/supabase-database/migrate.php'),
    'El arranque no debe abrir conexiones PostgreSQL para servir archivos estáticos');
test_assert(str_contains($requestMigrator, "getenv('POSTGRES_URL')")
    && str_contains($requestMigrator, "getenv('POSTGRES_URL_NON_POOLING')")
    && str_contains($requestMigrator, "if (PHP_SAPI === 'cli')")
    && str_contains($requestMigrator, 'sys_get_temp_dir()')
    && str_contains($requestMigrator, 'tindercows-schema-v10.ready')
    && str_contains($requestMigrator, 'tindercows-schema-v10.lock')
    && str_contains($requestMigrator, 'flock($lock, LOCK_EX | LOCK_NB)')
    && str_contains($requestMigrator, '10_000_000_000')
    && str_contains($requestMigrator, '$schemaIsChecked()')
    && str_contains($requestMigrator, 'file_put_contents($readyMarker')
    && str_contains($requestMigrator, "require __DIR__ . '/migrate.php'"),
    'El migrador por solicitud debe ejecutarse solo cuando Vercel entregue una URL PostgreSQL');
test_assert(strpos($requestMigrator, "require __DIR__ . '/migrate.php'")
    < strpos($requestMigrator, 'file_put_contents($readyMarker'),
    'La marca por contenedor solo se escribe después de terminar la migración');
test_assert(str_contains($supabaseMigrator, 'return false;') && str_contains($supabaseMigrator, 'return true;'),
    'La migración debe distinguir un esquema degradado de uno listo para compartir el resultado por contenedor');
test_assert(strpos($supabaseMigrator, "['POSTGRES_URL_NON_POOLING', 'POSTGRES_URL']") !== false,
    'El migrador debe preferir la conexión directa para no ocupar el pool de sesiones');
test_assert(str_contains($supabaseMigrator, "require_once __DIR__ . '/schema-contract.php';"),
    'El migrador debe cargar el contrato e introspección de esquema separados');
test_assert(strpos($supabaseMigrator, 'if (schemaIsReady($connection, $schema))') !== false
    && strpos($supabaseMigrator, '$connection->exec($schema);') !== false
    && strpos($supabaseMigrator, 'if (schemaIsReady($connection, $schema))')
        < strpos($supabaseMigrator, '$connection->exec($schema);'),
    'La migración debe evitar repetir DDL si el esquema compartido ya está listo');
$degradedPathStart = strpos($supabaseMigrator, '$llavesPendientes = unrepairablePrimaryKeyDifferences($connection, $schema)');
$degradedPathEnd = strpos($supabaseMigrator, '$connection->exec($schema);', $degradedPathStart);
test_assert($degradedPathStart !== false && $degradedPathEnd !== false
    && $degradedPathStart < $degradedPathEnd
    && str_contains(substr($supabaseMigrator, $degradedPathStart, $degradedPathEnd - $degradedPathStart), 'reportPendingPrimaryKeys')
    && str_contains($supabaseMigrator, 'function unrepairablePrimaryKeyDifferences(PDO $connection, string $schema)')
    && str_contains($supabaseMigrator, 'function reportPendingPrimaryKeys(array $tables): void'),
    'Las réplicas con solo llaves primarias no reparables deben informar degraded sin repetir DDL');
test_assert(str_contains($supabaseMigrator, '&& schemaHasExpectedNullability($connection, $schema)')
    && str_contains($supabaseMigrator, 'if (!schemaHasRequiredRowSecurity($connection))')
    && str_contains($supabaseMigrator, 'if (!schemaHasExpectedNullability($connection, $schema))')
    && str_contains($supabaseMigrator, 'ensureExpectedNullability($connection, $schema)'),
    'La migración rápida debe revisar la nulabilidad de schema.sql y la completa repararla antes de confirmar');
$fastPathStart = strpos($supabaseMigrator, 'if (schemaIsReady($connection, $schema))');
$fastPathEnd = strpos($supabaseMigrator, '$connection->exec($schema);', $fastPathStart);
$fastPath = substr($supabaseMigrator, $fastPathStart, $fastPathEnd - $fastPathStart);
test_assert(!str_contains($fastPath, "NOTIFY pgrst, 'reload schema'"),
    'El fast path no debe recargar PostgREST si no ejecuta DDL');
test_assert(str_contains($supabaseMigrator, "if (PHP_SAPI === 'cli') {")
    && str_contains($supabaseMigrator, 'exit(1);')
    && str_contains($supabaseMigrator, 'http_response_code(503)'),
    'Una falla de esquema debe mantener el error CLI y responder 503 en solicitudes web');
test_assert(str_contains($publicHtaccess, 'HTTP_AUTHORIZATION:%{HTTP:Authorization}'),
    'Apache debe conservar el Bearer para que PHP resuelva la sesión');
test_assert(str_contains($databaseSchema, 'CREATE TABLE IF NOT EXISTS tbfinca'),
    'El esquema debe crear tbfinca de forma idempotente');
test_assert(str_contains($databaseSchema, 'CREATE TABLE IF NOT EXISTS tbcomprador'),
    'El esquema debe crear tbcomprador de forma idempotente');
test_same('Dockerfile.vercel', $vercelConfiguration['services']['app']['entrypoint'] ?? null,
    'Vercel debe construir explícitamente el contenedor');
test_same(['service' => 'app'], $vercelConfiguration['rewrites'][0]['destination'] ?? null,
    'Vercel debe dirigir todo el tráfico al contenedor');
test_same(true, $vercelConfiguration['git']['deploymentEnabled']['dev'] ?? null,
    'Vercel debe crear previews automáticos únicamente desde dev');
test_same(true, $vercelConfiguration['git']['deploymentEnabled']['main'] ?? null,
    'Vercel debe conservar los despliegues de producción desde main');
test_same(false, $vercelConfiguration['git']['deploymentEnabled']['**'] ?? null,
    'Las ramas no listadas se bloquean con "**": sin esa regla Vercel las despliega por omisión');
test_assert(!str_contains(json_encode($vercelConfiguration), 'ignoreCommand'),
    'ignoreCommand no se ejecuta con services: la política de ramas vive en deploymentEnabled');
test_assert(str_contains($vercelPruneWorkflow, 'branches: [dev, main]'),
    'La poda debe correr en cada push a las ramas que empujan imagen');
test_assert(str_contains($vercelPruneWorkflow, 'bash Tools/vercel-prune-registry.sh --conservar'),
    'El workflow debe reutilizar la poda determinista del registro');
test_assert(str_contains($vercelPruneWorkflow, 'VERCEL_TOKEN: ${{ secrets.VERCEL_TOKEN }}'),
    'El token de Vercel debe llegar como secreto, nunca escrito en el repositorio');
test_assert(str_contains($vercelPruneWorkflow, 'environment: vercel-registry'),
    'El secreto debe vivir en el entorno limitado a dev y main');
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
