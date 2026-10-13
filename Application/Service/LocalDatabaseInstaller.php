<?php

declare(strict_types=1);

namespace Application\Service;

use Application\LocalDatabaseUnavailable;
use Configuration\LocalDatabaseBootstrap;
use PDO;
use PDOException;

require_once dirname(__DIR__, 2) . '/Configuration/LocalDatabaseBootstrap.php';
require_once dirname(__DIR__, 2) . '/Tools/schema-manifest.php';

/**
 * Compara la base local con el SQL canónico y crea o completa lo que falte. Solo agrega: nunca borra,
 * no altera tablas existentes y no vuelve a escribir datos iniciales en una tabla que ya tiene filas.
 * (El SQL canónico fija con `ALTER DATABASE` el juego de caracteres de la base; solo corre sobre una
 * base nueva, vacía o a medias y sin personas.)
 * Lo invoca `LocalDatabaseBootstrap`; aquí no se decide qué conexión usar ni quién puede instalar.
 */
final class LocalDatabaseInstaller
{
    private const DATABASE_NAME = LocalDatabaseBootstrap::DATABASE_NAME;
    private const TABLE_COUNT = 48;
    private const SCHEMA_FILE = 'SqlScripts/000instalacioncompleta.sql';
    /** Datos iniciales y las tablas que deben estar vacías para cargarlos. Sin personas ni publicaciones de demostración. */
    private const SEEDS = [
        'SeedData/101initialpagometodo.sql' => ['tbpagometodo'],
        'SeedData/102administrador.sql' => ['tbadministrador'],
        'SeedData/104catalogosanimal.sql' => ['tbespecie', 'tbanimaltipo', 'tbraza', 'tbvacuna'],
    ];
    private const LOCK_SECONDS = 20;

    private function __construct()
    {
    }

    /**
     * Estado de la base frente al SQL canónico. Solo lee.
     *
     * - `schemaComplete`: están las 48 tablas con su llave primaria y sus columnas (se toleran tablas y
     *   columnas opcionales de más, que no estorban a la aplicación).
     * - `completable`: no está completa, pero solo le faltan tablas enteras, no tiene nada ajeno al esquema
     *   ni personas registradas: es una base vacía o una preparación que se cortó.
     * - `seedsPending`: algún dato inicial falta y su tabla está vacía.
     */
    public static function inspect(PDO $server): array
    {
        $manifest = self::manifest();
        $state = [
            'databaseExists' => self::databaseExists($server),
            'tableCount' => 0,
            'primaryKeyCount' => 0,
            'missingTables' => $manifest['tables_ordered'],
            'missingPrimaryKeys' => [],
            'columnDifferences' => [],
            'extraTables' => [],
            'extraColumns' => [],
            'personas' => 0,
            'schemaComplete' => false,
            'completable' => false,
            'seedsPending' => false,
        ];
        if (!$state['databaseExists']) {
            return $state;
        }

        $tables = self::column($server, 'SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = :database AND TABLE_TYPE = \'BASE TABLE\'');
        $state['tableCount'] = count($tables);
        $state['missingTables'] = array_values(array_diff($manifest['tables_ordered'], $tables));
        $state['extraTables'] = array_values(array_diff($tables, $manifest['tables_ordered']));
        $present = array_values(array_intersect($manifest['tables_ordered'], $tables));

        $primaryKeys = [];
        $query = $server->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA = :database AND CONSTRAINT_NAME = \'PRIMARY\' ORDER BY TABLE_NAME, ORDINAL_POSITION');
        $query->execute(['database' => self::DATABASE_NAME]);
        foreach ($query->fetchAll(PDO::FETCH_NUM) as [$table, $column]) {
            $primaryKeys[(string) $table][] = (string) $column;
        }
        foreach ($present as $table) {
            if (($primaryKeys[$table] ?? []) === [$table . 'id']) {
                $state['primaryKeyCount']++;
            } else {
                $state['missingPrimaryKeys'][] = $table;
            }
        }
        [$state['columnDifferences'], $state['extraColumns']] = self::columnDifferences($server, $manifest['columns'], $present);

        $state['schemaComplete'] = $state['missingTables'] === [] && $state['missingPrimaryKeys'] === []
            && $state['columnDifferences'] === [];
        if (in_array('tbpersona', $present, true)) {
            $state['personas'] = (int) $server->query('SELECT COUNT(*) FROM ' . self::DATABASE_NAME . '.tbpersona')->fetchColumn();
        }
        $state['completable'] = !$state['schemaComplete'] && $state['missingPrimaryKeys'] === []
            && $state['columnDifferences'] === [] && $state['extraTables'] === [] && $state['extraColumns'] === []
            && $state['personas'] === 0;
        $state['seedsPending'] = $state['schemaComplete'] && self::pendingSeeds($server) !== [];

        return $state;
    }

    /** Resumen de una línea para el registro de errores de PHP. */
    public static function summary(array $report): string
    {
        $parts = ["{$report['tableCount']} tablas", "{$report['primaryKeyCount']} llaves primarias", "{$report['personas']} personas"];
        foreach (['missingTables' => 'faltan tablas', 'missingPrimaryKeys' => 'sin llave primaria esperada',
            'columnDifferences' => 'columnas distintas', 'extraTables' => 'tablas ajenas'] as $key => $label) {
            if ($report[$key] !== []) {
                $parts[] = $label . ': ' . implode(', ', array_slice($report[$key], 0, 12))
                    . (count($report[$key]) > 12 ? '… (' . count($report[$key]) . ')' : '');
            }
        }

        return implode('; ', $parts);
    }

    /**
     * ¿Esta cuenta puede insertar, actualizar y borrar en la base? Las tres sentencias no afectan filas
     * (`WHERE 1 = 0`): MySQL revisa el privilegio igual y rechaza la que no esté permitida.
     */
    public static function writable(PDO $connection): bool
    {
        $table = self::DATABASE_NAME . '.tbpagometodo';
        try {
            $connection->exec("INSERT INTO {$table} SELECT * FROM {$table} WHERE 1 = 0");
            $connection->exec("UPDATE {$table} SET tbpagometodoactivo = tbpagometodoactivo WHERE 1 = 0");
            $connection->exec("DELETE FROM {$table} WHERE 1 = 0");

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Deja la base lista: la crea si no existe, agrega las tablas que falten a una base vacía o a medias y
     * carga los datos iniciales en las tablas vacías. Un candado de MySQL hace esperar a las peticiones
     * simultáneas; al obtenerlo se vuelve a mirar, así que repetirla no cambia nada.
     *
     * @param array{host:string,port:int,user:string,password:string,socket:?string} $connection
     */
    public static function prepare(array $connection): array
    {
        if (!extension_loaded('mysqli')) {
            LocalDatabaseBootstrap::log('falta la extensión mysqli de PHP');
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
        }
        $schema = self::readSqlFile(self::SCHEMA_FILE);
        $seeds = [];
        foreach (array_keys(self::SEEDS) as $file) {
            $seeds[$file] = self::readSqlFile($file);
        }
        if (preg_match('/^\s*(?:DROP|TRUNCATE|DELETE|RENAME|ALTER\s+TABLE)\b/im', $schema . "\n" . implode("\n", $seeds)) === 1) {
            LocalDatabaseBootstrap::log('el SQL del paquete contiene una operación destructiva; no se ejecutó');
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
        }

        // Crear 48 tablas o esperar a la petición que las crea puede pasar del límite por defecto de PHP (30 s).
        set_time_limit(120);
        $lockName = 'ganadocerca_' . substr(hash('sha256', self::DATABASE_NAME), 0, 48);
        $lockHeld = false;
        try {
            $server = LocalDatabaseBootstrap::open($connection, false);
            $lock = $server->prepare('SELECT GET_LOCK(:name, :seconds)');
            $lock->execute(['name' => $lockName, 'seconds' => self::LOCK_SECONDS]);
            if ((int) $lock->fetchColumn() !== 1) {
                LocalDatabaseBootstrap::log('otra petición sigue preparando la base');
                throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
            }
            $lockHeld = true;

            $report = self::inspect($server);
            $new = !$report['databaseExists'];
            if ($new) {
                // Sin IF NOT EXISTS: una base que aparezca entre la comprobación y el DDL no se adopta a ciegas.
                self::runSqlBatch($connection, 'CREATE DATABASE ' . self::DATABASE_NAME
                    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
            } elseif (!$report['schemaComplete'] && !$report['completable']) {
                LocalDatabaseBootstrap::log('base existente incompatible, no se modificó: ' . self::summary($report));
                throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INCOMPATIBLE);
            }
            if (!$report['schemaComplete']) {
                self::runSqlBatch($connection, $schema);
            }
            foreach (self::pendingSeeds($server) as $file) {
                self::runSqlBatch($connection, $seeds[$file]);
            }

            $report = self::inspect($server);
            if (!$report['schemaComplete'] || ($new && self::pendingSeeds($server) !== [])) {
                LocalDatabaseBootstrap::log('la preparación terminó incompleta: ' . self::summary($report));
                throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
            }

            return $report;
        } catch (PDOException $exception) {
            LocalDatabaseBootstrap::log('MySQL rechazó la preparación: error ' . (int) ($exception->errorInfo[1] ?? $exception->getCode()));
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
        } finally {
            if ($lockHeld) {
                try {
                    $server->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $lockName]);
                } catch (PDOException) {
                    // MySQL suelta el candado al cerrar la conexión.
                }
            }
        }
    }

    /** Datos iniciales que faltan por completo: alguna de sus tablas existe y está vacía. */
    private static function pendingSeeds(PDO $server): array
    {
        $pending = [];
        foreach (self::SEEDS as $file => $tables) {
            foreach ($tables as $table) {
                if ((int) $server->query('SELECT COUNT(*) FROM ' . self::DATABASE_NAME . ".{$table}")->fetchColumn() === 0) {
                    $pending[] = $file;
                    break;
                }
            }
        }

        return $pending;
    }

    private static function databaseExists(PDO $server): bool
    {
        return self::column($server, 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :database') !== [];
    }

    /** @return list<string> */
    private static function column(PDO $server, string $sql): array
    {
        $query = $server->prepare($sql);
        $query->execute(['database' => self::DATABASE_NAME]);

        return array_map('strval', $query->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Columnas de las tablas presentes que faltan o tienen otro tipo o nulabilidad (impiden usar la base) y,
     * aparte, las que sobran. Una columna de más que acepta NULL o tiene valor por defecto no rompe ningún
     * INSERT de la aplicación; una obligatoria sin valor sí, y cuenta como diferencia.
     *
     * @return array{0:list<string>,1:list<string>}
     */
    private static function columnDifferences(PDO $server, array $expected, array $present): array
    {
        $query = $server->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :database');
        $query->execute(['database' => self::DATABASE_NAME]);
        $actual = [];
        $optional = [];
        foreach ($query->fetchAll(PDO::FETCH_NUM) as [$table, $column, $type, $nullable, $default, $extra]) {
            $isNullable = strtoupper((string) $nullable) === 'YES';
            $actual[(string) $table][(string) $column] = \schema_manifest_column_signature((string) $type, $isNullable);
            $optional[(string) $table][(string) $column] = $isNullable || $default !== null || (string) $extra !== '';
        }
        $differences = [];
        $extras = [];
        foreach ($present as $table) {
            foreach ($expected[$table] as $column => $signature) {
                $found = $actual[$table][$column] ?? null;
                if ($found === null) {
                    $differences[] = "{$table}.{$column} falta";
                } elseif ($found !== $signature) {
                    $differences[] = "{$table}.{$column} es {$found} y se esperaba {$signature}";
                }
            }
            foreach (array_keys(array_diff_key($actual[$table] ?? [], $expected[$table])) as $extra) {
                $extras[] = "{$table}.{$extra}";
                if (!$optional[$table][$extra]) {
                    $differences[] = "{$table}.{$extra} no pertenece al esquema y exige un valor";
                }
            }
        }

        return [$differences, $extras];
    }

    private static function manifest(): array
    {
        $manifest = \schema_manifest();
        if (($manifest['database'] ?? null) !== self::DATABASE_NAME || ($manifest['table_count'] ?? null) !== self::TABLE_COUNT) {
            LocalDatabaseBootstrap::log('el SQL del paquete no describe la base y las 48 tablas esperadas');
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
        }

        return $manifest;
    }

    private static function readSqlFile(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . '/Database/' . $relativePath;
        $contents = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($contents) || trim($contents) === '') {
            LocalDatabaseBootstrap::log('falta un archivo SQL del paquete: ' . $relativePath);
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::INSTALACION);
        }

        return $contents;
    }

    /**
     * Ejecuta un archivo SQL completo con mysqli (PDO no ejecuta varias sentencias con preparación nativa).
     * Cualquier sentencia rechazada detiene la preparación y queda en el log.
     */
    private static function runSqlBatch(array $credentials, string $sql): void
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $connection = mysqli_init();
        $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (!@$connection->real_connect(
            $credentials['socket'] !== null ? 'localhost' : $credentials['host'],
            $credentials['user'],
            $credentials['password'],
            null,
            $credentials['port'],
            $credentials['socket'],
        )) {
            LocalDatabaseBootstrap::log('la conexión a MySQL se perdió durante la preparación');
            throw new LocalDatabaseUnavailable(LocalDatabaseUnavailable::SIN_CONEXION);
        }
        $connection->set_charset('utf8mb4');
        $more = $connection->multi_query($sql);
        while ($more) {
            $result = $connection->store_result();
            if ($result instanceof \mysqli_result) {
                $result->free();
            }
            if ($connection->errno !== 0 || !$connection->more_results()) {
                break;
            }
            $more = $connection->next_result();
        }
        $errno = $connection->errno;
        $connection->close();
        if ($errno !== 0) {
            // 1044, 1142 y 1227 son rechazos por privilegios: no hubo cambios parciales que explicar.
            $withoutPrivilege = in_array($errno, [1044, 1142, 1227], true);
            LocalDatabaseBootstrap::log("MySQL rechazó una sentencia de la preparación: error {$errno}"
                . ($withoutPrivilege ? '' : '; lo ya creado se conserva y se completa en la siguiente visita'));
            throw new LocalDatabaseUnavailable($withoutPrivilege
                ? LocalDatabaseUnavailable::SIN_PERMISO : LocalDatabaseUnavailable::INSTALACION);
        }
    }
}
