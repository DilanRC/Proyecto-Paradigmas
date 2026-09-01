<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Auth\ActorContext;
use Application\Auth\SupabaseActorResolver;
use Application\HttpException;
use Application\Model\Bitacora;

$db = test_db();
$existePersona = $db->prepare('SELECT COUNT(*) FROM tbpersona WHERE tbpersonaid = :id');
do {
    $personaId = random_int(100000000, 199999999);
    $existePersona->execute(['id' => $personaId]);
} while ((int) $existePersona->fetchColumn() !== 0);
$email = 'actor.' . bin2hex(random_bytes(4)) . '@example.test';
$registro = 'AUTH-' . bin2hex(random_bytes(4));

try {
    $db->prepare('INSERT INTO tbpersona
        (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo, tbpersonanombre,
         tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
        VALUES (:id, :identificacion, :tipo, :nombre, :telefono, :correo, 1)')
        ->execute([
            'id' => $personaId,
            'identificacion' => $registro,
            'tipo' => 'CEDULA',
            'nombre' => 'Actor Autenticado',
            'telefono' => '+506 2000-0000',
            'correo' => $email,
        ]);

    $resolver = new SupabaseActorResolver(
        fn (string $url, string $authorization): array => [
            'status' => 200,
            'body' => json_encode(['success' => true, 'data' => [
                'id' => 'supabase-user-1',
                'email' => $email,
                'role' => 'authenticated',
            ]], JSON_THROW_ON_ERROR),
        ],
    );
    $actor = $resolver->resolve($db, ['HTTP_AUTHORIZATION' => 'Bearer token-valido']);
    if ($actor->tipo !== 'PERSONA_AUTENTICADA' || $actor->personaId !== $personaId) {
        throw new RuntimeException('El actor autenticado debe resolverse a tbpersonaid.');
    }
    if ($actor->rolTecnico !== 'authenticated') {
        throw new RuntimeException('El rol del proveedor solo se conserva como dato técnico.');
    }

    (new Bitacora($db, $actor))->registrar('CREAR', $registro, null, ['ok' => true], test_token('auth'));
    $bitacora = $db->prepare('SELECT tbbitacoraactortipo, tbbitacorausuarioid FROM tbbitacora
        WHERE tbbitacoraregistroidentificacionnumero = :registro ORDER BY tbbitacoraid DESC LIMIT 1');
    $bitacora->execute(['registro' => $registro]);
    test_same(['tbbitacoraactortipo' => 'PERSONA_AUTENTICADA', 'tbbitacorausuarioid' => $personaId],
        $bitacora->fetch(), 'La bitácora debe guardar el actor real autenticado');

    $sinToken = $resolver->resolve($db, []);
    test_assert($sinToken instanceof ActorContext && $sinToken->tipo === 'NO_AUTENTICADO',
        'Sin Authorization se conserva el modo no autenticado local');

    $invalido = new SupabaseActorResolver(fn (): array => [
        'status' => 401,
        'body' => '{"success":false,"error":{"message":"Invalid credentials"}}',
    ]);
    try {
        $invalido->resolve($db, ['HTTP_AUTHORIZATION' => 'Bearer token-malo']);
        throw new RuntimeException('Token inválido debe fallar.');
    } catch (HttpException $error) {
        test_same(401, $error->estadoHttp, 'Token inválido debe preservar 401');
    }

    $caido = new SupabaseActorResolver(fn (): array => throw new RuntimeException('down'));
    try {
        $caido->resolve($db, ['HTTP_AUTHORIZATION' => 'Bearer token']);
        throw new RuntimeException('Sidecar caído debe fallar.');
    } catch (HttpException $error) {
        test_same(503, $error->estadoHttp, 'Sidecar caído debe responder 503');
    }

    $sinVinculo = new SupabaseActorResolver(fn (): array => [
        'status' => 200,
        'body' => '{"success":true,"data":{"id":"user-2","email":"sin-vinculo@example.test","role":"authenticated"}}',
    ]);
    try {
        $sinVinculo->resolve($db, ['HTTP_AUTHORIZATION' => 'Bearer token-valido']);
        throw new RuntimeException('Token sin vínculo debe fallar.');
    } catch (HttpException $error) {
        test_same(409, $error->estadoHttp, 'Token válido sin persona vinculada debe responder 409');
    }
} finally {
    $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :registro')
        ->execute(['registro' => $registro]);
    $db->prepare('DELETE FROM tbpersona WHERE tbpersonaid = :id')->execute(['id' => $personaId]);
}

echo "OK auth_actor_test: Supabase auth resuelve tbpersonaid y bitácora registra actor real.\n";
