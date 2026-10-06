<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/Persona.php';
require_once dirname(__DIR__) . '/Application/Controller/AnimalPublicacionController.php';
require_once dirname(__DIR__) . '/Application/Controller/MiPerfilController.php';

use Application\Auth\ActorContext;
use Application\Controller\MiPerfilController;

/**
 * P1-4: la persona edita su foto, alias y teléfono. Cubre: sesión obligatoria,
 * solo campos editables, validación (https, teléfono), que "no tocar" no borre,
 * que el teléfono nuevo deje histórico y que la bitácora no guarde el teléfono.
 */

$identificaciones = [];

try {
    $db = test_db();
    $persona = test_create_completo(['fincas' => [['nombre' => 'Finca Perfil']]]);
    $identificaciones[] = $persona['identificacionNumero'];

    $buscar = $db->prepare(
        'SELECT tbpersonaid, tbpersonacorreoelectronico, tbpersonatelefono FROM tbpersona
         WHERE tbpersonaidentificacionnumero = :identificacion'
    );
    $buscar->execute(['identificacion' => $persona['identificacionNumero']]);
    $fila = $buscar->fetch();
    $personaId = (int) $fila['tbpersonaid'];
    $sujeto = 'test-perfil-' . test_token('subject');
    $actor = ActorContext::usuarioVerificado($personaId, $sujeto, $fila['tbpersonacorreoelectronico'], null);
    $perfil = new MiPerfilController($db, $actor, test_token('perfil'));

    test_same(401, (new MiPerfilController($db, ActorContext::noAutenticado()))->procesar('PATCH', ['alias' => 'x'])['status'],
        'Editar el perfil exige sesión');
    test_same(405, $perfil->procesar('GET', [])['status'], 'Solo PATCH');
    test_same(422, $perfil->procesar('PATCH', [])['status'], 'Sin cambios es 422');
    $ajeno = $perfil->procesar('PATCH', ['nombre' => 'Otro Nombre', 'correoElectronico' => 'x@y.z']);
    test_same(422, $ajeno['status'], 'El nombre y el correo no se editan aquí');
    test_assert(isset($ajeno['body']['errors']['nombre'], $ajeno['body']['errors']['correoElectronico']), 'Indica cada campo no editable');
    test_same(422, $perfil->procesar('PATCH', ['fotoUrl' => 'http://example.com/a.jpg'])['status'], 'La foto exige https');
    test_assert(isset($perfil->procesar('PATCH', ['fotoUrl' => 'javascript:alert(1)'])['body']['errors']['fotoUrl']), 'El error se reporta en fotoUrl');
    test_same(422, $perfil->procesar('PATCH', ['telefono' => '123'])['status'], 'El teléfono debe ser válido');
    test_same(422, $perfil->procesar('PATCH', ['telefono' => ''])['status'], 'El teléfono no puede quedar vacío');

    $ok = $perfil->procesar('PATCH', ['alias' => '  Don Chepe ', 'fotoUrl' => 'https://example.com/yo.jpg']);
    test_same(200, $ok['status'], 'El dueño edita alias y foto');
    test_same('Don Chepe', $ok['body']['data']['persona']['alias'], 'El alias se guarda recortado');
    test_same('https://example.com/yo.jpg', $ok['body']['data']['persona']['fotoUrl'], 'La foto se guarda');
    test_same($fila['tbpersonatelefono'], $ok['body']['data']['persona']['telefono'], 'No tocar el teléfono lo conserva');

    $soloAlias = $perfil->procesar('PATCH', ['alias' => 'Chepe']);
    test_same('https://example.com/yo.jpg', $soloAlias['body']['data']['persona']['fotoUrl'], 'Cambiar solo el alias no borra la foto');

    $historial = $db->prepare('SELECT COUNT(*) FROM tbproductorpersonatelefonohistorico h
        INNER JOIN tbproductor p ON p.tbproductorid = h.tbproductorid WHERE p.tbpersonaid = :id');
    $historial->execute(['id' => $personaId]);
    $antes = (int) $historial->fetchColumn();
    $telefono = $perfil->procesar('PATCH', ['telefono' => '+506 7000-1234']);
    test_same(200, $telefono['status'], 'Cambiar el teléfono es válido');
    test_same('+50670001234', $telefono['body']['data']['persona']['telefono'], 'El teléfono se normaliza');
    $historial->execute(['id' => $personaId]);
    test_same($antes + 1, (int) $historial->fetchColumn(), 'Un teléfono nuevo deja histórico para el productor');

    $quitada = $perfil->procesar('PATCH', ['fotoUrl' => null, 'alias' => '']);
    test_same(null, $quitada['body']['data']['persona']['fotoUrl'], 'Quitar la foto la deja en NULL');
    test_same(null, $quitada['body']['data']['persona']['alias'], 'Un alias vacío queda en NULL');

    // P2-5: documento de identidad. Solo una ruta de la carpeta propia del bucket; queda PENDIENTE.
    test_same(null, $quitada['body']['data']['persona']['documento'], 'Sin documento subido, documento es null');
    $uuid = '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';
    foreach ([
        'otro-usuario/' . $uuid . '.jpg' => 'carpeta ajena',
        $sujeto . '/' . $uuid . '.exe' => 'extensión no permitida',
        $sujeto . '/../otro/' . $uuid . '.jpg' => 'ruta con ..',
        'https://example.com/' . $uuid . '.jpg' => 'URL en lugar de ruta',
    ] as $ruta => $motivo) {
        $rechazo = $perfil->procesar('PATCH', ['documentoRuta' => $ruta]);
        test_same(422, $rechazo['status'], "documentoRuta rechazada: {$motivo}");
        test_assert(isset($rechazo['body']['errors']['documentoRuta']), "El error va en documentoRuta: {$motivo}");
    }
    test_same(422, $perfil->procesar('PATCH', ['documentoRuta' => null])['status'], 'documentoRuta null no se acepta');
    $documento = $perfil->procesar('PATCH', ['documentoRuta' => $sujeto . '/' . $uuid . '.pdf']);
    test_same(200, $documento['status'], 'La persona registra su documento');
    test_same('PENDIENTE', $documento['body']['data']['persona']['documento']['estado'], 'Un documento nuevo queda PENDIENTE');
    test_assert(!str_contains(json_encode($documento['body']), $uuid), 'La respuesta nunca expone la ruta del archivo privado');
    $db->prepare("UPDATE tbpersona SET tbpersonadocumentoestado = 'VERIFICADO' WHERE tbpersonaid = :id")->execute(['id' => $personaId]);
    $reemplazo = $perfil->procesar('PATCH', ['documentoRuta' => $sujeto . '/' . $uuid . '.jpg']);
    test_same('PENDIENTE', $reemplazo['body']['data']['persona']['documento']['estado'], 'Un documento nuevo vuelve a revisión aunque el anterior estuviera verificado');
    test_same('PENDIENTE', $perfil->procesar('PATCH', ['alias' => 'Chepe'])['body']['data']['persona']['documento']['estado'], 'Editar otro dato no toca el documento');
    $guardado = $db->prepare('SELECT tbpersonadocumentoruta FROM tbpersona WHERE tbpersonaid = :id');
    $guardado->execute(['id' => $personaId]);
    test_same($sujeto . '/' . $uuid . '.jpg', $guardado->fetchColumn(), 'Se guarda la ruta dentro del bucket');

    $bitacora = $db->prepare('SELECT tbbitacoraentidad, tbbitacoraorigen, tbbitacoradatosnuevos FROM tbbitacora
        WHERE tbbitacoraregistroidentificacionnumero = :r ORDER BY tbbitacoraid DESC LIMIT 1');
    $bitacora->execute(['r' => 'PERSONA:' . $personaId]);
    $evento = $bitacora->fetch();
    test_same('PERSONA', $evento['tbbitacoraentidad'] ?? null, 'La edición deja bitácora de PERSONA');
    test_same('API_MI_PERFIL', $evento['tbbitacoraorigen'] ?? null, 'La bitácora indica el origen');
    test_assert(!str_contains((string) ($evento['tbbitacoradatosnuevos'] ?? ''), '7000'), 'La bitácora no guarda el teléfono');

    echo "OK api_mi_perfil_test: sesión, campos editables, validación, no borrar lo no enviado, histórico de teléfono y bitácora.\n";
} finally {
    test_cleanup_productores($identificaciones);
}
