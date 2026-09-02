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
    'apache_sin_warning_servername' => str_contains($dockerfile, 'a2enconf servername')
        && str_contains($vercelDockerfile, 'a2enconf servername'),
    'migracion_supabase' => str_contains($entrypoint, 'services/supabase-database/migrate.php'),
    'servicio_vercel_explicito' => str_contains($vercelConfiguration, '"entrypoint": "Dockerfile.vercel"'),
    'preview_solo_dev' => str_contains($vercelConfiguration, '"ignoreCommand": "bash Tools/vercel-ignore-build.sh"')
        && !str_contains($vercelConfiguration, '"*": false')
        && str_contains(file_get_contents("{$root}/Tools/vercel-ignore-build.sh"), 'VERCEL_GIT_COMMIT_REF:-}" == "dev"'),
    'registro_con_poda' => is_file("{$root}/Tools/vercel-prune-registry.sh")
        && is_file("{$root}/Tools/vercel-prune-registry.php")
        && str_contains(file_get_contents("{$root}/Tools/vercel-prune-registry.sh"), 'vcr image rm')
        && str_contains(file_get_contents("{$root}/Tools/vercel-prune-registry.php"), 'function vercel_registry_borrables')
        && str_contains($readme, 'Tools/vercel-prune-registry.sh'),
    'procedimiento_tbfinca' => str_contains($readme, 'Aplicar `tbfinca` a una base existente'),
    'procedimiento_tbcomprador' => str_contains($readme, 'Aplicar `tbcomprador` a una base existente'),
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
