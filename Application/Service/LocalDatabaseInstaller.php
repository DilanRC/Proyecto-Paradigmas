<?php

declare(strict_types=1);

namespace Application\Service;

use Configuration\LocalDatabaseCredentials;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 2) . '/Configuration/Configuration.php';
require_once dirname(__DIR__, 2) . '/Configuration/LocalDatabaseCredentials.php';
require_once dirname(__DIR__, 2) . '/Tools/schema-manifest.php';

final class LocalDatabaseInstallerException extends RuntimeException
{
    public int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        $this->httpStatus = $httpStatus;
        parent::__construct($message);
    }
}

final class LocalDatabaseInstaller
{
    private const DATABASE_NAME = 'bdmercadoganadero';
    private const TABLE_COUNT = 48;
    private const SEED_DEFINITIONS = [
        '101initialpagometodo.sql' => ['required' => true, 'label' => 'Método de pago inicial'],
        '102administrador.sql' => ['required' => true, 'label' => 'Política de acceso administrativo'],
        '103exampleproductores.sql' => ['required' => false, 'label' => 'Personas y fincas de demostración'],
        '104catalogosanimal.sql' => ['required' => true, 'label' => 'Catálogos de animales y vacunas'],
    ];

    private function __construct()
    {
    }

    public static function requestIsLocal(array $server): bool
    {
        if (!self::environmentIsSupported()) {
            return false;
        }
        foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $proxyHeader) {
            if (isset($server[$proxyHeader]) && trim((string) $server[$proxyHeader]) !== '') {
                return false;
            }
        }

        $remoteAddress = strtolower(trim((string) ($server['REMOTE_ADDR'] ?? '')));
        $hostHeader = (string) ($server['HTTP_HOST'] ?? '');
        $host = parse_url('http://' . $hostHeader, PHP_URL_HOST);
        if (!is_string($host)) {
            return false;
        }
        $host = strtolower(trim($host, '[]'));

        return in_array($remoteAddress, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function environmentIsSupported(): bool
    {
        $vercel = getenv('VERCEL');
        if ($vercel !== false && trim($vercel) !== '') {
            return false;
        }
        foreach (['POSTGRES_URL', 'POSTGRES_URL_NON_POOLING'] as $variable) {
            $value = getenv($variable);
            if ($value !== false && trim($value) !== '') {
                return false;
            }
        }

        $configuredHost = getenv('DB_HOST');
        if ($configuredHost !== false && trim($configuredHost) !== ''
            && !LocalDatabaseCredentials::validHost(trim($configuredHost))) {
            return false;
        }
        $configuredDatabase = getenv('DB_NAME');
        if ($configuredDatabase !== false && trim($configuredDatabase) !== ''
            && trim($configuredDatabase) !== self::DATABASE_NAME) {
            return false;
        }

        return true;
    }

    public static function defaultCredentials(): array
    {
        $configuredHost = getenv('DB_HOST');
        if (!LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment()) {
            $stored = LocalDatabaseCredentials::load();
            if ($stored !== null) {
                return $stored;
            }
        }

        $host = trim((string) ($configuredHost ?: '127.0.0.1'));
        $port = trim((string) (getenv('DB_PORT') ?: '3306'));
        $user = trim((string) (getenv('DB_USER') ?: 'root'));
        $password = getenv('DB_PASS');

        return [
            'database' => self::DATABASE_NAME,
            'host' => LocalDatabaseCredentials::validHost($host) ? $host : '127.0.0.1',
            'port' => ctype_digit($port) ? (int) $port : 3306,
            'user' => $user === '' ? 'root' : $user,
            'password' => $password === false ? '' : $password,
        ];
    }

    public static function credentialsManagedByEnvironment(): bool
    {
        return LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment();
    }

    public static function validateCredentials(array $input): array
    {
        $host = trim((string) ($input['host'] ?? ''));
        $portValue = trim((string) ($input['port'] ?? ''));
        $user = trim((string) ($input['user'] ?? ''));
        $password = $input['password'] ?? '';
        if (!LocalDatabaseCredentials::validHost($host)) {
            throw new LocalDatabaseInstallerException('El host debe ser localhost, 127.0.0.1 o ::1.');
        }
        if (!ctype_digit($portValue) || (int) $portValue < 1 || (int) $portValue > 65535) {
            throw new LocalDatabaseInstallerException('El puerto debe ser un número entre 1 y 65535.');
        }
        if ($user === '' || strlen($user) > 80 || str_contains($user, "\0")) {
            throw new LocalDatabaseInstallerException('Escriba el usuario local de MySQL.');
        }
        if (!is_string($password) || strlen($password) > 4096 || str_contains($password, "\0")) {
            throw new LocalDatabaseInstallerException('La contraseña no tiene un formato válido.');
        }

        return [
            'database' => self::DATABASE_NAME,
            'host' => $host,
            'port' => (int) $portValue,
            'user' => $user,
            'password' => $password,
        ];
    }

    public static function inspect(array $input): array
    {
        $credentials = self::validateCredentials($input);
        $server = self::connectServer($credentials);
        try {
            $version = (string) $server->query('SELECT VERSION()')->fetchColumn();
            $exists = self::databaseExists($server);
            $state = [
                'connected' => true,
                'mysqlVersion' => $version,
                'databaseExists' => $exists,
                'database' => self::DATABASE_NAME,
                'tableCount' => 0,
                'expectedTableCount' => self::TABLE_COUNT,
                'primaryKeyCount' => 0,
                'expectedPrimaryKeyCount' => self::TABLE_COUNT,
                'missingTables' => [],
                'missingPrimaryKeys' => [],
                'schemaComplete' => false,
                'seeds' => self::emptySeedStatuses(),
                'installationState' => LocalDatabaseCredentials::state(),
            ];
            if (!$exists) {
                return $state;
            }

            $manifest = self::manifest();
            $tablesQuery = $server->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = :database AND TABLE_TYPE = \'BASE TABLE\''
            );
            $tablesQuery->execute(['database' => self::DATABASE_NAME]);
            $tables = array_map('strval', $tablesQuery->fetchAll(PDO::FETCH_COLUMN));
            $state['tableCount'] = count($tables);
            $tableSet = array_fill_keys($tables, true);
            $state['missingTables'] = array_values(array_diff($manifest['tables_ordered'], $tables));

            $primaryKeysQuery = $server->prepare(
                'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE CONSTRAINT_SCHEMA = :database AND CONSTRAINT_NAME = \'PRIMARY\'
                 ORDER BY TABLE_NAME, ORDINAL_POSITION'
            );
            $primaryKeysQuery->execute(['database' => self::DATABASE_NAME]);
            $primaryKeys = [];
            foreach ($primaryKeysQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $primaryKeys[(string) $row['TABLE_NAME']][] = (string) $row['COLUMN_NAME'];
            }
            foreach ($manifest['tables_ordered'] as $table) {
                if (!isset($tableSet[$table])) {
                    $state['missingPrimaryKeys'][] = $table;
                    continue;
                }
                if (($primaryKeys[$table] ?? []) === [$table . 'id']) {
                    $state['primaryKeyCount']++;
                } else {
                    $state['missingPrimaryKeys'][] = $table;
                }
            }
            $state['schemaComplete'] = $state['tableCount'] === self::TABLE_COUNT
                && $state['missingTables'] === [] && $state['missingPrimaryKeys'] === [];
            $state['seeds'] = self::inspectSeeds($server, $tableSet);

            return $state;
        } catch (PDOException) {
            throw new LocalDatabaseInstallerException(
                'MySQL está disponible, pero este usuario no puede consultar la base seleccionada.',
                403,
            );
        }
    }

    public static function saveExistingConnection(array $input): array
    {
        $credentials = self::validateCredentials($input);
        self::assertMatchesEnvironment($credentials);
        $inspection = self::inspect($credentials);
        if (!$inspection['databaseExists'] || !$inspection['schemaComplete']) {
            throw new LocalDatabaseInstallerException(
                'Solo se guarda la conexión cuando la base existente tiene las 48 tablas y sus 48 llaves primarias.',
                409,
            );
        }
        if (LocalDatabaseCredentials::state() !== null) {
            throw new LocalDatabaseInstallerException('Esta carpeta ya tiene una instalación local registrada.', 409);
        }

        try {
            LocalDatabaseCredentials::store($credentials, 'existing');
        } catch (Throwable) {
            throw new LocalDatabaseInstallerException(
                'PHP no pudo guardar la conexión en almacenamiento temporal privado. No se modificó la base de datos.',
                500,
            );
        }

        return $inspection;
    }

    public static function install(array $input): array
    {
        $credentials = self::validateCredentials($input);
        self::assertMatchesEnvironment($credentials);
        if (!extension_loaded('mysqli')) {
            throw new LocalDatabaseInstallerException('La extensión mysqli de PHP no está disponible.', 500);
        }
        self::manifest();
        $schema = self::readSqlFile('SqlScripts/000instalacioncompleta.sql');
        $paymentSeed = self::readSqlFile('SeedData/101initialpagometodo.sql');
        $adminSeed = self::readSqlFile('SeedData/102administrador.sql');
        $catalogSeed = self::readSqlFile('SeedData/104catalogosanimal.sql');
        if (preg_match('/^\s*(?:DROP|TRUNCATE)\b/im', $schema . "\n" . $paymentSeed . "\n" . $adminSeed . "\n" . $catalogSeed) === 1) {
            throw new LocalDatabaseInstallerException('El SQL del paquete contiene una operación destructiva.', 500);
        }
        $server = self::connectServer($credentials);
        $lockName = 'ganadocerca_' . substr(hash('sha256', self::DATABASE_NAME), 0, 48);
        $lockHeld = false;
        $attemptStarted = false;

        try {
            $lock = $server->prepare('SELECT GET_LOCK(:lock_name, 0)');
            $lock->execute(['lock_name' => $lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                throw new LocalDatabaseInstallerException(
                    'Otra pestaña está comprobando o instalando la base. Espere y vuelva a cargar la página.',
                    409,
                );
            }
            $lockHeld = true;
            if (self::databaseExists($server)) {
                throw new LocalDatabaseInstallerException(
                    'La base ya existe. No se aplicó ningún cambio; vuelva a cargar la página para revisar su estado.',
                    409,
                );
            }
            if (LocalDatabaseCredentials::state() !== null) {
                throw new LocalDatabaseInstallerException(
                    'Esta carpeta ya inició una instalación. No se repetirá el SQL; revise el estado antes de continuar.',
                    409,
                );
            }

            try {
                LocalDatabaseCredentials::store($credentials, 'installing');
            } catch (Throwable) {
                throw new LocalDatabaseInstallerException(
                    'PHP no puede guardar la conexión local con permisos privados. La base no se modificó.',
                    500,
                );
            }
            $attemptStarted = true;

            // CREATE DATABASE sin IF NOT EXISTS cierra la ventana entre la comprobación y el DDL:
            // una base que aparezca mientras tanto no se adopta ni se modifica.
            self::runSqlBatch(
                $credentials,
                'CREATE DATABASE bdmercadoganadero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
            );
            self::runSqlBatch($credentials, $schema);
            self::runSqlBatch($credentials, $paymentSeed);
            self::runSqlBatch($credentials, $adminSeed);
            self::runSqlBatch($credentials, $catalogSeed);

            $inspection = self::inspect($credentials);
            if (!$inspection['schemaComplete'] || !$inspection['seeds']['101initialpagometodo.sql']['complete']
                || !$inspection['seeds']['102administrador.sql']['complete']
                || !$inspection['seeds']['104catalogosanimal.sql']['complete']) {
                throw new LocalDatabaseInstallerException(
                    'MySQL terminó la importación, pero la comprobación final encontró elementos incompletos. '
                    . 'No se repetirá automáticamente una importación parcial.',
                    500,
                );
            }
            LocalDatabaseCredentials::store($credentials, 'installed');

            return $inspection;
        } catch (PDOException) {
            self::clearAttemptIfNoDatabase($server, $attemptStarted);
            throw new LocalDatabaseInstallerException(
                'No se pudo crear o comprobar la base. El usuario de MySQL puede carecer de permiso CREATE DATABASE.',
                403,
            );
        } catch (LocalDatabaseInstallerException $exception) {
            self::clearAttemptIfNoDatabase($server, $attemptStarted);
            throw $exception;
        } catch (Throwable) {
            self::clearAttemptIfNoDatabase($server, $attemptStarted);
            throw new LocalDatabaseInstallerException(
                'Falló una parte de la instalación. MySQL puede conservar tablas creadas antes del error; '
                . 'el instalador no hará rollback ni repetirá el SQL.',
                500,
            );
        } finally {
            if ($lockHeld) {
                try {
                    $release = $server->prepare('SELECT RELEASE_LOCK(:lock_name)');
                    $release->execute(['lock_name' => $lockName]);
                } catch (Throwable) {
                }
            }
            $server = null;
        }
    }

    public static function seedDefinitions(): array
    {
        return self::SEED_DEFINITIONS;
    }

    private static function connectServer(array $credentials): PDO
    {
        try {
            return new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $credentials['host'], $credentials['port']),
                $credentials['user'],
                $credentials['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ],
            );
        } catch (PDOException) {
            throw new LocalDatabaseInstallerException(
                'No fue posible conectar a MySQL. Compruebe el servicio, el host, el puerto y las credenciales.',
                503,
            );
        }
    }

    private static function assertMatchesEnvironment(array $credentials): void
    {
        if (!self::credentialsManagedByEnvironment()) {
            return;
        }
        $expected = self::defaultCredentials();
        if ($credentials['host'] !== $expected['host'] || $credentials['port'] !== $expected['port']
            || $credentials['user'] !== $expected['user'] || $credentials['password'] !== $expected['password']) {
            throw new LocalDatabaseInstallerException(
                'Apache ya define la conexión MySQL. Use esos mismos valores o quite la configuración local antes de continuar.',
                409,
            );
        }
    }

    private static function databaseExists(PDO $server): bool
    {
        $query = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :database');
        $query->execute(['database' => self::DATABASE_NAME]);

        return (int) $query->fetchColumn() > 0;
    }

    private static function clearAttemptIfNoDatabase(PDO $server, bool $attemptStarted): void
    {
        if (!$attemptStarted) {
            return;
        }
        try {
            if (!self::databaseExists($server)) {
                LocalDatabaseCredentials::clearFailedAttempt();
            }
        } catch (Throwable) {
        }
    }

    private static function manifest(): array
    {
        $manifest = \schema_manifest();
        if (($manifest['database'] ?? null) !== self::DATABASE_NAME
            || ($manifest['table_count'] ?? null) !== self::TABLE_COUNT) {
            throw new LocalDatabaseInstallerException(
                'El SQL del paquete no coincide con la base y las 48 tablas esperadas.',
                500,
            );
        }

        return $manifest;
    }

    private static function inspectSeeds(PDO $server, array $tables): array
    {
        $statuses = self::emptySeedStatuses();
        if (isset($tables['tbpagometodo'])) {
            $method = $server->query(
                "SELECT COUNT(*) FROM bdmercadoganadero.tbpagometodo
                 WHERE tbpagometodoid = 1 AND tbpagometodonombre = 'Efectivo'
                   AND tbpagometododescripcion = 'Pago realizado en efectivo' AND tbpagometodoactivo = 1"
            );
            $statuses['101initialpagometodo.sql']['complete'] = (int) $method->fetchColumn() === 1;
            $statuses['101initialpagometodo.sql']['partial'] = !$statuses['101initialpagometodo.sql']['complete']
                && (int) $server->query('SELECT COUNT(*) FROM bdmercadoganadero.tbpagometodo')->fetchColumn() > 0;
        }
        if (isset($tables['tbadministrador'])) {
            $count = (int) $server->query(
                'SELECT COUNT(*) FROM bdmercadoganadero.tbadministrador WHERE tbadministradorestado = 1'
            )->fetchColumn();
            $statuses['102administrador.sql']['complete'] = $count > 0;
            $statuses['102administrador.sql']['optionalMessage'] = $count > 0
                ? 'Hay una política activa; el archivo no crea usuarios ni contraseñas.'
                : 'Falta la política de acceso; no se modificó la base existente.';
        }
        if (isset($tables['tbpersona'])) {
            $demo = $server->query(
                "SELECT COUNT(DISTINCT tbpersonaidentificacionnumero)
                 FROM bdmercadoganadero.tbpersona
                 WHERE tbpersonaidentificacionnumero IN ('101110111', '3101111111')"
            );
            $demoCount = (int) $demo->fetchColumn();
            $statuses['103exampleproductores.sql']['complete'] = $demoCount === 2;
            $statuses['103exampleproductores.sql']['partial'] = $demoCount === 1;
            $statuses['103exampleproductores.sql']['optionalMessage'] = $demoCount === 0
                ? 'Los registros ficticios no se cargan en la instalación inicial.'
                : 'Se detectaron registros de demostración existentes.';
        }

        $catalogs = [
            'tbespecie' => 6,
            'tbanimaltipo' => 26,
            'tbraza' => 42,
            'tbvacuna' => 10,
        ];
        $catalogsComplete = true;
        $catalogsPartial = false;
        foreach ($catalogs as $table => $expectedCount) {
            if (!isset($tables[$table])) {
                $catalogsComplete = false;
                continue;
            }
            $id = $table . 'id';
            $count = (int) $server->query(
                "SELECT COUNT(DISTINCT {$id}) FROM bdmercadoganadero.{$table} WHERE {$id} BETWEEN 1 AND {$expectedCount}"
            )->fetchColumn();
            if ($count !== $expectedCount) {
                $catalogsComplete = false;
                $catalogsPartial = $catalogsPartial || $count > 0;
            }
        }
        $statuses['104catalogosanimal.sql']['complete'] = $catalogsComplete;
        $statuses['104catalogosanimal.sql']['partial'] = !$catalogsComplete && $catalogsPartial;
        $statuses['104catalogosanimal.sql']['optionalMessage'] = '';

        return $statuses;
    }

    private static function emptySeedStatuses(): array
    {
        $statuses = [];
        foreach (self::SEED_DEFINITIONS as $file => $definition) {
            $statuses[$file] = [
                'label' => $definition['label'],
                'required' => $definition['required'],
                'complete' => false,
                'partial' => false,
                'optionalMessage' => $definition['required'] ? 'No se pudo verificar.' : 'No se cargó.',
            ];
        }

        return $statuses;
    }

    private static function readSqlFile(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . '/Database/' . $relativePath;
        $contents = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($contents) || trim($contents) === '') {
            throw new LocalDatabaseInstallerException('Falta un archivo SQL requerido en el paquete: ' . basename($path), 500);
        }

        return $contents;
    }

    private static function runSqlBatch(array $credentials, string $sql): void
    {
        if (!extension_loaded('mysqli')) {
            throw new LocalDatabaseInstallerException('La extensión mysqli de PHP no está disponible.', 500);
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $connection = mysqli_init();
        if (!$connection instanceof \mysqli) {
            throw new LocalDatabaseInstallerException('PHP no pudo iniciar la conexión MySQL.', 500);
        }
        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (!$connection->real_connect(
            $credentials['host'],
            $credentials['user'],
            $credentials['password'],
            null,
            $credentials['port'],
        )) {
            $connection->close();
            throw new LocalDatabaseInstallerException('La conexión a MySQL se perdió durante la importación.', 503);
        }
        $connection->set_charset('utf8mb4');
        if (!$connection->multi_query($sql)) {
            $connection->close();
            throw new LocalDatabaseInstallerException(
                'MySQL rechazó una sentencia del archivo SQL. La instalación puede haber quedado parcial.',
                500,
            );
        }
        do {
            $result = $connection->store_result();
            if ($result instanceof \mysqli_result) {
                $result->free();
            }
            if ($connection->errno !== 0) {
                $connection->close();
                throw new LocalDatabaseInstallerException(
                    'MySQL rechazó una sentencia del archivo SQL. La instalación puede haber quedado parcial.',
                    500,
                );
            }
            if (!$connection->more_results()) {
                break;
            }
            if (!$connection->next_result()) {
                $connection->close();
                throw new LocalDatabaseInstallerException(
                    'MySQL rechazó una sentencia del archivo SQL. La instalación puede haber quedado parcial.',
                    500,
                );
            }
        } while (true);
        $connection->close();
    }
}
