<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/MiPerfilController.php';

use Application\Auth\ActorContext;
use Application\Auth\SupabaseActorResolver;
use Application\Controller\MiPerfilController;
use Application\HttpException;
use Application\Service\CorreoCambioService;
use Application\Service\SupabaseAuthAdmin;

/**
 * Cambio de correo interrumpido entre Supabase y la base: cada punto de fallo debe terminar
 * con los dos correos iguales o con un cambio PENDIENTE que la siguiente petición concilia.
 */
final class ConciliacionCorreoPDO extends PDO
{
    public string $fallo = '';
    public function commit(): bool
    {
        if ($this->fallo === 'antes') throw new PDOException('Fallo simulado antes de COMMIT');
        $resultado = parent::commit();
        if ($this->fallo === 'ambiguo') throw new PDOException('Respuesta de COMMIT perdida');
        return $resultado;
    }
}

$identificaciones = [];
$personaIds = [];
$verifyAnterior = getenv('SUPABASE_AUTH_VERIFY_URL');
$urlAnterior = getenv('SUPABASE_URL');
$claveAnterior = getenv('SUPABASE_SECRET_KEY');
try {
    $crearPersona = function (string $finca) use (&$identificaciones, &$personaIds): array {
        $persona = test_create_completo(['fincas' => [['nombre' => $finca]]]);
        $identificaciones[] = $persona['identificacionNumero'];
        $buscar = test_db()->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
        $buscar->execute(['id' => $persona['identificacionNumero']]);
        $fila = $buscar->fetch();
        $personaIds[] = (int) $fila['tbpersonaid'];
        return [(int) $fila['tbpersonaid'], mb_strtolower($fila['tbpersonacorreoelectronico'], 'UTF-8')];
    };
    [$personaId, $original] = $crearPersona('Finca Conciliación Correo');
    [$otraPersonaId, $correoOtra] = $crearPersona('Finca Conciliación Ajena');

    $uuid = '22222222-3333-4444-8555-666666666666';
    $remoto = $original;
    $modo = 'normal';
    putenv('SUPABASE_AUTH_VERIFY_URL');
    putenv('SUPABASE_URL=https://conciliacion.example.test');
    putenv('SUPABASE_SECRET_KEY=clave-falsa-solo-transporte-local');
    $usuario = function () use (&$remoto, $uuid): string {
        return json_encode(['id' => $uuid, 'email' => $remoto, 'role' => 'authenticated', 'email_confirmed_at' => '2026-01-01T00:00:00Z']);
    };
    $transporte = function (string $url, string $clave, string $cuerpo, string $metodo = 'PUT') use (&$remoto, &$modo, $usuario): array {
        if ($metodo === 'PUT') {
            if ($modo === 'correo-ocupado') return ['status' => 422, 'body' => '{"error_code":"email_exists"}'];
            $remoto = json_decode($cuerpo, true)['email'];
            return ['status' => 200, 'body' => $usuario()];
        }
        // Tras el PUT, Supabase deja de responder: no se puede comprobar ni restaurar.
        return $modo === 'sin-lectura' ? ['status' => 0, 'body' => ''] : ['status' => 200, 'body' => $usuario()];
    };
    $db = new ConciliacionCorreoPDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME')), getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $admin = new SupabaseAuthAdmin($transporte);
    $patch = function (string $correoSesion, string $correo) use ($db, $personaId, $uuid, $admin): int {
        $controlador = new MiPerfilController($db, ActorContext::usuarioVerificado($personaId, $uuid, $correoSesion, null), test_token('conciliar-correo'), $admin);
        return $controlador->procesar('PATCH', ['correoElectronico' => $correo])['status'];
    };
    $local = function (int $id) : string {
        $q = test_db()->prepare('SELECT tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaid = :id');
        $q->execute(['id' => $id]);
        return (string) $q->fetchColumn();
    };
    $estados = function () use ($personaId): array {
        $q = test_db()->prepare('SELECT tbpersonacorreocambioestado FROM tbpersonacorreocambio WHERE tbpersonaid = :id ORDER BY tbpersonacorreocambioid');
        $q->execute(['id' => $personaId]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    };
    $conciliadas = function () use ($personaId): int {
        $q = test_db()->prepare("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraorigen = 'CONCILIACION_CORREO' AND tbbitacoraregistroidentificacionnumero = :id");
        $q->execute(['id' => 'PERSONA:' . $personaId]);
        return (int) $q->fetchColumn();
    };
    // La sesión se valida directo contra Supabase Auth (como en XAMPP): trae el correo actual de la cuenta.
    $sesion = function (string $sujeto, string $correo): ActorContext {
        $resolver = new SupabaseActorResolver(fn (): array => ['status' => 200, 'body' => json_encode(
            ['id' => $sujeto, 'email' => $correo, 'role' => 'authenticated', 'email_confirmed_at' => '2026-01-01T00:00:00Z']
        )]);
        return $resolver->resolve(test_db(), ['HTTP_AUTHORIZATION' => 'Bearer sesion-de-prueba']);
    };
    $sinVinculo = function (string $sujeto, string $correo, string $mensaje) use ($sesion): void {
        try {
            $sesion($sujeto, $correo);
            throw new RuntimeException($mensaje);
        } catch (HttpException $error) {
            test_same(409, $error->estadoHttp, $mensaje);
        }
    };
    $correo = fn (string $etiqueta): string => strtolower(test_token($etiqueta)) . '@example.test';

    // 1. Sin fallos: el cambio queda APLICADO en la misma transacción que el perfil.
    $a = $correo('conciliar-a');
    test_same(200, $patch($original, $a), 'Un cambio sin fallos responde 200');
    test_same(['APLICADO'], $estados(), 'El cambio completo queda APLICADO');
    test_same([$a, $a], [$local($personaId), $remoto], 'Los dos sistemas tienen el correo nuevo');

    // 2. Supabase rechaza el correo: no cambió nada y el cambio se CANCELA.
    $modo = 'correo-ocupado';
    test_same(409, $patch($a, $correo('conciliar-ocupado')), 'Un correo que Supabase rechaza responde 409');
    test_same(['APLICADO', 'CANCELADO'], $estados(), 'El cambio rechazado queda CANCELADO');
    test_same([$a, $a], [$local($personaId), $remoto], 'Ningún sistema cambió con el rechazo');
    $modo = 'normal';

    // 3. Falla el COMMIT y la compensación restaura Supabase: CANCELADO.
    $db->fallo = 'antes';
    test_same(503, $patch($a, $correo('conciliar-rollback')), 'El fallo antes de COMMIT no anuncia éxito');
    test_same(['APLICADO', 'CANCELADO', 'CANCELADO'], $estados(), 'El cambio compensado queda CANCELADO');
    test_same([$a, $a], [$local($personaId), $remoto], 'La compensación deja los dos sistemas con el correo anterior');

    // 4. Falla el COMMIT y Supabase ya no responde: cuenta con el correo nuevo, tbpersona con el anterior.
    $modo = 'sin-lectura';
    $b = $correo('conciliar-b');
    test_same(503, $patch($a, $b), 'Sin poder restaurar Supabase no se anuncia éxito');
    test_same([$a, $b], [$local($personaId), $remoto], 'Estado interrumpido: tbpersona anterior, Supabase nuevo');
    test_same('PENDIENTE', $estados()[3], 'El cambio interrumpido queda PENDIENTE');
    $modo = 'normal';
    $db->fallo = '';

    // 5. Nadie más puede reclamar ese cambio: otra cuenta con el mismo correo, o la misma cuenta con otro correo.
    $sinVinculo('99999999-3333-4444-8555-666666666666', $b, 'Otra cuenta de Supabase no hereda el cambio pendiente');
    $sinVinculo($uuid, $correo('conciliar-ajeno'), 'La misma cuenta con un correo distinto del pendiente no se vincula');
    test_same($a, $local($personaId), 'Los intentos ajenos no cambian tbpersona');

    // 6. La siguiente petición de la cuenta concilia: tbpersona avanza al correo de Supabase.
    test_same($personaId, $sesion($uuid, $b)->personaId, 'La sesión con el correo nuevo recupera su Persona');
    test_same($b, $local($personaId), 'La conciliación lleva tbpersona al correo de Supabase');
    test_same('APLICADO', $estados()[3], 'El cambio conciliado queda APLICADO');
    test_same(1, $conciliadas(), 'La conciliación deja un evento en la bitácora');
    test_same($personaId, $sesion($uuid, $b)->personaId, 'Repetir la petición devuelve la misma Persona');
    test_same(1, $conciliadas(), 'Repetir la petición no concilia dos veces');
    $duplicadas = test_db()->prepare('SELECT COUNT(*) FROM tbpersona WHERE LOWER(tbpersonacorreoelectronico) IN (:a, :b)');
    $duplicadas->execute(['a' => $a, 'b' => $b]);
    test_same(1, (int) $duplicadas->fetchColumn(), 'No se creó otra Persona para la misma cuenta');

    // 7. COMMIT ambiguo que sí persistió: los dos sistemas avanzaron y el cambio quedó APLICADO con el perfil.
    $db->fallo = 'ambiguo';
    $c = $correo('conciliar-c');
    test_same(503, $patch($b, $c), 'COMMIT ambiguo responde 503');
    test_same([$c, $c, 'APLICADO'], [$local($personaId), $remoto, $estados()[4]], 'COMMIT persistido: correos iguales y cambio APLICADO');
    $db->fallo = '';

    // 8. El proceso muere antes de llamar a Supabase: la cuenta no cambió y el siguiente cambio descarta el pendiente.
    $servicio = new CorreoCambioService(test_db());
    $actor = ActorContext::usuarioVerificado($personaId, $uuid, $c, null);
    $servicio->solicitar($actor, $c, $correo('conciliar-huerfano'), fn (): string => $remoto);
    test_same($personaId, $sesion($uuid, $c)->personaId, 'Con la cuenta sin cambiar la sesión sigue vinculada');
    $d = $correo('conciliar-d');
    test_same(200, $patch($c, $d), 'Un pendiente que Supabase nunca aplicó no bloquea el siguiente cambio');
    test_same(['CANCELADO', 'APLICADO'], array_slice($estados(), 5), 'El pendiente sin efecto se CANCELA y el nuevo se APLICA');

    // 9. El proceso muere después de Supabase y la sesión todavía trae el correo anterior (token sin refrescar).
    $e = $correo('conciliar-e');
    $servicio->solicitar(ActorContext::usuarioVerificado($personaId, $uuid, $d, null), $d, $e, fn (): string => $remoto);
    $remoto = $e;
    test_same(409, $patch($d, $correo('conciliar-f')), 'Con un cambio pendiente ya aplicado en Supabase se pide volver a entrar');
    test_same([$e, 'APLICADO'], [$local($personaId), $estados()[7]], 'Ese cambio pendiente se termina antes de aceptar otro');

    // 10. El correo pendiente ya es de otra Persona: no se adivina, queda PENDIENTE.
    $servicio->solicitar(ActorContext::usuarioVerificado($personaId, $uuid, $e, null), $e, $correoOtra, fn (): string => $remoto);
    test_same(null, $servicio->conciliar($uuid, $correoOtra, null, true), 'No se concilia hacia el correo de otra Persona');
    test_same([$e, $correoOtra], [$local($personaId), $local($otraPersonaId)], 'Ninguna Persona toma el correo de otra');

    // 11. Con ese pendiente sin resolver no se acepta otro cambio ni se anuncia que se completó.
    $remoto = $correoOtra;
    $bloqueado = (new MiPerfilController($db, ActorContext::usuarioVerificado($personaId, $uuid, $e, null), test_token('conciliar-correo'), $admin))
        ->procesar('PATCH', ['correoElectronico' => $correo('conciliar-g')]);
    test_same(409, $bloqueado['status'], 'Un pendiente que no se puede completar bloquea el siguiente cambio');
    test_assert(str_contains($bloqueado['body']['message'], 'administrador'), 'El mensaje pide revisión, no anuncia un cambio completado');
    $remoto = $e;

    // 12. Otra cuenta vinculada a la misma Persona no puede descartar el pendiente de la primera.
    $otraCuenta = '77777777-3333-4444-8555-666666666666';
    try {
        $servicio->solicitar(ActorContext::usuarioVerificado($personaId, $otraCuenta, $e, null), $e, $correo('conciliar-h'), fn (): string => $e);
        throw new RuntimeException('Otra cuenta no debe cerrar un pendiente ajeno');
    } catch (HttpException $error) {
        test_same(409, $error->estadoHttp, 'El pendiente de otra cuenta exige revisión manual');
    }
    test_same('PENDIENTE', $estados()[count($estados()) - 1], 'El pendiente de la primera cuenta sigue intacto');

    echo "OK perfil_correo_conciliacion_test: rechazo, compensación, interrupción conciliada por la cuenta, COMMIT ambiguo, pendientes sin efecto y pendientes ajenos.\n";
} finally {
    putenv($verifyAnterior === false ? 'SUPABASE_AUTH_VERIFY_URL' : "SUPABASE_AUTH_VERIFY_URL={$verifyAnterior}");
    putenv($urlAnterior === false ? 'SUPABASE_URL' : "SUPABASE_URL={$urlAnterior}");
    putenv($claveAnterior === false ? 'SUPABASE_SECRET_KEY' : "SUPABASE_SECRET_KEY={$claveAnterior}");
    foreach ($personaIds as $id) {
        test_db()->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'PERSONA' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => 'PERSONA:' . $id]);
    }
    test_cleanup_productores($identificaciones);
}
