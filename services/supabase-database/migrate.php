<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-contract.php';

function postgresConnection(string $url): PDO
{
    $parts = parse_url($url);
    if ($parts === false || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
        || !isset($parts['host'], $parts['user'], $parts['pass'])) {
        throw new RuntimeException('La URL PostgreSQL configurada no es válida.');
    }
    $database = ltrim($parts['path'] ?? '/postgres', '/');
    if ($database === '') {
        $database = 'postgres';
    }
    parse_str($parts['query'] ?? '', $query);
    $sslMode = is_string($query['sslmode'] ?? null) ? $query['sslmode'] : 'require';
    if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
        throw new RuntimeException('El sslmode PostgreSQL configurado no es válido.');
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;connect_timeout=10',
        $parts['host'],
        $parts['port'] ?? 5432,
        rawurldecode($database),
        $sslMode,
    );

    return new PDO($dsn, rawurldecode($parts['user']), rawurldecode($parts['pass']), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function configuredConnection(): PDO
{
    $errors = [];
    foreach (['POSTGRES_URL_NON_POOLING', 'POSTGRES_URL'] as $name) {
        $url = getenv($name);
        if ($url === false || $url === '' || $url === '[SENSITIVE]') {
            continue;
        }
        try {
            return postgresConnection($url);
        } catch (Throwable $exception) {
            $errors[] = "{$name}: {$exception->getMessage()}";
        }
    }
    throw new RuntimeException($errors === []
        ? 'No hay una URL PostgreSQL utilizable.'
        : 'No fue posible conectar con las URLs PostgreSQL configuradas: ' . implode('; ', $errors));
}

function ensureExpectedNullability(PDO $connection, string $schema): void
{
    foreach (schemaNullabilityDifferences($connection, $schema) as $difference) {
        $table = $difference['table'];
        $column = $difference['column'];
        if (!$difference['nullable']) {
            $hasNull = $connection->query(sprintf(
                'SELECT EXISTS (SELECT 1 FROM public.%s WHERE %s IS NULL)',
                $table,
                $column,
            ))->fetchColumn();
            if (in_array($hasNull, [true, 1, '1', 't'], true)) {
                throw new RuntimeException("No se puede exigir NOT NULL en public.{$table}.{$column}: hay valores NULL.");
            }
        }
        $action = $difference['nullable'] ? 'DROP' : 'SET';
        $connection->exec("ALTER TABLE public.{$table} ALTER COLUMN {$column} {$action} NOT NULL");
    }
}

function schemaIsReady(PDO $connection, string $schema): bool
{
    return schemaColumnDifferences($connection) === []
        && schemaHasExpectedPrimaryKeys($connection)
        && schemaHasRequiredRowSecurity($connection)
        && schemaHasExpectedNullability($connection, $schema);
}

/** Evita repetir DDL si solo quedan llaves que requieren corregir datos o esquema a mano. */
function unrepairablePrimaryKeyDifferences(PDO $connection, string $schema): array
{
    $differences = schemaPrimaryKeyDifferences($connection);
    if ($differences === []
        || schemaColumnDifferences($connection) !== []
        || !schemaHasRequiredRowSecurity($connection)
        || !schemaHasExpectedNullability($connection, $schema)) {
        return [];
    }

    $unrepairable = [];
    foreach ($differences as $table => $columns) {
        if ($columns !== []) {
            $unrepairable[] = $table;
            continue;
        }

        $id = $table . 'id';
        $invalidos = (int) $connection->query(
            "SELECT (SELECT COUNT(*) FROM public.{$table} WHERE {$id} IS NULL)
                  + (SELECT COUNT(*) FROM (SELECT 1 FROM public.{$table} GROUP BY {$id} HAVING COUNT(*) > 1) repetidos)"
        )->fetchColumn();
        if ($invalidos > 0) {
            $unrepairable[] = $table;
        } else {
            return [];
        }
    }

    return $unrepairable;
}

/** Migra los tres perfiles heredados a una identidad compartida. Todo el DDL
 * PostgreSQL es transaccional: cualquier conflicto restaura el esquema previo. */
function normalizePersonCapabilities(PDO $connection): void
{
    $legacy = (int) $connection->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'tbproductor'
          AND column_name = 'tbproductoridentificacionnumero'")->fetchColumn();
    if ($legacy === 0) {
        return;
    }

    // La comprobación exacta por capacidad evita perder IDs históricos.
    $duplicates = (int) $connection->query("SELECT
        (SELECT COUNT(*) FROM (SELECT 1 FROM public.tbproductor GROUP BY tbproductoridentificacionnumero HAVING COUNT(*) > 1) x) +
        (SELECT COUNT(*) FROM (SELECT 1 FROM public.tbcomprador GROUP BY tbcompradoridentificacionnumero HAVING COUNT(*) > 1) x) +
        (SELECT COUNT(*) FROM (SELECT 1 FROM public.tbtransportista GROUP BY tbtransportistaidentificacionnumero HAVING COUNT(*) > 1) x)")->fetchColumn();
    if ($duplicates > 0) {
        throw new RuntimeException('Migración abortada: capacidad duplicada por identificación.');
    }
    $conflicts = (int) $connection->query("SELECT COUNT(*) FROM (
        SELECT identificacion FROM (
          SELECT tbproductoridentificacionnumero identificacion, tbproductoridentificaciontipo tipo,
                 tbproductornombre nombre, tbproductortelefono telefono,
                 tbproductorcorreoelectronico correo FROM public.tbproductor
          UNION ALL SELECT tbcompradoridentificacionnumero, tbcompradoridentificaciontipo,
                 tbcompradornombre, tbcompradortelefono, tbcompradorcorreoelectronico FROM public.tbcomprador
          UNION ALL SELECT tbtransportistaidentificacionnumero, tbtransportistaidentificaciontipo,
                 tbtransportistanombre, tbtransportistatelefono,
                 tbtransportistacorreoelectronico FROM public.tbtransportista
        ) personas GROUP BY identificacion
        HAVING COUNT(DISTINCT ROW(tipo, nombre, telefono, correo)) > 1
      ) conflictos")->fetchColumn();
    if ($conflicts > 0) {
        throw new RuntimeException('Migración abortada: datos personales incompatibles.');
    }

    $connection->exec('ALTER TABLE public.tbproductor ADD COLUMN tbpersonaid INTEGER NULL;
        ALTER TABLE public.tbcomprador ADD COLUMN tbpersonaid INTEGER NULL;
        ALTER TABLE public.tbtransportista ADD COLUMN tbpersonaid INTEGER NULL');
    $connection->exec("INSERT INTO public.tbpersona
      (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo,
       tbpersonanombre, tbpersonaalias, tbpersonatelefono,
       tbpersonacorreoelectronico, tbpersonaestado)
      SELECT ROW_NUMBER() OVER (ORDER BY identificacion)::INTEGER, identificacion,
             MIN(tipo), MIN(nombre), NULL, MIN(telefono), MIN(correo), 1
      FROM (
        SELECT tbproductoridentificacionnumero identificacion, tbproductoridentificaciontipo tipo,
               tbproductornombre nombre, tbproductortelefono telefono,
               tbproductorcorreoelectronico correo FROM public.tbproductor
        UNION ALL SELECT tbcompradoridentificacionnumero, tbcompradoridentificaciontipo,
               tbcompradornombre, tbcompradortelefono, tbcompradorcorreoelectronico FROM public.tbcomprador
        UNION ALL SELECT tbtransportistaidentificacionnumero, tbtransportistaidentificaciontipo,
               tbtransportistanombre, tbtransportistatelefono,
               tbtransportistacorreoelectronico FROM public.tbtransportista
      ) personas GROUP BY identificacion");
    foreach (['productor', 'comprador', 'transportista'] as $profile) {
        $connection->exec("UPDATE public.tb{$profile} p SET tbpersonaid = x.tbpersonaid
          FROM public.tbpersona x
          WHERE x.tbpersonaidentificacionnumero = p.tb{$profile}identificacionnumero");
        $orphans = (int) $connection->query("SELECT COUNT(*) FROM public.tb{$profile}
          WHERE tbpersonaid IS NULL")->fetchColumn();
        if ($orphans !== 0) {
            throw new RuntimeException("Migración abortada: {$profile} sin persona.");
        }
        $connection->exec("ALTER TABLE public.tb{$profile}
          DROP COLUMN tb{$profile}identificacionnumero,
          DROP COLUMN tb{$profile}identificaciontipo,
          DROP COLUMN tb{$profile}nombre,
          DROP COLUMN tb{$profile}telefono,
          DROP COLUMN tb{$profile}correoelectronico,
          ALTER COLUMN tbpersonaid SET NOT NULL");
    }
}

/** Completa columnas que ya usan los modelos pero faltaban en el primer espejo
 * PostgreSQL. Se ejecuta también cuando la base ya está normalizada. */
function ensureCurrentColumns(PDO $connection): void
{
    $connection->exec('ALTER TABLE public.tbpersona
        ADD COLUMN IF NOT EXISTS tbpersonaalias VARCHAR(150) NULL,
        ADD COLUMN IF NOT EXISTS tbpersonafotourl VARCHAR(500) NULL,
        ADD COLUMN IF NOT EXISTS tbpersonadocumentoruta VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS tbpersonadocumentoestado VARCHAR(20) NULL,
        ADD COLUMN IF NOT EXISTS tbpersonadocumentofecha TIMESTAMP WITHOUT TIME ZONE NULL,
        ADD COLUMN IF NOT EXISTS tbpersonadocumentomotivo VARCHAR(250) NULL,
        ADD COLUMN IF NOT EXISTS tbpersonadocumentonumeroleido VARCHAR(20) NULL,
        ADD COLUMN IF NOT EXISTS tbpersonadocumentolectura VARCHAR(20) NULL;
        ALTER TABLE public.tbvehiculo
        ADD COLUMN IF NOT EXISTS tbvehiculofotourl VARCHAR(500) NULL;
        ALTER TABLE public.tbdireccion
        ADD COLUMN IF NOT EXISTS tbdireccionlatitud NUMERIC(10,7) NULL,
        ADD COLUMN IF NOT EXISTS tbdireccionlongitud NUMERIC(10,7) NULL;
        ALTER TABLE public.tbanimalpublicacion
        ADD COLUMN IF NOT EXISTS tbanimalpublicacionimagenurl VARCHAR(500) NULL;
        ALTER TABLE public.tbanimal
        ADD COLUMN IF NOT EXISTS tbespecieid INTEGER NULL,
        ADD COLUMN IF NOT EXISTS tbanimaltipoid INTEGER NULL,
        ADD COLUMN IF NOT EXISTS tbrazaid INTEGER NULL,
        ADD COLUMN IF NOT EXISTS tbanimalfechanacimiento DATE NULL,
        ADD COLUMN IF NOT EXISTS tbanimalfechanacimientoestimada SMALLINT NULL,
        ADD COLUMN IF NOT EXISTS tbanimalpartos INTEGER NULL,
        ADD COLUMN IF NOT EXISTS tbanimalestado VARCHAR(20) NULL,
        ADD COLUMN IF NOT EXISTS tbproductorid INTEGER NULL;
        ALTER TABLE public.tbcompra
        ADD COLUMN IF NOT EXISTS tbcompradorid INTEGER NULL,
        ALTER COLUMN tbproductorcompradorid DROP NOT NULL,
        ALTER COLUMN tbpagometodoid DROP NOT NULL;
        ALTER TABLE public.tbventa
        ADD COLUMN IF NOT EXISTS tbcompradorid INTEGER NULL,
        ADD COLUMN IF NOT EXISTS tbcomprasolicitudid INTEGER NULL,
        ALTER COLUMN tbproductorcompradorid DROP NOT NULL,
        ALTER COLUMN tbpagometodoid DROP NOT NULL');
}

/**
 * Deshace la migración 027 (viaje enlazado a la solicitud), retirada por decisión de Dilan: tbtransportistaflete
 * vuelve a no tener tbcomprasolicitudid y la nulabilidad de tbpagometodoid la restaura ensureExpectedNullability()
 * (que se niega si hay NULL). Si algún viaje guarda el enlace, aborta: borrar la columna perdería un hecho que el
 * esquema anterior no puede guardar. Todo corre en la transacción del migrador: un error lo deja como estaba.
 */
function revertirViajeSolicitud(PDO $connection): void
{
    $existe = (int) $connection->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'tbtransportistaflete'
          AND column_name = 'tbcomprasolicitudid'")->fetchColumn();
    if ($existe === 0) {
        return;
    }
    $enlazados = (int) $connection->query('SELECT COUNT(*) FROM public.tbtransportistaflete
        WHERE tbcomprasolicitudid IS NOT NULL')->fetchColumn();
    if ($enlazados > 0) {
        throw new RuntimeException("Migración abortada: {$enlazados} viajes guardan tbcomprasolicitudid; revertir la 027 los perdería.");
    }
    $connection->exec('ALTER TABLE public.tbtransportistaflete DROP COLUMN IF EXISTS tbcomprasolicitudid');
}

/**
 * Traslada la residencia del productor a tbdireccion y deja tbproductordireccion
 * como enlace de tres columnas. Idempotente: si las columnas heredadas ya no
 * existen, solamente confirma que el enlace es obligatorio.
 */
function normalizeProductorAddress(PDO $connection): void
{
    $connection->exec('ALTER TABLE public.tbproductordireccion
        ADD COLUMN IF NOT EXISTS tbdireccionid INTEGER NULL');

    $legacy = $connection->prepare("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'tbproductordireccion'
          AND column_name = 'tbproductordireccionprovincia'");
    $legacy->execute();

    if ((int) $legacy->fetchColumn() === 1) {
        // Desplazamiento fijo: cada residencia recibe un tbdireccionid propio y
        // estable. Se calcula una sola vez, antes de insertar.
        $maximo = $connection->prepare('SELECT COALESCE(MAX(tbdireccionid), 0) FROM public.tbdireccion');
        $maximo->execute();
        $offset = (int) $maximo->fetchColumn();

        $insertar = $connection->prepare('INSERT INTO public.tbdireccion (
                tbdireccionid, tbdireccionprovincia, tbdireccioncanton,
                tbdirecciondistrito, tbdireccionpueblo, tbdireccionsenas)
            SELECT :offset + tbproductordireccionid,
                   tbproductordireccionprovincia, tbproductordireccioncanton,
                   tbproductordirecciondistrito, tbproductordireccionpueblo,
                   tbproductordireccionsenas
            FROM public.tbproductordireccion
            WHERE tbdireccionid IS NULL');
        $insertar->execute(['offset' => $offset]);

        $enlazar = $connection->prepare('UPDATE public.tbproductordireccion
            SET tbdireccionid = :offset + tbproductordireccionid
            WHERE tbdireccionid IS NULL');
        $enlazar->execute(['offset' => $offset]);
        $huerfanas = $connection->prepare('SELECT COUNT(*) FROM public.tbproductordireccion pd
            LEFT JOIN public.tbdireccion d ON d.tbdireccionid = pd.tbdireccionid
            WHERE d.tbdireccionid IS NULL');
        $huerfanas->execute();
        if ((int) $huerfanas->fetchColumn() !== 0) {
            throw new RuntimeException('La normalización dejó residencias sin ubicación en tbdireccion.');
        }
        $connection->exec('ALTER TABLE public.tbproductordireccion
            DROP COLUMN tbproductordireccionprovincia,
            DROP COLUMN tbproductordireccioncanton,
            DROP COLUMN tbproductordirecciondistrito,
            DROP COLUMN tbproductordireccionpueblo,
            DROP COLUMN tbproductordireccionsenas');
    }

    $connection->exec('ALTER TABLE public.tbproductordireccion
        ALTER COLUMN tbdireccionid SET NOT NULL');
}

/**
 * Agrega las columnas de fecha del futuro histórico de dirección (plan §8) a
 * una base ya desplegada. Idempotente vía ADD COLUMN IF NOT EXISTS; en una
 * base nueva schema.sql ya las crea y este paso no hace nada.
 */
function agregarHistoricoDireccion(PDO $connection): void
{
    $connection->exec('ALTER TABLE public.tbproductordireccion
        ADD COLUMN IF NOT EXISTS tbproductordireccionfechainicio TIMESTAMP WITHOUT TIME ZONE NULL,
        ADD COLUMN IF NOT EXISTS tbproductordireccionfechafin TIMESTAMP WITHOUT TIME ZONE NULL');
}

/**
 * Traslada tbproductorestado al histórico de periodos y retira la columna
 * de tbproductor (plan §4). Idempotente: si la columna no existe, solamente
 * confirma que la tabla ya tiene la estructura objetivo.
 */
function eliminarEstadoProductor(PDO $connection): void
{
    $existe = $connection->prepare("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'tbproductor'
          AND column_name = 'tbproductorestado'");
    $existe->execute();
    if ((int) $existe->fetchColumn() === 0) {
        return;
    }

    $maximo = $connection->prepare('SELECT COALESCE(MAX(tbproductorestadoperiodoid), 0) FROM public.tbproductorestadoperiodo');
    $maximo->execute();
    $offset = (int) $maximo->fetchColumn();

    $connection->prepare("INSERT INTO public.tbproductorestadoperiodo
        (tbproductorestadoperiodoid, tbproductorid, tbproductorestadoperiodoestado,
         tbproductorestadoperiodofechainicio, tbproductorestadoperiodofechafin,
         tbproductorestadoperiodomotivo)
        SELECT :offset + ROW_NUMBER() OVER (ORDER BY p.tbproductorid), p.tbproductorid,
               p.tbproductorestado, NOW(), NULL, 'Migración v5: estado heredado'
        FROM public.tbproductor p
        WHERE NOT EXISTS (
            SELECT 1 FROM public.tbproductorestadoperiodo ep
            WHERE ep.tbproductorid = p.tbproductorid
        )")
        ->execute(['offset' => $offset]);

    $connection->exec('ALTER TABLE public.tbproductor DROP COLUMN IF EXISTS tbproductorestado');
}

/**
 * Declara la llave primaria `<tabla>id` en las tablas que todavía no la tienen
 * (Calidad: una por tabla, sin llaves foráneas). Una base nueva ya la trae de
 * schema.sql. Si una tabla tiene identificadores repetidos o nulos no se toca:
 * se devuelve su nombre para avisar, porque abortar aquí dejaría el despliegue
 * caído y repararla exige revisar los datos a mano.
 *
 * @return list<string> tablas que quedaron sin llave primaria
 */
function ensurePrimaryKeys(PDO $connection): array
{
    $primaryKeyColumns = $connection->query(
        "SELECT c.relname AS table_name, a.attname AS column_name
         FROM pg_constraint k
         INNER JOIN pg_class c ON c.oid = k.conrelid
         INNER JOIN pg_namespace n ON n.oid = c.relnamespace
         CROSS JOIN LATERAL unnest(k.conkey) WITH ORDINALITY AS pk_column(attnum, ordinal)
         INNER JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = pk_column.attnum
         WHERE n.nspname = 'public' AND k.contype = 'p'
         ORDER BY c.relname, pk_column.ordinal"
    )->fetchAll(PDO::FETCH_ASSOC);
    $primaryKeys = [];
    foreach ($primaryKeyColumns as $row) {
        $primaryKeys[(string) $row['table_name']][] = (string) $row['column_name'];
    }
    $pendientes = [];
    foreach (array_keys(EXPECTED_COLUMNS) as $table) {
        $id = $table . 'id';
        if (isset($primaryKeys[$table])) {
            if ($primaryKeys[$table] === [$id]) {
                continue;
            }
            $pendientes[] = $table;
            continue;
        }
        $invalidos = (int) $connection->query(
            "SELECT (SELECT COUNT(*) FROM public.{$table} WHERE {$id} IS NULL)
                  + (SELECT COUNT(*) FROM (SELECT 1 FROM public.{$table} GROUP BY {$id} HAVING COUNT(*) > 1) repetidos)"
        )->fetchColumn();
        if ($invalidos > 0) {
            $pendientes[] = $table;
            continue;
        }
        $connection->exec("ALTER TABLE public.{$table} ADD PRIMARY KEY ({$id})");
    }
    return $pendientes;
}

/** Informa que el esquema puede servir, pero queda degradado por llaves primarias. */
function reportPendingPrimaryKeys(array $tables): void
{
    if ($tables === []) {
        return;
    }

    $status = 'supabase_schema_status=degraded tables=48 migration=v10 primary_key_pending=' . count($tables);
    $detail = 'supabase_primary_key_pendiente tablas=' . implode(',', $tables)
        . ' causa=llave_existente_incorrecta_o_identificadores_repetidos_o_nulos';
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, $status . "\n");
        fwrite(STDERR, $detail . "\n");
        exit(2);
    }
    error_log($status);
    error_log($detail);
}

/**
 * Catálogos del animal (P2-2) y vacunas (P2-3). Solo se siembra una tabla VACÍA: lo que un administrador haya cambiado,
 * agregado o desactivado no se reinserta al redesplegar. Mismos datos que Database/SeedData/104catalogosanimal.sql.
 */
function seedCatalogs(PDO $connection): void
{
    $catalogos = [
    'tbespecie' => [
        [1, 'Bovino', 1],
        [2, 'Porcino', 1],
        [3, 'Equino', 1],
        [4, 'Ovino', 1],
        [5, 'Caprino', 1],
        [6, 'Bufalino', 1],
    ],
    'tbanimaltipo' => [
        [1, 1, 'Ternero', 'M', 1],
        [2, 1, 'Ternera', 'H', 1],
        [3, 1, 'Torete', 'M', 1],
        [4, 1, 'Novillo', 'M', 1],
        [5, 1, 'Vaquilla', 'H', 1],
        [6, 1, 'Vaca', 'H', 1],
        [7, 1, 'Toro', 'M', 1],
        [8, 1, 'Buey', 'M', 1],
        [9, 2, 'Lechón', null, 1],
        [10, 2, 'Cerdo de engorde', null, 1],
        [11, 2, 'Cerda', 'H', 1],
        [12, 2, 'Verraco', 'M', 1],
        [13, 3, 'Potro', 'M', 1],
        [14, 3, 'Potra', 'H', 1],
        [15, 3, 'Caballo', 'M', 1],
        [16, 3, 'Yegua', 'H', 1],
        [17, 4, 'Cordero', 'M', 1],
        [18, 4, 'Cordera', 'H', 1],
        [19, 4, 'Carnero', 'M', 1],
        [20, 4, 'Oveja', 'H', 1],
        [21, 5, 'Cabrito', 'M', 1],
        [22, 5, 'Cabrita', 'H', 1],
        [23, 5, 'Macho cabrío', 'M', 1],
        [24, 5, 'Cabra', 'H', 1],
        [25, 6, 'Búfalo', 'M', 1],
        [26, 6, 'Búfala', 'H', 1],
    ],
    'tbraza' => [
        [1, 1, 'Brahman', 1],
        [2, 1, 'Holstein', 1],
        [3, 1, 'Jersey', 1],
        [4, 1, 'Pardo Suizo', 1],
        [5, 1, 'Angus', 1],
        [6, 1, 'Nelore', 1],
        [7, 1, 'Gyr', 1],
        [8, 1, 'Girolando', 1],
        [9, 1, 'Simmental', 1],
        [10, 1, 'Guzerat', 1],
        [11, 1, 'Sindi', 1],
        [12, 1, 'Charolais', 1],
        [13, 1, 'Santa Gertrudis', 1],
        [14, 1, 'Brangus', 1],
        [15, 1, 'Beefmaster', 1],
        [16, 1, 'Criollo', 1],
        [17, 1, 'Mestizo', 1],
        [18, 2, 'Landrace', 1],
        [19, 2, 'Yorkshire', 1],
        [20, 2, 'Duroc', 1],
        [21, 2, 'Pietrain', 1],
        [22, 2, 'Criollo', 1],
        [23, 2, 'Mestizo', 1],
        [24, 3, 'Criollo', 1],
        [25, 3, 'Cuarto de milla', 1],
        [26, 3, 'Pura sangre', 1],
        [27, 3, 'Paso fino', 1],
        [28, 3, 'Mestizo', 1],
        [29, 4, 'Pelibuey', 1],
        [30, 4, 'Katahdin', 1],
        [31, 4, 'Dorper', 1],
        [32, 4, 'Criollo', 1],
        [33, 4, 'Mestizo', 1],
        [34, 5, 'Saanen', 1],
        [35, 5, 'Alpina', 1],
        [36, 5, 'Toggenburg', 1],
        [37, 5, 'Nubia', 1],
        [38, 5, 'Criollo', 1],
        [39, 5, 'Mestizo', 1],
        [40, 6, 'Murrah', 1],
        [41, 6, 'Mediterráneo', 1],
        [42, 6, 'Mestizo', 1],
    ],
    'tbvacuna' => [
        [1, 'Fiebre aftosa', 1],
        [2, 'Brucelosis', 1],
        [3, 'Rabia paralítica bovina', 1],
        [4, 'Carbunco sintomático', 1],
        [5, 'Carbunco bacteridiano', 1],
        [6, 'Clostridiales (multiclostridial)', 1],
        [7, 'IBR / DVB (rinotraqueítis y diarrea viral)', 1],
        [8, 'Leptospirosis', 1],
        [9, 'Complejo respiratorio bovino', 1],
        [10, 'Pasteurelosis', 1],
    ],
    ];
    $columnas = [
        'tbespecie' => ['tbespecieid', 'tbespecienombre', 'tbespecieactivo'],
        'tbanimaltipo' => ['tbanimaltipoid', 'tbespecieid', 'tbanimaltiponombre', 'tbanimaltiposexo', 'tbanimaltipoactivo'],
        'tbraza' => ['tbrazaid', 'tbespecieid', 'tbrazanombre', 'tbrazaactivo'],
        'tbvacuna' => ['tbvacunaid', 'tbvacunanombre', 'tbvacunaactivo'],
    ];
    foreach ($catalogos as $tabla => $filas) {
        if ((int) $connection->query("SELECT COUNT(*) FROM public.{$tabla}")->fetchColumn() > 0) {
            continue;
        }
        $lista = $columnas[$tabla];
        $insertar = $connection->prepare(sprintf(
            'INSERT INTO public.%s (%s) VALUES (%s)',
            $tabla,
            implode(', ', $lista),
            implode(', ', array_fill(0, count($lista), '?')),
        ));
        foreach ($filas as $fila) {
            $insertar->execute($fila);
        }
    }
}

/** Registra el único método de pago del alcance vigente sin duplicarlo. */
function seedInitialData(PDO $connection): void
{
    $connection->exec("INSERT INTO public.tbpagometodo (
            tbpagometodoid, tbpagometodonombre, tbpagometododescripcion, tbpagometodoactivo)
        SELECT 1, 'Efectivo', 'Pago realizado en efectivo', 1
        WHERE NOT EXISTS (SELECT 1 FROM public.tbpagometodo WHERE tbpagometodoid = 1)");

    $connection->exec("INSERT INTO public.tbadministrador (
            tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado)
        SELECT 1, 'cortesdila2023@gmail.com', 1
        WHERE NOT EXISTS (
            SELECT 1 FROM public.tbadministrador
            WHERE LOWER(tbadministradorcorreoelectronico) = LOWER('cortesdila2023@gmail.com')
        )");
}

try {
    $connection = configuredConnection();
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No fue posible leer schema.sql.');
    }
    $connection->beginTransaction();
    // Otra instancia que arranca a la vez espera aquí a que termine la primera, con tope:
    // lock_timeout también limita la espera del advisory lock y de cada ALTER.
    $connection->exec("SET LOCAL lock_timeout = '30s'");
    $connection->exec("SET LOCAL statement_timeout = '120s'");
    $connection->exec("SELECT pg_advisory_xact_lock(hashtext('tindercows_supabase_schema_v10'))");
    if (schemaIsReady($connection, $schema)) {
        seedInitialData($connection);
        seedCatalogs($connection);
        $connection->commit();
        unset($connection);
        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "supabase_schema_status=ready tables=48 migration=v10\n");
        }
        return true;
    }

    $llavesPendientes = unrepairablePrimaryKeyDifferences($connection, $schema);
    if ($llavesPendientes !== []) {
        seedInitialData($connection);
        seedCatalogs($connection);
        $connection->commit();
        unset($connection);
        reportPendingPrimaryKeys($llavesPendientes);
        return false;
    }

    $connection->exec($schema);
    ensureCurrentColumns($connection);
    normalizePersonCapabilities($connection);
    normalizeProductorAddress($connection);
    agregarHistoricoDireccion($connection);
    eliminarEstadoProductor($connection);
    revertirViajeSolicitud($connection);
    ensureExpectedNullability($connection, $schema);
    seedInitialData($connection);
    seedCatalogs($connection);
    $sinLlavePrimaria = ensurePrimaryKeys($connection);
    validateSchema($connection, $schema);
    $connection->exec("NOTIFY pgrst, 'reload schema'");
    $connection->commit();
    unset($connection);
    if ($sinLlavePrimaria !== []) {
        reportPendingPrimaryKeys($sinLlavePrimaria);
        return false;
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, "supabase_schema_status=ready tables=48 migration=v10\n");
    }
    return true;
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    unset($connection);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'supabase_schema_status=error message=' . $exception->getMessage() . "\n");
        exit(1);
    }
    error_log('supabase_schema_status=error message=' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'El servicio está temporalmente no disponible. Intente de nuevo.',
        'data' => null,
        'errors' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
