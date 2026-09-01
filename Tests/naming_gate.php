<?php

declare(strict_types=1);

$root = dirname(__DIR__);
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
foreach (['tbproductor', 'tbproductordireccion', 'tbfinca', 'tbbitacora', 'tbcomprador',
    'tbdireccion', 'tbfincadireccion', 'tbpagometodo', 'tbtransportista', 'tbvehiculo',
    'tbtransportistavehiculo', 'tbproductorestadoperiodo', 'tbproductorubicacion',
    'tbproductoractividad'] as $table) {
    if (!str_contains($sql, "CREATE TABLE IF NOT EXISTS {$table}")) throw new RuntimeException("Falta tabla {$table}");
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
    || !str_contains($sql, 'ALTER DATABASE dbmercadoganadero')) {
    throw new RuntimeException('SQL no fija utf8mb4_unicode_ci de forma consistente.');
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
    'FROM tbtransportistavehiculo', 'HAVING COUNT(*) > 1'] as $consulta) {
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

foreach (['AvanceSemanal.pdf', 'DAplicacion.pdf', 'DER.pdf'] as $pdf) {
    $path = "{$root}/Documentation/{$pdf}";
    if (!is_file($path) || filesize($path) < 1000 || file_get_contents($path, false, null, 0, 4) !== '%PDF') {
        throw new RuntimeException("PDF obligatorio inválido: {$pdf}");
    }
}

echo "OK naming_gate: tablas singulares, cero PK/FK/CHECK, ID PHP, sentencias preparadas y PDFs.\n";