<?php

declare(strict_types=1);

const EXPECTED_COLUMNS = [
    'tbproductor' => [
        'tbproductorid', 'tbproductoridentificacionnumero', 'tbproductoridentificaciontipo',
        'tbproductornombre', 'tbproductortelefono', 'tbproductorcorreoelectronico', 'tbproductorestado',
    ],
    'tbproductordireccion' => [
        'tbproductordireccionid', 'tbproductorid', 'tbproductordireccionprovincia',
        'tbproductordireccioncanton', 'tbproductordirecciondistrito', 'tbproductordireccionpueblo',
        'tbproductordireccionsenas',
    ],
    'tbfinca' => ['tbfincaid', 'tbproductorid', 'tbfincanombre', 'tbfincaestado'],
    'tbbitacora' => [
        'tbbitacoraid', 'tbbitacoraentidad', 'tbbitacoraregistroidentificacionnumero',
        'tbbitacoraaccion', 'tbbitacorafecha', 'tbbitacoradatosanteriores', 'tbbitacoradatosnuevos',
        'tbbitacoraactortipo', 'tbbitacorausuarioid', 'tbbitacoraorigen', 'tbbitacorasolicitudid',
    ],
    'tbcomprador' => [
        'tbcompradorid', 'tbcompradoridentificacionnumero', 'tbcompradoridentificaciontipo',
        'tbcompradornombre', 'tbcompradortelefono', 'tbcompradorcorreoelectronico', 'tbcompradorestado',
    ],
];

function postgresConnection(string $url): PDO
{
    $parts = parse_url($url);
    if ($parts === false || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
        || !isset($parts['host'], $parts['user'], $parts['pass'])) {
        throw new RuntimeException('La URL PostgreSQL configurada no es válida.');
    }
    $database = ltrim($parts['path'] ?? '/postgres', '/');
    if ($database === '') {
        $database = 'postgres';
    }
    parse_str($parts['query'] ?? '', $query);
    $sslMode = is_string($query['sslmode'] ?? null) ? $query['sslmode'] : 'require';
    if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
        throw new RuntimeException('El sslmode PostgreSQL configurado no es válido.');
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=10',
        $parts['host'],
        $parts['port'] ?? 5432,
        rawurldecode($database),
        $sslMode,
    );

    return new PDO($dsn, rawurldecode($parts['user']), rawurldecode($parts['pass']), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function configuredConnection(): PDO
{
    $errors = [];
    foreach (['POSTGRES_URL', 'POSTGRES_URL_NON_POOLING'] as $name) {
        $url = getenv($name);
        if ($url === false || $url === '' || $url === '[SENSITIVE]') {
            continue;
        }
        try {
            return postgresConnection($url);
        } catch (Throwable $exception) {
            $errors[] = "{$name}: {$exception->getMessage()}";
        }
    }
    throw new RuntimeException($errors === []
        ? 'No hay una URL PostgreSQL utilizable.'
        : 'No fue posible conectar con las URLs PostgreSQL configuradas: ' . implode('; ', $errors));
}

function validateSchema(PDO $connection): void
{
    $statement = $connection->prepare(
        "SELECT table_name, column_name
         FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = ANY(CAST(:tables AS text[]))
         ORDER BY table_name, ordinal_position"
    );
    $tableLiteral = '{' . implode(',', array_keys(EXPECTED_COLUMNS)) . '}';
    $statement->execute(['tables' => $tableLiteral]);
    $actual = [];
    foreach ($statement->fetchAll() as $column) {
        $actual[$column['table_name']][] = $column['column_name'];
    }
    ksort($actual);
    $expected = EXPECTED_COLUMNS;
    ksort($expected);
    if ($actual !== $expected) {
        throw new RuntimeException('El esquema Supabase no coincide con el contrato de cinco tablas.');
    }
}

try {
    $connection = configuredConnection();
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No fue posible leer schema.sql.');
    }
    $connection->beginTransaction();
    $connection->exec("SELECT pg_advisory_xact_lock(hashtext('tindercows_supabase_schema_v2'))");
    $connection->exec($schema);
    validateSchema($connection);
    $connection->commit();
    fwrite(STDOUT, "supabase_schema_status=ready tables=5 migration=v2\n");
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'supabase_schema_status=error message=' . $exception->getMessage() . "\n");
    exit(1);
}
