<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Tools/schema-manifest.php';

$manifest = schema_manifest();
$expectedTables = ['tbbitacora', 'tbcomprador', 'tbdireccion', 'tbfinca', 'tbfincadireccion',
    'tbpagometodo', 'tbpersona', 'tbproductor', 'tbproductoractividad', 'tbproductordireccion',
    'tbproductorestadoperiodo', 'tbproductorubicacion', 'tbtransportista',
    'tbtransportistavehiculo', 'tbvehiculo'];

if ($manifest['database'] !== 'bdmercadoganadero') {
    throw new RuntimeException('El manifest debe leer bdmercadoganadero como base canónica.');
}
if ($manifest['table_count'] !== 15 || $manifest['tables_sorted'] !== $expectedTables) {
    throw new RuntimeException('El manifest debe derivar las 15 tablas canónicas desde el SQL.');
}

try {
    schema_manifest_from_sql('CREATE DATABASE bdmercadoganadero; USE dbmercadoganadero;');
    throw new RuntimeException('El manifest debe rechazar scripts con bases incoherentes.');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'El SQL debe nombrar una sola base de datos.') {
        throw $error;
    }
}

echo "OK schema_manifest_test: manifest derivado del SQL canónico y gate de base incoherente.\n";
