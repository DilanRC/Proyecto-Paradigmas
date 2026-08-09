<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$dockerfile = file_get_contents("{$root}/Dockerfile");
$vercelDockerfile = file_get_contents("{$root}/Dockerfile.vercel");
$entrypoint = file_get_contents("{$root}/docker/apache/container-entrypoint.sh");
$fincaSchema = file_get_contents("{$root}/Database/SqlScripts/004createfinca.sql");

foreach ([$dockerfile, $vercelDockerfile] as $definition) {
    test_assert(str_contains($definition, 'COPY Application /var/www/html/Application'),
        'La imagen debe incluir la aplicación');
    test_assert(str_contains($definition, 'COPY Configuration /var/www/html/Configuration'),
        'La imagen debe incluir la configuración');
    test_assert(str_contains($definition, 'COPY Public /var/www/html/Public'),
        'La imagen debe incluir el document root');
    test_assert(str_contains($definition, 'CMD ["tindercows-entrypoint"]'),
        'La imagen debe iniciar mediante el adaptador de puerto');
}

test_assert(str_contains($entrypoint, '${PORT:-80}'), 'El contenedor debe respetar PORT y usar 80 localmente');
test_assert(str_contains($entrypoint, 'exec apache2-foreground'), 'Apache debe quedar como proceso principal');
test_assert(str_contains($fincaSchema, 'CREATE TABLE IF NOT EXISTS tbfinca'),
    'El esquema debe crear tbfinca de forma idempotente');

echo "OK deployment_test: imágenes autocontenidas, puerto configurable y tbfinca idempotente.\n";
