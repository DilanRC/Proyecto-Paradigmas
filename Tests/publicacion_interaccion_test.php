<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/PublicacionInteraccion.php';
require_once dirname(__DIR__) . '/Application/Controller/PublicacionInteraccionController.php';

use Application\Auth\ActorContext;
use Application\Controller\PublicacionInteraccionController;

$identificaciones = [];
$personaId = null;

try {
    $db = test_db();
    $persona = test_create_completo(['fincas' => [['nombre' => 'Finca Interacción']]]);
    $identificaciones[] = $persona['identificacionNumero'];

    $buscar = $db->prepare(
        'SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona
         WHERE tbpersonaidentificacionnumero = :identificacion'
    );
    $buscar->execute(['identificacion' => $persona['identificacionNumero']]);
    $fila = $buscar->fetch();
    test_assert(is_array($fila), 'La fixture debe dejar una Persona');
    $personaId = (int) $fila['tbpersonaid'];

    $sinSesion = new PublicacionInteraccionController($db, ActorContext::noAutenticado());
    test_same(401, $sinSesion->procesar('POST', [
        'publicacionId' => 1, 'tipo' => 'ME_INTERESA',
    ])['status'], 'Una interacción sin sesión debe rechazarse');

    $actor = ActorContext::usuarioVerificado(
        $personaId,
        'test-interaccion-' . test_token('subject'),
        $fila['tbpersonacorreoelectronico'],
        null,
    );
    $controlador = new PublicacionInteraccionController($db, $actor, test_token('interaccion'));
    $respuesta = $controlador->procesar('POST', [
        'publicacionId' => 1,
        'tipo' => 'me_interesa',
    ]);
    test_same(201, $respuesta['status'], 'Una Persona autenticada debe guardar la interacción');
    $interaccionId = (int) ($respuesta['body']['data']['interaccionId'] ?? 0);
    test_assert($interaccionId > 0, 'La respuesta debe devolver el id persistido');

    $consulta = $db->prepare(
        'SELECT tbpersonaid, tbanimalpublicacionid, tbanimalpublicacioninteracciontipo
         FROM tbanimalpublicacioninteraccion
         WHERE tbanimalpublicacioninteraccionid = :id'
    );
    $consulta->execute(['id' => $interaccionId]);
    $guardada = $consulta->fetch();
    test_same($personaId, (int) ($guardada['tbpersonaid'] ?? 0), 'La interacción debe pertenecer a la Persona autenticada');
    test_same(1, (int) ($guardada['tbanimalpublicacionid'] ?? 0), 'La interacción debe apuntar a la publicación');
    test_same('ME_INTERESA', $guardada['tbanimalpublicacioninteracciontipo'] ?? null, 'El tipo debe normalizarse');

    $bitacora = $db->prepare(
        'SELECT tbbitacoraentidad, tbbitacoraorigen FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :registro
         ORDER BY tbbitacoraid DESC LIMIT 1'
    );
    $bitacora->execute(['registro' => 'PUBLICACION_INTERACCION:' . $interaccionId]);
    $evento = $bitacora->fetch();
    test_same('PUBLICACION_INTERACCION', $evento['tbbitacoraentidad'] ?? null, 'La interacción debe dejar bitácora');
    test_same('API_PUBLICACION_INTERACCIONES', $evento['tbbitacoraorigen'] ?? null, 'La bitácora debe indicar el endpoint');

    echo "OK publicacion_interaccion_test: actor Persona, persistencia, validación y bitácora.\n";
} finally {
    if ($personaId !== null) {
        $db = test_db();
        $db->prepare('DELETE FROM tbanimalpublicacioninteraccion WHERE tbpersonaid = :personaId')->execute(['personaId' => $personaId]);
    }
    test_cleanup_productores($identificaciones);
}
