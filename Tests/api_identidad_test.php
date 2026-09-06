<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Auth\ActorContext;
use Application\Controller\IdentidadController;

// --- Pruebas unitarias del controller (sin HTTP) ---

// 1. Sin autenticación → esProductor: false
$actorNoAuth = ActorContext::noAutenticado();
$controlador = new IdentidadController(test_db(), $actorNoAuth);
$resultado = $controlador->procesar();
test_same(200, $resultado['status'], 'Identidad sin auth responde 200');
test_same(true, $resultado['body']['success'], 'Identidad sin auth es exitosa');
test_same(false, $resultado['body']['data']['esProductor'], 'Sin auth → esProductor false');
test_same(null, $resultado['body']['data']['productorId'], 'Sin auth → productorId null');

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
test_same($productorId, $resultadoAuth['body']['data']['productorId'], 'Con auth → productorId correcto');
test_same($identificador, $resultadoAuth['body']['data']['identificacionNumero'], 'Con auth → identificacionNumero correcto');

// 3. Persona sin perfil productor → esProductor: false
// Crear una persona sin productor asociado
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
test_same(null, $resultadoSinProductor['body']['data']['productorId'], 'Sin productor → productorId null');

// Limpiar
test_cleanup_ubicaciones([$productorId]);
test_cleanup_productores([$identificador]);
$db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero = :id")->execute(['id' => $identPersonaSolo]);

// --- Prueba HTTP del endpoint (si el servidor está corriendo) ---
$url = 'http://127.0.0.1/api/identidad.php';

// 4. Método no permitido → 405
$trace = test_http_json('TRACE', null, 'application/json', $url);
test_same(405, $trace['status'], 'TRACE en identidad.php responde 405');

// 5. GET sin Bearer → esProductor: false (en demo local sin Supabase)
$get = test_http_json('GET', null, 'application/json', $url);
test_same(200, $get['status'], 'GET identidad sin Bearer responde 200');
test_same(true, $get['body']['success'], 'GET identidad sin Bearer es exitoso');
test_same(false, $get['body']['data']['esProductor'], 'GET sin Bearer → esProductor false');

echo "Todos los tests de api_identidad pasaron.\n";
