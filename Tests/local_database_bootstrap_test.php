<?php

declare(strict_types=1);

// Arranque automático de la base local: dónde aplica, quién puede preparar la base y qué conexiones se
// consideran. No abre ninguna conexión; el comportamiento contra MySQL está en local_database_bootstrap_mysql_test.

require_once __DIR__ . '/../Configuration/LocalDatabaseBootstrap.php';
require_once __DIR__ . '/../Tools/schema-manifest.php';

use Configuration\LocalDatabaseBootstrap as Arranque;
use Configuration\LocalDatabaseCredentials;
use Application\LocalDatabaseUnavailable;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$environmentNames = ['VERCEL', 'POSTGRES_URL', 'POSTGRES_URL_NON_POOLING', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_DRIVER'];
$previousEnvironment = [];
foreach ($environmentNames as $name) {
    $previousEnvironment[$name] = getenv($name);
    putenv($name);
}
// Carpeta donde el asistente anterior guardaba `mysql.json` (mismo cálculo que LocalDatabaseCredentials).
$temporaryRoot = sys_get_temp_dir();
foreach ([(string) get_cfg_var('session.save_path')] as $candidate) {
    $candidate = $candidate === '' ? false : realpath($candidate);
    if ($candidate !== false && is_dir($candidate) && is_writable($candidate)) {
        $temporaryRoot = $candidate;
    }
}
$legacyDirectory = $temporaryRoot . '/ganado-cerca-'
    . substr(hash('sha256', 'ganado-cerca:' . realpath(dirname(__DIR__)) . ':bdmercadoganadero'), 0, 24);
$legacyFile = $legacyDirectory . '/mysql.json';
$createdDirectory = false;

try {
    $assert(Arranque::applies(), 'Sin variables, el MySQL de esta computadora es el destino.');
    $local = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost:18082'];
    $assert(Arranque::requestIsLocal($local), 'Acepta una visita localhost con puerto.');
    $assert(Arranque::requestIsLocal(['REMOTE_ADDR' => '::1', 'HTTP_HOST' => '[::1]:18082']), 'Acepta loopback IPv6.');
    $assert(!Arranque::requestIsLocal(['REMOTE_ADDR' => '192.0.2.10', 'HTTP_HOST' => 'localhost']),
        'Una visita de otra computadora no puede preparar la base.');
    $assert(!Arranque::requestIsLocal(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.test']),
        'Un Host externo no puede preparar la base, ni desde loopback.');
    $assert(!Arranque::requestIsLocal($local + ['HTTP_X_FORWARDED_FOR' => '192.0.2.10']),
        'Una visita que pasó por un proxy no puede preparar la base.');

    // Producción y bases ajenas: el arranque local no aplica.
    putenv('VERCEL=1');
    $assert(!Arranque::applies() && !Arranque::requestIsLocal($local), 'Nunca aplica en Vercel.');
    putenv('VERCEL');
    putenv('POSTGRES_URL=postgres://u:p@db.example.test:5432/postgres');
    $assert(!Arranque::applies(), 'Con POSTGRES_URL (producción) no aplica.');
    putenv('DB_DRIVER=mysql');
    $assert(LocalDatabaseCredentials::mysqlForced() && Arranque::applies(),
        'DB_DRIVER=mysql mantiene MySQL local aunque el servidor herede POSTGRES_URL.');
    putenv('DB_DRIVER');
    putenv('POSTGRES_URL');
    putenv('DB_HOST=db');
    $assert(!Arranque::applies(), 'Un MySQL que no es de esta computadora (Docker, remoto) queda fuera.');
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_NAME=otra_base');
    $assert(!Arranque::applies(), 'Otra base (pruebas aisladas) queda fuera.');
    putenv('DB_HOST');
    putenv('DB_NAME');

    // Conexiones candidatas.
    $porDefecto = Arranque::candidates();
    $configurada = array_values(array_filter($porDefecto, static fn (array $c): bool => $c['origen'] === 'configuración'));
    $assert(count($configurada) === 1 && $configurada[0]['host'] === '127.0.0.1' && $configurada[0]['port'] === 3306
        && $configurada[0]['user'] === 'root' && $configurada[0]['password'] === '' && $configurada[0]['socket'] === null,
        'Sin configuración, la conexión es el root sin contraseña de XAMPP por TCP.');
    foreach ($porDefecto as $candidata) {
        $assert($candidata['user'] === 'root' && $candidata['password'] === '',
            'Las alternativas (socket) usan la misma cuenta: no se prueban otras credenciales.');
    }

    if (LocalDatabaseCredentials::load() === null && PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid') && !file_exists($legacyFile)) {
        $createdDirectory = !is_dir($legacyDirectory) && mkdir($legacyDirectory, 0700);
        chmod($legacyDirectory, 0700);
        file_put_contents($legacyFile, json_encode(['version' => 1, 'database' => 'bdmercadoganadero', 'host' => '127.0.0.1',
            'port' => 3311, 'user' => 'heredado', 'password' => 'heredada', 'state' => 'existing']));
        chmod($legacyFile, 0600);
        $conHeredada = Arranque::candidates();
        $assert($conHeredada[0]['user'] === 'heredado' && $conHeredada[0]['port'] === 3311 && $conHeredada[1]['origen'] === 'configuración',
            'La conexión que guardó el asistente anterior se prueba primero: ahí están los datos de esa instalación.');
        chmod($legacyFile, 0644);
        $assert(LocalDatabaseCredentials::load() === null, 'Un archivo de conexión legible por otros usuarios se ignora.');
        chmod($legacyFile, 0600);
        putenv('DB_USER=servidor');
        $delServidor = Arranque::candidates();
        $assert(count($delServidor) === 1 && $delServidor[0]['user'] === 'servidor',
            'Si el servidor fija DB_*, esa es la única conexión: ni heredada ni socket.');
        putenv('DB_USER');
    }

    // Lo que solo viene del archivo .env del paquete no cuenta como fijado por el servidor.
    $envFile = tempnam(sys_get_temp_dir(), 'tcenv');
    file_put_contents($envFile, "DB_HOST=127.0.0.1\nDB_PORT=3306\nDB_USER=root\nDB_PASS=\nDB_DRIVER=mysql\n");
    \Configuration\loadEnvironmentFile($envFile);
    unlink($envFile);
    $assert(getenv('DB_USER') === 'root' && !LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment() && Arranque::applies(),
        'Los DB_* del .env del paquete son el punto de partida y admiten las alternativas locales.');
    putenv('DB_USER=servidor');
    $assert(LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment(), 'Un DB_* que el servidor cambia respecto del archivo sí manda.');

    $problema = new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INCOMPATIBLE);
    $assert($problema instanceof \Application\HttpException && $problema->estadoHttp === 503
        && $problema->motivo() === LocalDatabaseUnavailable::INCOMPATIBLE && str_contains($problema->getMessage(), 'No se modificó'),
        'El fallo de la base local es un 503 con mensaje para la persona, que las API responden como JSON.');
    foreach ((new ReflectionClass(LocalDatabaseUnavailable::class))->getConstant('MENSAJES') as $mensaje) {
        $assert(preg_match('/contraseña:|password|\.php|mysql\.json|sudo|terminal/i', $mensaje) === 0,
            'Los mensajes no piden comandos ni muestran detalles internos.');
    }

    $columns = \schema_manifest()['columns'];
    $assert(count($columns) === 48 && $columns['tbpersona']['tbpersonaid'] === 'int not null'
        && \schema_manifest_column_signature('bigint(20) unsigned', false) === $columns['tbbitacora']['tbbitacoraid']
        && \schema_manifest_column_signature('int(11)', true) === \schema_manifest_column_signature('INT', true),
        'El manifiesto describe las columnas y compara tipos sin el ancho de los enteros.');

    // El asistente ya no existe: ni pantalla, ni ruta, ni código que guarde credenciales.
    $raiz = dirname(__DIR__);
    $assert(!is_file($raiz . '/Public/instalacion.php')
        && !str_contains((string) file_get_contents($raiz . '/Public/.htaccess'), 'instalacion'),
        'No queda pantalla ni ruta de instalación.');
    $credenciales = (string) file_get_contents($raiz . '/Configuration/LocalDatabaseCredentials.php');
    $assert(preg_match('/fopen|file_put_contents|rename\(|mkdir\(|unlink\(/', $credenciales) === 0,
        'Nada escribe ni borra la conexión heredada: solo se lee.');
    $instalador = (string) file_get_contents($raiz . '/Application/Service/LocalDatabaseInstaller.php');
    $assert(preg_match('/\b(CREATE\s+USER|ALTER\s+USER|GRANT)\b/', $instalador) === 0, 'La preparación no toca cuentas de MySQL.');
    // Lo que realmente se ejecuta: el esquema y las tres semillas. Ninguno borra ni altera tablas.
    foreach (['SqlScripts/000instalacioncompleta.sql', 'SeedData/101initialpagometodo.sql', 'SeedData/102administrador.sql', 'SeedData/104catalogosanimal.sql'] as $sql) {
        $assert(str_contains($instalador, "'{$sql}'") && preg_match('/^\s*(DROP|TRUNCATE|DELETE|ALTER\s+TABLE|RENAME)\b/im', (string) file_get_contents("{$raiz}/Database/{$sql}")) === 0,
            "{$sql} se ejecuta en la preparación y no puede borrar ni alterar tablas.");
    }
    $assert(Arranque::failureReason([2002, 2002]) === LocalDatabaseUnavailable::SIN_CONEXION
        && Arranque::failureReason([2002, 1045]) === LocalDatabaseUnavailable::CREDENCIALES
        && Arranque::failureReason([1698]) === LocalDatabaseUnavailable::CREDENCIALES
        && Arranque::failureReason([1044]) === LocalDatabaseUnavailable::SIN_PERMISO,
        'MySQL apagado, cuenta rechazada y cuenta sin derechos se informan como motivos distintos.');
} finally {
    if (is_file($legacyFile) && str_contains((string) file_get_contents($legacyFile), '"heredado"')) {
        unlink($legacyFile);
        if ($createdDirectory) {
            rmdir($legacyDirectory);
        }
    }
    foreach ($previousEnvironment as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
}

echo "OK local_database_bootstrap_test: contexto local, visitas que pueden preparar, conexiones candidatas y sin asistente.\n";
