<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../Application/Model/Transportista.php';
require_once __DIR__ . '/../Application/Model/TransportistaVehiculo.php';
require_once __DIR__ . '/../Application/Service/CapacidadService.php';

use Application\Service\CapacidadService;

function test_capacidad_service(?string $requestId = null): CapacidadService
{
    return new CapacidadService(test_db(), $requestId ?? test_token('capacidad'));
}

function test_expect_http(callable $operacion, int $codigo, string $mensaje): void
{
    try {
        $operacion();
    } catch (Application\HttpException $excepcion) {
        test_same($codigo, $excepcion->estadoHttp, $mensaje);
        return;
    }
    throw new RuntimeException($mensaje . '. Se esperaba HttpException ' . $codigo);
}

function test_bitacora_entidad(string $identificacion, string $entidad, string $origen): bool
{
    $sentencia = test_db()->prepare(
        'SELECT COUNT(*) FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id
           AND tbbitacoraentidad = :entidad
           AND tbbitacoraorigen = :origen'
    );
    $sentencia->execute(['id' => $identificacion, 'entidad' => $entidad, 'origen' => $origen]);

    return (int) $sentencia->fetchColumn() > 0;
}

$datosCompletos = [
    'nombre' => 'Capacidad Ficticia de Prueba',
    'telefono' => '+506 8888-1111',
    'correoElectronico' => 'capacidad.tests@example.test',
];
$compradorId = test_document();
$transportistaId = test_document();
$productorId = test_document();
$productorCreado = null;

try {
    $servicio = test_capacidad_service();

    // ============================================================
    // Catálogos: contexto y motivos explícitos (nada inventado).
    // ============================================================
    test_expect_http(
        fn () => $servicio->inscribir('GANADERO', $compradorId),
        422,
        'Contexto fuera del catálogo responde 422',
    );
    test_expect_http(
        fn () => $servicio->inscribir('COMPRADOR', $compradorId, 'SIMPATIA'),
        422,
        'Motivo fuera del catálogo responde 422',
    );
    test_expect_http(
        fn () => $servicio->inscribir('COMPRADOR', ''),
        422,
        'Identificación vacía responde 422',
    );

    // ============================================================
    // COMPLETAR_PERSONA: persona incompleta, sin datos enviados.
    // ============================================================
    $pendiente = $servicio->inscribir('TRANSPORTISTA', $transportistaId);
    test_same('COMPLETAR_PERSONA', $pendiente['estado'],
        'Persona sin registar responde COMPLETAR_PERSONA');
    test_same(['nombre', 'telefono', 'correoElectronico'], $pendiente['pendientes'],
        'La lista de pendientes reutiliza los campos de ValidacionService');
    test_same(null, $pendiente['contextoRegistrado'],
        'Sin datos no se crea contexto');

    // ============================================================
    // TRANSPORTISTA: inscribir con datos → INSCRITO, idempotente.
    // ============================================================
    $inscrito = $servicio->inscribir('TRANSPORTISTA', $transportistaId, 'INSCRIPCION', $datosCompletos);
    test_same('INSCRITO', $inscrito['estado'], 'Inscribir transportista crea el contexto');
    test_same('TRANSPORTISTA', $inscrito['contexto'], 'El contexto devuelto coincide');
    test_same('ACTIVO', $inscrito['contextoRegistrado']['estado'],
        'El transportista queda ACTIVO');
    test_same([], $inscrito['pendientes'], 'No quedan pendientes tras completar');

    $repetido = $servicio->inscribir('TRANSPORTISTA', $transportistaId);
    test_same('ACTIVO', $repetido['estado'], 'Inscribir de nuevo es idempotente (ACTIVO)');

    // ============================================================
    // Abandono ≠ borrado: desactiva pero la fila se conserva.
    // ============================================================
    $abandonado = $servicio->abandonar('TRANSPORTISTA', $transportistaId);
    test_same('INACTIVO', $abandonado['estado'], 'Abandonar deja INACTIVO');
    $conteoFilas = test_db()->prepare(
        'SELECT COUNT(*) FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
         WHERE pe.tbpersonaidentificacionnumero = :id'
    );
    $conteoFilas->execute(['id' => $transportistaId]);
    test_same(1, (int) $conteoFilas->fetchColumn(),
        'Abandonar no borra el contexto: la fila sigue existiendo (recuperable)');

    $abandonado2 = $servicio->abandonar('TRANSPORTISTA', $transportistaId);
    test_same('INACTIVO', $abandonado2['estado'], 'Abandonar de nuevo es idempotente');

    $reactivado = $servicio->reactivar('TRANSPORTISTA', $transportistaId);
    test_same('REACTIVADO', $reactivado['estado'], 'Reactivar recupera el contexto');
    $activo = $servicio->reactivar('TRANSPORTISTA', $transportistaId);
    test_same('ACTIVO', $activo['estado'], 'Reactivar de nuevo es idempotente');

    test_assert(test_bitacora_entidad($transportistaId, 'TRANSPORTISTA', 'API_CAPACIDADES'),
        'La bitácora registra INSCRIBIR/REACTIVAR con entidad TRANSPORTISTA y origen API_CAPACIDADES');

    // ============================================================
    // COMPRADOR: mismo contrato uniforme.
    // ============================================================
    $comprador = $servicio->inscribir('COMPRADOR', $compradorId, 'INSCRIPCION', $datosCompletos);
    test_same('INSCRITO', $comprador['estado'], 'Inscribir comprador responde INSCRITO');
    test_same('ACTIVO', $comprador['contextoRegistrado']['estado'], 'El comprador queda ACTIVO');

    $comprador2 = $servicio->inscribir('COMPRADOR', $compradorId);
    test_same('ACTIVO', $comprador2['estado'], 'Inscribir comprador de nuevo es idempotente');

    $compradorAbandonado = $servicio->abandonar('COMPRADOR', $compradorId);
    test_same('INACTIVO', $compradorAbandonado['estado'], 'Abandonar comprador deja INACTIVO');
    $compradorReactivado = $servicio->reactivar('COMPRADOR', $compradorId);
    test_same('REACTIVADO', $compradorReactivado['estado'], 'Reactivar comprador responde REACTIVADO');

    test_assert(test_bitacora_entidad($compradorId, 'COMPRADOR', 'API_CAPACIDADES'),
        'La bitácora registra el comprador con entidad COMPRADOR y origen API_CAPACIDADES');

    // ============================================================
    // PRODUCTOR: existe pero abandonado; adopción uniforme.
    // ============================================================
    $sinProductor = $servicio->inscribir('PRODUCTOR', $productorId);
    test_same('COMPLETAR_PERSONA', $sinProductor['estado'],
        'Inscribir productor de persona sin contexto responde COMPLETAR_PERSONA');

    $productorCreado = test_create([], $productorId);
    $activoAntes = $servicio->inscribir('PRODUCTOR', $productorId);
    test_same('ACTIVO', $activoAntes['estado'],
        'Inscribir productor ya activo es idempotente');
    $productorAbandonado = $servicio->abandonar('PRODUCTOR', $productorId);
    test_same('INACTIVO', $productorAbandonado['estado'], 'Abandonar productor deja INACTIVO');
    $productorReactivado = $servicio->reactivar('PRODUCTOR', $productorId);
    test_same('REACTIVADO', $productorReactivado['estado'], 'Reactivar productor responde REACTIVADO');

    test_assert(test_bitacora_entidad($productorId, 'PRODUCTOR', 'API_CAPACIDADES'),
        'La bitácora registra el productor con entidad PRODUCTOR y origen API_CAPACIDADES');

    // ============================================================
    // 404: reactivar/abandonar sin contexto previo.
    // ============================================================
    test_expect_http(
        fn () => $servicio->reactivar('COMPRADOR', test_document()),
        404,
        'Reactivar comprador sin contexto responde 404',
    );
    test_expect_http(
        fn () => $servicio->abandonar('TRANSPORTISTA', test_document()),
        404,
        'Abandonar transportista sin contexto responde 404',
    );

    // ============================================================
    // 409: persona inactiva no puede operar capacidades.
    // ============================================================
    $bloqueado = $servicio->abandonar('COMPRADOR', $compradorId);
    test_same('INACTIVO', $bloqueado['estado'], 'Estado previo del comprador es INACTIVO');
    test_db()->prepare('UPDATE tbpersona SET tbpersonaestado = 0
        WHERE tbpersonaidentificacionnumero = :id')->execute(['id' => $compradorId]);
    test_expect_http(
        fn () => $servicio->reactivar('COMPRADOR', $compradorId),
        409,
        'Reactivar con persona inactiva responde 409',
    );
    test_expect_http(
        fn () => $servicio->abandonar('COMPRADOR', $compradorId),
        409,
        'Abandonar con persona inactiva responde 409',
    );
    test_db()->prepare('UPDATE tbpersona SET tbpersonaestado = 1
        WHERE tbpersonaidentificacionnumero = :id')->execute(['id' => $compradorId]);

    echo "OK capacidad_test: inscribir/abandonar/reactivar uniformes e idempotentes, abandono "
        . "≠ borrado, catálogos de contexto/motivo y errores 404/409.\n";
} finally {
    $db = test_db();
    $idsContextos = array_values(array_unique(array_filter(
        [$compradorId, $transportistaId, $productorId],
        static fn (string $id): bool => $id !== '',
    )));
    if ($idsContextos !== []) {
        $marcadores = implode(',', array_fill(0, count($idsContextos), '?'));
        $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero IN ({$marcadores})")->execute($idsContextos);
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadores})")->execute($idsContextos);
    }
    test_cleanup_compradores([$compradorId]);
    if ($productorCreado !== null) {
        test_cleanup_estado_periodos([(int) $productorCreado['productorId']]);
    }
    test_cleanup_productores($idsContextos);
}