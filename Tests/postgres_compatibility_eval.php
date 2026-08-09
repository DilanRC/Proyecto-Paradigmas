<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$database = file_get_contents("{$root}/Configuration/Database.php");
$locks = file_get_contents("{$root}/Application/Model/NamedLock.php");
$checks = [
    'supabase_no_pooling_primero' => strpos($database, "'POSTGRES_URL_NON_POOLING'")
        < strpos($database, "'POSTGRES_URL'"),
    'fallback_mysql' => str_contains($database, 'mysql:host=%s'),
    'driver_postgresql' => str_contains($database, 'pgsql:host=%s'),
    'tls_configurable' => str_contains($database, "\$query['sslmode']"),
    'bloqueo_postgresql' => str_contains($locks, 'pg_advisory_lock'),
    'desbloqueo_postgresql' => str_contains($locks, 'pg_advisory_unlock'),
    'bloqueo_mysql' => str_contains($locks, 'GET_LOCK'),
    'timeout_postgresql' => str_contains($locks, 'statement_timeout'),
];
$score = (int) round(100 * count(array_filter($checks)) / count($checks));
echo json_encode(['eval' => 'mysql_postgresql_compatibility', 'score' => $score, 'threshold' => 100,
    'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if ($score < 100) {
    exit(1);
}
