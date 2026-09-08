<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$sql = file_get_contents("{$root}/Database/SqlScripts/000instalacioncompleta.sql");
$persona = file_get_contents("{$root}/Application/Model/Persona.php");
$historico = file_get_contents("{$root}/Application/Model/PersonaTelefonoHistorico.php");

foreach ([
    'tbpersonaalias VARCHAR(150) NULL',
    'CREATE TABLE IF NOT EXISTS tbproductorpersonatelefonohistorico',
    'tbproductorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL',
    'tbproductorpersonatelefonohistoricofecha DATETIME NOT NULL',
    'CREATE TABLE IF NOT EXISTS tbcompradorpersonatelefonohistorico',
    'tbcompradorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL',
    'tbcompradorpersonatelefonohistoricofecha DATETIME NOT NULL',
] as $fragmento) {
    test_assert(str_contains($sql, $fragmento), "Falta contrato SQL: {$fragmento}");
}

foreach ([
    'tbproductorpersonatelefonohistoricoestado',
    'tbcompradorpersonatelefonohistoricoestado',
    'tbproductorpersonatelefonohistoricofechafin',
    'tbcompradorpersonatelefonohistoricofechafin',
] as $prohibido) {
    test_assert(!str_contains($sql, $prohibido), "El histórico no debe contener {$prohibido}");
}

test_assert(str_contains($persona, 'registrarCambio('),
    'Persona debe registrar el histórico cuando cambia el teléfono.');
test_assert(str_contains($persona, "gmdate('Y-m-d H:i:s')"),
    'PHP debe generar la fecha del cambio.');
test_assert(str_contains($historico, 'FROM tbproductor WHERE tbpersonaid = :personaId'),
    'El histórico debe detectar el contexto Productor de la Persona.');
test_assert(str_contains($historico, 'FROM tbcomprador WHERE tbpersonaid = :personaId'),
    'El histórico debe detectar el contexto Comprador de la Persona.');
test_assert(str_contains($historico, 'MAX(tbproductorpersonatelefonohistoricoid)'),
    'PHP debe calcular el ID del histórico Productor.');
test_assert(str_contains($historico, 'MAX(tbcompradorpersonatelefonohistoricoid)'),
    'PHP debe calcular el ID del histórico Comprador.');
test_assert(!str_contains($historico, '->query(') && !str_contains($historico, '->exec('),
    'El histórico debe usar sentencias preparadas.');

echo "OK persona_telefono_historico_contract_test: contrato de Calidad preservado.\n";
