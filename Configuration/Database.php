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

        $host = self::environmentVariable('DB_HOST', '127.0.0.1');
        $port = self::environmentVariable('DB_PORT', '3306');
        $databaseName = self::environmentVariable('DB_NAME', 'tinder_cows');
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

    private static function environmentVariable(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }
}
