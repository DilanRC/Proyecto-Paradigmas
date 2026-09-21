<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Service\ValidacionService;

/**
 * Política de duplicados DEC-31: identificación de persona normalizada es un
 * duplicado imposible (409); fincas con el mismo nombre en productores
 * distintos son legítimas (advertencia, nunca bloqueo).
 */

$idA = test_document();
$idB = test_document();
$idComprador = test_document();

try {
    // Declaración explícita de la política.
    test_same(['persona.identificacion'], ValidacionService::DUPLICADOS_IMPOSIBLES,
        'La política declara el conjunto de duplicados imposibles');

    // ============================================================
    // PERSONA: identificación normalizada duplicada es imposible.
    // ============================================================
    $nombreCompartido = ['nombre' => 'Poseedor Compartido de Prueba',
        'telefono' => '+506 8888-2222', 'correoElectronico' => 'duplicados.tests@example.test'];

    $compradorCtrl = test_comprador_controller();
    $comprador = $compradorCtrl->procesar('POST', [], [
        ...$nombreCompartido,
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $idComprador],
    ]);
    test_same(201, $comprador['status'], 'El comprador de referencia se inscribe');

    // Misma identificación, datos personales diferentes → 409 claro (imposible).
    $conflicto = test_controller()->procesar('POST', [], test_payload($idComprador, [
        'nombre' => 'Poseedor Distinto de Prueba',
        'telefono' => '+506 1111-9999',
    ]));
    test_same(409, $conflicto['status'], 'Persona con identificación duplicada y datos distintos responde 409');
    test_assert(str_contains((string) $conflicto['body']['errors']['identificacion.numero'] ?? '', 'ya existe'),
        'El mensaje del 409 aclara el duplicado imposible');

    // Misma identificación con datos personales IDÉNTICOS → comparten persona
    // (caso legítimo de contexto sobre la misma persona).
    $legitimo = test_controller()->procesar('POST', [], test_payload($idComprador, $nombreCompartido));
    test_same(201, $legitimo['status'],
        'El mismo número con datos idénticos crea el contexto (la persona se comparte)');

    // ============================================================
    // FINCAS: mismo nombre en dos productores → advertencia sin bloqueo.
    // ============================================================
    $primero = test_controller()->procesar('POST', [], test_payload($idA, ['fincas' => [['nombre' => 'La Esperanza'], ['nombre' => 'La Palma']]]));
    test_same(201, $primero['status'], 'El primer productor se crea con sus fincas');
    test_same([], $primero['body']['data']['advertencias'],
        'Primer productor: sin advertencias (nadie más usa esos nombres)');

    $segundo = test_controller()->procesar('POST', [], test_payload($idB, ['fincas' => [['nombre' => 'La Esperanza']]]));
    test_same(201, $segundo['status'], 'El segundo productor se crea aunque repita el nombre');
    $advertencias = $segundo['body']['data']['advertencias'] ?? [];
    test_assert(is_array($advertencias) && $advertencias !== [],
        'El contrato incluye advertencias por campo sin bloquear');
    test_same('fincas', $advertencias[0]['campo'] ?? null,
        'La advertencia apunta al campo fincas');
    test_assert(str_contains((string) ($advertencias[0]['mensaje'] ?? ''), 'no se bloquea'),
        'El mensaje de advertencia deja decidir (no bloquea)');

    // La finca del segundo productor existe de verdad (no se impidió).
    $fincaSegunda = $segundo['body']['data']['fincas'] ?? [];
    test_same(1, count($fincaSegunda), 'La finca repetida se registró en el segundo productor');

    // Re-sincronizar la finca del primero (PUT) reactiva la propia sin duplicar.
    $reactivacion = test_controller()->procesar('PUT', [], test_payload($idA, [
        'fincas' => [['nombre' => 'La Esperanza'], ['nombre' => 'La Palma']],
        'identificacionNumeroOriginal' => $idA,
        'direccionPrincipal' => test_direccion_payload(),
    ]));
    test_same(200, $reactivacion['status'], 'Re-sincronizar las fincas propias responde 200');
    test_same(2, count($reactivacion['body']['data']['fincas'] ?? []),
        'Las fincas propias no se duplican (reactivación en el modelo)');

    echo "OK duplicados_test: persona.identificacion duplicado imposible (409) y fincas "
        . "con nombre repetido advertidas sin bloquear (contrato advertencias).\n";
} finally {
    // Persona compartida Productor+Comprador (duplicado legítimo): se retira
    // primero el contexto Comprador y después el Productor con sus dependencias.
    test_db()->prepare('DELETE c FROM tbcomprador c INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
        WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $idComprador]);
    test_cleanup_productores([$idA, $idB, $idComprador]);
    test_cleanup_compradores([$idComprador]);
}