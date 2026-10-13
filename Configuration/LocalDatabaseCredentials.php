<?php

declare(strict_types=1);

namespace Configuration;

use RuntimeException;

require_once __DIR__ . '/Configuration.php';

/**
 * Qué conexión MySQL configuró el entorno y, solo para leerla, la que guardó el asistente de instalación
 * de una versión anterior (`mysql.json`). Nada escribe ya ese archivo: el arranque automático
 * (`LocalDatabaseBootstrap`) lo sigue respetando para no separar a una instalación de sus datos.
 */
final class LocalDatabaseCredentials
{
    private const DATABASE_NAME = 'bdmercadoganadero';
    private const STATES = ['existing', 'installing', 'installed'];

    private function __construct()
    {
    }

    public static function load(): ?array
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) {
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
            || !in_array($configuration['state'] ?? null, self::STATES, true)
            || !self::validHost($configuration['host'] ?? null)
            || !is_int($configuration['port'] ?? null) || $configuration['port'] < 1 || $configuration['port'] > 65535
            || !is_string($configuration['user'] ?? null) || $configuration['user'] === ''
            || !is_string($configuration['password'] ?? null)) {
            return null;
        }

        return $configuration;
    }

    /**
     * ¿El servidor (Apache, Docker, la terminal) fija la conexión MySQL? Los valores que solo vienen
     * del archivo `.env` no cuentan: son el punto de partida del paquete, que admite alternativas locales.
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
        // El asistente guardaba en la carpeta de sesiones de php.ini (/opt/lampp/temp en XAMPP) y,
        // si no era una carpeta utilizable, en /tmp. Se busca en el mismo orden.
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
