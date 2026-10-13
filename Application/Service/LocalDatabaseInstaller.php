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
    public const TABLE_COUNT = 48;
    /** Cuenta MySQL que usa la aplicación después de instalar: solo lee y escribe datos de esta base. */
    public const APP_USER = 'ganadocerca_app';
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
        foreach (LocalDatabaseCredentials::mysqlForced() ? [] : ['POSTGRES_URL', 'POSTGRES_URL_NON_POOLING'] as $variable) {
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
        if (!LocalDatabaseCredentials::hasNonEmptyConnectionEnvironment()) {
            $stored = LocalDatabaseCredentials::load();
            if ($stored !== null) {
                return $stored;
            }
        }

        return self::environmentDefaults();
    }

    /** Conexión de partida: variables del servidor o del `.env`; sin ellas, el root sin contraseña de XAMPP. */
    public static function environmentDefaults(): array
    {
        $configuredHost = getenv('DB_HOST');
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
                'columnDifferences' => [],
                'schemaComplete' => false,
                'requiredSeedsComplete' => false,
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
            $state['columnDifferences'] = self::columnDifferences($server, $manifest['columns'], $tableSet);
            $state['schemaComplete'] = $state['tableCount'] === self::TABLE_COUNT
                && $state['missingTables'] === [] && $state['missingPrimaryKeys'] === []
                && $state['columnDifferences'] === [];
            $state['seeds'] = self::inspectSeeds($server, $tableSet);
            $state['requiredSeedsComplete'] = true;
            foreach (self::SEED_DEFINITIONS as $file => $definition) {
                if ($definition['required'] && !$state['seeds'][$file]['complete']) {
                    $state['requiredSeedsComplete'] = false;
                }
            }

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
                'Solo se guarda la conexión cuando la base existente tiene las 48 tablas, sus 48 llaves primarias y las columnas del esquema actual.',
                409,
            );
        }
        // Una conexión guardada que todavía sirve no se reemplaza. Si dejó de servir (cambió la contraseña
        // o se eliminó la cuenta de la aplicación), esta conexión comprobada ocupa su lugar.
        if (self::storedConnectionWorks()) {
            throw new LocalDatabaseInstallerException('Esta carpeta ya tiene una instalación local registrada.', 409);
        }
        LocalDatabaseCredentials::discard();

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

    /** Crea la base ausente con el esquema canónico y los datos iniciales. */
    public static function install(array $input): array
    {
        return self::run($input, false);
    }

    /**
     * Termina una instalación que este asistente empezó y no pudo cerrar (Apache o MySQL se detuvieron
     * a medias). Repite el mismo SQL: crea las tablas que falten y deja los datos iniciales en su valor
     * canónico; no borra nada.
     */
    public static function resume(array $input): array
    {
        return self::run($input, true);
    }

    private static function run(array $input, bool $resume): array
    {
        $credentials = self::validateCredentials($input);
        self::assertMatchesEnvironment($credentials);
        if (!extension_loaded('mysqli')) {
            throw new LocalDatabaseInstallerException('La extensión mysqli de PHP no está disponible.', 500);
        }
        self::manifest();
        $batches = [];
        foreach (['SqlScripts/000instalacioncompleta.sql', 'SeedData/101initialpagometodo.sql',
            'SeedData/102administrador.sql', 'SeedData/104catalogosanimal.sql'] as $file) {
            $batches[] = self::readSqlFile($file);
        }
        if (preg_match('/^\s*(?:DROP|TRUNCATE)\b/im', implode("\n", $batches)) === 1) {
            throw new LocalDatabaseInstallerException('El SQL del paquete contiene una operación destructiva.', 500);
        }
        $server = self::connectServer($credentials);
        $lockName = 'ganadocerca_' . substr(hash('sha256', self::DATABASE_NAME), 0, 48);
        $lockHeld = false;
        $beforeDatabaseCreated = false;
        $previous = null;

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
            $exists = self::databaseExists($server);
            if ($resume) {
                $started = LocalDatabaseCredentials::load();
                if (!$exists || ($started['state'] ?? null) !== 'installing'
                    || $started['host'] !== $credentials['host'] || $started['port'] !== $credentials['port']) {
                    throw new LocalDatabaseInstallerException(
                        'No hay una instalación interrumpida de este asistente que terminar. No se aplicó ningún cambio.',
                        409,
                    );
                }
            } else {
                if ($exists) {
                    throw new LocalDatabaseInstallerException(
                        'La base ya existe. No se aplicó ningún cambio; vuelva a cargar la página para revisar su estado.',
                        409,
                    );
                }
                // La base no existe: un registro anterior de esta carpeta (un intento que se cortó o una base
                // que se eliminó después) ya no describe nada y no debe impedir instalar de nuevo.
                $previous = LocalDatabaseCredentials::load();
                LocalDatabaseCredentials::discard();
                try {
                    LocalDatabaseCredentials::store($credentials, 'installing');
                } catch (Throwable) {
                    throw new LocalDatabaseInstallerException(
                        'PHP no puede guardar la conexión local con permisos privados. La base no se modificó.',
                        500,
                    );
                }
                $beforeDatabaseCreated = true;

                // CREATE DATABASE sin IF NOT EXISTS cierra la ventana entre la comprobación y el DDL:
                // una base que aparezca mientras tanto no se adopta ni se modifica.
                self::runSqlBatch(
                    $credentials,
                    'CREATE DATABASE bdmercadoganadero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
                );
                $beforeDatabaseCreated = false;
            }

            foreach ($batches as $batch) {
                self::runSqlBatch($credentials, $batch);
            }

            $inspection = self::inspect($credentials);
            if (!$inspection['schemaComplete'] || !$inspection['requiredSeedsComplete']) {
                throw new LocalDatabaseInstallerException(
                    'MySQL terminó la importación, pero la comprobación final encontró elementos incompletos. '
                    . 'Compruebe la conexión de nuevo para ver el detalle y terminar la instalación.',
                    500,
                );
            }
            $application = self::limitedAccount($server, $credentials);
            LocalDatabaseCredentials::store($application ?? $credentials, 'installed');
            $inspection['applicationUser'] = ($application ?? $credentials)['user'];

            return $inspection;
        } catch (PDOException) {
            self::forgetAttemptWithoutDatabase($beforeDatabaseCreated, $previous);
            throw new LocalDatabaseInstallerException(
                'No se pudo crear o comprobar la base. El usuario de MySQL puede carecer de permiso CREATE DATABASE.',
                403,
            );
        } catch (LocalDatabaseInstallerException $exception) {
            self::forgetAttemptWithoutDatabase($beforeDatabaseCreated, $previous);
            throw $exception;
        } catch (Throwable) {
            self::forgetAttemptWithoutDatabase($beforeDatabaseCreated, $previous);
            throw new LocalDatabaseInstallerException(
                'La instalación se interrumpió. Lo ya creado se conserva; compruebe la conexión de nuevo para terminarla.',
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

    /**
     * Si falló antes de que este asistente creara la base, el intento no dejó nada y se olvida para
     * poder empezar de nuevo. Después de crearla, el estado `installing` queda y permite terminarla.
     */
    private static function forgetAttemptWithoutDatabase(bool $beforeDatabaseCreated, ?array $previous): void
    {
        if (!$beforeDatabaseCreated) {
            return;
        }
        LocalDatabaseCredentials::clearFailedAttempt();
        // Si ni siquiera se pudo crear la base (por ejemplo, una cuenta sin permisos que no la ve),
        // la conexión que la carpeta tenía guardada sigue siendo la válida.
        if ($previous !== null && LocalDatabaseCredentials::state() === null) {
            try {
                LocalDatabaseCredentials::store($previous, $previous['state']);
            } catch (Throwable) {
            }
        }
    }

    /** ¿La conexión guardada por el asistente todavía llega a la base? */
    public static function storedConnectionWorks(): bool
    {
        $stored = LocalDatabaseCredentials::load();
        if ($stored === null) {
            return false;
        }
        try {
            self::connectServer($stored)->query('SELECT COUNT(*) FROM bdmercadoganadero.tbpagometodo')->fetchColumn();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Crea (o renueva) una cuenta que solo puede leer y escribir datos de esta base, para que la
     * aplicación no siga usando la cuenta administrativa con la que se instaló. Si el usuario que
     * instala no puede crear cuentas, o la nueva no logra conectarse, devuelve null y se conserva
     * la conexión original.
     */
    private static function limitedAccount(PDO $server, array $credentials): ?array
    {
        if (self::credentialsManagedByEnvironment()) {
            return null;
        }
        try {
            $password = bin2hex(random_bytes(24));
            foreach (['localhost', '127.0.0.1', '::1'] as $host) {
                $account = "'" . self::APP_USER . "'@'{$host}'";
                $server->exec("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY " . $server->quote($password));
                $server->exec("ALTER USER {$account} IDENTIFIED BY " . $server->quote($password));
                $server->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON bdmercadoganadero.* TO {$account}");
            }
            $limited = ['user' => self::APP_USER, 'password' => $password] + $credentials;
            self::connectServer($limited)->query('SELECT COUNT(*) FROM bdmercadoganadero.tbpagometodo')->fetchColumn();

            return $limited;
        } catch (Throwable) {
            return null;
        }
    }

    /** Columnas que faltan, sobran o tienen otro tipo o nulabilidad respecto del SQL canónico. */
    private static function columnDifferences(PDO $server, array $expected, array $tables): array
    {
        $query = $server->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :database'
        );
        $query->execute(['database' => self::DATABASE_NAME]);
        $actual = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actual[(string) $row['TABLE_NAME']][(string) $row['COLUMN_NAME']] = \schema_manifest_column_signature(
                (string) $row['COLUMN_TYPE'],
                strtoupper((string) $row['IS_NULLABLE']) === 'YES',
            );
        }
        $differences = [];
        foreach ($expected as $table => $columns) {
            if (!isset($tables[$table])) {
                continue;
            }
            foreach ($columns as $column => $signature) {
                $found = $actual[$table][$column] ?? null;
                if ($found === null) {
                    $differences[] = "{$table}.{$column} falta";
                } elseif ($found !== $signature) {
                    $differences[] = "{$table}.{$column} es {$found} y se esperaba {$signature}";
                }
            }
            foreach (array_keys(array_diff_key($actual[$table] ?? [], $columns)) as $extra) {
                $differences[] = "{$table}.{$extra} no pertenece al esquema";
            }
        }

        return $differences;
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
            throw self::sqlRejected($connection);
        }
        do {
            $result = $connection->store_result();
            if ($result instanceof \mysqli_result) {
                $result->free();
            }
            if ($connection->errno !== 0) {
                throw self::sqlRejected($connection);
            }
            if (!$connection->more_results()) {
                break;
            }
            if (!$connection->next_result()) {
                throw self::sqlRejected($connection);
            }
        } while (true);
        $connection->close();
    }

    /** 1044, 1142 y 1227 son rechazos por privilegios: no hubo importación parcial que explicar. */
    private static function sqlRejected(\mysqli $connection): LocalDatabaseInstallerException
    {
        $withoutPrivilege = in_array($connection->errno, [1044, 1142, 1227], true);
        $connection->close();

        return $withoutPrivilege
            ? new LocalDatabaseInstallerException(
                'Ese usuario de MySQL no tiene permiso para crear la base o sus tablas. '
                . 'Use una cuenta con permiso CREATE (en XAMPP, normalmente root).',
                403,
            )
            : new LocalDatabaseInstallerException(
                'MySQL rechazó una sentencia del archivo SQL. La instalación puede haber quedado parcial.',
                500,
            );
    }
}
