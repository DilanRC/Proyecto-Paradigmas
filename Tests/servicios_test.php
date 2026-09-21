<?php

declare(strict_types=1);

use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorEstadoPeriodo;
use Application\Model\ProductorFinca;
use Application\Service\ProductorEstadoService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;

require __DIR__ . '/bootstrap.php';

$db = test_db();
$creados = [];

// ---------------------------------------------------------------- Tramo 16 --
// Contrato de validación unificado, ejecutable sin el controlador gigante.
$validacion = new ValidacionService();

test_same(5, count($validacion->tiposIdentificacion()), 'El catálogo de identificación debe tener 5 tipos.');

$datos = $validacion->validarProductor(test_payload(), false);
test_assert(($datos['datos']['identificacionNumero'] ?? '') !== '', 'validarProductor debe devolver datos normalizados.');
test_same([], $datos['errores'], 'Un payload válido no debe reportar errores.');

$validado = $validacion->validarIdentificacionUnica(['identificacionNumero' => test_document()]);
test_same(strtoupper($validado), $validado, 'La identificación única debe normalizarse a mayúsculas.');

$direccionValida = $validacion->validarIdentificacionYDireccion(
    ['identificacionNumero' => test_document(), 'direccionPrincipal' => test_direccion_payload()],
    'direccionPrincipal',
);
test_assert(($direccionValida['identificacionNumero'] ?? '') !== '', 'La dirección válida debe devolver identificación.');
test_same('Provincia Prueba', $direccionValida['direccion']['provincia'] ?? '', 'La dirección válida debe conservar la provincia.');

foreach ([
    ['CEDULA_FISICA', '1-1111-1111', true],
    ['CEDULA_JURIDICA', '3-101-111111', true],
    ['DIMEX', '11111111111', true],
    ['DIMEX', '111111111111', true],
    ['NITE', '1111111111', true],
    ['PASAPORTE', 'AB1234567', true],
    ['CEDULA_FISICA', '1234', false],
    ['NITE', 'AB1234567', false],
    ['PASAPORTE', 'AB-123456', false],
] as [$tipo, $numero, $esperado]) {
    $errores = [];
    $validacion->validarIdentificacion(['tipoCodigo' => $tipo, 'numero' => $numero], $errores);
    test_same($esperado, !isset($errores['identificacion.numero']),
        "Validación específica para {$tipo} debe distinguir {$numero}");
}

$nombreErrores = [];
$personaSeparada = $validacion->validarPersona([
    'identificacion' => ['tipoCodigo' => 'CEDULA_FISICA', 'numero' => '1-1111-1111'],
    'nombres' => 'María Fernanda',
    'apellidos' => 'Solano Vargas',
    'telefono' => '+506 87776655',
    'correoElectronico' => 'nombres@example.test',
], false);
test_same('María Fernanda Solano Vargas', $personaSeparada['datos']['nombre'],
    'Nombres y apellidos deben persistir como nombre canónico');

try {
    $validacion->validarProductor(
        ['identificacion' => ['tipoCodigo' => 'XX', 'numero' => '1'], 'nombre' => 'A'],
        false,
    );
    test_assert(false, 'Un payload inválido debe lanzar ValidacionException.');
} catch (ValidacionException $excepcion) {
    test_assert(($excepcion->errores['identificacion.tipoCodigo'] ?? null) !== null, 'Debe reportar tipo de identificación inválido.');
    test_assert(($excepcion->errores['nombre'] ?? null) !== null, 'Debe reportar nombre demasiado corto.');
}

try {
    $validacion->validarIdentificacionUnica(['otroCampo' => 'x']);
    test_assert(false, 'Debe rechazar campos no permitidos en la identificación única.');
} catch (ValidacionException $excepcion) {
    test_assert(($excepcion->errores['otroCampo'] ?? null) !== null, 'Debe reportar el campo no permitido.');
}

// ---------------------------------------------------------------- Tramo 14 --
// Transiciones de estado atómicas e idempotentes del servicio de estado.
$creado = test_create([], 'S' . substr(test_document(), 1));
$creados[] = $creado['identificacionNumero'];

$productor = (new Productor($db, new ProductorFinca($db)))->buscar($creado['identificacionNumero']);
test_assert($productor !== null, 'El producto creado debe existir para probar el servicio.');
$productorId = (int) $productor['productorId'];

$estadoService = new ProductorEstadoService(
    new ProductorEstadoPeriodo($db),
    new Productor($db, new ProductorFinca($db)),
    new Bitacora($db),
    test_token('request'),
);

$periodos = new ProductorEstadoPeriodo($db);

test_assert(
    $estadoService->transicionar($productorId, 1, 'Reactivación', $creado['identificacionNumero']) === false,
    'Transicionar al mismo estado vigente debe ser un no-op idempotente.',
);

test_assert(
    $estadoService->transicionar($productorId, 0, 'Desactivación', $creado['identificacionNumero']) === true,
    'Desactivar debe reportar una transición real.',
);
$abierto = $periodos->consultarAbierto($productorId);
test_assert($abierto !== null && (int) $abierto['tbproductorestadoperiodoestado'] === 0, 'Tras desactivar, el periodo abierto debe estar en 0.');

test_assert(
    $estadoService->transicionar($productorId, 0, 'Desactivación', $creado['identificacionNumero']) === false,
    'Desactivar dos veces seguidas debe ser idempotente.',
);

test_assert(
    $estadoService->transicionar($productorId, 1, 'Reactivación', $creado['identificacionNumero']) === true,
    'Reactivar debe reportar una transición real.',
);
$abierto = $periodos->consultarAbierto($productorId);
test_assert($abierto !== null && (int) $abierto['tbproductorestadoperiodoestado'] === 1, 'Tras reactivar, el periodo abierto debe estar en 1.');

$cuenta = (int) $db->query(
    "SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = "
    . $db->quote($creado['identificacionNumero'])
)->fetchColumn();
test_assert($cuenta >= 3, "El servicio debe registrar las transiciones reales en bitácora (encontradas: {$cuenta}).");

foreach ($creados as $identificacion) {
    test_cleanup_productores([$identificacion]);
}

echo "OK servicios_test: contrato de validación unificado y transiciones de estado idempotentes.\n";
