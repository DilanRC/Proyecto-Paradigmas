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
$evaluate('pk_identificacion', str_contains($schema, 'PRIMARY KEY (tbproductoresIdentificacionNumero)'), 'Identificación es PK del productor');
$evaluate('direccion_1_1', str_contains($schema, 'pk_tbproductoresdireccion') && str_contains($schema, 'fk_tbproductoresdireccion_productor'), 'Dirección comparte PK/FK');
$evaluate('finca_sin_entidad_separada', str_contains($schema, 'tbproductoresfinca') && !str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbfinca'), 'Finca queda dentro de productoresFinca');
$evaluate('sin_roles_catalogos', !str_contains($schema, 'tbrol') && !str_contains($schema, 'tbidentificaciontipo'), 'No existen tablas de rol o tipo');
$evaluate('bitacora_textual', str_contains($schema, 'tbbitacoraRegistroIdentificacionNumero VARCHAR'), 'Bitácora usa la PK textual');
$evaluate('collation_consistente', str_contains($schema, 'ALTER DATABASE dbtindercows')
    && substr_count($schema, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;') === 5,
    'Base y sesiones declaran utf8mb4_unicode_ci');
$evaluate('fk_restrict', substr_count($schema, 'ON UPDATE RESTRICT ON DELETE RESTRICT') === 2
    && !str_contains($schema, 'ON UPDATE CASCADE'), 'Las dos FK restringen UPDATE y DELETE');
$evaluate('decision_docente', str_contains($docs, 'instrucción docente') && str_contains($docs, 'tbproductores'), 'Decisión de corrección documentada');
$evaluate('protocolo_identificacion', str_contains($docs, 'desactivar el registro incorrecto')
    && str_contains($docs, 'conservar su bitácora') && str_contains($docs, 'crear el registro correcto'),
    'La corrección de identificación conserva la trazabilidad');
$evaluate('readme_operativo', str_contains($readme, 'dbtindercows') && str_contains($readme, 'docker compose'), 'README conserva instalación reproducible');
$evaluate('pdf_obligatorios', count(array_filter(['AvanceSemanal.pdf', 'DAplicacion.pdf', 'DER.pdf'],
    fn (string $pdf): bool => is_file("{$root}/Documentation/{$pdf}") && filesize("{$root}/Documentation/{$pdf}") > 1000)) === 3,
    'Existen los tres PDF de la entrega');
$evaluate('restauracion_sin_falso_positivo', str_contains($restoreTool, 'AS signature')
    && str_contains($restoreTool, 'if ! output=') && str_contains($restoreTool, 'return 1'),
    'El comparador propaga errores SQL y agrupa metadatos por una firma con alias');
$score = (int) round(100 * count(array_filter($checks, fn ($c) => $c['cumple'])) / count($checks));
echo json_encode(['eval' => 'modelo_simplificado_profesor', 'score' => $score, 'threshold' => 100, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if ($score < 100) exit(1);
