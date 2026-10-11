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
    $ajeno = $perfil->procesar('PATCH', ['personaId' => 9, 'correo' => 'x@y.z']);
    test_same(422, $ajeno['status'], 'Campos fuera de la lista no se editan');
    test_assert(isset($ajeno['body']['errors']['personaId'], $ajeno['body']['errors']['correo']), 'Indica cada campo no editable');

    // Nombre, identificación y correo (la cuenta de Supabase se simula con un transporte falso).
    test_same('Ana María Pérez', $perfil->procesar('PATCH', ['nombre' => '  Ana   María Pérez '])['body']['data']['persona']['nombre'] ?? null,
        'El nombre se guarda recortado');
    test_assert(isset($perfil->procesar('PATCH', ['nombre' => 'A'])['body']['errors']['nombre']), 'Nombre muy corto es 422 en nombre');
    test_assert(isset($perfil->procesar('PATCH', ['identificacion' => ['tipoCodigo' => 'CEDULA_FISICA', 'numero' => '0-1']])['body']['errors']['identificacion.numero']),
        'La identificación sigue las reglas del registro');
    $otra = test_create_completo(['correoElectronico' => 'otra-' . test_token('correo') . '@example.test', 'fincas' => [['nombre' => 'Finca Perfil Otra']]]);
    $identificaciones[] = $otra['identificacionNumero'];
    $choque = $perfil->procesar('PATCH', ['identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $otra['identificacionNumero']]]);
    test_same(409, $choque['status'], 'La identificación de otra cuenta es 409');
    $nuevoNumero = '7' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $cambio = $perfil->procesar('PATCH', ['identificacion' => ['tipoCodigo' => 'CEDULA_FISICA', 'numero' => $nuevoNumero]]);
    test_same(200, $cambio['status'], 'Cambia la identificación: ' . json_encode($cambio['body']));
    $identificaciones[] = $nuevoNumero;
    test_same($nuevoNumero, $cambio['body']['data']['persona']['identificacionNumero'], 'La identificación nueva se guarda');
    $evento = $db->prepare("SELECT tbbitacoradatosnuevos FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :r ORDER BY tbbitacoraid DESC LIMIT 1");
    $evento->execute(['r' => 'PERSONA:' . $personaId]);
    $nuevos = (string) $evento->fetchColumn();
    test_assert(!str_contains($nuevos, $nuevoNumero) && (json_decode($nuevos, true)['identificacionCambiada'] ?? null) === true, 'La bitácora no guarda la identificación');
    test_same(200, $perfil->procesar('PATCH', ['identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $persona['identificacionNumero']]])['status'],
        'Vuelve a la identificación original');

    if (!getenv('SUPABASE_URL')) putenv('SUPABASE_URL=https://ejemplo.supabase.co');
    if (!getenv('SUPABASE_SECRET_KEY')) putenv('SUPABASE_SECRET_KEY=sb_secret_prueba');
    $llamadas = [];
    $respuestaAuth = ['status' => 200, 'body' => '{}'];
    $transporte = function (string $url, string $clave, string $cuerpo) use (&$llamadas, &$respuestaAuth): array {
        $llamadas[] = ['url' => $url, 'cuerpo' => json_decode($cuerpo, true)];
        return $respuestaAuth;
    };
    $uuid = '11111111-2222-4333-8444-555555555555';
    $conCuenta = new MiPerfilController($db, ActorContext::usuarioVerificado($personaId, $uuid, $fila['tbpersonacorreoelectronico'], null),
        test_token('perfil-correo'), new Application\Service\SupabaseAuthAdmin($transporte));
    $correoOtra = $db->prepare('SELECT tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :n');
    $correoOtra->execute(['n' => $otra['identificacionNumero']]);
    test_same(409, $conCuenta->procesar('PATCH', ['correoElectronico' => strtoupper((string) $correoOtra->fetchColumn())])['status'],
        'El correo de otra persona es 409 (sin distinguir mayúsculas)');
    test_same([], $llamadas, 'Un correo repetido no llega a Supabase');
    test_same(200, $conCuenta->procesar('PATCH', ['correoElectronico' => strtoupper($fila['tbpersonacorreoelectronico'])])['status'], 'El mismo correo no cambia nada');
    test_same([], $llamadas, 'El mismo correo no llama a Supabase');
    $correoNuevo = strtolower('perfil-' . test_token('correo') . '@ejemplo.test');
    $respuestaAuth = ['status' => 422, 'body' => '{"code":422,"error_code":"email_exists"}'];
    $rechazo = $conCuenta->procesar('PATCH', ['correoElectronico' => $correoNuevo, 'alias' => 'No se guarda']);
    test_same(409, $rechazo['status'], 'Si Supabase dice que el correo existe, es 409');
    $correoActual = $db->prepare('SELECT tbpersonacorreoelectronico, tbpersonaalias FROM tbpersona WHERE tbpersonaid = :id');
    $correoActual->execute(['id' => $personaId]);
    $guardado = $correoActual->fetch();
    test_same($fila['tbpersonacorreoelectronico'], $guardado['tbpersonacorreoelectronico'], 'Si Supabase falla, el correo no cambia');
    test_assert($guardado['tbpersonaalias'] !== 'No se guarda', 'Si Supabase falla, nada del PATCH se guarda');
    $respuestaAuth = ['status' => 200, 'body' => '{}'];
    $llamadas = [];
    $cambioCorreo = $conCuenta->procesar('PATCH', ['correoElectronico' => $correoNuevo]);
    test_same(200, $cambioCorreo['status'], 'Cambia el correo: ' . json_encode($cambioCorreo['body']));
    test_same($correoNuevo, $cambioCorreo['body']['data']['persona']['correoElectronico'], 'El correo nuevo se guarda');
    test_assert(str_ends_with($llamadas[0]['url'] ?? '', '/auth/v1/admin/users/' . $uuid), 'Cambia la cuenta de la sesión');
    test_same(['email' => $correoNuevo, 'email_confirm' => true], $llamadas[0]['cuerpo'] ?? null, 'Con el correo ya confirmado');
    test_same(200, $conCuenta->procesar('PATCH', ['correoElectronico' => $fila['tbpersonacorreoelectronico']])['status'], 'Vuelve al correo original');
    test_same(409, $perfil->procesar('PATCH', ['correoElectronico' => $correoNuevo . 'x'])['status'], 'Sin una cuenta de Supabase válida no cambia el correo');
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
