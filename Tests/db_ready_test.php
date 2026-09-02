<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Tools/schema-manifest.php';

$root = dirname(__DIR__);
$schema = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$migration = file_get_contents("{$root}/Database/Migrations/006estructuracomercialhistorica.sql");
$diagnostico = file_get_contents("{$root}/Database/Tests/diagnostico.sql");
$decisiones = file_get_contents("{$root}/Documentation/Decisiones.md");
$diccionario = file_get_contents("{$root}/Documentation/DiccionarioDatos.md");
$der = file_get_contents("{$root}/Documentation/DER.md");
$manifest = schema_manifest();

test_same('bdmercadoganadero', $manifest['database'], 'La base activa debe ser bdmercadoganadero');
test_same(30, $manifest['table_count'], 'La capa DB ready debe contener 30 tablas');

foreach (['tbproductorclasificacionperiodo', 'tbanimal', 'tbanimalproduccionsalud', 'tbanimalpublicacion',
    'tbanimalpublicacionestadoperiodo', 'tbcompra', 'tbventa', 'tbanimalinteraccion',
    'tbcarrito', 'tbcarritoanimal', 'tbcarritoestadoperiodo',
    'tbtransportistaestadoperiodo', 'tbtransportistahorario', 'tbtransportistaflete',
    'tbtransportistaresena'] as $table) {
    test_assert(in_array($table, $manifest['tables_sorted'], true), "Falta {$table} en el SQL canónico");
    test_assert(str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"), "Falta {$table} en la migración");
    test_assert(str_contains($diagnostico, $table), "Falta diagnóstico para {$table}");
}

foreach (['INSERT INTO', 'UPDATE ', 'DELETE FROM', 'ALTER TABLE tbcomprador'] as $forbidden) {
    test_assert(!str_contains($migration, $forbidden), "La migración 006 no debe ejecutar {$forbidden}");
}

foreach (['CREATE TABLE IF NOT EXISTS tbvendedor', 'tbcompradorestadoperiodo',
    'tbvendedorestadoperiodo', 'tbvendedoractividad', 'tbanimalfechanacimiento',
    'tbcompraestado', 'cantidadfletessemanales', 'metodopagofrecuente',
    'calificacionpromedio'] as $forbidden) {
    test_assert(!str_contains($schema, $forbidden), "El esquema no debe contener {$forbidden}");
}

test_assert(str_contains($schema, 'tbcompraid INT NULL'), 'tbventa debe permitir tbcompraid NULL');
test_assert(substr_count($schema, 'CREATE TABLE IF NOT EXISTS tbcomprador') === 1,
    'tbcomprador se conserva legacy sin tablas satélite');
test_assert(str_contains($decisiones, '`tbcomprador` se conserva solo como legacy')
    && str_contains($decisiones, 'Trabajo de Backend que permite retirarla')
    && str_contains($diccionario, 'Estructura legacy de compatibilidad temporal')
    && str_contains($der, 'tbcomprador : "legacy por tbpersonaid"'),
    'Documentación debe marcar tbcomprador como legacy temporal con plan de retiro');
test_assert(!str_contains($decisiones, '`tbcomprador` tiene destino definitivo')
    && !str_contains($diccionario, 'Marca de capacidad de compra de una persona'),
    'tbcomprador no puede documentarse como capacidad permanente');

// Concordancia con la evidencia directa de Calidad (DEC-DBREADY-005): cada dato
// pedido tiene columna y ningún estado de negocio sobrevive como columna mutable.
foreach ([
    'tbanimalidentificacion' => 'identificación del animal',
    'tbanimalcaracteristicas' => 'características del animal',
    'tbventadireccionid' => 'dirección de la venta',
    'tbventaproposito' => 'propósito de la venta',
    'tbvehiculoid INT NULL' => 'vehículo usado en el flete',
    'tbtransportistafletecantidadcabezas' => 'cantidad de cabezas del flete',
    'tbtransportistafletedistanciakm' => 'distancia del flete',
    'tbtransportistahorariohorainicio' => 'horario del transportista',
] as $columna => $dato) {
    test_assert(str_contains($schema, $columna) && str_contains($migration, $columna),
        "Falta {$dato} ({$columna}) en esquema y migración");
}
foreach (['tbcarritoestado VARCHAR', 'tbanimalpublicacionestado VARCHAR',
    'tbanimalobservacion'] as $eliminado) {
    test_assert(!str_contains($schema, $eliminado) && !str_contains($migration, $eliminado),
        "El esquema no debe conservar {$eliminado}");
}
test_assert(str_contains($schema, 'tbanimalproduccionsaludedadmeses')
    && !str_contains($schema, 'tbanimaledad') && !str_contains($schema, 'tbanimalpeso'),
    'Edad y peso quedan fuera de la identidad del animal');
test_assert(str_contains($decisiones, 'locks en Backend')
    && str_contains($decisiones, 'MAX(id)+1')
    && str_contains($decisiones, 'lock global'),
    'Decisiones debe dejar contrato de IDs y locks para Backend');

echo "OK db_ready_test: esquema comercial histórico preparado sin pasado inventado ni tbvendedor.\n";
