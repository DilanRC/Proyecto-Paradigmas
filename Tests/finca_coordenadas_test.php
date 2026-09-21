<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$id = test_document();
$nombreFinca = 'Finca Punto ' . strtoupper(bin2hex(random_bytes(3)));

try {
    test_create(['fincas' => [['nombre' => $nombreFinca]]], $id);

    $soloLatitud = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload([
            'provincia' => 'San José', 'canton' => 'San José', 'distrito' => 'Carmen',
        ]) + ['latitud' => '9.9347390'],
    ]);
    test_same(422, $soloLatitud['status'], 'Latitud sin longitud debe rechazarse');

    $fueraRango = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload([
            'provincia' => 'San José', 'canton' => 'San José', 'distrito' => 'Carmen',
        ]) + ['latitud' => '91', 'longitud' => '-84.0875020'],
    ]);
    test_same(422, $fueraRango['status'], 'Latitud fuera de rango debe rechazarse');

    $creada = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload([
            'provincia' => 'San José', 'canton' => 'San José', 'distrito' => 'Carmen',
        ]) + ['latitud' => '9.9347390', 'longitud' => '-84.0875020'],
    ]);
    test_same(201, $creada['status'], 'La dirección con punto exacto debe crearse');
    test_same('9.9347390', $creada['body']['data']['direccionFinca']['latitud'],
        'La latitud debe conservar siete decimales');
    test_same('-84.0875020', $creada['body']['data']['direccionFinca']['longitud'],
        'La longitud debe conservar siete decimales');

    $consultada = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
    ], []);
    test_same(200, $consultada['status'], 'GET debe recuperar el punto exacto de la finca');
    test_same('9.9347390', $consultada['body']['data']['direccionFinca']['latitud'],
        'GET debe recuperar la latitud persistida');
    test_same('-84.0875020', $consultada['body']['data']['direccionFinca']['longitud'],
        'GET debe recuperar la longitud persistida');

    $sinPunto = test_finca_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id,
        'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload([
            'provincia' => 'San José', 'canton' => 'San José', 'distrito' => 'Carmen',
        ]) + ['latitud' => null, 'longitud' => null],
    ]);
    test_same(200, $sinPunto['status'], 'El punto debe poder quitarse sin borrar la dirección textual');
    test_same(null, $sinPunto['body']['data']['direccionFinca']['latitud'],
        'Quitar el punto debe dejar latitud NULL');
    test_same(null, $sinPunto['body']['data']['direccionFinca']['longitud'],
        'Quitar el punto debe dejar longitud NULL');

    echo "OK finca_coordenadas_test: punto opcional, validacion y persistencia de direccion de finca.\n";
} finally {
    test_cleanup_productores([$id]);
}
