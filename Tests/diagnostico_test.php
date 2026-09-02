<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$sql = file_get_contents(dirname(__DIR__) . '/Database/Tests/diagnostico.sql');
if ($sql === false) {
    throw new RuntimeException('No se pudo leer diagnostico.sql');
}

$db = test_db();
$statements = array_filter(array_map('trim', explode(';', $sql)));
$detailsChecked = 0;
foreach ($statements as $statement) {
    $withoutComments = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));
    if ($withoutComments === '' || str_starts_with($withoutComments, 'USE ')) {
        continue;
    }
    if (str_starts_with($withoutComments, 'SET ')) {
        $db->exec($withoutComments);
        continue;
    }
    if (preg_match("/^SELECT\s+'D-[^']+'\s+AS\s+diagnostico$/i", $withoutComments)) {
        continue;
    }
    if (!str_starts_with($withoutComments, 'SELECT ')) {
        continue;
    }

    $rows = $db->query($withoutComments)->fetchAll();
    test_same([], $rows, 'Diagnóstico debe devolver cero filas: ' . substr($withoutComments, 0, 90));
    $detailsChecked++;
}

test_assert($detailsChecked >= 20, 'El diagnóstico debe cubrir las consultas de detalle DB ready');
echo "OK diagnostico_test: {$detailsChecked} consultas de diagnóstico devuelven cero inconsistencias.\n";
