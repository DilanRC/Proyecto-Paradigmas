<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dockerfile = file_get_contents("{$root}/Dockerfile");
$vercelDockerfile = file_get_contents("{$root}/Dockerfile.vercel");
$entrypoint = file_get_contents("{$root}/docker/apache/container-entrypoint.sh");
$readme = file_get_contents("{$root}/README.md");
$vercelConfiguration = file_get_contents("{$root}/vercel.json");

$checks = [
    'imagen_compose_autocontenida' => str_contains($dockerfile, 'COPY Public /var/www/html/Public'),
    'imagen_vercel_detectable' => str_contains($vercelDockerfile, 'FROM php:8.3-apache'),
    'puerto_vercel' => str_contains($entrypoint, '${PORT:-80}'),
    'apache_proceso_principal' => str_contains($entrypoint, 'exec apache2-foreground'),
    'migracion_supabase' => str_contains($entrypoint, 'services/supabase-database/migrate.php'),
    'servicio_vercel_explicito' => str_contains($vercelConfiguration, '"entrypoint": "Dockerfile.vercel"'),
    'procedimiento_tbfinca' => str_contains($readme, 'Aplicar `tbfinca` a una base existente'),
    'verificacion_dominio' => str_contains($readme, 'curl -fsS https://tindervacas.dpdns.org/'),
];
$passed = count(array_filter($checks));
$score = (int) round(100 * $passed / count($checks));

echo json_encode([
    'eval' => 'despliegue_frontend_y_tbfinca',
    'score' => $score,
    'threshold' => 100,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($score < 100) {
    exit(1);
}
