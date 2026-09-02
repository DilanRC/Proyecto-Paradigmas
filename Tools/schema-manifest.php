<?php

declare(strict_types=1);

function schema_manifest_path(?string $source = null): string
{
    return $source ?? dirname(__DIR__) . '/Database/SqlScripts/000instalacioncompleta.sql';
}

function schema_manifest_from_sql(string $sql, string $source = 'Database/SqlScripts/000instalacioncompleta.sql'): array
{
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-z][a-z0-9_]*)`?\s*\(/i', $sql, $tableMatches);
    $tablesOrdered = $tableMatches[1];
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
    ];
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
