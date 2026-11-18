<?php

declare(strict_types=1);

const EXPECTED_COLUMNS = [
    'tbadministrador' => [
        'tbadministradorid', 'tbadministradorcorreoelectronico', 'tbadministradorestado',
    ],
    'tbpersona' => [
        'tbpersonaid', 'tbpersonaidentificacionnumero', 'tbpersonaidentificaciontipo',
        'tbpersonanombre', 'tbpersonaalias', 'tbpersonatelefono', 'tbpersonacorreoelectronico', 'tbpersonaestado',
        'tbpersonafotourl', 'tbpersonadocumentoruta', 'tbpersonadocumentoestado', 'tbpersonadocumentofecha',
        'tbpersonadocumentomotivo', 'tbpersonadocumentonumeroleido', 'tbpersonadocumentolectura',
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
        'tbdireccionpueblo', 'tbdireccionsenas', 'tbdireccionlatitud', 'tbdireccionlongitud',
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
        'tbvehiculofotourl',
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
    'tbproductorpersonatelefonohistorico' => [
        'tbproductorpersonatelefonohistoricoid', 'tbproductorid',
        'tbproductorpersonatelefonohistoriconuevo', 'tbproductorpersonatelefonohistoricofecha',
    ],
    'tbcompradorpersonatelefonohistorico' => [
        'tbcompradorpersonatelefonohistoricoid', 'tbcompradorid',
        'tbcompradorpersonatelefonohistoriconuevo', 'tbcompradorpersonatelefonohistoricofecha',
    ],
    'tbproductorclasificacionperiodo' => [
        'tbproductorclasificacionperiodoid', 'tbproductorid', 'tbproductorclasificacionperiodotipo',
        'tbproductorclasificacionperiodofechainicio', 'tbproductorclasificacionperiodofechafin',
        'tbproductorclasificacionperiodomotivo',
    ],
    'tbanimal' => [
        'tbanimalid', 'tbanimalidentificacion', 'tbanimalsexo', 'tbanimalraza',
        'tbanimalcaracteristicas', 'tbanimalfecharegistroensistema', 'tbanimalorigenregistro',
        'tbespecieid', 'tbanimaltipoid', 'tbrazaid', 'tbanimalfechanacimiento',
        'tbanimalfechanacimientoestimada', 'tbanimalpartos', 'tbanimalestado', 'tbproductorid',
    ],
    'tbanimalproduccionsalud' => [
        'tbanimalproduccionsaludid', 'tbanimalid', 'tbanimalproduccionsaludfecha',
        'tbanimalproduccionsaludorigen', 'tbanimalproduccionsaludcontexto',
        'tbanimalproduccionsaludedadmeses', 'tbanimalproduccionsaludpeso',
        'tbanimalproduccionsaludproposito', 'tbanimalproduccionsaludestadoreproductivo',
        'tbanimalproduccionsaludpartos', 'tbanimalproduccionsaludlitrosleche',
        'tbanimalproduccionsaludproduccion', 'tbanimalproduccionsaludsalud',
    ],
    'tbanimalpublicacion' => [
        'tbanimalpublicacionid', 'tbanimalid', 'tbproductorvendedorid', 'tbfincaid',
        'tbanimalpublicacionfecha', 'tbanimalpublicacionprecio', 'tbanimalpublicaciontitulo',
        'tbanimalpublicaciondescripcion', 'tbanimalpublicacionimagenurl', 'tbanimalpublicacionorigen',
    ],
    'tbanimalpublicacionestadoperiodo' => [
        'tbanimalpublicacionestadoperiodoid', 'tbanimalpublicacionid',
        'tbanimalpublicacionestadoperiodoestado', 'tbanimalpublicacionestadoperiodofechainicio',
        'tbanimalpublicacionestadoperiodofechafin', 'tbanimalpublicacionestadoperiodomotivo',
        'tbanimalpublicacionestadoperiodoorigen',
    ],
    'tbcompra' => [
        'tbcompraid', 'tbanimalid', 'tbproductorcompradorid', 'tbcompradorid', 'tbfincaorigenid',
        'tbcomprafecha', 'tbcomprahora', 'tbcompralugar', 'tbcompraprecio',
        'tbpagometodoid', 'tbcompraorigen',
    ],
    'tbventa' => [
        'tbventaid', 'tbanimalid', 'tbproductorvendedorid', 'tbproductorcompradorid', 'tbcompradorid',
        'tbfincaid', 'tbcompraid', 'tbcomprasolicitudid', 'tbventafecha', 'tbventahora', 'tbventalugar',
        'tbventadireccionid', 'tbventaproposito', 'tbventaprecio', 'tbpagometodoid',
        'tbventaedadmeses', 'tbventapeso', 'tbventarazasnapshot', 'tbventaorigen',
    ],
    'tbanimalinteraccion' => [
        'tbanimalinteraccionid', 'tbproductorid', 'tbanimalid',
        'tbanimalinteracciontipo', 'tbanimalinteraccionaccion',
        'tbanimalinteraccionfecha', 'tbanimalinteraccionorigen',
    ],
    'tbanimalpublicacioninteraccion' => [
        'tbanimalpublicacioninteraccionid', 'tbpersonaid', 'tbanimalpublicacionid',
        'tbanimalpublicacioninteracciontipo', 'tbanimalpublicacioninteraccionaccion',
        'tbanimalpublicacioninteraccionfecha', 'tbanimalpublicacioninteraccionorigen',
    ],
    'tbcarrito' => [
        'tbcarritoid', 'tbproductorid', 'tbcarritofechacreacion',
    ],
    'tbcarritoestadoperiodo' => [
        'tbcarritoestadoperiodoid', 'tbcarritoid', 'tbcarritoestadoperiodoestado',
        'tbcarritoestadoperiodofechainicio', 'tbcarritoestadoperiodofechafin',
        'tbcarritoestadoperiodomotivo', 'tbcarritoestadoperiodoorigen',
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
        'tbfincaorigenid', 'tbdireccionorigenid', 'tbdirecciondestinoid', 'tbvehiculoid',
        'tbtransportistafletefecha', 'tbtransportistafletehora',
        'tbtransportistafletedescripcion', 'tbtransportistafletecantidadcabezas',
        'tbtransportistafletedistanciakm', 'tbtransportistafleteprecio',
        'tbpagometodoid', 'tbtransportistafleteorigen',
    ],
    'tbtransportistahorario' => [
        'tbtransportistahorarioid', 'tbtransportistaid', 'tbtransportistahorariodiasemana',
        'tbtransportistahorariohorainicio', 'tbtransportistahorariohorafin',
        'tbtransportistahorariofechainicio', 'tbtransportistahorariofechafin',
        'tbtransportistahorarioorigen',
    ],
    'tbtransportistaresena' => [
        'tbtransportistaresenaid', 'tbtransportistaid', 'tbpersonaid',
        'tbtransportistafleteid', 'tbtransportistaresenafecha',
        'tbtransportistaresenacalificacion', 'tbtransportistaresenacomentario',
        'tbtransportistaresenaorigen',
    ],
    'tbregistroconsulta' => [
        'tbregistroconsultaid', 'tbregistroconsultaclave', 'tbregistroconsultafecha',
    ],
    'tbcomprasolicitud' => [
        'tbcomprasolicitudid', 'tbanimalpublicacionid', 'tbcompradorid', 'tbtransportistaofertaid',
        'tbpagometodoid', 'tbcomprasolicitudprecio', 'tbcomprasolicitudmensaje', 'tbcomprasolicitudestado',
        'tbcomprasolicitudfleteestado', 'tbcomprasolicitudfecha', 'tbcomprasolicitudrespuestafecha',
        'tbcomprasolicitudrespuestamotivo', 'tbcomprasolicitudfleterespuestafecha',
    ],
    'tbtransportistaoferta' => [
        'tbtransportistaofertaid', 'tbtransportistaid', 'tbvehiculoid', 'tbdireccionid',
        'tbtransportistaofertaradiokm', 'tbtransportistaofertacapacidad', 'tbtransportistaofertaprecio',
        'tbtransportistaofertadescripcion', 'tbtransportistaofertaestado', 'tbtransportistaofertafecha',
    ],
    'tbespecie' => ['tbespecieid', 'tbespecienombre', 'tbespecieactivo'],
    'tbanimaltipo' => [
        'tbanimaltipoid', 'tbespecieid', 'tbanimaltiponombre', 'tbanimaltiposexo', 'tbanimaltipoactivo',
    ],
    'tbraza' => ['tbrazaid', 'tbespecieid', 'tbrazanombre', 'tbrazaactivo'],
    'tbanimalpublicacionanimal' => ['tbanimalpublicacionanimalid', 'tbanimalpublicacionid', 'tbanimalid'],
    'tbvacuna' => ['tbvacunaid', 'tbvacunanombre', 'tbvacunaactivo'],
    'tbanimalvacunacion' => [
        'tbanimalvacunacionid', 'tbanimalid', 'tbvacunaid', 'tbanimalvacunacionfecha', 'tbanimalvacunaciondosis',
        'tbanimalvacunacionlote', 'tbanimalvacunacionaplicadapor', 'tbanimalvacunacionproximadosis',
        'tbanimalvacunacionobservaciones', 'tbanimalvacunacionfecharegistro',
    ],
    'tbsubasta' => [
        'tbsubastaid', 'tbproductorid', 'tbanimalid', 'tbfincaid', 'tbsubastatitulo', 'tbsubastadescripcion',
        'tbsubastaimagenurl', 'tbsubastapreciobase', 'tbsubastaincrementominimo', 'tbsubastafechainicio',
        'tbsubastafechafin', 'tbsubastaestado', 'tbsubastapujaganadoraid', 'tbsubastafechacierre',
    ],
    'tbsubastapuja' => ['tbsubastapujaid', 'tbsubastaid', 'tbpersonaid', 'tbsubastapujamonto', 'tbsubastapujafecha'],
    'tbsubastacomentario' => [
        'tbsubastacomentarioid', 'tbsubastaid', 'tbpersonaid', 'tbsubastacomentariotexto', 'tbsubastacomentariofecha',
    ],
    'tbnotificacion' => [
        'tbnotificacionid', 'tbpersonaid', 'tbnotificaciontipo', 'tbnotificaciontitulo', 'tbnotificacionmensaje',
        'tbnotificacionenlace', 'tbnotificacionfecha', 'tbnotificacionleida',
    ],
    'tbpersonacorreocambio' => [
        'tbpersonacorreocambioid', 'tbpersonaid', 'tbpersonacorreocambiosujeto', 'tbpersonacorreocambioanterior',
        'tbpersonacorreocambionuevo', 'tbpersonacorreocambioestado', 'tbpersonacorreocambiofecha',
        'tbpersonacorreocambiofecharesolucion',
    ],
];

function schemaColumnDifferences(PDO $connection): array
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
    $differences = [];
    foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $table) {
        $expectedColumns = $expected[$table] ?? [];
        $actualColumns = $actual[$table] ?? [];
        if ($expectedColumns !== $actualColumns) {
            $differences[] = sprintf('%s esperado=[%s] actual=[%s]', $table,
                implode(',', $expectedColumns), implode(',', $actualColumns));
        }
    }

    return $differences;
}

function validateSchema(PDO $connection, string $schema): void
{
    $differences = schemaColumnDifferences($connection);
    if ($differences !== []) {
        throw new RuntimeException(
            'El esquema Supabase no coincide con el contrato de 48 tablas: ' . implode('; ', $differences)
        );
    }
    if (!schemaHasRequiredRowSecurity($connection)) {
        throw new RuntimeException('El esquema Supabase no conserva RLS sin políticas en las 48 tablas.');
    }
    if (!schemaHasExpectedNullability($connection, $schema)) {
        throw new RuntimeException('La nulabilidad del esquema Supabase no coincide con el contrato actual.');
    }
}

/** Devuelve las llaves primarias que no coinciden con `<tabla>id`. */
function schemaPrimaryKeyDifferences(PDO $connection): array
{
    $tables = '{' . implode(',', array_keys(EXPECTED_COLUMNS)) . '}';
    $rows = $connection->query(
        "SELECT c.relname AS table_name, a.attname AS column_name
         FROM pg_constraint k
         INNER JOIN pg_class c ON c.oid = k.conrelid
         INNER JOIN pg_namespace n ON n.oid = c.relnamespace
         CROSS JOIN LATERAL unnest(k.conkey) WITH ORDINALITY AS pk_column(attnum, ordinal)
         INNER JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = pk_column.attnum
         WHERE n.nspname = 'public' AND k.contype = 'p'
           AND c.relname = ANY(CAST(" . $connection->quote($tables) . " AS text[]))
         ORDER BY c.relname, pk_column.ordinal"
    )->fetchAll(PDO::FETCH_ASSOC);
    $actual = [];
    foreach ($rows as $row) {
        $actual[(string) $row['table_name']][] = (string) $row['column_name'];
    }

    $differences = [];
    foreach (array_keys(EXPECTED_COLUMNS) as $table) {
        if (($actual[$table] ?? []) !== [$table . 'id']) {
            $differences[$table] = $actual[$table] ?? [];
        }
    }

    return $differences;
}

/** Comprueba en modo lectura si otra instancia ya dejó las llaves esperadas. */
function schemaHasExpectedPrimaryKeys(PDO $connection): bool
{
    return schemaPrimaryKeyDifferences($connection) === [];
}

function schemaHasRequiredRowSecurity(PDO $connection): bool
{
    $tables = '{' . implode(',', array_keys(EXPECTED_COLUMNS)) . '}';
    $statement = $connection->prepare(
        "SELECT c.relname AS table_name, c.relrowsecurity, COUNT(p.oid) AS policy_count
         FROM pg_class c
         INNER JOIN pg_namespace n ON n.oid = c.relnamespace
         LEFT JOIN pg_policy p ON p.polrelid = c.oid
         WHERE n.nspname = 'public' AND c.relkind = 'r'
           AND c.relname = ANY(CAST(:tables AS text[]))
         GROUP BY c.relname, c.relrowsecurity"
    );
    $statement->execute(['tables' => $tables]);
    $actual = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $actual[(string) $row['table_name']] = [
            'enabled' => in_array($row['relrowsecurity'], [true, 1, '1', 't'], true),
            'policies' => (int) $row['policy_count'],
        ];
    }

    foreach (array_keys(EXPECTED_COLUMNS) as $table) {
        if (($actual[$table] ?? null) !== ['enabled' => true, 'policies' => 0]) {
            return false;
        }
    }

    return true;
}

function schemaNullabilityDifferences(PDO $connection, string $schema): array
{
    preg_match_all(
        '/^CREATE TABLE IF NOT EXISTS public\.([a-z0-9_]+) \(\s*\n(.*?)^\);$/ms',
        $schema,
        $tableDefinitions,
        PREG_SET_ORDER,
    );
    if (count($tableDefinitions) !== count(EXPECTED_COLUMNS)) {
        throw new RuntimeException('No se pudo leer la nulabilidad de las 48 tablas de schema.sql.');
    }

    $expected = [];
    foreach ($tableDefinitions as $tableDefinition) {
        $table = $tableDefinition[1];
        foreach (explode("\n", $tableDefinition[2]) as $line) {
            $definition = rtrim(trim($line), ',');
            if ($definition === '' || str_starts_with($definition, 'PRIMARY KEY')) {
                continue;
            }
            if (!preg_match('/^([a-z0-9_]+)\s+.+?\s+(NOT NULL|NULL)$/', $definition, $columnDefinition)) {
                throw new RuntimeException("No se pudo leer una columna de public.{$table} en schema.sql.");
            }
            $expected[$table][$columnDefinition[1]] = $columnDefinition[2] === 'NULL';
        }
    }
    foreach (EXPECTED_COLUMNS as $table => $columns) {
        $expectedColumns = array_keys($expected[$table] ?? []);
        sort($expectedColumns);
        sort($columns);
        if ($expectedColumns !== $columns) {
            throw new RuntimeException("La nulabilidad de public.{$table} no coincide con EXPECTED_COLUMNS.");
        }
    }

    $tables = '{' . implode(',', array_keys(EXPECTED_COLUMNS)) . '}';
    $statement = $connection->prepare(
        "SELECT table_name, column_name, is_nullable
         FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = ANY(CAST(:tables AS text[]))"
    );
    $statement->execute(['tables' => $tables]);
    $actual = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $actual[(string) $row['table_name']][(string) $row['column_name']] = $row['is_nullable'] === 'YES';
    }

    $differences = [];
    foreach ($expected as $table => $columns) {
        foreach ($columns as $column => $nullable) {
            // La PK impone NOT NULL; IDs con valores nulos se conservan en modo degradado.
            if ($column === $table . 'id') {
                continue;
            }
            if (($actual[$table][$column] ?? null) !== $nullable) {
                $differences[] = ['table' => $table, 'column' => $column, 'nullable' => $nullable];
            }
        }
    }

    return $differences;
}

function schemaHasExpectedNullability(PDO $connection, string $schema): bool
{
    return schemaNullabilityDifferences($connection, $schema) === [];
}
