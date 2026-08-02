<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'Database/SqlScripts/002_create_productores.sql',
    'Database/SqlScripts/003_create_productores_direccion.sql',
    'Database/SqlScripts/004_create_productores_finca.sql',
    'Application/Model/Productor.php',
    'Application/Model/ProductorDireccion.php',
    'Application/Model/ProductorFinca.php',
    'Public/api/productores.php',
];
foreach ($required as $file) {
    if (!is_file("{$root}/{$file}")) throw new RuntimeException("Falta {$file}");
}
$forbiddenFiles = [
    'Application/Model/Participante.php', 'Application/Model/Rol.php',
    'Application/Model/TipoIdentificacion.php', 'Application/Model/Finca.php',
    'Database/SqlScripts/002_create_catalogs.sql',
    'Database/SqlScripts/003_create_participante_schema.sql',
];
foreach ($forbiddenFiles as $file) {
    if (file_exists("{$root}/{$file}")) throw new RuntimeException("Archivo obsoleto: {$file}");
}
$sql = implode("\n", array_map('file_get_contents', glob("{$root}/Database/SqlScripts/*.sql")));
foreach (['tbproductor', 'tbproductordireccion', 'tbproductorfinca', 'tbbitacora'] as $table) {
    if (!str_contains($sql, "CREATE TABLE IF NOT EXISTS {$table}")) throw new RuntimeException("Falta tabla {$table}");
}
foreach (['tbparticipante ', 'tbrol ', 'tbparticipanterol ', 'tbidentificaciontipo ',
    'tbparticipanteidentificacion ', 'CREATE TABLE IF NOT EXISTS tbfinca'] as $obsolete) {
    if (str_contains($sql, $obsolete)) throw new RuntimeException("Referencia obsoleta en SQL: {$obsolete}");
}
foreach (['PRIMARY KEY', 'FOREIGN KEY', 'CHECK (', 'CONSTRAINT ', 'REFERENCES ', 'ON UPDATE', 'ON DELETE'] as $forbiddenSql) {
    if (str_contains($sql, $forbiddenSql)) {
        throw new RuntimeException("El esquema no puede contener {$forbiddenSql}");
    }
}
if (!preg_match('/tbproductorId INT NOT NULL/', $sql)
    || preg_match('/tbproductorId[^,\n]*AUTO_INCREMENT/', $sql)) {
    throw new RuntimeException('tbproductorId debe ser INT ordinario sin AUTO_INCREMENT.');
}
foreach (['tbproductores ', 'tbproductoresdireccion', 'tbproductoresfinca'] as $plural) {
    if (str_contains($sql, $plural)) throw new RuntimeException("Nombre plural prohibido: {$plural}");
}
if (substr_count($sql, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;') !== 5
    || !str_contains($sql, 'ALTER DATABASE dbtindercows')) {
    throw new RuntimeException('SQL no fija utf8mb4_unicode_ci de forma consistente.');
}
$controller = file_get_contents("{$root}/Application/Controller/ProductorController.php");
if (str_contains($controller, 'participanteId') || str_contains($controller, 'tbrol')) {
    throw new RuntimeException('El controlador aún depende del modelo descartado.');
}
$models = implode("\n", array_map('file_get_contents', glob("{$root}/Application/Model/*.php")));
if (str_contains($models, '->query(') || str_contains($models, '->exec(')
    || !str_contains($models, '->prepare(') || !str_contains($models, 'GET_LOCK')
    || !str_contains($models, 'MAX(tbproductorId)')) {
    throw new RuntimeException('Los modelos deben usar sentencias preparadas y calcular tbproductorId en PHP.');
}
$databaseConfig = file_get_contents("{$root}/Configuration/Database.php");
if (!str_contains($databaseConfig, 'PDO::ATTR_EMULATE_PREPARES => false')) {
    throw new RuntimeException('PDO debe usar sentencias preparadas nativas.');
}
$js = file_get_contents("{$root}/Public/js/productores.js");
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
