<?php

declare(strict_types=1);

namespace Configuration;

use RuntimeException;

require_once __DIR__ . '/Configuration.php';

final class LocalDatabaseCredentials
{
    private const DATABASE_NAME = 'bdmercadoganadero';
    private const INSTALLER_STATE_ORDER = ['existing' => 1, 'installing' => 2, 'installed' => 3];

    private function __construct()
    {
    }

    public static function load(): ?array
    {
        if (!self::supportsPrivateStoragePlatform()) {
            return null;
        }
        try {
            $directory = self::directoryPath();
        } catch (RuntimeException) {
            return null;
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'mysql.json';
        if (is_link($directory) || is_link($path) || !is_file($path)
            || !self::ownedByCurrentUser($directory) || !self::hasPrivatePermissions($directory, 0700)
            || !self::hasPrivatePermissions($path, 0600)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }
        try {
            $configuration = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($configuration) || ($configuration['version'] ?? null) !== 1
            || ($configuration['database'] ?? null) !== self::DATABASE_NAME
            || !in_array($configuration['state'] ?? null, array_keys(self::INSTALLER_STATE_ORDER), true)
            || !self::validHost($configuration['host'] ?? null)
            || !is_int($configuration['port'] ?? null) || $configuration['port'] < 1 || $configuration['port'] > 65535
            || !is_string($configuration['user'] ?? null) || $configuration['user'] === ''
            || !is_string($configuration['password'] ?? null)) {
            return null;
        }

        return $configuration;
    }

    public static function state(): ?string
    {
        return self::load()['state'] ?? null;
    }

    public static function sessionDirectory(): string
    {
        return self::ensurePrivateDirectory();
    }

    public static function supportsPrivateStoragePlatform(): bool
    {
        return PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid');
    }

    /**
     * ¿El servidor (Apache, Docker, la terminal) fija la conexión MySQL? Los valores que solo vienen
     * del archivo `.env` no cuentan: son el punto de partida que el asistente puede reemplazar.
     */
    public static function hasNonEmptyConnectionEnvironment(): bool
    {
        $fromFile = environmentFileValues();
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
            $value = getenv($name);
            if ($value !== false && trim($value) !== '' && ($fromFile[$name] ?? null) !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * `DB_DRIVER=mysql` obliga a usar MySQL aunque el servidor herede una `POSTGRES_URL`.
     * Producción no define la variable y sigue eligiendo PostgreSQL.
     */
    public static function mysqlForced(): bool
    {
        return strtolower(trim((string) getenv('DB_DRIVER'))) === 'mysql';
    }

    /** Olvida la conexión guardada. Solo tiene sentido cuando la base ya no existe. */
    public static function discard(): void
    {
        try {
            $path = self::directoryPath() . DIRECTORY_SEPARATOR . 'mysql.json';
        } catch (RuntimeException) {
            return;
        }
        if (!is_link($path) && is_file($path)) {
            @unlink($path);
        }
    }

    public static function clearFailedAttempt(): void
    {
        if (self::state() === 'installing') {
            self::discard();
        }
    }

    public static function store(array $credentials, string $state): void
    {
        if (!in_array($state, array_keys(self::INSTALLER_STATE_ORDER), true)
            || !self::validHost($credentials['host'] ?? null)
            || !is_int($credentials['port'] ?? null) || $credentials['port'] < 1 || $credentials['port'] > 65535
            || !is_string($credentials['user'] ?? null) || $credentials['user'] === ''
            || !is_string($credentials['password'] ?? null)) {
            throw new RuntimeException('No se pudo guardar la conexión local.');
        }

        $previous = self::load();
        if ($previous !== null
            && self::INSTALLER_STATE_ORDER[$state] < self::INSTALLER_STATE_ORDER[$previous['state']]) {
            throw new RuntimeException('No se puede cambiar el estado de una instalación ya iniciada.');
        }

        $directory = self::ensurePrivateDirectory();

        $path = $directory . DIRECTORY_SEPARATOR . 'mysql.json';
        if (is_link($path)) {
            throw new RuntimeException('El archivo privado de conexión no es seguro.');
        }
        $temporary = $directory . DIRECTORY_SEPARATOR . '.mysql-' . bin2hex(random_bytes(12)) . '.tmp';
        $payload = json_encode([
            'version' => 1,
            'database' => self::DATABASE_NAME,
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'user' => $credentials['user'],
            'password' => $credentials['password'],
            'state' => $state,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $handle = @fopen($temporary, 'x');
        if ($handle === false) {
            throw new RuntimeException('PHP no puede guardar la conexión local.');
        }
        $payloadWritten = false;
        try {
            $offset = 0;
            $length = strlen($payload);
            while ($offset < $length) {
                $written = fwrite($handle, substr($payload, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('PHP no pudo terminar de guardar la conexión local.');
                }
                $offset += $written;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('PHP no pudo terminar de guardar la conexión local.');
            }
            $payloadWritten = true;
        } finally {
            fclose($handle);
            if (!$payloadWritten) {
                @unlink($temporary);
            }
        }

        if (!@chmod($temporary, 0600) || !self::hasPrivatePermissions($temporary, 0600)
            || !@rename($temporary, $path) || !@chmod($path, 0600)) {
            @unlink($temporary);
            throw new RuntimeException('PHP no pudo proteger la conexión local.');
        }
    }

    public static function validHost(mixed $host): bool
    {
        return is_string($host) && in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);
    }

    private static function directoryPath(): string
    {
        $projectRoot = realpath(dirname(__DIR__));
        if ($projectRoot === false) {
            throw new RuntimeException('No se pudo identificar la carpeta de esta instalación.');
        }
        // XAMPP guarda sus sesiones en /opt/lampp/temp, que sobrevive a un reinicio del equipo;
        // /tmp no. Se lee de php.ini (get_cfg_var) porque el asistente cambia session.save_path
        // en tiempo de ejecución; un valor con formato "N;/ruta" no es una carpeta y cae a /tmp.
        // Nunca se usa una carpeta dentro del proyecto (sería descargable).
        $temporaryRoot = false;
        foreach ([(string) get_cfg_var('session.save_path'), sys_get_temp_dir()] as $candidate) {
            $candidate = $candidate === '' ? false : realpath($candidate);
            if ($candidate !== false && is_dir($candidate) && is_writable($candidate)
                && !str_starts_with($candidate . DIRECTORY_SEPARATOR, $projectRoot . DIRECTORY_SEPARATOR)) {
                $temporaryRoot = $candidate;
                break;
            }
        }
        if ($temporaryRoot === false) {
            throw new RuntimeException('El servidor PHP no tiene un directorio temporal disponible.');
        }
        $identifier = substr(hash('sha256', 'ganado-cerca:' . $projectRoot . ':' . self::DATABASE_NAME), 0, 24);

        return $temporaryRoot . DIRECTORY_SEPARATOR . 'ganado-cerca-' . $identifier;
    }

    private static function ensurePrivateDirectory(): string
    {
        if (!self::supportsPrivateStoragePlatform()) {
            throw new RuntimeException('El almacenamiento privado del asistente requiere XAMPP en Linux con la extensión POSIX.');
        }
        $directory = self::directoryPath();
        if (is_link($directory)) {
            throw new RuntimeException('El almacenamiento privado local no es seguro.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700)) {
            throw new RuntimeException('PHP no puede crear el almacenamiento privado local.');
        }
        if (!self::ownedByCurrentUser($directory) || !@chmod($directory, 0700)
            || !self::hasPrivatePermissions($directory, 0700)) {
            throw new RuntimeException('PHP no puede proteger el almacenamiento privado local.');
        }

        return $directory;
    }

    private static function ownedByCurrentUser(string $path): bool
    {
        if (!function_exists('posix_geteuid')) {
            return true;
        }
        $owner = fileowner($path);

        return $owner !== false && $owner === posix_geteuid();
    }

    private static function hasPrivatePermissions(string $path, int $expected): bool
    {
        $permissions = fileperms($path);

        return $permissions !== false && ($permissions & 0777) === $expected;
    }
}
