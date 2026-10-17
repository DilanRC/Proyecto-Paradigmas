<?php

declare(strict_types=1);

namespace Configuration;

use Application\LocalDatabaseUnavailable;
use Application\Service\LocalDatabaseInstaller;
use PDO;
use PDOException;

require_once __DIR__ . '/Configuration.php';
require_once __DIR__ . '/LocalDatabaseCredentials.php';
require_once dirname(__DIR__) . '/Application/LocalDatabaseUnavailable.php';

/**
 * Arranque automático de la base en XAMPP: elige la conexión MySQL local, reutiliza la base que ya
 * exista y, si falta, la crea. Reemplaza al asistente de instalación; no guarda estado ni credenciales.
 * Nunca corre en Vercel, con PostgreSQL ni contra un MySQL que no sea de esta computadora.
 */
final class LocalDatabaseBootstrap
{
    public const DATABASE_NAME = 'bdmercadoganadero';
    private const STORED = 'conexión guardada por el asistente anterior';

    /** Conexión con la que abrió la base esta petición: la preparación usa exactamente la misma. */
    private static ?array $active = null;

    private function __construct()
    {
    }

    /** ¿La configuración apunta al MySQL de esta computadora y a la base del proyecto? */
    public static function applies(): bool
    {
        $vercel = getenv('VERCEL');
        if ($vercel !== false && trim($vercel) !== '') {
            return false;
        }
        foreach (LocalDatabaseCredentials::mysqlForced() ? [] : ['POSTGRES_URL', 'POSTGRES_URL_NON_POOLING'] as $variable) {
            $value = getenv($variable);
            if ($value !== false && trim($value) !== '') {
                return false;
            }
        }
        $host = getenv('DB_HOST');
        if ($host !== false && trim($host) !== '' && !LocalDatabaseCredentials::validHost(trim($host))) {
            return false;
        }
        $database = getenv('DB_NAME');

        return $database === false || trim($database) === '' || trim($database) === self::DATABASE_NAME;
    }

    /** Visita hecha desde la misma computadora, sin proxy: la única que puede crear o completar la base. */
    public static function requestIsLocal(array $server): bool
    {
        if (!self::applies()) {
            return false;
        }
        foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_REAL_IP', 'HTTP_VIA', 'HTTP_CLIENT_IP'] as $proxyHeader) {
            if (isset($server[$proxyHeader]) && trim((string) $server[$proxyHeader]) !== '') {
                return false;
            }
        }
        $host = parse_url('http://' . (string) ($server['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if (!is_string($host)) {
            return false;
        }

        return in_array(strtolower(trim((string) ($server['REMOTE_ADDR'] ?? ''))), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)
            && in_array(strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Conexiones legítimas, en orden. Si el servidor fija los `DB_*`, solo esa. Si no: la que guardó el
     * asistente de una versión anterior (ahí están los datos de esa instalación), la del `.env` del paquete
     * por TCP y la misma cuenta por el socket local de MySQL (XAMPP con la red de MySQL desactivada).
     * No se prueban otras cuentas ni contraseñas.
     *
     * @return list<array{origen:string,host:string,port:int,user:string,password:string,socket:?string}>
     */
    public static function candidates(): array
    {
        $host = trim((string) (getenv('DB_HOST') ?: '127.0.0.1'));
        $port = trim((string) (getenv('DB_PORT') ?: '3306'));
        $password = getenv('DB_PASS');
        $environment = [
            'origen' => 'configuración',
            'host' => LocalDatabaseCredentials::validHost($host) ? $host : '127.0.0.1',
            'port' => ctype_digit($port) ? (int) $port : 3306,
            'user' => trim((string) (getenv('DB_USER') ?: 'root')) ?: 'root',
            'password' => $password === false ? '' : $password,
            'socket' => null,
        ];
        if (LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment()) {
            return [$environment];
        }

        $candidates = [];
        $stored = LocalDatabaseCredentials::load();
        if ($stored !== null) {
            $candidates[] = ['origen' => self::STORED, 'socket' => null,
                'host' => $stored['host'], 'port' => $stored['port'], 'user' => $stored['user'], 'password' => $stored['password']];
        }
        $candidates[] = $environment;
        foreach (self::socketPaths() as $socket) {
            $candidates[] = ['origen' => 'socket local', 'socket' => $socket] + $environment;
        }

        return $candidates;
    }

    /** Abre la base del proyecto; si MySQL responde y la base no existe, la crea antes (solo visita local). */
    public static function connection(): PDO
    {
        $withoutDatabase = null;
        $storedUnreachable = false;
        $attempts = [];
        $codes = [];
        $first = null;
        foreach (self::candidates() as $candidate) {
            try {
                $connection = self::open($candidate, true);
                if ($candidate['origen'] !== 'configuración') {
                    self::log('usa la ' . self::describe($candidate));
                }
                self::$active = $candidate;

                return $connection;
            } catch (PDOException $exception) {
                $first ??= $exception;
                $codes[] = $code = (int) ($exception->errorInfo[1] ?? $exception->getCode());
                $attempts[] = self::describe($candidate) . ' → error ' . $code;
                // 1049: el servidor aceptó la cuenta y la base todavía no existe.
                if ($code === 1049) {
                    $withoutDatabase ??= $candidate;
                } elseif ($candidate['origen'] === self::STORED) {
                    $storedUnreachable = true;
                }
            }
        }
        // Herramientas y pruebas de consola conservan el error original de PDO y no instalan nada.
        if (PHP_SAPI === 'cli') {
            throw $first;
        }
        // Si la instalación anterior tiene sus datos en un servidor que hoy no responde, crear otra base en
        // un segundo servidor repartiría los registros entre los dos: se informa y no se crea nada.
        if ($withoutDatabase === null || $storedUnreachable) {
            self::log('sin conexión utilizable: ' . implode('; ', $attempts));
            throw new LocalDatabaseUnavailable(self::failureReason($codes));
        }
        self::prepare($withoutDatabase, 'la base no existe');
        self::$active = $withoutDatabase;

        return self::open($withoutDatabase, true);
    }

    /**
     * Comprobación completa para las páginas: estructura, datos iniciales y permiso de escritura.
     * Una base nueva o a medias se completa; una con otra estructura no se toca y se informa.
     */
    public static function verify(PDO $connection): void
    {
        require_once dirname(__DIR__) . '/Application/Service/LocalDatabaseInstaller.php';
        $report = LocalDatabaseInstaller::inspect($connection);
        if (!$report['schemaComplete'] || $report['seedsPending']) {
            if (!$report['schemaComplete'] && !$report['completable']) {
                self::log('base existente incompatible, no se modificó: ' . LocalDatabaseInstaller::summary($report));
                throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INCOMPATIBLE);
            }
            self::prepare(self::$active, $report['schemaComplete']
                ? 'faltan datos iniciales' : 'la base está incompleta y sin personas registradas');
        }
        if (!LocalDatabaseInstaller::writable($connection)) {
            self::log('el usuario de MySQL no puede escribir en ' . self::DATABASE_NAME);
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::SIN_PERMISO);
        }
    }

    /** @param array{host:string,port:int,user:string,password:string,socket:?string} $candidate */
    public static function open(array $candidate, bool $withDatabase): PDO
    {
        $dsn = $candidate['socket'] !== null
            ? 'mysql:unix_socket=' . $candidate['socket']
            : sprintf('mysql:host=%s;port=%d', $candidate['host'], $candidate['port']);

        return new PDO(
            $dsn . ($withDatabase ? ';dbname=' . self::DATABASE_NAME : '') . ';charset=utf8mb4',
            $candidate['user'],
            $candidate['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );
    }

    public static function log(string $message): void
    {
        error_log('[GanadoCerca] base local: ' . $message);
    }

    private static function prepare(array $candidate, string $reason): void
    {
        if (!self::requestIsLocal($_SERVER)) {
            self::log("sin preparar ({$reason}); la visita no viene de esta computadora");
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::SIN_PREPARAR);
        }
        require_once dirname(__DIR__) . '/Application/Service/LocalDatabaseInstaller.php';
        self::log("preparación automática ({$reason}) con la " . self::describe($candidate));
        self::log('preparación terminada: ' . LocalDatabaseInstaller::summary(LocalDatabaseInstaller::prepare($candidate)));
    }

    /** Sockets que PHP ya tiene configurados o que usa XAMPP en Linux; no se buscan otros. `filetype` descarta rutas que no son un socket vivo. */
    private static function socketPaths(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [];
        }
        $paths = [];
        foreach ([ini_get('pdo_mysql.default_socket'), ini_get('mysqli.default_socket'), '/opt/lampp/var/mysql/mysql.sock'] as $path) {
            if (is_string($path) && $path !== '' && !in_array($path, $paths, true) && @filetype($path) === 'socket') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Motivo cuando ninguna conexión abrió la base. 1045 y 1698: MySQL respondió y rechazó la cuenta.
     * 1044 y 1142: la cuenta entra pero no tiene derechos sobre la base. Lo demás: no hay servidor.
     *
     * @param list<int> $codes
     */
    public static function failureReason(array $codes): string
    {
        if (array_intersect([1045, 1698], $codes) !== []) {
            return LocalDatabaseUnavailable::CREDENCIALES;
        }

        return array_intersect([1044, 1142], $codes) !== []
            ? LocalDatabaseUnavailable::SIN_PERMISO : LocalDatabaseUnavailable::SIN_CONEXION;
    }

    /** Nunca incluye la contraseña. */
    private static function describe(array $candidate): string
    {
        $where = $candidate['socket'] !== null ? 'socket ' . $candidate['socket'] : "{$candidate['host']}:{$candidate['port']}";

        return "{$candidate['origen']} ({$candidate['user']}@{$where})";
    }
}
