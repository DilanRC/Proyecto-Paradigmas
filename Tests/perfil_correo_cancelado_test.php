<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/CorreoCambioService.php';

use Application\Auth\ActorContext;
use Application\Model\PersonaCorreoCambio;
use Application\Service\CorreoCambioService;

/**
 * La conciliación lee el cambio PENDIENTE antes de tomar el bloqueo de Persona. Si mientras
 * espera el cambio se cancela (Supabase volvió al correo anterior), no debe escribir el
 * correo nuevo en tbpersona: la cuenta quedaría sin Persona y sin pendiente que la repare.
 */
$identificaciones = [];
$cambioId = null;
$personaId = null;
try {
    $persona = test_create_completo(['fincas' => [['nombre' => 'Finca Correo Cancelado']]]);
    $identificaciones[] = $persona['identificacionNumero'];
    $buscar = test_db()->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $buscar->execute(['id' => $persona['identificacionNumero']]);
    $fila = $buscar->fetch();
    $personaId = (int) $fila['tbpersonaid'];
    $original = mb_strtolower($fila['tbpersonacorreoelectronico'], 'UTF-8');
    $nuevo = 'cancelado-' . bin2hex(random_bytes(4)) . '@example.test';
    $sujeto = '99999999-3333-4444-8555-666666666666';

    $cambios = new PersonaCorreoCambio(test_db());
    $cambioId = $cambios->crear($personaId, $sujeto, $original, $nuevo);
    $copiaVieja = $cambios->pendientePorSujeto($sujeto);
    test_assert($cambios->cerrar($cambioId, PersonaCorreoCambio::CANCELADO), 'Cerrar un PENDIENTE informa que lo cerró');
    test_assert(!$cambios->cerrar($cambioId, PersonaCorreoCambio::APLICADO), 'Cerrar un cambio ya resuelto informa que no cambió nada');

    $aplicar = new ReflectionMethod(CorreoCambioService::class, 'aplicar');
    $aplicar->setAccessible(true);
    $resultado = $aplicar->invoke(
        new CorreoCambioService(test_db()),
        $copiaVieja,
        ActorContext::usuarioVerificado($personaId, $sujeto, $nuevo, null, true),
    );
    test_same(null, $resultado, 'Un cambio cancelado mientras se esperaba el bloqueo no se concilia');

    $buscar->execute(['id' => $persona['identificacionNumero']]);
    test_same($original, mb_strtolower($buscar->fetch()['tbpersonacorreoelectronico'], 'UTF-8'), 'La Persona conserva su correo');
    $estado = test_db()->prepare('SELECT tbpersonacorreocambioestado FROM tbpersonacorreocambio WHERE tbpersonacorreocambioid = :id');
    $estado->execute(['id' => $cambioId]);
    test_same('CANCELADO', $estado->fetchColumn(), 'El cambio sigue CANCELADO');
    $bitacora = test_db()->prepare("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraorigen = 'CONCILIACION_CORREO' AND tbbitacoraregistroidentificacionnumero = :id");
    $bitacora->execute(['id' => 'PERSONA:' . $personaId]);
    test_same(0, (int) $bitacora->fetchColumn(), 'No queda bitácora de una conciliación que no ocurrió');

    echo "OK perfil_correo_cancelado_test: un cambio cancelado durante la espera del bloqueo no modifica la Persona.\n";
} finally {
    if ($cambioId !== null) {
        test_db()->prepare('DELETE FROM tbpersonacorreocambio WHERE tbpersonacorreocambioid = :id')->execute(['id' => $cambioId]);
    }
    if ($personaId !== null) {
        test_db()->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'PERSONA' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => 'PERSONA:' . $personaId]);
    }
    test_cleanup_productores($identificaciones);
}
