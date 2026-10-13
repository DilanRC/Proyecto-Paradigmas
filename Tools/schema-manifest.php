<?php

declare(strict_types=1);

function schema_manifest_path(?string $source = null): string
{
    return $source ?? dirname(__DIR__) . '/Database/SqlScripts/000instalacioncompleta.sql';
}

function schema_manifest_from_sql(string $sql, string $source = 'Database/SqlScripts/000instalacioncompleta.sql'): array
{
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-z][a-z0-9_]*)`?\s*\(/i', $sql, $tableMatches, PREG_OFFSET_CAPTURE);
    $tablesOrdered = array_column($tableMatches[1], 0);
    // Columnas declaradas por tabla (nombre => tipo normalizado + nulabilidad), para comparar una base existente.
    $columns = [];
    foreach ($tableMatches[0] as $index => [, $offset]) {
        $end = $tableMatches[0][$index + 1][1] ?? strlen($sql);
        preg_match_all(
            '/^\s+(tb[a-z0-9_]+)\s+([A-Z]+(?:\(\d+(?:,\s*\d+)?\))?(?:\s+UNSIGNED)?)\s+(NOT NULL|NULL)\s*,?\s*$/m',
            substr($sql, $offset, $end - $offset),
            $columnMatches,
            PREG_SET_ORDER
        );
        foreach ($columnMatches as [, $column, $type, $nullability]) {
            $columns[$tablesOrdered[$index]][$column] = schema_manifest_column_signature($type, $nullability === 'NULL');
        }
    }
    $uniqueTables = array_values(array_unique($tablesOrdered));
    if ($uniqueTables !== $tablesOrdered) {
        throw new RuntimeException('El SQL contiene CREATE TABLE duplicados.');
    }
    $tablesSorted = $tablesOrdered;
    sort($tablesSorted, SORT_STRING);

    preg_match_all('/(?:CREATE\s+DATABASE(?:\s+IF\s+NOT\s+EXISTS)?|ALTER\s+DATABASE|USE)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $databaseMatches);
    $databases = array_values(array_unique($databaseMatches[1]));
    if (count($databases) !== 1) {
        throw new RuntimeException('El SQL debe nombrar una sola base de datos.');
    }

    return [
        'source' => $source,
        'database' => $databases[0],
        'table_count' => count($tablesOrdered),
        'tables_ordered' => $tablesOrdered,
        'tables_sorted' => $tablesSorted,
        'columns' => $columns,
    ];
}

/**
 * Tipo comparable entre el SQL canónico y information_schema. MariaDB y MySQL 5.7 informan el ancho
 * de los enteros (`int(11)`) y MySQL 8 no: el ancho no cambia lo que se guarda y se descarta.
 */
function schema_manifest_column_signature(string $type, bool $nullable): string
{
    $type = preg_replace('/\b((?:tiny|small|medium|big)?int)\(\d+\)/', '$1', strtolower(trim($type)));

    return preg_replace('/\s+/', ' ', str_replace(', ', ',', (string) $type)) . ($nullable ? ' null' : ' not null');
}

function schema_manifest(?string $source = null): array
{
    $path = schema_manifest_path($source);
    if (!is_file($path)) {
        throw new RuntimeException("No existe el SQL canónico: {$path}");
    }
    return schema_manifest_from_sql(file_get_contents($path), 'Database/SqlScripts/000instalacioncompleta.sql');
}

function schema_manifest_main(array $argv): int
{
    $format = 'json';
    $source = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--format=')) {
            $format = substr($argument, strlen('--format='));
        } elseif (str_starts_with($argument, '--source=')) {
            $source = substr($argument, strlen('--source='));
        } else {
            throw new RuntimeException("Argumento no reconocido: {$argument}");
        }
    }

    $manifest = schema_manifest($source);
    if ($format === 'json') {
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($format === 'csv') {
        echo implode(',', $manifest['tables_sorted']) . PHP_EOL;
    } elseif ($format === 'lines') {
        echo implode(PHP_EOL, $manifest['tables_ordered']) . PHP_EOL;
    } elseif ($format === 'count') {
        echo $manifest['table_count'] . PHP_EOL;
    } else {
        throw new RuntimeException("Formato no soportado: {$format}");
    }

    return 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(schema_manifest_main($argv));
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
