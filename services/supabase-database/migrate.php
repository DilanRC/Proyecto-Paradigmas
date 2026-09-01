<?php

declare(strict_types=1);

const EXPECTED_COLUMNS = [
    'tbpersona' => [
        'tbpersonaid', 'tbpersonaidentificacionnumero', 'tbpersonaidentificaciontipo',
        'tbpersonanombre', 'tbpersonatelefono', 'tbpersonacorreoelectronico', 'tbpersonaestado',
    ],
    'tbproductor' => [
        'tbproductorid', 'tbpersonaid',
    ],
    'tbproductordireccion' => [
        'tbproductordireccionid', 'tbproductorid', 'tbdireccionid',
        'tbproductordireccionfechainicio', 'tbproductordireccionfechafin',
    ],
    'tbdireccion' => [
        'tbdireccionid', 'tbdireccionprovincia', 'tbdireccioncanton', 'tbdirecciondistrito',
        'tbdireccionpueblo', 'tbdireccionsenas',
    ],
    'tbproductorestadoperiodo' => [
        'tbproductorestadoperiodoid', 'tbproductorid', 'tbproductorestadoperiodoestado',
        'tbproductorestadoperiodofechainicio', 'tbproductorestadoperiodofechafin',
        'tbproductorestadoperiodomotivo',
    ],
    'tbproductorubicacion' => [
        'tbproductorubicacionid', 'tbproductorid', 'tbproductorubicacionlatitud',
        'tbproductorubicacionlongitud', 'tbproductorubicacionprecision',
        'tbproductorubicacionfecha', 'tbproductorubicacionorigen',
    ],
    'tbproductoractividad' => [
        'tbproductoractividadid', 'tbproductorid', 'tbproductoractividadtipo',
        'tbproductoractividadfecha', 'tbproductoractividadorigen',
    ],
    'tbfinca' => ['tbfincaid', 'tbproductorid', 'tbfincanombre', 'tbfincaestado'],
    'tbfincadireccion' => ['tbfincadireccionid', 'tbfincaid', 'tbdireccionid'],
    'tbpagometodo' => [
        'tbpagometodoid', 'tbpagometodonombre', 'tbpagometododescripcion', 'tbpagometodoactivo',
    ],
    'tbtransportista' => [
        'tbtransportistaid', 'tbpersonaid', 'tbtransportistaestado',
    ],
    'tbvehiculo' => [
        'tbvehiculoid', 'tbvehiculoplaca', 'tbvehiculovin', 'tbvehiculomodelo', 'tbvehiculoestado',
    ],
    'tbtransportistavehiculo' => [
        'tbtransportistavehiculoid', 'tbtransportistaid', 'tbvehiculoid',
    ],
    'tbbitacora' => [
        'tbbitacoraid', 'tbbitacoraentidad', 'tbbitacoraregistroidentificacionnumero',
        'tbbitacoraaccion', 'tbbitacorafecha', 'tbbitacoradatosanteriores', 'tbbitacoradatosnuevos',
        'tbbitacoraactortipo', 'tbbitacorausuarioid', 'tbbitacoraorigen', 'tbbitacorasolicitudid',
    ],
    'tbcomprador' => [
        'tbcompradorid', 'tbpersonaid', 'tbcompradorestado',
    ],
    'tbproductorclasificacionperiodo' => [
        'tbproductorclasificacionperiodoid', 'tbproductorid', 'tbproductorclasificacionperiodotipo',
        'tbproductorclasificacionperiodofechainicio', 'tbproductorclasificacionperiodofechafin',
        'tbproductorclasificacionperiodomotivo',
    ],
    'tbanimal' => [
        'tbanimalid', 'tbanimalcodigo', 'tbanimalsexo', 'tbanimalraza',
        'tbanimalfecharegistroensistema', 'tbanimalorigenregistro',
    ],
    'tbanimalobservacion' => [
        'tbanimalobservacionid', 'tbanimalid', 'tbanimalobservacionfecha',
        'tbanimalobservacionorigen', 'tbanimalobservacioncontexto',
        'tbanimalobservacionedadmeses', 'tbanimalobservacionpeso',
        'tbanimalobservacionproposito', 'tbanimalobservacionestadoreproductivo',
        'tbanimalobservacionpartos', 'tbanimalobservacionlitrosleche',
        'tbanimalobservacionproduccion', 'tbanimalobservacionsalud',
    ],
    'tbanimalpublicacion' => [
        'tbanimalpublicacionid', 'tbanimalid', 'tbproductorvendedorid', 'tbfincaid',
        'tbanimalpublicacionfecha', 'tbanimalpublicacionprecio', 'tbanimalpublicaciontitulo',
        'tbanimalpublicaciondescripcion', 'tbanimalpublicacionestado', 'tbanimalpublicacionorigen',
    ],
    'tbcompra' => [
        'tbcompraid', 'tbanimalid', 'tbproductorcompradorid', 'tbfincaorigenid',
        'tbcomprafecha', 'tbcomprahora', 'tbcompralugar', 'tbcompraprecio',
        'tbpagometodoid', 'tbcompraorigen',
    ],
    'tbventa' => [
        'tbventaid', 'tbanimalid', 'tbproductorvendedorid', 'tbproductorcompradorid',
        'tbfincaid', 'tbcompraid', 'tbventafecha', 'tbventahora', 'tbventalugar',
        'tbventaprecio', 'tbpagometodoid', 'tbventaedadmeses', 'tbventapeso',
        'tbventarazasnapshot', 'tbventaorigen',
    ],
    'tbanimalinteraccion' => [
        'tbanimalinteraccionid', 'tbproductorid', 'tbanimalid',
        'tbanimalinteracciontipo', 'tbanimalinteraccionaccion',
        'tbanimalinteraccionfecha', 'tbanimalinteraccionorigen',
    ],
    'tbcarrito' => [
        'tbcarritoid', 'tbproductorid', 'tbcarritofechacreacion', 'tbcarritoestado',
    ],
    'tbcarritoanimal' => [
        'tbcarritoanimalid', 'tbcarritoid', 'tbanimalid', 'tbcarritoanimalaccion',
        'tbcarritoanimalfecha', 'tbcarritoanimalorigen',
    ],
    'tbtransportistaestadoperiodo' => [
        'tbtransportistaestadoperiodoid', 'tbtransportistaid',
        'tbtransportistaestadoperiodoestado', 'tbtransportistaestadoperiodofechainicio',
        'tbtransportistaestadoperiodofechafin', 'tbtransportistaestadoperiodomotivo',
        'tbtransportistaestadoperiodofecharegistroensistema',
    ],
    'tbtransportistaflete' => [
        'tbtransportistafleteid', 'tbtransportistaid', 'tbproductororigenid',
        'tbfincaorigenid', 'tbdireccionorigenid', 'tbdirecciondestinoid',
        'tbtransportistafletefecha', 'tbtransportistafletehora',
        'tbtransportistafletedescripcion', 'tbtransportistafleteprecio',
        'tbpagometodoid', 'tbtransportistafleteorigen',
    ],
    'tbtransportistaresena' => [
        'tbtransportistaresenaid', 'tbtransportistaid', 'tbproductorid',
        'tbtransportistafleteid', 'tbtransportistaresenafecha',
        'tbtransportistaresenacalificacion', 'tbtransportistaresenacomentario',
        'tbtransportistaresenaorigen',
    ],
];

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
    foreach (['POSTGRES_URL', 'POSTGRES_URL_NON_POOLING'] as $name) {
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

function validateSchema(PDO $connection): void
{
    $statement = $connection->prepare(
        "SELECT table_name, column_name
         FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = ANY(CAST(:tables AS text[]))
         ORDER BY table_name, ordinal_position"
    );
    $tableLiteral = '{' . implode(',', array_keys(EXPECTED_COLUMNS)) . '}';
    $statement->execute(['tables' => $tableLiteral]);
    $actual = [];
    foreach ($statement->fetchAll() as $column) {
        $actual[$column['table_name']][] = $column['column_name'];
    }
    foreach ($actual as &$columns) {
        sort($columns);
    }
    unset($columns);
    ksort($actual);
    $expected = EXPECTED_COLUMNS;
    foreach ($expected as &$columns) {
        sort($columns);
    }
    unset($columns);
    ksort($expected);
    if ($actual !== $expected) {
        $differences = [];
        foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $table) {
            $expectedColumns = $expected[$table] ?? [];
            $actualColumns = $actual[$table] ?? [];
            if ($expectedColumns !== $actualColumns) {
                $differences[] = sprintf('%s esperado=[%s] actual=[%s]', $table,
                    implode(',', $expectedColumns), implode(',', $actualColumns));
            }
        }
        throw new RuntimeException(
            'El esquema Supabase no coincide con el contrato de 27 tablas: ' . implode('; ', $differences)
        );
    }
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
      SELECT ROW_NUMBER() OVER (ORDER BY identificacion)::INTEGER, identificacion,
             MIN(tipo), MIN(nombre), MIN(telefono), MIN(correo), 1
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

/** Registra el único método de pago del alcance vigente sin duplicarlo. */
function seedInitialData(PDO $connection): void
{
    $connection->exec("INSERT INTO public.tbpagometodo (
            tbpagometodoid, tbpagometodonombre, tbpagometododescripcion, tbpagometodoactivo)
        SELECT 1, 'Efectivo', 'Pago realizado en efectivo', 1
        WHERE NOT EXISTS (SELECT 1 FROM public.tbpagometodo WHERE tbpagometodoid = 1)");
}

try {
    $connection = configuredConnection();
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No fue posible leer schema.sql.');
    }
    $connection->beginTransaction();
    $connection->exec("SELECT pg_advisory_xact_lock(hashtext('tindercows_supabase_schema_v6'))");
    $connection->exec($schema);
    normalizePersonCapabilities($connection);
    normalizeProductorAddress($connection);
    agregarHistoricoDireccion($connection);
    eliminarEstadoProductor($connection);
    seedInitialData($connection);
    validateSchema($connection);
    $connection->exec("NOTIFY pgrst, 'reload schema'");
    $connection->commit();
    fwrite(STDOUT, "supabase_schema_status=ready tables=27 migration=v6\n");
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'supabase_schema_status=error message=' . $exception->getMessage() . "\n");
    exit(1);
}
