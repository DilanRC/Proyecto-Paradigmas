<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

$postgresUrl = getenv('POSTGRES_URL');
$postgresUrlNonPooling = getenv('POSTGRES_URL_NON_POOLING');
if (($postgresUrl === false || trim($postgresUrl) === '')
    && ($postgresUrlNonPooling === false || trim($postgresUrlNonPooling) === '')) {
    return;
}

$readyMarker = sys_get_temp_dir() . '/tindercows-schema-v10.ready';
$lockPath = sys_get_temp_dir() . '/tindercows-schema-v10.lock';
$schemaIsChecked = static function () use ($readyMarker): bool {
    $state = @file_get_contents($readyMarker);
    return $state === "ready\n" || $state === "degraded\n";
};
if ($schemaIsChecked()) {
    return;
}

$respondUnavailable = static function (string $reason): void {
    error_log('supabase_schema_status=error message=' . $reason);
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'El servicio está temporalmente no disponible. Intente de nuevo.',
        'data' => null,
        'errors' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
};

$lock = fopen($lockPath, 'c');
if ($lock === false) {
    $respondUnavailable('no fue posible abrir el bloqueo local de migración');
}
$lockDeadline = hrtime(true) + 10_000_000_000;
while (!flock($lock, LOCK_EX | LOCK_NB)) {
    if ($schemaIsChecked()) {
        fclose($lock);
        return;
    }
    if (hrtime(true) >= $lockDeadline) {
        fclose($lock);
        $respondUnavailable('se agotó el tiempo de espera del bloqueo local de migración');
    }
    usleep(10_000);
}

if (!$schemaIsChecked()) {
    $migrationReady = require __DIR__ . '/migrate.php';
    $state = $migrationReady ? "ready\n" : "degraded\n";
    if (file_put_contents($readyMarker, $state, LOCK_EX) === false) {
        flock($lock, LOCK_UN);
        fclose($lock);
        $respondUnavailable('no fue posible guardar el estado local de migración');
    }
    if ($migrationReady) {
        error_log('supabase_schema_status=ready tables=48 migration=v10');
    }
}

flock($lock, LOCK_UN);
fclose($lock);
