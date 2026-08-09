<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = implode("\n", array_map('file_get_contents', glob("{$root}/Database/SqlScripts/*.sql")));
$docs = file_get_contents("{$root}/Documentation/Decisiones.md") . file_get_contents("{$root}/Documentation/DiccionarioDatos.md");
$readme = file_get_contents("{$root}/README.md");
$restoreTool = file_get_contents("{$root}/Tools/test-restore.sh");
$checks = [];
$evaluate = static function (string $criterio, bool $cumple, string $evidencia) use (&$checks): void {
    $checks[] = compact('criterio', 'cumple', 'evidencia');
};
$evaluate('cuatro_tablas', substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 4, 'SQL crea exactamente cuatro tablas');
$evaluate('cero_restricciones_indices', !str_contains($schema, 'PRIMARY KEY')
    && !str_contains($schema, 'FOREIGN KEY') && !str_contains($schema, 'CHECK (')
    && !str_contains($schema, 'CONSTRAINT ') && !str_contains($schema, 'AUTO_INCREMENT')
    && !str_contains($schema, 'CREATE INDEX') && !str_contains($schema, 'UNIQUE'),
    'El esquema no contiene claves, restricciones, índices ni AUTO_INCREMENT');
$evaluate('productor_id_php', str_contains($schema, 'tbproductorid INT NOT NULL'),
    'tbproductorid es INT ordinario y el consecutivo pertenece a PHP');
$evaluate('direccion_politica_aplicacion', !str_contains($schema, 'pk_tbproductordireccion')
    && str_contains($docs, 'política de aplicación'), 'Dirección no tiene llave y su relación 1:1 pertenece a la aplicación');
$evaluate('tabla_finca', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbfinca')
    && !str_contains($schema, 'tbproductorfinca'), 'La finca usa la tabla tbfinca solicitada');
$evaluate('sin_roles_catalogos', !str_contains($schema, 'tbrol') && !str_contains($schema, 'tbidentificaciontipo'), 'No existen tablas de rol o tipo');
$evaluate('bitacora_textual', str_contains($schema, 'tbbitacoraregistroidentificacionnumero VARCHAR'), 'Bitácora conserva la identificación lógica textual');
$evaluate('collation_consistente', str_contains($schema, 'ALTER DATABASE dbtindervacas')
    && substr_count($schema, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;') === 5,
    'Base y sesiones declaran utf8mb4_unicode_ci');
$evaluate('sin_reglas_referenciales', !str_contains($schema, 'ON UPDATE') && !str_contains($schema, 'ON DELETE'),
    'No existen reglas referenciales porque no existen FK');
$evaluate('tablas_singulares', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbproductor ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbproductordireccion ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbfinca '),
    'Las tablas usan nombres singulares');
$models = implode("\n", array_map('file_get_contents', glob("{$root}/Application/Model/*.php")));
$evaluate('sentencias_preparadas', str_contains($models, '->prepare(')
    && !str_contains($models, '->query(') && !str_contains($models, '->exec('),
    'Los modelos usan PDO prepare sin query o exec');
$evaluate('consecutivos_php', str_contains($models, 'MAX(tbproductorid)')
    && str_contains($models, 'MAX(tbbitacoraid)') && str_contains($models, 'GET_LOCK'),
    'PHP calcula y serializa los consecutivos');
$evaluate('identificadores_minusculos', !preg_match('/\btb[a-z0-9]*[A-Z][A-Za-z0-9]*/', $schema),
    'Todos los identificadores SQL están en minúscula');
$evaluate('decision_docente', str_contains($docs, 'instrucción docente') && str_contains($docs, 'tbproductor'), 'Decisión de corrección documentada');
$evaluate('protocolo_identificacion', str_contains($docs, 'desactivar el registro incorrecto')
    && str_contains($docs, 'conservar su bitácora') && str_contains($docs, 'crear el registro correcto'),
    'La corrección de identificación conserva la trazabilidad');
$evaluate('readme_operativo', str_contains($readme, 'dbtindervacas') && str_contains($readme, 'docker compose'), 'README conserva instalación reproducible');
$evaluate('pdf_obligatorios', count(array_filter(['AvanceSemanal.pdf', 'DAplicacion.pdf', 'DER.pdf'],
    fn (string $pdf): bool => is_file("{$root}/Documentation/{$pdf}") && filesize("{$root}/Documentation/{$pdf}") > 1000)) === 3,
    'Existen los tres PDF de la entrega');
$evaluate('restauracion_sin_falso_positivo', str_contains($restoreTool, 'AS signature')
    && str_contains($restoreTool, 'if ! output=') && str_contains($restoreTool, 'return 1'),
    'El comparador propaga errores SQL y agrupa metadatos por una firma con alias');
$evaluate('restauracion_cero_restricciones', str_contains($restoreTool, 'constraint_count')
    && str_contains($restoreTool, 'check_count') && str_contains($restoreTool, 'foreign_key_count'),
    'La restauración exige cero restricciones, PK, FK y CHECK');
$score = (int) round(100 * count(array_filter($checks, fn ($c) => $c['cumple'])) / count($checks));
echo json_encode(['eval' => 'modelo_simplificado_profesor', 'score' => $score, 'threshold' => 100, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if ($score < 100) exit(1);
