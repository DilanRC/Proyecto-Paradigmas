<?php

declare(strict_types=1);

/**
 * Un respaldo antiguo restaurado debe poder llegar al esquema vigente con las migraciones.
 * Prueba estática (no necesita base): la restauración real se documenta en Respaldos.md.
 */
$raiz = dirname(__DIR__);
$falla = static function (string $mensaje): void {
    fwrite(STDERR, "FALLO migraciones_cadena_test: {$mensaje}\n");
    exit(1);
};
$tablas = static function (string $sql): array {
    preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(tb[a-z]+)`?/i', $sql, $m);
    return array_values(array_unique(array_map('strtolower', $m[1])));
};

$canonicas = $tablas((string) file_get_contents("{$raiz}/Database/SqlScripts/000instalacioncompleta.sql"));
// Avance03 es el último respaldo anterior a la cadena de migraciones 006 en adelante.
$respaldo = $tablas((string) file_get_contents("{$raiz}/Database/Backups/Avance03/dbmercadoganadero_avance03_estructura.sql"));
if (count($respaldo) !== 15) {
    $falla('el respaldo Avance03 debe declarar sus 15 tablas; se leyeron ' . count($respaldo));
}

$migraciones = '';
foreach (glob("{$raiz}/Database/Migrations/0*.sql") ?: [] as $archivo) {
    $migraciones .= "\n" . file_get_contents($archivo);
}
$creadas = $tablas($migraciones);
preg_match_all('/RENAME TABLE\s+\w+\s+TO\s+(tb[a-z]+)/i', $migraciones, $renombradas);
$alcanzables = array_merge($respaldo, $creadas, array_map('strtolower', $renombradas[1]));
$sinMigracion = array_values(array_diff($canonicas, $alcanzables));
if ($sinMigracion !== []) {
    $falla('tablas del esquema vigente que ninguna migración crea: ' . implode(', ', $sinMigracion));
}

// MySQL resuelve las tablas de una subconsulta aunque el IF no tome esa rama: toda lectura de
// la tabla heredada debe ir dentro de un texto para PREPARE, o la 009 falla sin esa tabla.
$normaliza = (string) file_get_contents("{$raiz}/Database/Migrations/009normalizahistoricocomprador.sql");
$sinTextos = preg_replace("/'(?:[^']|'')*'/s", "''", preg_replace('/^\s*--.*$/m', '', $normaliza));
if (preg_match('/\bFROM\s+tbcompradortelefonohistorico\b/i', (string) $sinTextos)) {
    $falla('la migración 009 lee tbcompradortelefonohistorico fuera de PREPARE');
}

// phpMyAdmin sigue ejecutando después del error de la comprobación: ningún ALTER puede depender
// de que el cliente se detenga.
$llaves = (string) file_get_contents("{$raiz}/Database/Migrations/025llavesprimarias.sql");
$alter = substr_count($llaves, 'ADD PRIMARY KEY');
if ($alter < 47 || $alter !== substr_count($llaves, 'IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS')) {
    $falla('cada ALTER de la migración 025 debe exigir @repetidos = 0');
}

echo 'OK migraciones_cadena_test: las ' . count($canonicas) . " tablas vigentes se alcanzan desde el respaldo Avance03, la 009 tolera una base ya normalizada y la 025 no cambia nada tras abortar.\n";
