<?php

declare(strict_types=1);

// Preparación automática contra un MySQL real y DESECHABLE: base nueva, base a medias, base ajena con datos,
// datos iniciales y permisos. Elimina y recrea bdmercadoganadero; se niega a correr si tiene personas.
$raiz = dirname(__DIR__);
if (getenv('TC_PRUEBA_INSTALADOR_DESTRUCTIVA') !== '1') {
    echo "OK local_database_bootstrap_mysql_test: omitida; elimina y recrea bdmercadoganadero, así que solo corre con TC_PRUEBA_INSTALADOR_DESTRUCTIVA=1 contra un MySQL desechable (root sin contraseña en 127.0.0.1, puerto DB_PORT o 3306).\n";
    return;
}
require $raiz . '/Application/Service/LocalDatabaseInstaller.php';

use Application\Service\LocalDatabaseInstaller as Instalador;
use Configuration\LocalDatabaseBootstrap as Arranque;
use Application\LocalDatabaseUnavailable as NoDisponible;

$root = ['origen' => 'prueba', 'host' => '127.0.0.1', 'port' => (int) (getenv('DB_PORT') ?: 3306), 'user' => 'root', 'password' => '', 'socket' => null];
$pdo = Arranque::open($root, false);
$ok = static function (bool $condicion, string $mensaje): void {
    echo ($condicion ? 'PASS ' : 'FAIL ') . $mensaje . "\n";
    if (!$condicion) {
        exit(1);
    }
};
$motivo = static function (callable $accion): ?string {
    try {
        $accion();
    } catch (NoDisponible $problema) {
        return $problema->motivo();
    }

    return null;
};
$huella = static fn (): string => md5(json_encode($pdo->query(
    'CHECKSUM TABLE bdmercadoganadero.tbpersona, bdmercadoganadero.tbpagometodo, bdmercadoganadero.tbadministrador, bdmercadoganadero.tbraza'
)->fetchAll()));
try {
    $personas = (int) $pdo->query('SELECT COUNT(*) FROM bdmercadoganadero.tbpersona')->fetchColumn();
} catch (PDOException) {
    $personas = 0;
}
$ok($personas === 0, 'la base no tiene personas registradas (esta prueba la elimina)');
$pdo->exec('DROP DATABASE IF EXISTS bdmercadoganadero');

// 1. Base nueva.
$ok(!Instalador::inspect($pdo)['databaseExists'], 'sin base: se informa que no existe');
$r = Instalador::prepare($root);
$ok($r['schemaComplete'] && $r['tableCount'] === 48 && $r['primaryKeyCount'] === 48 && !$r['seedsPending'] && $r['personas'] === 0,
    'base nueva: 48 tablas, 48 llaves primarias, datos iniciales y cero personas');
$conteos = $pdo->query('SELECT (SELECT COUNT(*) FROM bdmercadoganadero.tbpagometodo), (SELECT COUNT(*) FROM bdmercadoganadero.tbadministrador),
    (SELECT COUNT(*) FROM bdmercadoganadero.tbespecie), (SELECT COUNT(*) FROM bdmercadoganadero.tbanimaltipo), (SELECT COUNT(*) FROM bdmercadoganadero.tbraza),
    (SELECT COUNT(*) FROM bdmercadoganadero.tbvacuna), (SELECT COUNT(*) FROM bdmercadoganadero.tbanimalpublicacion)')->fetch(PDO::FETCH_NUM);
$ok(array_map('intval', $conteos) === [1, 1, 6, 26, 42, 10, 0], 'datos iniciales 101, 102 y 104; ninguna publicación de demostración');
$ok(Instalador::writable(Arranque::open($root, true)), 'root puede escribir en la base');

// 2. Repetir no cambia nada, aunque un administrador haya editado los datos iniciales.
$pdo->exec("UPDATE bdmercadoganadero.tbpagometodo SET tbpagometodonombre = 'Sinpe' WHERE tbpagometodoid = 1");
$pdo->exec('UPDATE bdmercadoganadero.tbadministrador SET tbadministradorestado = 0');
$pdo->exec('DELETE FROM bdmercadoganadero.tbraza WHERE tbrazaid > 40');
$antes = $huella();
Instalador::prepare($root);
$ok($antes === $huella() && !Instalador::inspect($pdo)['seedsPending'], 'una base completa no recibe semillas de nuevo ni pierde cambios');

// 3. Preparación cortada: faltan tablas y no hay personas.
foreach (['tbvacuna', 'tbanimalvacunacion', 'tbsubasta'] as $tabla) {
    $pdo->exec("DROP TABLE bdmercadoganadero.{$tabla}");
}
$r = Instalador::inspect($pdo);
$ok(!$r['schemaComplete'] && $r['completable'] && count($r['missingTables']) === 3, 'base a medias sin personas: se puede completar');
$r = Instalador::prepare($root);
$ok($r['schemaComplete'] && $antes === $huella()
    && (int) $pdo->query('SELECT COUNT(*) FROM bdmercadoganadero.tbvacuna')->fetchColumn() === 10,
    'completar agrega las tablas que faltan y sus datos iniciales sin tocar lo demás');

// 4. Columnas y tablas de más que no estorban.
$pdo->exec('CREATE TABLE bdmercadoganadero.notas_ajenas (id INT PRIMARY KEY)');
$pdo->exec('ALTER TABLE bdmercadoganadero.tbpersona ADD COLUMN extra_opcional VARCHAR(10) NULL');
$r = Instalador::inspect($pdo);
$ok($r['schemaComplete'] && $r['extraTables'] === ['notas_ajenas'] && $r['extraColumns'] === ['tbpersona.extra_opcional'],
    'una tabla ajena y una columna opcional de más no impiden usar la base');
$pdo->exec('ALTER TABLE bdmercadoganadero.tbpersona ADD COLUMN extra_obligatoria INT NOT NULL');
$ok(!Instalador::inspect($pdo)['schemaComplete'], 'una columna obligatoria de más sí la hace incompatible');
$pdo->exec('ALTER TABLE bdmercadoganadero.tbpersona DROP COLUMN extra_obligatoria, DROP COLUMN extra_opcional');
$pdo->exec('DROP TABLE bdmercadoganadero.notas_ajenas');

// 5. Base de otra versión con datos: no se toca.
$pdo->exec("INSERT INTO bdmercadoganadero.tbpersona (tbpersonaid, tbpersonaidentificaciontipo, tbpersonaidentificacionnumero, tbpersonanombre, tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
    SELECT 1, 'PASAPORTE', 'PRUEBA001', 'Persona Existente', '+50688887777', 'existente@example.test', 1");
$pdo->exec('ALTER TABLE bdmercadoganadero.tbpersona DROP COLUMN tbpersonafotourl');
$pdo->exec('DROP TABLE bdmercadoganadero.tbsubasta');
$antes = $huella();
$tablas = Instalador::inspect($pdo)['tableCount'];
$r = Instalador::inspect($pdo);
$ok(!$r['schemaComplete'] && !$r['completable'] && $r['personas'] === 1 && $r['columnDifferences'] === ['tbpersona.tbpersonafotourl falta'],
    'otra estructura con personas: incompatible y no completable');
$ok($motivo(static fn () => Instalador::prepare($root)) === NoDisponible::INCOMPATIBLE
    && $antes === $huella() && Instalador::inspect($pdo)['tableCount'] === $tablas,
    'la preparación la rechaza sin crear tablas ni modificar filas');
$pdo->exec('ALTER TABLE bdmercadoganadero.tbpersona ADD COLUMN tbpersonafotourl VARCHAR(500) NULL');
$ok($motivo(static fn () => Instalador::prepare($root)) === NoDisponible::INCOMPATIBLE,
    'con personas registradas tampoco se agregan tablas faltantes');

// 6. Cuenta sin privilegios: ni crea la base ni escribe.
$pdo->exec('DROP DATABASE bdmercadoganadero');
$pdo->exec("CREATE USER IF NOT EXISTS 'tc_prueba_lector'@'127.0.0.1' IDENTIFIED BY 'lector1'");
$pdo->exec("CREATE USER IF NOT EXISTS 'tc_prueba_lector'@'localhost' IDENTIFIED BY 'lector1'");
$lector = ['user' => 'tc_prueba_lector', 'password' => 'lector1'] + $root;
$ok($motivo(static fn () => Instalador::prepare($lector)) === NoDisponible::SIN_PERMISO && !Instalador::inspect($pdo)['databaseExists'],
    'una cuenta sin CREATE no crea nada y se informa como falta de permiso');
Instalador::prepare($root);
$pdo->exec("GRANT SELECT ON bdmercadoganadero.* TO 'tc_prueba_lector'@'127.0.0.1'");
$pdo->exec("GRANT SELECT ON bdmercadoganadero.* TO 'tc_prueba_lector'@'localhost'");
$ok(!Instalador::writable(Arranque::open($lector, true)), 'una cuenta de solo lectura se detecta antes de registrar a nadie');
$pdo->exec("DROP USER 'tc_prueba_lector'@'127.0.0.1'");
$pdo->exec("DROP USER 'tc_prueba_lector'@'localhost'");

echo "OK local_database_bootstrap_mysql_test: base nueva, repetición, base a medias, diferencias toleradas, base ajena intacta y permisos.\n";
