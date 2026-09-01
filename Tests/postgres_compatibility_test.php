<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$database = file_get_contents("{$root}/Configuration/Database.php");
$locks = file_get_contents("{$root}/Application/Model/NamedLock.php");
$direccion = file_get_contents("{$root}/Application/Model/Direccion.php");
$api = file_get_contents("{$root}/Public/api/productores.php");

test_assert(str_contains($database, "['POSTGRES_URL_NON_POOLING', 'POSTGRES_URL']"),
    'Producción debe preferir la conexión PostgreSQL no agrupada');
test_assert(str_contains($database, "'pgsql:host=%s"), 'PDO debe construir un DSN PostgreSQL');
test_assert(str_contains($database, "\$query['sslmode']"), 'La conexión debe respetar sslmode');
test_assert(str_contains($locks, 'pg_advisory_lock'), 'PostgreSQL debe usar bloqueos asesores');
test_assert(str_contains($locks, 'GET_LOCK'), 'MySQL debe conservar sus bloqueos nombrados');
test_assert(str_contains($api, "['NamedLock',"), 'La API debe cargar el adaptador de bloqueos');

test_assert(!str_contains($direccion, 'IS_USED_LOCK'),
    'Direccion no debe inspeccionar locks con funciones exclusivas de MySQL');
test_assert(!str_contains($direccion, 'CONNECTION_ID'),
    'Direccion no debe depender de CONNECTION_ID en producción PostgreSQL');
test_assert(str_contains($direccion, 'profundidadBloqueoAlta'),
    'Direccion debe comprobar en memoria que el lock fue adquirido por su wrapper portable');

// PostgreSQL rechaza FOR UPDATE junto a funciones de agregacion y produccion
// corre sobre PostgreSQL. Una lectura con bloqueo escrita como
// "SELECT MAX(...) ... FOR UPDATE" pasa en MySQL y revienta en produccion, y
// las pruebas de este repositorio corren contra MySQL: nada la detendria.
// Solo se inspeccionan las cadenas SQL literales; el count() de PHP no cuenta.
foreach (glob("{$root}/Application/Model/*.php") as $modeloPhp) {
    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/s", file_get_contents($modeloPhp), $literales);
    foreach ($literales[1] as $sqlLiteral) {
        if (stripos($sqlLiteral, 'FOR UPDATE') === false) {
            continue;
        }
        test_assert(
            preg_match('/\\b(MAX|MIN|COUNT|SUM|AVG)\\s*\\(/i', $sqlLiteral) !== 1,
            sprintf(
                '%s combina una funcion de agregacion con FOR UPDATE; PostgreSQL lo rechaza. '
                . 'Bloquee la fila del extremo con ORDER BY ... LIMIT 1 FOR UPDATE.',
                basename($modeloPhp)
            )
        );
    }
}

echo "OK postgres_compatibility_test: conexión Supabase y bloqueos duales configurados sin SQL exclusivo de MySQL en Direccion.\n";
