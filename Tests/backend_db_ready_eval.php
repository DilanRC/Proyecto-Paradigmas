<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$modelos = [
    'ProductorClasificacionPeriodo' => file_get_contents($root . '/Application/Model/ProductorClasificacionPeriodo.php'),
    'AnimalComercial' => file_get_contents($root . '/Application/Model/AnimalComercial.php'),
    'TransportistaHistorico' => file_get_contents($root . '/Application/Model/TransportistaHistorico.php'),
];

foreach ($modelos as $nombre => $codigo) {
    if ($codigo === false) {
        throw new RuntimeException("No se pudo leer {$nombre}.");
    }
    foreach (['->prepare(', 'NamedLock::acquire', 'NamedLock::release', 'FOR UPDATE', 'inTransaction()'] as $contrato) {
        if (!str_contains($codigo, $contrato)) {
            throw new RuntimeException("{$nombre} debe conservar {$contrato}.");
        }
    }
    if (preg_match('/->(?:query|exec)\s*\(/', $codigo)) {
        throw new RuntimeException("{$nombre} no debe usar query()/exec() para SQL.");
    }
}

$animal = $modelos['AnimalComercial'];
foreach (['tbanimal', 'tbanimalproduccionsalud', 'tbanimalpublicacion',
    'tbanimalpublicacionestadoperiodo', 'tbcompra', 'tbventa',
    'tbanimalinteraccion', 'tbcarrito', 'tbcarritoestadoperiodo', 'tbcarritoanimal'] as $tabla) {
    if (!str_contains($animal, "'{$tabla}'")) {
        throw new RuntimeException("AnimalComercial debe declarar lock para {$tabla}.");
    }
}

$clasificacion = $modelos['ProductorClasificacionPeriodo'];
foreach (['COMPRADOR', 'VENDEDOR'] as $tipo) {
    if (!str_contains($clasificacion, "'{$tipo}'")) {
        throw new RuntimeException("ProductorClasificacionPeriodo debe validar {$tipo} en PHP.");
    }
}

foreach (['tbcompradorestadoperiodo', 'tbvendedor'] as $prohibido) {
    foreach ($modelos as $nombre => $codigo) {
        if (str_contains(strtolower($codigo), $prohibido)) {
            throw new RuntimeException("{$nombre} no debe introducir {$prohibido}.");
        }
    }
}

// Concordancia DEC-DBREADY-005: ningún estado de negocio se escribe como
// columna sobrescribible desde los modelos.
foreach (['tbcarritoestado ', 'tbanimalpublicacionestado ', 'tbanimalobservacion'] as $columnaMutable) {
    if (str_contains($animal, $columnaMutable)) {
        throw new RuntimeException("AnimalComercial no debe escribir {$columnaMutable}.");
    }
}
$transporte = $modelos['TransportistaHistorico'];
foreach (['tbvehiculoid', 'tbtransportistafletecantidadcabezas', 'tbtransportistafletedistanciakm',
    'tbpersonaid'] as $dato) {
    if (!str_contains($transporte, $dato)) {
        throw new RuntimeException("TransportistaHistorico debe escribir {$dato}.");
    }
}

$controladores = glob($root . '/Application/Controller/*Comercial*.php') ?: [];
$controladores = array_merge($controladores, glob($root . '/api/*animal*.php') ?: [], glob($root . '/api/*venta*.php') ?: []);
if ($controladores !== []) {
    throw new RuntimeException('Este tramo no debe crear endpoints/controladores comerciales.');
}

echo "OK backend_db_ready_eval: capa DB-backend usa prepare, NamedLock, FOR UPDATE y no crea endpoints.\n";
