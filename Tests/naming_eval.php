<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schemaFiles = glob("{$root}/Database/SqlScripts/*.sql");
$schema = implode("\n", array_map('file_get_contents', $schemaFiles));
$seed = file_get_contents("{$root}/Database/SeedData/101initialpagometodo.sql");
$diagnostico = file_get_contents("{$root}/Database/Tests/diagnostico.sql");
$relaciones = file_get_contents("{$root}/Database/Tests/comprobacionrelaciones.sql");
$docs = file_get_contents("{$root}/Documentation/Decisiones.md") . file_get_contents("{$root}/Documentation/DiccionarioDatos.md");
$readme = file_get_contents("{$root}/README.md");
$restoreTool = file_get_contents("{$root}/Tools/test-restore.sh");
$checks = [];
$evaluate = static function (string $criterio, bool $cumple, string $evidencia) use (&$checks): void {
    $checks[] = compact('criterio', 'cumple', 'evidencia');
};
$evaluate('once_tablas', substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 11,
    'SQL crea exactamente once tablas: cinco del CRUD vigente y seis del avance de direcciones, pagos y transporte');
$evaluate('cero_restricciones_indices', !str_contains($schema, 'PRIMARY KEY')
    && !str_contains($schema, 'FOREIGN KEY') && !str_contains($schema, 'CHECK (')
    && !str_contains($schema, 'CONSTRAINT ') && !str_contains($schema, 'AUTO_INCREMENT')
    && !str_contains($schema, 'CREATE INDEX') && !str_contains($schema, 'UNIQUE'),
    'El esquema no contiene claves, restricciones, índices ni AUTO_INCREMENT');
$evaluate('sin_logica_motor', !str_contains($schema, 'DEFAULT ')
    && !str_contains($schema, 'CURRENT_TIMESTAMP') && !str_contains($schema, 'CREATE TRIGGER')
    && !str_contains($schema, 'CREATE PROCEDURE') && !str_contains($schema, 'CREATE FUNCTION')
    && !str_contains($schema, 'CREATE EVENT'),
    'El motor no asigna valores ni ejecuta lógica automática');
$evaluate('productor_id_php', str_contains($schema, 'tbproductorid INT NOT NULL'),
    'tbproductorid es INT ordinario y el consecutivo pertenece a PHP');
$evaluate('direccion_politica_aplicacion', !str_contains($schema, 'pk_tbproductordireccion')
    && str_contains($docs, 'política de aplicación'), 'Dirección no tiene llave y su relación 1:1 pertenece a la aplicación');
$evaluate('tabla_finca', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbfinca')
    && !str_contains($schema, 'tbproductorfinca'), 'La finca usa la tabla tbfinca solicitada');
$evaluate('tabla_comprador', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbcomprador')
    && str_contains($schema, 'tbcompradoridentificacionnumero VARCHAR(250) NOT NULL'),
    'Comprador tiene nombre singular y perfil de identificación explícito');
$evaluate('sin_roles_catalogos', !str_contains($schema, 'tbrol') && !str_contains($schema, 'tbidentificaciontipo'), 'No existen tablas de rol o tipo');
$evaluate('bitacora_textual', str_contains($schema, 'tbbitacoraregistroidentificacionnumero VARCHAR'), 'Bitácora conserva la identificación lógica textual');
$evaluate('collation_consistente', str_contains($schema, 'ALTER DATABASE dbtindervacas')
    && substr_count($schema, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;') === count($schemaFiles),
    'Base y sesiones declaran utf8mb4_unicode_ci');
$evaluate('direccion_centralizada', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbdireccion ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbfincadireccion ')
    && str_contains($schema, 'tbdireccionid INT NULL'),
    'La ubicación vive en tbdireccion y productor y finca la referencian por tbdireccionid');
$evaluate('direccion_compartida_demostrada', str_contains($relaciones, 'direccion_compartida')
    && str_contains($relaciones, 'direccion_productor') && str_contains($relaciones, 'direccion_finca'),
    'La comprobación demuestra ubicación compartida y ubicaciones distintas');
$evaluate('asociaciones_con_identificador', str_contains($schema, 'tbfincadireccionid INT NOT NULL')
    && str_contains($schema, 'tbtransportistavehiculoid INT NOT NULL'),
    'Cada asociación tiene identificador propio');
$evaluate('transporte_confirmado', str_contains($schema, 'tbvehiculoplaca VARCHAR(20) NOT NULL')
    && str_contains($schema, 'tbvehiculovin VARCHAR(50) NOT NULL')
    && str_contains($schema, 'tbvehiculomodelo VARCHAR(100) NOT NULL')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbtransportista '),
    'Vehículo registra placa, vin y modelo y el transportista es independiente');
$evaluate('pago_efectivo', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbpagometodo ')
    && str_contains($seed, "'Efectivo', 'Pago realizado en efectivo', 1")
    && stripos($seed, 'SINPE') === false,
    'El catálogo de pago existe y solo contiene efectivo');
$evaluate('diagnostico_sin_restriccion', str_contains($diagnostico, 'DETECTAN')
    && str_contains($diagnostico, 'No las IMPIDEN')
    && substr_count($diagnostico, 'HAVING COUNT') >= 5,
    'Las consultas de diagnóstico detectan inconsistencias sin impedirlas');
$evaluate('sin_reglas_referenciales', !str_contains($schema, 'ON UPDATE') && !str_contains($schema, 'ON DELETE'),
    'No existen reglas referenciales porque no existen FK');
$evaluate('tablas_singulares', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbproductor ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbproductordireccion ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbfinca ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbcomprador ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbdireccion ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbvehiculo ')
    && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbtransportistavehiculo '),
    'Las tablas usan nombres singulares');
$models = implode("\n", array_map('file_get_contents', glob("{$root}/Application/Model/*.php")));
$evaluate('sentencias_preparadas', str_contains($models, '->prepare(')
    && !str_contains($models, '->query(') && !str_contains($models, '->exec('),
    'Los modelos usan PDO prepare sin query o exec');
$evaluate('fecha_bitacora_php', str_contains($models, "'fecha' => gmdate('Y-m-d H:i:s')")
    && str_contains($models, ':fecha'), 'PHP asigna explícitamente la fecha de bitácora');
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
$evaluate('restauracion_sin_logica_motor', str_contains($restoreTool, 'automatic_columns')
    && str_contains($restoreTool, 'programmable_objects'),
    'La restauración exige cero defaults, generación automática y objetos programables');
$score = (int) round(100 * count(array_filter($checks, fn ($c) => $c['cumple'])) / count($checks));
echo json_encode(['eval' => 'modelo_simplificado_profesor', 'score' => $score, 'threshold' => 100, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if ($score < 100) exit(1);
