<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$database = file_get_contents("{$root}/Configuration/Database.php");
$locks = file_get_contents("{$root}/Application/Model/NamedLock.php");
$api = file_get_contents("{$root}/Public/api/productores.php");

test_assert(str_contains($database, "['POSTGRES_URL_NON_POOLING', 'POSTGRES_URL']"),
    'Producción debe preferir la conexión PostgreSQL no agrupada');
test_assert(str_contains($database, "'pgsql:host=%s"), 'PDO debe construir un DSN PostgreSQL');
test_assert(str_contains($database, "\$query['sslmode']"), 'La conexión debe respetar sslmode');
test_assert(str_contains($locks, 'pg_advisory_lock'), 'PostgreSQL debe usar bloqueos asesores');
test_assert(str_contains($locks, 'GET_LOCK'), 'MySQL debe conservar sus bloqueos nombrados');
test_assert(str_contains($api, "['NamedLock',"), 'La API debe cargar el adaptador de bloqueos');

echo "OK postgres_compatibility_test: conexión Supabase y bloqueos duales configurados.\n";
