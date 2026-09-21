<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Tools/schema-manifest.php';

$manifest = schema_manifest();
$expectedTables = ['tbanimal', 'tbanimalinteraccion', 'tbanimalproduccionsalud', 'tbanimalpublicacion',
    'tbanimalpublicacionestadoperiodo', 'tbbitacora', 'tbcarrito', 'tbcarritoanimal',
    'tbcarritoestadoperiodo', 'tbcompra', 'tbcomprador', 'tbdireccion',
    'tbanimalpublicacioninteraccion', 'tbfinca', 'tbfincadireccion', 'tbpagometodo', 'tbpersona', 'tbproductor',
    'tbproductoractividad', 'tbproductorclasificacionperiodo', 'tbproductordireccion',
    'tbproductorestadoperiodo', 'tbproductorubicacion', 'tbtransportista',
    'tbtransportistaestadoperiodo', 'tbtransportistaflete', 'tbtransportistahorario',
    'tbtransportistaresena', 'tbtransportistavehiculo', 'tbvehiculo', 'tbventa',
    'tbcompradorpersonatelefonohistorico', 'tbproductorpersonatelefonohistorico'];

if ($manifest['database'] !== 'bdmercadoganadero') {
    throw new RuntimeException('El manifest debe leer bdmercadoganadero como base canónica.');
}
sort($expectedTables);
if ($manifest['table_count'] !== 33 || $manifest['tables_sorted'] !== $expectedTables) {
    throw new RuntimeException('El manifest debe derivar las 33 tablas canónicas desde el SQL.');
}

try {
    schema_manifest_from_sql('CREATE DATABASE bdmercadoganadero; USE dbmercadoganadero;');
    throw new RuntimeException('El manifest debe rechazar scripts con bases incoherentes.');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'El SQL debe nombrar una sola base de datos.') {
        throw $error;
    }
}

echo "OK schema_manifest_test: manifest derivado del SQL canónico de 33 tablas y gate de base incoherente.\n";
