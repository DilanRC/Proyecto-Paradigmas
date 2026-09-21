<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Auth\ActorContext;
use Application\Controller\IdentidadController;

// Carga adicional para el contexto Transportista (modelo + controlador).
$raiz = dirname(__DIR__);
require_once $raiz . '/Application/Model/Transportista.php';
require_once $raiz . '/Application/Model/TransportistaVehiculo.php';
require_once $raiz . '/Application/Controller/TransportistaController.php';

// --- Pruebas unitarias del controller (sin HTTP) ---

// 1. Sin autenticación → modo público: todos los contextos en false y null.
$actorNoAuth = ActorContext::noAutenticado();
$controlador = new IdentidadController(test_db(), $actorNoAuth);
$resultado = $controlador->procesar();
test_same(200, $resultado['status'], 'Identidad sin auth responde 200');
test_same(true, $resultado['body']['success'], 'Identidad sin auth es exitosa');
test_same(false, $resultado['body']['data']['esProductor'], 'Sin auth → esProductor false');
test_same(false, $resultado['body']['data']['esComprador'], 'Sin auth → esComprador false');
test_same(false, $resultado['body']['data']['esTransportista'], 'Sin auth → esTransportista false');
test_same(null, $resultado['body']['data']['productorId'], 'Sin auth → productorId null');
test_same(null, $resultado['body']['data']['compradorId'], 'Sin auth → compradorId null');
test_same(null, $resultado['body']['data']['transportistaId'], 'Sin auth → transportistaId null');
test_same(null, $resultado['body']['data']['persona'], 'Sin auth → persona null (no se expone a terceros)');

// 2. Crear un productor de prueba y consultar su identidad
$identificador = test_document();
$productor = test_create([], $identificador);
$productorId = (int) $productor['productorId'];

// Resolver el personaId del productor recién creado
$db = test_db();
$sentencia = $db->prepare(
    'SELECT p.tbpersonaid FROM tbproductor p
     INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
     WHERE pe.tbpersonaidentificacionnumero = :id'
);
$sentencia->execute(['id' => $identificador]);
$personaId = (int) $sentencia->fetchColumn();

$actorProductor = ActorContext::personaAutenticada($personaId, 'supabase-subject', 'test@example.com', 'authenticated');
$controladorAuth = new IdentidadController(test_db(), $actorProductor);
$resultadoAuth = $controladorAuth->procesar();
test_same(200, $resultadoAuth['status'], 'Identidad con auth responde 200');
test_same(true, $resultadoAuth['body']['success'], 'Identidad con auth es exitosa');
test_same(true, $resultadoAuth['body']['data']['esProductor'], 'Con auth productor → esProductor true');
test_same(false, $resultadoAuth['body']['data']['esComprador'], 'Con auth productor → esComprador false');
test_same(false, $resultadoAuth['body']['data']['esTransportista'], 'Con auth productor → esTransportista false');
test_same($productorId, $resultadoAuth['body']['data']['productorId'], 'Con auth → productorId correcto');
test_same(null, $resultadoAuth['body']['data']['compradorId'], 'Con auth → compradorId null');
test_same(null, $resultadoAuth['body']['data']['transportistaId'], 'Con auth → transportistaId null');
test_same($identificador, $resultadoAuth['body']['data']['identificacionNumero'], 'Con auth → identificacionNumero correcto');
test_assert(is_array($resultadoAuth['body']['data']['persona'])
    && (int) ($resultadoAuth['body']['data']['persona']['tbpersonaid'] ?? 0) === $personaId,
    'Con auth → persona resuelta desde tbpersona');

// 3. La misma persona con contexto Comprador además del Productor: el endpoint
//    resuelve ambos contextos de la misma Persona (DEC-28/29).
$compradorCtrl = test_comprador_controller();
$inscripcion = $compradorCtrl->procesar('POST', [], [
    'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $identificador],
    'nombre' => 'Productor Ficticio de Prueba',
    'telefono' => '+506 8888-7777',
    'correoElectronico' => 'crud.tests@example.test',
]);
test_same(201, $inscripcion['status'], 'La misma persona se inscribe como comprador (contexto compartido)');
$controladorAmbos = new IdentidadController(test_db(), $actorProductor);
$resultadoAmbos = $controladorAmbos->procesar();
test_same(true, $resultadoAmbos['body']['data']['esProductor'], 'Persona compartida → esProductor true');
test_same(true, $resultadoAmbos['body']['data']['esComprador'], 'Persona compartida → esComprador true');
test_same($productorId, $resultadoAmbos['body']['data']['productorId'], 'Persona compartida → productorId correcto');
test_assert(is_int($resultadoAmbos['body']['data']['compradorId']),
    'Persona compartida → compradorId entero');

// 4. Transportista: contexto sobre su propia persona.
$identificadorTransporte = test_document();
$transportistaCtrl = new Application\Controller\TransportistaController(test_db(), test_token('request'));
$transportista = $transportistaCtrl->procesar('POST', [], [
    'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $identificadorTransporte],
    'nombre' => 'Transportista Ficticio de Prueba',
    'telefono' => '+506 7777-6666',
    'correoElectronico' => 'transportista.identidad@example.test',
]);
test_same(201, $transportista['status'], 'El transportista de prueba se crea');
$personaTransporte = $db->prepare(
    'SELECT t.tbpersonaid, t.tbtransportistaid FROM tbtransportista t
     INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
     WHERE pe.tbpersonaidentificacionnumero = :id'
);
$personaTransporte->execute(['id' => $identificadorTransporte]);
$filaTransporte = $personaTransporte->fetch();
$personaIdTransporte = (int) $filaTransporte['tbpersonaid'];
$transportistaId = (int) $filaTransporte['tbtransportistaid'];

$actorTransportista = ActorContext::personaAutenticada($personaIdTransporte, 'supabase-subject-3', 'transportista.identidad@example.test', 'authenticated');
$controladorTransportista = new IdentidadController(test_db(), $actorTransportista);
$resultadoTransportista = $controladorTransportista->procesar();
test_same(true, $resultadoTransportista['body']['data']['esTransportista'], 'Con auth transportista → esTransportista true');
test_same(false, $resultadoTransportista['body']['data']['esProductor'], 'Con auth transportista → esProductor false');
test_same(false, $resultadoTransportista['body']['data']['esComprador'], 'Con auth transportista → esComprador false');
test_same($transportistaId, $resultadoTransportista['body']['data']['transportistaId'], 'Con auth → transportistaId correcto');

// 5. Persona sin ningún contexto → persona resuelta, contextos false
$personaSinProductor = $db->prepare(
    'INSERT INTO tbpersona (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo,
     tbpersonanombre, tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
     VALUES (:id, :ident, :tipo, :nombre, :tel, :correo, 1)'
);
$identPersonaSolo = 'TST' . strtoupper(bin2hex(random_bytes(6)));
$personaSinProductor->execute([
    'id' => 9999001,
    'ident' => $identPersonaSolo,
    'tipo' => 'PASAPORTE',
    'nombre' => 'Persona Sin Productor',
    'tel' => '+506 8888-0000',
    'correo' => 'sin.productor@example.test',
]);

$actorSinProductor = ActorContext::personaAutenticada(9999001, 'supabase-subject-2', 'sin.productor@example.test', 'authenticated');
$controladorSinProductor = new IdentidadController(test_db(), $actorSinProductor);
$resultadoSinProductor = $controladorSinProductor->procesar();
test_same(200, $resultadoSinProductor['status'], 'Identidad sin productor responde 200');
test_same(true, $resultadoSinProductor['body']['success'], 'Identidad sin productor es exitosa');
test_same(false, $resultadoSinProductor['body']['data']['esProductor'], 'Sin productor → esProductor false');
test_same(false, $resultadoSinProductor['body']['data']['esComprador'], 'Sin productor → esComprador false');
test_same(false, $resultadoSinProductor['body']['data']['esTransportista'], 'Sin productor → esTransportista false');
test_same(null, $resultadoSinProductor['body']['data']['productorId'], 'Sin productor → productorId null');
test_assert(is_array($resultadoSinProductor['body']['data']['persona'])
    && ($resultadoSinProductor['body']['data']['persona']['tbpersonaid'] ?? null) === 9999001,
    'Sin contextos → persona resuelta desde tbpersona, contextos en false');

// 6. PersonaId inexistente (sesión colgada) → todo null sin mentir.
$actorFantasma = ActorContext::personaAutenticada(9999002, 'supabase-subject-4', 'fantasma@example.test', 'authenticated');
$controladorFantasma = new IdentidadController(test_db(), $actorFantasma);
$resultadoFantasma = $controladorFantasma->procesar();
test_same(false, $resultadoFantasma['body']['data']['esProductor'], 'Persona inexistente → esProductor false');
test_same(null, $resultadoFantasma['body']['data']['persona'], 'Persona inexistente → persona null');

// Limpiar
$db->prepare('DELETE c FROM tbcomprador c INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
    WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $identificador]);
test_cleanup_productores([$identificador]);
$db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id')
    ->execute(['id' => $identificadorTransporte]);
$db->prepare('DELETE t FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
    WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $identificadorTransporte]);
$db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero = :id")
    ->execute(['id' => $identificadorTransporte]);
$db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero = :id")->execute(['id' => $identPersonaSolo]);

// --- Prueba HTTP del endpoint (si el servidor está corriendo) ---
$url = 'http://127.0.0.1/api/identidad.php';

// 7. Método no permitido → 405
$trace = test_http_json('TRACE', null, 'application/json', $url);
test_same(405, $trace['status'], 'TRACE en identidad.php responde 405');

// 8. GET sin Bearer → modo público: contextos false y persona null
$get = test_http_json('GET', null, 'application/json', $url);
test_same(200, $get['status'], 'GET identidad sin Bearer responde 200');
test_same(true, $get['body']['success'], 'GET identidad sin Bearer es exitoso');
test_same(false, $get['body']['data']['esProductor'], 'GET sin Bearer → esProductor false');
test_same(false, $get['body']['data']['esComprador'], 'GET sin Bearer → esComprador false');
test_same(false, $get['body']['data']['esTransportista'], 'GET sin Bearer → esTransportista false');
test_same(null, $get['body']['data']['persona'], 'GET sin Bearer → persona null (superficie pública)');

echo "Todos los tests de api_identidad pasaron.\n";