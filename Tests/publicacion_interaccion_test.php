<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/PublicacionInteraccion.php';
require_once dirname(__DIR__) . '/Application/Controller/PublicacionInteraccionController.php';

use Application\Auth\ActorContext;
use Application\Controller\PublicacionInteraccionController;
use Application\Model\AnimalComercial;

$identificaciones = [];
$personaId = null;
$animalId = null;
$publicacionId = null;

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
    $buscarFinca = $db->prepare('SELECT tbfincaid FROM tbfinca WHERE tbproductorid = :id');
    $buscarFinca->execute(['id' => $persona['productorId']]);
    $fincaId = (int) $buscarFinca->fetchColumn();
    test_assert($fincaId > 0, 'La fixture debe crear su propia finca');

    $animales = new AnimalComercial($db);
    $db->beginTransaction();
    try {
        $animalId = $animales->ejecutarConBloqueoAlta('tbanimal',
            static fn (): int => $animales->crearAnimal(
                'INT-' . test_token('animal'), 'HEMBRA', 'Brahman', 'PRUEBA'
            ));
        $publicacionId = $animales->ejecutarConBloqueoAlta('tbanimalpublicacion',
            static fn (): int => $animales->publicarAnimal(
                $animalId, (int) $persona['productorId'], $fincaId,
                ['titulo' => 'Interacción ' . test_token('titulo'), 'origen' => 'PRUEBA', 'estado' => 'ACTIVO']
            ));
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        $animalId = null;
        $publicacionId = null;
        throw $error;
    }

    $sinSesion = new PublicacionInteraccionController($db, ActorContext::noAutenticado());
    test_same(401, $sinSesion->procesar('POST', [
        'publicacionId' => $publicacionId, 'tipo' => 'ME_INTERESA',
    ])['status'], 'Una interacción sin sesión debe rechazarse');

    $actor = ActorContext::usuarioVerificado(
        $personaId,
        'test-interaccion-' . test_token('subject'),
        $fila['tbpersonacorreoelectronico'],
        null,
    );
    $controlador = new PublicacionInteraccionController($db, $actor, test_token('interaccion'));
    $respuesta = $controlador->procesar('POST', [
        'publicacionId' => $publicacionId,
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
    test_same($publicacionId, (int) ($guardada['tbanimalpublicacionid'] ?? 0), 'La interacción debe apuntar a la publicación');
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

    // Quitar de la lista queda como RETIRAR, no como CREAR.
    $retiro = $controlador->procesar('POST', ['publicacionId' => $publicacionId, 'tipo' => 'ME_INTERESA', 'accion' => 'RETIRAR']);
    test_same(201, $retiro['status'], 'Retirar el interés responde 201');
    $accion = $db->prepare('SELECT tbbitacoraaccion FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :registro ORDER BY tbbitacoraid DESC LIMIT 1');
    $accion->execute(['registro' => 'PUBLICACION_INTERACCION:' . $retiro['body']['data']['interaccionId']]);
    test_same('RETIRAR', $accion->fetchColumn(), 'La bitácora registra la acción RETIRAR');

    echo "OK publicacion_interaccion_test: actor Persona, persistencia, validación y bitácora.\n";
} finally {
    $db = test_db();
    if ($personaId !== null) {
        $interacciones = $db->prepare('SELECT tbanimalpublicacioninteraccionid FROM tbanimalpublicacioninteraccion WHERE tbpersonaid = :personaId');
        $interacciones->execute(['personaId' => $personaId]);
        $borrarEvento = $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'PUBLICACION_INTERACCION' AND tbbitacoraregistroidentificacionnumero = :registro");
        foreach ($interacciones->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $borrarEvento->execute(['registro' => 'PUBLICACION_INTERACCION:' . $id]);
        }
        $db->prepare('DELETE FROM tbanimalpublicacioninteraccion WHERE tbpersonaid = :personaId')->execute(['personaId' => $personaId]);
    }
    if ($publicacionId !== null) {
        $db->prepare('DELETE FROM tbanimalpublicacionestadoperiodo WHERE tbanimalpublicacionid = :id')->execute(['id' => $publicacionId]);
        $db->prepare('DELETE FROM tbanimalpublicacion WHERE tbanimalpublicacionid = :id')->execute(['id' => $publicacionId]);
    }
    if ($animalId !== null) {
        $db->prepare('DELETE FROM tbanimal WHERE tbanimalid = :id')->execute(['id' => $animalId]);
    }
    test_cleanup_productores($identificaciones);
}
