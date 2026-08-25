<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dockerfile = file_get_contents("{$root}/Dockerfile");
$vercelDockerfile = file_get_contents("{$root}/Dockerfile.vercel");
$entrypoint = file_get_contents("{$root}/docker/apache/container-entrypoint.sh");
$readme = file_get_contents("{$root}/README.md");
$vercelConfiguration = file_get_contents("{$root}/vercel.json");
$compose = file_get_contents("{$root}/compose.yaml");
$environmentExample = file_get_contents("{$root}/.env.example");
$databaseConfiguration = file_get_contents("{$root}/Configuration/Database.php");
$vercelIgnoreBuild = file_get_contents("{$root}/Tools/vercel-ignore-build.sh");

$checks = [
    'imagen_compose_autocontenida' => str_contains($dockerfile, 'COPY Public /var/www/html/Public'),
    'imagen_vercel_detectable' => str_contains($vercelDockerfile, 'FROM php:8.3-apache'),
    'puerto_vercel' => str_contains($entrypoint, '${PORT:-80}'),
    'apache_proceso_principal' => str_contains($entrypoint, 'exec apache2-foreground'),
    'apache_sin_warning_servername' => str_contains($dockerfile, 'a2enconf servername')
        && str_contains($vercelDockerfile, 'a2enconf servername'),
    'migracion_supabase' => str_contains($entrypoint, 'services/supabase-database/migrate.php'),
    'servicio_vercel_explicito' => str_contains($vercelConfiguration, '"entrypoint": "Dockerfile.vercel"'),
    'preview_solo_dev' => str_contains($vercelConfiguration, '"app"')
        && str_contains($vercelConfiguration, 'Tools/vercel-ignore-build.sh')
        && str_contains($vercelIgnoreBuild, 'VERCEL_GIT_COMMIT_REF:-}" == "dev"')
        && str_contains($vercelIgnoreBuild, 'VERCEL_ENV:-}" == "production"'),
    'phpmyadmin_local' => str_contains($compose, 'phpmyadmin:5.2.2-apache')
        && str_contains($compose, 'PMA_HOST: db')
        && str_contains($readme, 'phpMyAdmin: <http://localhost:8081>'),
    'nombre_db_mercado_ganadero' => str_contains($environmentExample, 'DB_NAME=dbmercadoganadero')
        && str_contains($databaseConfiguration, "'dbmercadoganadero'")
        && str_contains($readme, 'Base MySQL: `dbmercadoganadero`')
        && str_contains($environmentExample, 'DB_HOST_PORT=3309'),
    'procedimiento_tbfinca' => str_contains($readme, '### Aplicar el esquema a una base existente')
        && str_contains($readme, 'por ejemplo `tbfinca`'),
    'procedimiento_tbcomprador' => str_contains($readme, '`tbcomprador` o las del avance de direcciones'),
    'verificacion_dominio' => str_contains($readme, 'curl -fsS https://tindervacas.dpdns.org/'),
];
$passed = count(array_filter($checks));
$score = (int) round(100 * $passed / count($checks));

echo json_encode([
    'eval' => 'despliegue_frontend_y_esquema',
    'score' => $score,
    'threshold' => 100,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($score < 100) {
    exit(1);
}
