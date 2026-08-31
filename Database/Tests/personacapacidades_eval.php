<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$schema = file_get_contents($root . '/SqlScripts/000instalacioncompleta.sql') ?: '';
$migration = file_get_contents($root . '/Migrations/003personacapacidades.sql') ?: '';
$checks = [
  'quince_tablas' => substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 15,
  'identidad_unica' => str_contains($schema, 'CREATE TABLE IF NOT EXISTS tbpersona'),
  'perfiles_minimos' => !preg_match('/tb(productor|comprador|transportista)identificacionnumero/', $schema),
  'aborta_duplicados' => str_contains($migration, 'MIGRACION_ABORTADA_CAPACIDAD_DUPLICADA'),
  'aborta_incompatibles' => str_contains($migration, 'MIGRACION_ABORTADA_DATOS_PERSONALES_INCOMPATIBLES'),
  'bloqueo_adquirido' => str_contains($migration, 'IF(@migracion_lock = 1'),
  'valida_antes_ddl' => strpos($migration, 'MIGRACION_ABORTADA_DATOS_PERSONALES_INCOMPATIBLES')
      < strpos($migration, 'CREATE TABLE tbpersona'),
  'preserva_ids' => !str_contains($migration, 'UPDATE tbproductor SET tbproductorid'),
  'sin_objetos_persistentes' => !preg_match('/CREATE\s+(PROCEDURE|FUNCTION|TRIGGER|EVENT)/i', $migration),
  'columnas_intactas_en_conflicto' => strpos($migration, 'ALTER TABLE tbproductor ADD COLUMN tbpersonaid')
      > strpos($migration, 'EXECUTE preflight'),
];
$score = (int) round(100 * count(array_filter($checks)) / count($checks));
echo json_encode(['eval' => 'mysql_persona_capacidades', 'score' => $score,
  'threshold' => 100, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($score === 100 ? 0 : 1);
