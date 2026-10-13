<?php

declare(strict_types=1);

require_once __DIR__ . '/../Application/Service/LocalDatabaseInstaller.php';
require_once __DIR__ . '/../Configuration/LocalDatabaseCredentials.php';

use Application\Service\LocalDatabaseInstaller;
use Application\Service\LocalDatabaseInstallerException;
use Configuration\LocalDatabaseCredentials;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectInvalid = static function (array $input) use ($assert): void {
    try {
        LocalDatabaseInstaller::validateCredentials($input);
    } catch (LocalDatabaseInstallerException) {
        return;
    }
    $assert(false, 'Las credenciales inválidas deben rechazarse.');
};

$environmentNames = [
    'VERCEL', 'POSTGRES_URL', 'POSTGRES_URL_NON_POOLING',
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
];
$previousEnvironment = [];
foreach ($environmentNames as $name) {
    $previousEnvironment[$name] = getenv($name);
    putenv($name);
}
$directory = null;

try {
    $assert(LocalDatabaseInstaller::environmentIsSupported(), 'El entorno local limpio debe aceptarse.');
    $assert(LocalDatabaseInstaller::requestIsLocal([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_HOST' => 'localhost:18082',
    ]), 'Debe aceptar una solicitud localhost con puerto.');
    $assert(LocalDatabaseInstaller::requestIsLocal([
        'REMOTE_ADDR' => '::1',
        'HTTP_HOST' => '[::1]:18082',
    ]), 'Debe aceptar loopback IPv6.');
    $assert(!LocalDatabaseInstaller::requestIsLocal([
        'REMOTE_ADDR' => '192.0.2.10',
        'HTTP_HOST' => 'localhost',
    ]), 'Debe rechazar conexiones que no vengan de loopback.');
    $assert(!LocalDatabaseInstaller::requestIsLocal([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_HOST' => 'example.test',
    ]), 'Debe rechazar un Host externo incluso desde loopback.');
    $assert(!LocalDatabaseInstaller::requestIsLocal([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_HOST' => 'localhost',
        'HTTP_X_FORWARDED_FOR' => '192.0.2.10',
    ]), 'Debe rechazar solicitudes con encabezados de proxy.');

    $valid = LocalDatabaseInstaller::validateCredentials([
        'host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => '',
    ]);
    $assert($valid['database'] === 'bdmercadoganadero' && $valid['port'] === 3306,
        'Debe fijar la base objetivo y normalizar el puerto.');
    $expectInvalid(['host' => 'db.example.test', 'port' => '3306', 'user' => 'root', 'password' => '']);
    $expectInvalid(['host' => '127.0.0.1', 'port' => '0', 'user' => 'root', 'password' => '']);
    $expectInvalid(['host' => '127.0.0.1', 'port' => '3306', 'user' => '', 'password' => '']);

    $assert(LocalDatabaseCredentials::state() === null,
        'No debe existir estado previo de esta carpeta antes de la prueba temporal.');
    $directory = LocalDatabaseCredentials::sessionDirectory();
    $assert((fileperms($directory) & 0777) === 0700, 'La carpeta privada debe tener permisos 0700.');
    LocalDatabaseCredentials::store([
        'host' => '127.0.0.1', 'port' => 3306, 'user' => 'test-only', 'password' => 'test-only',
    ], 'installing');
    $stored = LocalDatabaseCredentials::load();
    $assert(is_array($stored) && $stored['state'] === 'installing' && $stored['password'] === 'test-only',
        'Debe poder guardar y recuperar el estado temporal de instalación.');
    foreach ($environmentNames as $name) {
        if (str_starts_with($name, 'DB_')) {
            putenv($name . '=');
        }
    }
    $assert(!LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment()
        && !LocalDatabaseInstaller::credentialsManagedByEnvironment(),
        'Las asignaciones vacías del archivo de ejemplo no deben desplazar la conexión guardada.');
    $assert(LocalDatabaseInstaller::defaultCredentials()['user'] === 'test-only',
        'Sin valores DB_* explícitos, el asistente debe reutilizar su conexión guardada.');
    putenv('DB_PORT=13306');
    putenv('DB_USER=environment-user');
    putenv('DB_PASS=environment-password');
    $environmentCredentials = LocalDatabaseInstaller::defaultCredentials();
    $assert(LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment()
        && LocalDatabaseInstaller::credentialsManagedByEnvironment()
        && $environmentCredentials['port'] === 13306
        && $environmentCredentials['user'] === 'environment-user'
        && $environmentCredentials['password'] === 'environment-password',
        'Cualquier campo DB_* no vacío debe dar prioridad al entorno sobre la conexión guardada.');
    $assert((fileperms($directory . DIRECTORY_SEPARATOR . 'mysql.json') & 0777) === 0600,
        'El archivo temporal de conexión debe tener permisos 0600.');
    LocalDatabaseCredentials::clearFailedAttempt();
    $assert(LocalDatabaseCredentials::state() === null, 'Debe poder borrar el marcador de un intento sin base.');
    @rmdir($directory);
} finally {
    if (LocalDatabaseCredentials::state() === 'installing') {
        LocalDatabaseCredentials::clearFailedAttempt();
    }
    if (is_string($directory)) {
        @rmdir($directory);
    }
    foreach ($previousEnvironment as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
}

echo "OK local_database_installer_test: límites loopback, validación y almacenamiento temporal privado.\n";
