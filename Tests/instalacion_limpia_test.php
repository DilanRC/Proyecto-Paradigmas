<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Tools/seed-maestra.php';
require_once dirname(__DIR__) . '/Tools/schema-manifest.php';

use Application\Auth\ActorContext;
use Application\Controller\IdentidadController;

/**
 * Verificación de instalación limpia (TRAMO B / DEC-32): una base recién
 * creada con `000instalacioncompleta.sql` + semillas SQL + semilla maestra
 * (Tools/seed-maestra.php) soporta todas las lecturas públicas y la puerta de
 * esquema; la semilla es idempotente y no deja huérfanos.
 *
 * La semilla usa los mismos servicios PHP de la API, así que el sobre
 * {success,message,data,errors} y los bloqueos/queries no se rompen al sembrar.
 */

$db = test_db();

// -----------------------------------------------
// 1. Las 32 tablas canónicas del manifest existen en la base.
// -----------------------------------------------
$manifest = schema_manifest();
$existentes = $db->query('SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE "tb%"')->fetchAll(PDO::FETCH_COLUMN);
sort($existentes, SORT_STRING);
test_same($manifest['tables_sorted'], $existentes,
    'La base viva contiene exactamente las 32 tablas canónicas del SQL (puerta de esquema OK).');

// -----------------------------------------------
// 2. Semilla maestra idempotente: dos corridas consecutivas, mismos conteos.
// -----------------------------------------------
$conteosTablasMadre = static fn (): array => [
    'tbpersona' => (int) $db->query('SELECT COUNT(*) FROM tbpersona')->fetchColumn(),
    'tbproductor' => (int) $db->query('SELECT COUNT(*) FROM tbproductor')->fetchColumn(),
    'tbcomprador' => (int) $db->query('SELECT COUNT(*) FROM tbcomprador')->fetchColumn(),
    'tbtransportista' => (int) $db->query('SELECT COUNT(*) FROM tbtransportista')->fetchColumn(),
    'tbfinca' => (int) $db->query('SELECT COUNT(*) FROM tbfinca')->fetchColumn(),
    'tbdireccion' => (int) $db->query('SELECT COUNT(*) FROM tbdireccion')->fetchColumn(),
    'tbvehiculo' => (int) $db->query('SELECT COUNT(*) FROM tbvehiculo')->fetchColumn(),
    'tbpagometodo' => (int) $db->query('SELECT COUNT(*) FROM tbpagometodo')->fetchColumn(),
    'tbtransportistavehiculo' => (int) $db->query('SELECT COUNT(*) FROM tbtransportistavehiculo')->fetchColumn(),
];

$silencioso = static function (string $_mensaje): void {};
$conteosDespuesPrimera = null;
$conteosDespuesSegunda = null;
// La semilla es una secuencia de alzas por los mismos controladores de la API:
// cada una transacciona por su cuenta (GET_LOCK + beginTransaction) tal como
// sucede por HTTP. Aquí NO hay transacción madre: envolver en beginTransaction
// anidaría una segunda transacción sobre la misma conexión y el alta interna
// fallaría con "already an active transaction". La idempotencia se comprueba
// comparando los conteos de dos corridas completas.
seed_maestra($db, $silencioso);
$conteosDespuesPrimera = $conteosTablasMadre();
seed_maestra($db, $silencioso);
$conteosDespuesSegunda = $conteosTablasMadre();
test_same($conteosDespuesPrimera, $conteosDespuesSegunda,
    'La segunda corrida de la semilla no agrega ni retira filas (idempotente).');

// -----------------------------------------------
// 3. La semilla está completa (auditoría --check) y puebla las madres.
// -----------------------------------------------
$estadoSemilla = seed_maestra_check($db, $silencioso);
test_same([], $estadoSemilla['faltantes'], 'La semilla maestra está completa (--check).');
test_assert($conteosDespuesSegunda['tbpersona'] >= 5, 'tbpersona poblada por las semillas de instalación.');
test_assert($conteosDespuesSegunda['tbproductor'] >= 2, 'tbproductor poblado.');
test_assert($conteosDespuesSegunda['tbcomprador'] >= 1, 'tbcomprador poblado (contexto de Persona).');
test_assert($conteosDespuesSegunda['tbtransportista'] >= 1, 'tbtransportista poblado.');
test_assert($conteosDespuesSegunda['tbfinca'] >= 3, 'tbfinca poblada.');
test_assert($conteosDespuesSegunda['tbdireccion'] >= 3, 'tbdireccion poblada.');
test_assert($conteosDespuesSegunda['tbvehiculo'] >= 2, 'tbvehiculo poblado.');
test_assert($conteosDespuesSegunda['tbpagometodo'] >= 1, 'tbpagometodo poblado (Efectivo).');
test_assert($conteosDespuesSegunda['tbtransportistavehiculo'] >= 1,
    'La asignación transportista→vehículo está sembrada.');

// -----------------------------------------------
// 4. IDs únicos en las tablas madre (MAX(id)+1 bajo NamedLock, sin colisiones).
// -----------------------------------------------
foreach ([
    ['tbpersona', 'tbpersonaid'], ['tbproductor', 'tbproductorid'],
    ['tbcomprador', 'tbcompradorid'], ['tbtransportista', 'tbtransportistaid'],
    ['tbfinca', 'tbfincaid'], ['tbdireccion', 'tbdireccionid'],
    ['tbvehiculo', 'tbvehiculoid'], ['tbtransportistavehiculo', 'tbtransportistavehiculoid'],
] as [$tabla, $columna]) {
    $total = (int) $db->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
    $distintos = (int) $db->query("SELECT COUNT(DISTINCT {$columna}) FROM {$tabla}")->fetchColumn();
    test_same($total, $distintos, "{$tabla}.{$columna} sin IDs repetidos.");
}

// -----------------------------------------------
// 5. Sin filas huérfanas (integridad referencial a nivel de aplicación).
// -----------------------------------------------
$verificarSinHuerfanos = static function (string $sql, string $mensaje): void {
    $filas = (int) test_db()->query($sql)->fetchColumn();
    test_same(0, $filas, $mensaje);
};
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbproductor p LEFT JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid WHERE pe.tbpersonaid IS NULL',
    'Ningún tbproductor apunta a una persona inexistente.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbcomprador c LEFT JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid WHERE pe.tbpersonaid IS NULL',
    'Ningún tbcomprador apunta a una persona inexistente.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbtransportista t LEFT JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid WHERE pe.tbpersonaid IS NULL',
    'Ningún tbtransportista apunta a una persona inexistente.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbproductordireccion pd LEFT JOIN tbdireccion d ON d.tbdireccionid = pd.tbdireccionid WHERE d.tbdireccionid IS NULL',
    'Ningún enlace productor→dirección queda huérfano.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbfincadireccion fd LEFT JOIN tbdireccion d ON d.tbdireccionid = fd.tbdireccionid WHERE d.tbdireccionid IS NULL',
    'Ningún enlace finca→dirección queda huérfano.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbfinca f LEFT JOIN tbproductor p ON p.tbproductorid = f.tbproductorid WHERE p.tbproductorid IS NULL',
    'Ninguna finca apunta a un productor inexistente.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbtransportistavehiculo tv LEFT JOIN tbtransportista t ON t.tbtransportistaid = tv.tbtransportistaid LEFT JOIN tbvehiculo v ON v.tbvehiculoid = tv.tbvehiculoid WHERE t.tbtransportistaid IS NULL OR v.tbvehiculoid IS NULL',
    'Ninguna asignación transportista→vehículo queda huérfana.');
$verificarSinHuerfanos(
    'SELECT COUNT(*) FROM tbproductorestadoperiodo ep LEFT JOIN tbproductor p ON p.tbproductorid = ep.tbproductorid WHERE p.tbproductorid IS NULL',
    'Ningún periodo de estado de productor queda huérfano.');

// -----------------------------------------------
// 6. Lecturas públicas responden sin sesión (superficie pública intacta).
// -----------------------------------------------
$publicaciones = test_http_json('GET', null, 'application/json', 'http://127.0.0.1/api/publicaciones.php');
test_same(200, $publicaciones['status'], 'Instalación limpia: GET público de publicaciones responde 200.');
test_same(true, $publicaciones['body']['success'], 'Instalación limpia: publicaciones éxito.');

$identidadPublica = test_http_json('GET', null, 'application/json', 'http://127.0.0.1/api/identidad.php');
test_same(200, $identidadPublica['status'], 'Instalación limpia: GET público de identidad responde 200.');
test_same(false, $identidadPublica['body']['data']['esProductor'], 'Identidad pública sin contextos.');
test_same(null, $identidadPublica['body']['data']['persona'], 'Identidad pública sin persona expuesta.');

$productorSemilla = $db->prepare('SELECT p.tbproductorid FROM tbproductor p
    INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
    WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
$productorSemilla->execute(['id' => '104550123']);
$productorSemillaId = (int) $productorSemilla->fetchColumn();
test_assert($productorSemillaId > 0, 'La semilla dejó un productor consultable.');

$ubicacion = test_http_json('GET', null, 'application/json',
    "http://127.0.0.1/api/productores-ubicacion.php?productorId={$productorSemillaId}");
test_same(200, $ubicacion['status'], 'Instalación limpia: GET público de ubicación responde 200.');
test_same(true, $ubicacion['body']['success'], 'Instalación limpia: histórico de ubicación legible.');

// -----------------------------------------------
// 7. Identidad autenticada: la semilla alimenta la resolución de contextos.
// -----------------------------------------------
$controladorPublico = new IdentidadController($db, ActorContext::noAutenticado());
$respuestaPublica = $controladorPublico->procesar();
test_same(false, $respuestaPublica['body']['data']['esProductor'], 'Modo público: esProductor false.');
test_same(false, $respuestaPublica['body']['data']['esComprador'], 'Modo público: esComprador false.');
test_same(false, $respuestaPublica['body']['data']['esTransportista'], 'Modo público: esTransportista false.');

$personaProductor = $db->prepare('SELECT pe.tbpersonaid FROM tbproductor p
    INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
    WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
$personaProductor->execute(['id' => '104550123']);
$controladorProductor = new IdentidadController($db, ActorContext::personaAutenticada(
    (int) $personaProductor->fetchColumn(), 'seed-subject', 'santa.rosa@example.test', 'authenticated'));
$respuestaProductor = $controladorProductor->procesar();
test_same(true, $respuestaProductor['body']['data']['esProductor'], 'La persona sembrada es productor.');
test_same($productorSemillaId, $respuestaProductor['body']['data']['productorId'], 'productorId de la semilla.');
test_assert(is_array($respuestaProductor['body']['data']['persona']),
    'La persona sembrada se resuelve en el campo persona.');

$personaComprador = $db->prepare('SELECT pe.tbpersonaid FROM tbcomprador c
    INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
    WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
$personaComprador->execute(['id' => '3101333344']);
$controladorComprador = new IdentidadController($db, ActorContext::personaAutenticada(
    (int) $personaComprador->fetchColumn(), 'seed-subject-2', 'el.mercado@example.test', 'authenticated'));
$respuestaComprador = $controladorComprador->procesar();
test_same(true, $respuestaComprador['body']['data']['esComprador'], 'La persona sembrada es comprador.');
test_same(false, $respuestaComprador['body']['data']['esProductor'], 'El comprador semilla no es productor.');

$personaTransportista = $db->prepare('SELECT pe.tbpersonaid FROM tbtransportista t
    INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
    WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
$personaTransportista->execute(['id' => '108550999']);
$controladorTransportista = new IdentidadController($db, ActorContext::personaAutenticada(
    (int) $personaTransportista->fetchColumn(), 'seed-subject-3', 'julio.mora@example.test', 'authenticated'));
$respuestaTransportista = $controladorTransportista->procesar();
test_same(true, $respuestaTransportista['body']['data']['esTransportista'], 'La persona sembrada es transportista.');

$asignacion = $db->prepare('SELECT COUNT(*) FROM tbtransportistavehiculo tv
    INNER JOIN tbvehiculo v ON v.tbvehiculoid = tv.tbvehiculoid
    WHERE v.tbvehiculoplaca = :placa');
$asignacion->execute(['placa' => 'ABC-148']);
test_same(1, (int) $asignacion->fetchColumn(), 'El vehículo de la semilla está asignado al transportista.');

echo "OK instalacion_limpia_test: semilla maestra idempotente, 32 tablas vivas, sin huérfanos ni IDs "
    . "duplicados, superficie pública y resolución de identidad operando sobre instalación sembrada.\n";