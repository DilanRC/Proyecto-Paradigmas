<?php
// Recuperación del asistente local contra un MySQL real y DESECHABLE: instalación cortada, cuenta
// limitada, esquema con columnas distintas y base eliminada. Se niega a correr si la base tiene personas.
$raiz = dirname(__DIR__);
if (getenv("TC_PRUEBA_INSTALADOR_DESTRUCTIVA") !== "1") {
    echo "OK local_database_installer_recovery_test: omitida; elimina y recrea bdmercadoganadero, así que solo corre con TC_PRUEBA_INSTALADOR_DESTRUCTIVA=1 contra un MySQL desechable (root sin contraseña en 127.0.0.1, puerto DB_PORT o 3306).\n";
    return;
}
require $raiz . '/Application/Service/LocalDatabaseInstaller.php';
use Application\Service\LocalDatabaseInstaller as I;
use Application\Service\LocalDatabaseInstallerException as X;
use Configuration\LocalDatabaseCredentials as C;
$puerto = getenv('DB_PORT') ?: '3306';
foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'] as $n) putenv($n);
$root = ['host' => '127.0.0.1', 'port' => $puerto, 'user' => 'root', 'password' => ''];
$pdo = new PDO('mysql:host=127.0.0.1;port=' . $puerto, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ok = function (bool $c, string $m) { echo ($c ? 'PASS ' : 'FAIL ') . $m . "\n"; if (!$c) exit(1); };
$falla = function (callable $f): ?int { try { $f(); } catch (X $e) { return $e->httpStatus; } return null; };
try {
    $personas = (int) $pdo->query('SELECT COUNT(*) FROM bdmercadoganadero.tbpersona')->fetchColumn();
} catch (PDOException) {
    $personas = 0;
}
$ok($personas === 0, 'la base no tiene personas registradas (esta prueba la elimina)');
$ok(C::state() === null, 'esta carpeta no tiene una conexión guardada por el asistente');
$pdo->exec('DROP DATABASE IF EXISTS bdmercadoganadero'); C::discard();

// 1. Instalación cortada después de crear la base y 10 tablas.
C::store(['host' => '127.0.0.1', 'port' => (int) $puerto, 'user' => 'root', 'password' => ''], 'installing');
$sql = file_get_contents($raiz . '/Database/SqlScripts/000instalacioncompleta.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS.*?\n\)[^;]*;/s', $sql, $m);
$pdo->exec('CREATE DATABASE bdmercadoganadero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE bdmercadoganadero');
foreach (array_slice($m[0], 0, 10) as $ddl) $pdo->exec($ddl);
$r = I::inspect($root);
$ok($r['tableCount'] === 10 && !$r['schemaComplete'] && $r['installationState'] === 'installing', 'base a medias detectada (10/48, installing)');
$ok($falla(fn () => I::install($root)) === 409, 'instalar sobre una base existente se rechaza');
$r = I::resume($root);
$ok($r['schemaComplete'] && $r['requiredSeedsComplete'] && C::state() === 'installed' && $r['applicationUser'] === I::APP_USER, 'reanudar completa 48 tablas, semillas y cuenta limitada');
$ok($falla(fn () => I::resume($root)) === 409, 'reanudar una instalación terminada se rechaza');
$antes = $pdo->query('SELECT COUNT(*) FROM tbraza')->fetchColumn();

// 2. Cuenta limitada: lee y escribe datos, no cambia estructura.
$app = C::load();
$lim = new PDO('mysql:host=127.0.0.1;port=' . $puerto . ';dbname=bdmercadoganadero', $app['user'], $app['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$lim->exec("INSERT INTO tbregistroconsulta VALUES (999999, 'x', '2026-01-01 00:00:00')"); $lim->exec('DELETE FROM tbregistroconsulta WHERE tbregistroconsultaid = 999999');
$ddl = 0; foreach (['DROP TABLE tbraza', 'CREATE TABLE x (a INT)', 'ALTER TABLE tbraza ADD z INT', 'CREATE DATABASE otra', 'SELECT * FROM mysql.user'] as $q) { try { $lim->query($q); } catch (PDOException) { $ddl++; } }
$ok($ddl === 5, 'la cuenta de la aplicación no puede DROP, CREATE, ALTER ni leer otras bases');
$ok((int) $lim->query('SELECT GET_LOCK("t", 0)')->fetchColumn() === 1, 'la cuenta limitada puede usar GET_LOCK');

// 3. Esquema con 48 nombres pero columnas distintas.
$pdo->exec('ALTER TABLE tbpersona MODIFY tbpersonanombre VARCHAR(10) NOT NULL'); $pdo->exec('ALTER TABLE tbraza DROP COLUMN tbrazaactivo'); $pdo->exec('ALTER TABLE tbespecie ADD sobra INT NOT NULL');
$r = I::inspect($root);
$ok($r['tableCount'] === 48 && $r['primaryKeyCount'] === 48 && !$r['schemaComplete'] && count($r['columnDifferences']) === 3, '48 tablas con columnas distintas no pasan: ' . implode('; ', $r['columnDifferences']));
C::discard();
$ok($falla(fn () => I::saveExistingConnection($root)) === 409, 'no se adopta una base con columnas distintas');
$pdo->exec('ALTER TABLE tbpersona MODIFY tbpersonanombre VARCHAR(150) NOT NULL'); $pdo->exec('ALTER TABLE tbraza ADD tbrazaactivo TINYINT(1) NOT NULL'); $pdo->exec('ALTER TABLE tbespecie DROP COLUMN sobra');
$ok(I::inspect($root)['schemaComplete'], 'el esquema corregido vuelve a pasar');
I::saveExistingConnection($root);
$ok(C::state() === 'existing', 'una base completa ajena solo guarda la conexión');

// 3b. La conexión guardada deja de servir: se puede reemplazar por otra comprobada; si sirve, no.
$ok($falla(fn () => I::saveExistingConnection($root)) === 409, 'una conexión guardada que funciona no se reemplaza');
C::discard();
C::store(['host' => '127.0.0.1', 'port' => (int) $puerto, 'user' => 'ya-no-existe', 'password' => 'x'], 'installed');
$ok(!I::storedConnectionWorks(), 'se detecta la conexión guardada que ya no conecta');
I::saveExistingConnection($root);
$ok(C::load()['user'] === 'root' && I::storedConnectionWorks(), 'la conexión comprobada reemplaza a la que dejó de servir');

// 3c. Un intento con una cuenta sin permisos no borra la conexión válida, y no se reanuda en otro servidor.
$pdo->exec("CREATE USER IF NOT EXISTS 'sinpermiso'@'localhost' IDENTIFIED BY 'x'");
$pdo->exec("CREATE USER IF NOT EXISTS 'sinpermiso'@'127.0.0.1' IDENTIFIED BY 'x'");
$ok($falla(fn () => I::install(['user' => 'sinpermiso', 'password' => 'x'] + $root)) !== null && C::load()['user'] === 'root',
    'fallar al crear la base conserva la conexión anterior');
$pdo->exec("DROP USER 'sinpermiso'@'localhost'");
$pdo->exec("DROP USER 'sinpermiso'@'127.0.0.1'");
C::discard();
C::store(['host' => '127.0.0.1', 'port' => 1, 'user' => 'root', 'password' => ''], 'installing');
$ok($falla(fn () => I::resume($root)) === 409, 'no se reanuda una instalación que empezó en otro servidor o puerto');
C::discard();
I::saveExistingConnection($root);

// 4. Base eliminada después: el registro viejo no bloquea una instalación nueva.
$pdo->exec('DROP DATABASE bdmercadoganadero');
$r = I::install($root);
$ok($r['schemaComplete'] && C::state() === 'installed', 'tras eliminar la base se puede instalar de nuevo');

// 5. Intento cortado antes de crear la base.
$pdo->exec('DROP DATABASE bdmercadoganadero'); C::discard();
C::store(['host' => '127.0.0.1', 'port' => (int) $puerto, 'user' => 'root', 'password' => ''], 'installing');
$ok(I::install($root)['schemaComplete'], 'un "installing" sin base no bloquea');
$ok((int) $pdo->query('SELECT COUNT(*) FROM bdmercadoganadero.tbpersona')->fetchColumn() === 0
    && (int) $pdo->query('SELECT COUNT(*) FROM bdmercadoganadero.tbanimalpublicacion')->fetchColumn() === 0, 'instalación nueva: 0 personas y 0 publicaciones');
$pdo->exec('DROP DATABASE bdmercadoganadero'); C::discard();
echo "OK local_database_installer_recovery_test: reanudación, cuenta limitada, columnas y reinstalación.\n";
