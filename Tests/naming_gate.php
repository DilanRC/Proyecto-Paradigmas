<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once "{$root}/Tools/schema-manifest.php";
$required = [
    'Database/SqlScripts/000instalacioncompleta.sql',
    'Database/SeedData/101initialpagometodo.sql',
    'Database/SeedData/103exampleproductores.sql',
    'Database/Tests/comprobacionestructura.sql',
    'Database/Tests/comprobaciondatosiniciales.sql',
    'Database/Tests/comprobacionrelaciones.sql',
    'Database/Tests/diagnostico.sql',
    'Database/Migrations/001normalizadireccionproductor.sql',
    'Application/Model/Productor.php',
    'Application/Model/ProductorDireccion.php',
    'Application/Model/ProductorEstadoPeriodo.php',
    'Application/Model/ProductorFinca.php',
    'Application/Model/ProductorUbicacion.php',
    'Public/api/productores.php',
    'Public/api/productores-ubicacion.php',
];
foreach ($required as $file) {
    if (!is_file("{$root}/{$file}")) throw new RuntimeException("Falta {$file}");
}
$forbiddenFiles = [
    'Application/Model/Participante.php', 'Application/Model/Rol.php',
    'Application/Model/TipoIdentificacion.php', 'Application/Model/Finca.php',
    'Database/SqlScripts/002_create_catalogs.sql',
    'Database/SqlScripts/003_create_participante_schema.sql',
    'Database/SqlScripts/001createdatabase.sql',
    'Database/SqlScripts/002createproductores.sql',
    'Database/SqlScripts/003createproductoresdireccion.sql',
    'Database/SqlScripts/004createfinca.sql',
    'Database/SqlScripts/005createaudit.sql',
    'Database/SqlScripts/006createcomprador.sql',
    'Database/SqlScripts/007createdireccion.sql',
    'Database/SqlScripts/008createfincadireccion.sql',
    'Database/SqlScripts/009createpagometodo.sql',
    'Database/SqlScripts/010createtransportista.sql',
    'Database/SqlScripts/011createvehiculo.sql',
    'Database/SqlScripts/012createtransportistavehiculo.sql',
];
foreach ($forbiddenFiles as $file) {
    if (file_exists("{$root}/{$file}")) throw new RuntimeException("Archivo obsoleto: {$file}");
}
$sqlFiles = glob("{$root}/Database/SqlScripts/*.sql");
$sql = implode("\n", array_map('file_get_contents', $sqlFiles));
$manifest = schema_manifest();
$expectedTables = ['tbbitacora', 'tbcomprador', 'tbdireccion', 'tbfinca', 'tbfincadireccion',
    'tbpagometodo', 'tbpersona', 'tbproductor', 'tbproductoractividad', 'tbproductordireccion',
    'tbproductorestadoperiodo', 'tbproductorubicacion', 'tbtransportista',
    'tbtransportistavehiculo', 'tbvehiculo'];
if ($manifest['tables_sorted'] !== $expectedTables) {
    throw new RuntimeException('El listado de tablas no coincide con el SQL canónico.');
}
foreach (['tbparticipante ', 'tbrol ', 'tbparticipanterol ', 'tbidentificaciontipo ',
    'tbparticipanteidentificacion ', 'tbproductorfinca'] as $obsolete) {
    if (str_contains($sql, $obsolete)) throw new RuntimeException("Referencia obsoleta en SQL: {$obsolete}");
}
foreach (['PRIMARY KEY', 'FOREIGN KEY', 'CHECK (', 'CONSTRAINT ', 'REFERENCES ', 'ON UPDATE', 'ON DELETE',
    'AUTO_INCREMENT', 'CREATE INDEX', 'CREATE UNIQUE INDEX', 'UNIQUE KEY', 'UNIQUE ('] as $forbiddenSql) {
    if (str_contains($sql, $forbiddenSql)) {
        throw new RuntimeException("El esquema no puede contener {$forbiddenSql}");
    }
}
foreach (['DEFAULT ', 'CURRENT_TIMESTAMP', 'CREATE TRIGGER', 'CREATE PROCEDURE', 'CREATE FUNCTION', 'CREATE EVENT'] as $engineLogic) {
    if (str_contains($sql, $engineLogic)) {
        throw new RuntimeException("La lógica no puede delegarse al motor mediante {$engineLogic}");
    }
}
if (!preg_match('/tbproductorid INT NOT NULL/', $sql)) {
    throw new RuntimeException('tbproductorid debe ser INT ordinario sin AUTO_INCREMENT.');
}
if (preg_match('/\btb[a-z0-9]*[A-Z][A-Za-z0-9]*/', $sql, $coincidencia)) {
    throw new RuntimeException("Identificador camelCase prohibido en SQL: {$coincidencia[0]}");
}
foreach (['tbproductores ', 'tbproductoresdireccion', 'tbproductoresfinca'] as $plural) {
    if (str_contains($sql, $plural)) throw new RuntimeException("Nombre plural prohibido: {$plural}");
}
$modulosEsperados = 12; // 001createdatabase .. 012createtransportistavehiculo, unificados en 000instalacioncompleta.sql
if (substr_count($sql, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;') !== $modulosEsperados
    || !str_contains($sql, 'ALTER DATABASE bdmercadoganadero')) {
    throw new RuntimeException('SQL no fija utf8mb4_unicode_ci de forma consistente.');
}
// El script debe nombrar una sola base. Comprobar solo ALTER DATABASE dejaba pasar
// un CREATE DATABASE con otro nombre: un cambio previo creó un nombre distinto
// mientras ALTER y USE seguian en bdmercadoganadero, y una instalacion sin
// compose fallaba porque la base del ALTER no existia. Compose lo ocultaba
// porque MYSQL_DATABASE ya creaba la base antes de correr el script.
preg_match_all('/(?:CREATE DATABASE(?: IF NOT EXISTS)?|ALTER DATABASE|USE)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $bases);
$nombresBase = array_unique($bases[1]);
if (count($nombresBase) !== 1) {
    throw new RuntimeException(
        'El script de instalacion nombra varias bases de datos ('
        . implode(', ', $nombresBase)
        . '); CREATE, ALTER y USE deben referirse a la misma.'
    );
}
if ($nombresBase[0] !== 'bdmercadoganadero') {
    throw new RuntimeException(
        "La base canonica es bdmercadoganadero y el script usa {$nombresBase[0]}; "
        . 'Configuration/Database.php y .env.example fijan ese contrato.'
    );
}
$avance = [
    'tbdireccionid INT NOT NULL' => 'tbdireccion y tbproductordireccion usan el identificador de ubicación',
    'tbfincadireccionid INT NOT NULL' => 'la asociación finca-dirección necesita identificador propio',
    'tbpagometodonombre VARCHAR(100) NOT NULL' => 'el catálogo de pago necesita nombre',
    'tbtransportistaid INT NOT NULL' => 'el transportista es una persona independiente',
    'tbvehiculoplaca VARCHAR(20) NOT NULL' => 'el vehículo registra placa',
    'tbvehiculovin VARCHAR(50) NOT NULL' => 'el vehículo registra vin',
    'tbvehiculomodelo VARCHAR(100) NOT NULL' => 'el vehículo registra modelo',
    'tbtransportistavehiculoid INT NOT NULL' => 'la asociación transportista-vehículo necesita identificador propio',
];
foreach ($avance as $fragmento => $motivo) {
    if (!str_contains($sql, $fragmento)) throw new RuntimeException("Falta {$fragmento}: {$motivo}");
}
foreach (['tbproductordireccionprovincia', 'tbproductordireccioncanton', 'tbproductordirecciondistrito',
    'tbproductordireccionpueblo', 'tbproductordireccionsenas'] as $duplicada) {
    if (str_contains($sql, $duplicada)) {
        throw new RuntimeException("La ubicación vive solo en tbdireccion: sobra {$duplicada}");
    }
}
$seed = file_get_contents("{$root}/Database/SeedData/101initialpagometodo.sql");
if (!str_contains($seed, "SELECT 1, 'Efectivo', 'Pago realizado en efectivo', 1")) {
    throw new RuntimeException('Los datos iniciales deben registrar Efectivo.');
}
foreach (['transferencia', 'SINPE', 'cheque', 'tarjeta', 'PayPal'] as $fueraDeAlcance) {
    if (stripos($seed, $fueraDeAlcance) !== false) {
        throw new RuntimeException("Método de pago fuera del alcance: {$fueraDeAlcance}");
    }
}
$diagnostico = file_get_contents("{$root}/Database/Tests/diagnostico.sql");
foreach (['FROM tbproductordireccion', 'FROM tbfincadireccion', 'FROM tbvehiculo',
    'FROM tbtransportistavehiculo', 'HAVING COUNT(*) > 1',
    'tbproductoractividad.tbproductorid', 'D-12 actividad fuera del catalogo cerrado',
    'D-13 tbcompradorestado fuera de dominio'] as $consulta) {
    if (!str_contains($diagnostico, $consulta)) {
        throw new RuntimeException("El diagnóstico debe incluir {$consulta}");
    }
}
$controller = file_get_contents("{$root}/Application/Controller/ProductorController.php");
if (str_contains($controller, 'participanteId') || str_contains($controller, 'tbrol')) {
    throw new RuntimeException('El controlador aún depende del modelo descartado.');
}
$models = implode("\n", array_map('file_get_contents', glob("{$root}/Application/Model/*.php")));
if (str_contains($models, '->query(') || str_contains($models, '->exec(')
    || !str_contains($models, '->prepare(') || !str_contains($models, 'GET_LOCK')
    || !str_contains($models, 'MAX(tbproductorid)')
    || !str_contains($models, 'MAX(tbbitacoraid)')
    || !str_contains($models, 'FROM tbfinca')
    || !str_contains($models, "'fecha' => gmdate('Y-m-d H:i:s')")) {
    throw new RuntimeException('Los modelos deben usar sentencias preparadas y calcular los ID en PHP.');
}
$databaseConfig = file_get_contents("{$root}/Configuration/Database.php");
if (!str_contains($databaseConfig, 'PDO::ATTR_EMULATE_PREPARES => false')) {
    throw new RuntimeException('PDO debe usar sentencias preparadas nativas.');
}
$matrizP0C = file_get_contents("{$root}/Documentation/MatrizArquitectonicaP0C.md");
foreach ([
    'Productor es la entidad de negocio núcleo',
    '`tbvendedor` no debe existir',
    '`tbcomprador` queda como estructura legacy',
    '`tbproductorcompradorperiodo` y `tbproductorvendedorperiodo`',
    'Visualización por fila sigue como propuesta',
] as $decisionP0C) {
    if (!str_contains($matrizP0C, $decisionP0C)) {
        throw new RuntimeException("P0-C no documenta: {$decisionP0C}");
    }
}
// El panel se reparte entre su archivo de entrada y los modulos compartidos que
// importa, asi que el control se busca sobre el grafo completo y no solo sobre
// el archivo de entrada.
$js = file_get_contents("{$root}/Public/js/productores.js")
    . implode('', array_map('file_get_contents', glob("{$root}/Public/js/shared/*.js")));
foreach (['fetch(', 'textContent', 'identificacionNumero', 'AbortController'] as $needle) {
    if (!str_contains($js, $needle)) throw new RuntimeException("Falta control UI {$needle}");
}
if (str_contains($js, 'participanteId') || str_contains($js, 'fincaId')) {
    throw new RuntimeException('JavaScript aún usa identificadores artificiales.');
}
$compose = file_get_contents("{$root}/compose.yaml");
if (str_contains($compose, 'create_catalogs') || str_contains($compose, 'identification_types')) {
    throw new RuntimeException('Docker aún monta catálogos descartados.');
}
foreach (['--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci'] as $setting) {
    if (!str_contains($compose, $setting)) throw new RuntimeException("Falta configuración Docker {$setting}");
}
$restoreTool = file_get_contents("{$root}/Tools/test-restore.sh");
foreach (['dbmercadoganadero', 'Respaldo validado sin modificar MANIFEST ni SHA256'] as $restoreContract) {
    if (!str_contains($restoreTool, $restoreContract)) {
        throw new RuntimeException("Restore debe conservar respaldos legados: falta {$restoreContract}");
    }
}
foreach (['mv -- "$manifest_temp" "$manifest_file"', 'mv -- "$manifest_pending" "$manifest_file"'] as $manifestMutation) {
    if (str_contains($restoreTool, $manifestMutation)) {
        throw new RuntimeException('Restore no debe modificar MANIFEST.md ni SHA256SUMS.txt.');
    }
}

foreach (['AvanceSemanal.pdf', 'DAplicacion.pdf', 'DER.pdf'] as $pdf) {
    $path = "{$root}/Documentation/{$pdf}";
    if (!is_file($path) || filesize($path) < 1000 || file_get_contents($path, false, null, 0, 4) !== '%PDF') {
        throw new RuntimeException("PDF obligatorio inválido: {$pdf}");
    }
}

echo "OK naming_gate: tablas singulares, cero PK/FK/CHECK, ID PHP, sentencias preparadas y PDFs.\n";
