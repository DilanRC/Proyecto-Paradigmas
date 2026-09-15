<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Auth\ActorContext;
use Application\Service\AuthGuard;
use Application\HttpException;

// Guard: NO_AUTENTICADO → 401 con código claro para "inicie sesión".
$anonimo = ActorContext::noAutenticado();
try {
    AuthGuard::requerirAutenticado($anonimo);
    throw new RuntimeException('El guard debe rechazar al actor anónimo.');
} catch (HttpException $error) {
    test_same(401, $error->estadoHttp, 'Sin sesión debe responder 401');
    test_same(['auth' => AuthGuard::ERROR_SIN_SESION], $error->errores,
        'El 401 debe llevar el código SIN_SESION para el frontend');
}

// Guard: PERSONA_AUTENTICADA pasa y se devuelve el mismo actor.
$persona = ActorContext::personaAutenticada(7, 'supabase-user-1', 'persona@example.test', 'authenticated');
test_assert(
    AuthGuard::requerirAutenticado($persona) === $persona,
    'Un actor autenticado debe pasar el guard sin cambios'
);

// Guard: rol Administrador opcional → 403 si el proveedor no lo trae.
try {
    AuthGuard::requerirAutenticado($persona, AuthGuard::ERROR_ROL_REQUERIDO);
    throw new RuntimeException('El guard debe rechazar un rol distinto al exigido.');
} catch (HttpException $error) {
    test_same(403, $error->estadoHttp, 'Rol insuficiente debe responder 403');
}
$admin = ActorContext::personaAutenticada(7, 'supabase-user-1', 'admin@example.test', 'service_role');
test_assert(
    AuthGuard::requerirAutenticado($admin, 'service_role') === $admin,
    'El rol técnico de administración exigido debe pasar'
);

// Resolver + guard con transport simulado (misma técnica que auth_actor_test):
// un Bearer válido resuelve a PERSONA_AUTENTICADA y el guard lo deja pasar;
// el mismo flujo sin resolver (actor anónimo) responde 401.
$db = test_db();
$existePersona = $db->prepare('SELECT COUNT(*) FROM tbpersona WHERE tbpersonaid = :id');
do {
    $personaId = random_int(200000000, 299999999);
    $existePersona->execute(['id' => $personaId]);
} while ((int) $existePersona->fetchColumn() !== 0);
$correo = 'guard.' . bin2hex(random_bytes(4)) . '@example.test';
$identificacion = 'AUTHG-' . bin2hex(random_bytes(4));
try {
    $db->prepare('INSERT INTO tbpersona
        (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo, tbpersonanombre,
         tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
        VALUES (:id, :identificacion, :tipo, :nombre, :telefono, :correo, 1)')
        ->execute([
            'id' => $personaId,
            'identificacion' => $identificacion,
            'tipo' => 'PASAPORTE',
            'nombre' => 'Guard Autenticado',
            'telefono' => '+506 2000-0000',
            'correo' => $correo,
        ]);

    $resolver = new Application\Auth\SupabaseActorResolver(
        fn (string $url, string $authorization): array => [
            'status' => 200,
            'body' => json_encode(['success' => true, 'data' => [
                'id' => 'supabase-user-guard',
                'email' => $correo,
                'role' => 'authenticated',
            ]], JSON_THROW_ON_ERROR),
        ],
    );
    $resuelto = $resolver->resolve($db, ['HTTP_AUTHORIZATION' => 'Bearer token-valido']);
    test_assert($resuelto->tipo === 'PERSONA_AUTENTICADA', 'El transport simulado debe autenticar');
    test_same($personaId, AuthGuard::requerirAutenticado($resuelto)->personaId,
        'Guard + resolver deben dejar pasar al actor autenticado');

    $anonimoRenovado = $resolver->resolve($db, []);
    try {
        AuthGuard::requerirAutenticado($anonimoRenovado);
        throw new RuntimeException('El guard debe rechazar al anónimo tras el resolver.');
    } catch (HttpException $error) {
        test_same(401, $error->estadoHttp, 'Resolver-resuelto anónimo + guard = 401');
    }
} finally {
    $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :registro')
        ->execute(['registro' => $identificacion]);
    $db->prepare('DELETE FROM tbpersona WHERE tbpersonaid = :id')->execute(['id' => $personaId]);
}

echo "OK auth_guard_test: superficie autenticada rechaza 401/403 y deja pasar al actor autenticado.\n";