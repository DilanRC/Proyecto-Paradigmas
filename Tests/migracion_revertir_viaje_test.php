<?php

declare(strict_types=1);

/**
 * Reversión de la 027 en MySQL (Database/Migrations/028revertirviajerealizado.sql), sobre bases desechables:
 *  - sin datos que dependan de la 027: la tabla queda idéntica a la del instalador 000, y repetirla no cambia nada;
 *  - con un viaje enlazado a su solicitud: la columna se conserva (no se pierde el hecho) y lo avisa;
 *  - con un viaje sin método de pago: tbpagometodoid sigue admitiendo NULL (no se inventa un método) y lo avisa;
 *  - una base que nunca corrió la 027 no cambia.
 * Necesita permiso para crear bases (root, como en Tools/php-test.sh).
 */
require __DIR__ . '/bootstrap.php';

$raiz = dirname(__DIR__);
$servidor = test_new_db();
$prefijo = 'bdrev027_' . getmypid() . '_';
$bases = [];

$sentencias = static function (string $sql, string $base): array {
    $sql = preg_replace('/^\s*--.*$/m', '', str_replace('bdmercadoganadero', $base, $sql));
    return array_values(array_filter(array_map('trim', explode(";\n", (string) $sql)), static fn (string $s): bool => $s !== ''));
};
$crear = static function (string $sufijo) use ($servidor, $prefijo, &$bases): string {
    $base = $prefijo . $sufijo;
    $servidor->exec("DROP DATABASE IF EXISTS `{$base}`");
    $servidor->exec("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $bases[] = $base;
    return $base;
};
$ejecutar = static function (string $archivo, string $base) use ($servidor, $sentencias, $raiz): ?string {
    $ultimo = null;
    foreach ($sentencias((string) file_get_contents("{$raiz}/{$archivo}"), $base) as $sentencia) {
        $resultado = $servidor->query($sentencia);
        if ($resultado->columnCount() > 0) {
            $fila = $resultado->fetch(PDO::FETCH_NUM);
            $ultimo = $fila === false ? $ultimo : (string) $fila[0];
        }
        $resultado->closeCursor();
    }
    return $ultimo;
};
$tablaFlete = static function () use ($raiz): string {
    preg_match('/CREATE TABLE IF NOT EXISTS tbtransportistaflete \(.*?\) ENGINE=InnoDB;/s',
        (string) file_get_contents("{$raiz}/Database/SqlScripts/000instalacioncompleta.sql"), $m);
    test_assert(isset($m[0]), 'el instalador 000 declara tbtransportistaflete');
    return $m[0];
};
$columnas = static function (string $base) use ($servidor): array {
    $s = $servidor->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, ORDINAL_POSITION FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :b AND TABLE_NAME = \'tbtransportistaflete\' ORDER BY ORDINAL_POSITION');
    $s->execute(['b' => $base]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
};
$conClave = static function (string $base) use ($servidor): bool {
    return (int) $servidor->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '{$base}'
        AND TABLE_NAME = 'tbtransportistaflete' AND CONSTRAINT_TYPE = 'PRIMARY KEY'")->fetchColumn() === 1;
};
$fila = static function (string $base, int $id, ?int $solicitud, ?int $pago) use ($servidor): void {
    $s = $servidor->prepare("INSERT INTO `{$base}`.tbtransportistaflete (tbtransportistafleteid, tbtransportistaid, tbcomprasolicitudid,
        tbtransportistafletefecha, tbtransportistafletedescripcion, tbpagometodoid, tbtransportistafleteorigen)
        VALUES (:id, 1, :sol, '2026-11-01', 'Viaje de prueba', :pago, 'PRUEBA')");
    $s->execute(['id' => $id, 'sol' => $solicitud, 'pago' => $pago]);
};
// Estado "con 027": la tabla del instalador vigente (anterior a la 027) más la 027 aplicada.
$con027 = static function (string $sufijo) use ($crear, $servidor, $tablaFlete, $ejecutar): string {
    $base = $crear($sufijo);
    $servidor->exec("USE `{$base}`");
    $servidor->exec($tablaFlete());
    $ejecutar('Database/Migrations/027viajerealizado.sql', $base);
    return $base;
};

try {
    $referencia = $crear('referencia');
    $servidor->exec("USE `{$referencia}`");
    $servidor->exec($tablaFlete());
    $esperado = $columnas($referencia);
    $nombres = array_column($esperado, 'COLUMN_NAME');
    test_assert(!in_array('tbcomprasolicitudid', $nombres, true), 'el instalador limpio no trae tbcomprasolicitudid');
    test_same('NO', $esperado[array_search('tbpagometodoid', $nombres, true)]['IS_NULLABLE'], 'el instalador limpio exige tbpagometodoid');

    // Caso A: la 027 aplicada sin datos que dependan de ella → vuelve al esquema anterior, también al repetirla.
    $a = $con027('a');
    test_assert(in_array('tbcomprasolicitudid', array_column($columnas($a), 'COLUMN_NAME'), true), 'la base de partida tiene la 027');
    $fila($a, 1, null, 1);
    test_same('028 OK: tbtransportistaflete coincide con el esquema anterior a la 027.', $ejecutar('Database/Migrations/028revertirviajerealizado.sql', $a), 'la 028 informa el éxito');
    test_same($esperado, $columnas($a), 'tras la 028 la tabla coincide columna por columna con el instalador 000');
    test_assert($conClave($a), 'la llave primaria se conserva');
    test_same(1, (int) $servidor->query("SELECT COUNT(*) FROM `{$a}`.tbtransportistaflete WHERE tbpagometodoid = 1")->fetchColumn(), 'el viaje existente se conserva');
    test_same('028 OK: tbtransportistaflete coincide con el esquema anterior a la 027.', $ejecutar('Database/Migrations/028revertirviajerealizado.sql', $a), 'repetir la 028 es seguro');
    test_same($esperado, $columnas($a), 'repetirla no cambia el esquema');

    // Caso B: un viaje guarda el enlace → no se borra la columna (se perdería el hecho).
    $b = $con027('b');
    $fila($b, 1, 77, 1);
    test_same('028 PENDIENTE: 1 viajes guardan tbcomprasolicitudid; la columna se conservó.', $ejecutar('Database/Migrations/028revertirviajerealizado.sql', $b), 'la 028 avisa el bloqueo');
    test_same(77, (int) $servidor->query("SELECT tbcomprasolicitudid FROM `{$b}`.tbtransportistaflete")->fetchColumn(), 'el enlace histórico no se pierde');

    // Caso C: un viaje sin método de pago → no se exige NOT NULL ni se inventa un método.
    $c = $con027('c');
    $fila($c, 1, null, null);
    test_same('028 PENDIENTE: 1 viajes sin método de pago; tbpagometodoid sigue admitiendo NULL.', $ejecutar('Database/Migrations/028revertirviajerealizado.sql', $c), 'la 028 avisa el pago vacío');
    test_same(1, (int) $servidor->query("SELECT COUNT(*) FROM `{$c}`.tbtransportistaflete WHERE tbpagometodoid IS NULL")->fetchColumn(), 'no se asigna un método ficticio');
    test_assert(!in_array('tbcomprasolicitudid', array_column($columnas($c), 'COLUMN_NAME'), true), 'la columna sin datos sí se retira');
    // Corregido el dato por quien corresponde, repetir la 028 termina la reversión.
    $servidor->exec("UPDATE `{$c}`.tbtransportistaflete SET tbpagometodoid = 1");
    $ejecutar('Database/Migrations/028revertirviajerealizado.sql', $c);
    test_same($esperado, $columnas($c), 'al repetirla con el dato corregido queda el esquema anterior');

    // Base que nunca corrió la 027: la 028 no cambia nada.
    $d = $crear('d');
    $servidor->exec("USE `{$d}`");
    $servidor->exec($tablaFlete());
    $ejecutar('Database/Migrations/028revertirviajerealizado.sql', $d);
    test_same($esperado, $columnas($d), 'sin la 027 la 028 no cambia la tabla');
} finally {
    foreach ($bases as $base) $servidor->exec("DROP DATABASE IF EXISTS `{$base}`");
}

echo "OK migracion_revertir_viaje_test: la 028 deja tbtransportistaflete como en el 000, es repetible y no pierde viajes enlazados ni inventa métodos de pago.\n";
