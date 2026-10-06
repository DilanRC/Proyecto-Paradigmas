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
    // Lectura automática: el navegador manda solo el número leído y el servidor decide el resultado.
    $lectura = static function (array $l) use ($perfil, $sujeto, $uuid): ?string {
        $r = $perfil->procesar('PATCH', ['documentoRuta' => $sujeto . '/' . $uuid . '.jpg', 'documentoLectura' => $l]);
        test_same(200, $r['status'], 'La lectura se acepta junto al documento');
        return $r['body']['data']['persona']['documento']['lectura'];
    };
    test_same(null, $lectura(['numero' => '111111111']), 'Con pasaporte (letras) la lectura de dígitos no aplica');
    // Las fixtures usan pasaporte: se pasan a cédula física con números únicos (y se limpian al final).
    $cedula = static fn (): string => (string) random_int(100000000, 999999999);
    $propio = $cedula();
    $otraPersona = test_create_completo(['fincas' => [['nombre' => 'Finca Otra Lectura']]]);
    $otroNumero = $cedula();
    $aCedula = $db->prepare("UPDATE tbpersona SET tbpersonaidentificaciontipo = 'CEDULA_FISICA', tbpersonaidentificacionnumero = :nuevo
        WHERE tbpersonaidentificacionnumero = :actual");
    $aCedula->execute(['nuevo' => $propio, 'actual' => $persona['identificacionNumero']]);
    $aCedula->execute(['nuevo' => $otroNumero, 'actual' => $otraPersona['identificacionNumero']]);
    array_push($identificaciones, $propio, $otroNumero);
    test_same('COINCIDE', $lectura(['numero' => ' ' . substr($propio, 0, 1) . '-' . substr($propio, 1, 4) . '-' . substr($propio, 5) . ' ']),
        'El número leído (con guiones) es la identificación de la persona');
    test_same('NO_COINCIDE', $lectura(['numero' => '999999999']), 'Un número que no es de nadie no coincide');
    test_same('OTRA_CUENTA', $lectura(['numero' => $otroNumero]), 'El número de otra persona se marca aparte');
    test_same('SIN_LECTURA', $lectura(['numero' => null]), 'Si no se encontró número, SIN_LECTURA');
    test_same(422, $perfil->procesar('PATCH', ['documentoRuta' => $sujeto . '/' . $uuid . '.jpg', 'documentoLectura' => ['numero' => $propio, 'resultado' => 'COINCIDE']])['status'],
        'El navegador no puede mandar el resultado, solo el número');
    test_same(422, $perfil->procesar('PATCH', ['documentoLectura' => ['numero' => $propio]])['status'], 'Una lectura sin documento nuevo es 422');
    test_same(422, $perfil->procesar('PATCH', ['documentoRuta' => $sujeto . '/' . $uuid . '.jpg', 'documentoLectura' => ['numero' => 'sin-digitos']])['status'],
        'Un número sin dígitos es 422');
    test_same(null, $perfil->procesar('PATCH', ['documentoRuta' => $sujeto . '/' . $uuid . '.pdf'])['body']['data']['persona']['documento']['lectura'],
        'Un documento sin lectura (PDF) reemplaza la lectura anterior por null');

    $guardado = $db->prepare('SELECT tbpersonadocumentoruta FROM tbpersona WHERE tbpersonaid = :id');
    $guardado->execute(['id' => $personaId]);
    test_same($sujeto . '/' . $uuid . '.pdf', $guardado->fetchColumn(), 'Se guarda la ruta dentro del bucket');

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
