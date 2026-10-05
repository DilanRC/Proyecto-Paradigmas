<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/RegistroConsulta.php';

use Application\Model\RegistroConsulta;

$ipA = 'test-a-' . test_token('ip');
$ipB = 'test-b-' . test_token('ip');
$ipVieja = 'test-vieja-' . test_token('ip');
$claves = array_map(static fn (string $ip): string => hash('sha256', $ip), [$ipA, $ipB, $ipVieja]);
$limite = new RegistroConsulta(test_db());

try {
    // Una consulta vieja (fuera de la ventana) se borra en la siguiente consulta.
    test_db()->prepare(
        'INSERT INTO tbregistroconsulta (tbregistroconsultaid, tbregistroconsultaclave, tbregistroconsultafecha)
         SELECT COALESCE(MAX(tbregistroconsultaid), 0) + 1, :clave, :fecha FROM tbregistroconsulta'
    )->execute(['clave' => $claves[2], 'fecha' => gmdate('Y-m-d H:i:s', time() - RegistroConsulta::VENTANA_SEGUNDOS - 5)]);

    for ($i = 1; $i <= RegistroConsulta::LIMITE; $i++) {
        test_assert($limite->permitir($ipA), "La consulta {$i} cabe en el límite");
    }
    test_same(false, $limite->permitir($ipA), 'La consulta que pasa el límite se rechaza');
    test_same(true, $limite->permitir($ipB), 'Otra IP tiene su propio límite');

    $guardadas = test_db()->prepare('SELECT tbregistroconsultaclave FROM tbregistroconsulta WHERE tbregistroconsultaclave IN (?, ?, ?)');
    $guardadas->execute($claves);
    $filas = $guardadas->fetchAll(PDO::FETCH_COLUMN);
    test_same(RegistroConsulta::LIMITE, count(array_keys($filas, $claves[0], true)), 'Una consulta rechazada no se guarda');
    test_same(0, count(array_keys($filas, $claves[2], true)), 'Las consultas fuera de la ventana se borran');
    test_assert(!in_array($ipA, $filas, true), 'Se guarda el hash, nunca la IP');

    test_same('203.0.113.7', RegistroConsulta::ipCliente(['HTTP_X_REAL_IP' => '203.0.113.7', 'REMOTE_ADDR' => '10.0.0.1']), 'Detrás del proxy se usa X-Real-IP');
    test_same('10.0.0.1', RegistroConsulta::ipCliente(['REMOTE_ADDR' => '10.0.0.1']), 'Sin proxy se usa REMOTE_ADDR');
} finally {
    test_db()->prepare('DELETE FROM tbregistroconsulta WHERE tbregistroconsultaclave IN (?, ?, ?)')->execute($claves);
}

echo "OK registro_consulta_test: límite por IP, ventana deslizante, hash en lugar de IP.\n";
