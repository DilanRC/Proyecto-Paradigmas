<?php

declare(strict_types=1);

namespace Configuration;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        foreach (['POSTGRES_URL_NON_POOLING', 'POSTGRES_URL'] as $postgresVariable) {
            $postgresUrl = getenv($postgresVariable);
            if (is_string($postgresUrl) && $postgresUrl !== '' && $postgresUrl !== '[SENSITIVE]') {
                try {
                    self::$connection = self::postgresConnection($postgresUrl);
                    return self::$connection;
                } catch (\PDOException $exception) {
                    if ($postgresVariable === 'POSTGRES_URL') {
                        throw $exception;
                    }
                }
            }
        }

        $host = self::environmentVariable('DB_HOST', '127.0.0.1');
        $port = self::environmentVariable('DB_PORT', '3306');
        $databaseName = self::environmentVariable('DB_NAME', 'dbtindervacas');
        $username = self::environmentVariable('DB_USER', 'root');
        $password = self::environmentVariable('DB_PASS', '');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $databaseName);

        self::$connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return self::$connection;
    }

    private static function postgresConnection(string $url): PDO
    {
        $parts = parse_url($url);
        if ($parts === false || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
            || !isset($parts['host'], $parts['user'], $parts['pass'])) {
            throw new \PDOException('La URL PostgreSQL configurada no es válida.');
        }
        parse_str($parts['query'] ?? '', $query);
        $sslMode = is_string($query['sslmode'] ?? null) ? $query['sslmode'] : 'require';
        if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
            throw new \PDOException('El sslmode PostgreSQL configurado no es válido.');
        }
        $database = rawurldecode(ltrim($parts['path'] ?? '/postgres', '/')) ?: 'postgres';
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=10',
            $parts['host'], $parts['port'] ?? 5432, $database, $sslMode);

        return new PDO($dsn, rawurldecode($parts['user']), rawurldecode($parts['pass']), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    private static function environmentVariable(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }
}
