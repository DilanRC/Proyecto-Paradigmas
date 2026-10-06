<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';
require_once dirname(__DIR__) . '/Application/Model/Vehiculo.php';
require_once dirname(__DIR__) . '/Application/Model/TransportistaOferta.php';
require_once dirname(__DIR__) . '/Application/Model/CompraSolicitud.php';
require_once dirname(__DIR__) . '/Application/Model/PagoMetodo.php';
require_once dirname(__DIR__) . '/Application/Controller/MiVehiculosController.php';
require_once dirname(__DIR__) . '/Application/Controller/MiOfertasController.php';
require_once dirname(__DIR__) . '/Application/Controller/SolicitudesCompraController.php';
require_once dirname(__DIR__) . '/Application/Controller/FletesController.php';
require_once dirname(__DIR__) . '/Application/Controller/AdminFletesController.php';
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';

use Application\Auth\ActorContext;
use Application\Controller\AdminFletesController;
use Application\Controller\AnimalPublicacionController;
use Application\Controller\FletesController;
use Application\Controller\MiOfertasController;
use Application\Controller\MiVehiculosController;
use Application\Controller\RegistroPublicoController;
use Application\Controller\SolicitudesCompraController;

/**
 * Fletes para el administrador: lista de ofertas y de solicitudes (solo lectura, sin teléfonos), retirar y
 * reactivar ofertas con motivo, candado para el transportista y bitácora MODERAR / OFERTA_FLETE.
 */

$identificaciones = [];
$vendedores = [];
$animalIds = [];

/** @return array{0:int,1:ActorContext} */
function af_registro(string $identificacion, array $capacidades): array
{
    $correo = strtolower(test_token('adminflete')) . '@example.test';
    $actor = ActorContext::usuarioVerificado(null, 'supabase-' . test_token('adminflete'), $correo, 'authenticated');
    $respuesta = (new RegistroPublicoController(test_db(), $actor, test_token('registro-adminflete')))->procesar('POST', [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $identificacion,
            'nombres' => 'AdminFlete', 'apellidos' => 'Prueba', 'alias' => 'Prueba admin flete',
            'telefono' => '+506 8888-4444', 'correoElectronico' => $correo,
        ],
        'capacidades' => $capacidades,
        'fincas' => [],
    ]);
    test_same(201, $respuesta['status'], 'La fixture debe registrarse');
    $consulta = test_db()->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $consulta->execute(['id' => $identificacion]);
    $personaId = (int) $consulta->fetchColumn();
    return [$personaId, ActorContext::personaAutenticada($personaId, 'supabase-' . test_token('actor'), $correo, 'authenticated')];
}

function af_cleanup(array $identificaciones, array $vendedores, array $animalIds): void
{
    $db = test_db();
    $todas = array_values(array_unique(array_merge($identificaciones, $vendedores)));
    if ($todas === []) return;
    $marks = implode(',', array_fill(0, count($todas), '?'));

    $compradores = "SELECT c.tbcompradorid FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $filas = $db->prepare("SELECT tbcomprasolicitudid FROM tbcomprasolicitud WHERE tbcompradorid IN ({$compradores})");
    $filas->execute($todas);
    $solicitudIds = array_map('strval', $filas->fetchAll(PDO::FETCH_COLUMN));
    if ($solicitudIds !== []) {
        $m = implode(',', array_fill(0, count($solicitudIds), '?'));
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'COMPRA_SOLICITUD' AND tbbitacoraregistroidentificacionnumero IN ({$m})")->execute($solicitudIds);
        $db->prepare("DELETE FROM tbcomprasolicitud WHERE tbcomprasolicitudid IN ({$m})")->execute($solicitudIds);
    }
    $animalIds = array_values(array_unique(array_filter($animalIds)));
    if ($animalIds !== []) {
        $m = implode(',', array_fill(0, count($animalIds), '?'));
        $db->prepare("DELETE FROM tbventa WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $db->prepare("DELETE FROM tbcompra WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $publicaciones = $db->prepare("SELECT tbanimalpublicacionid FROM tbanimalpublicacion WHERE tbanimalid IN ({$m})");
        $publicaciones->execute($animalIds);
        $publicacionIds = array_map('intval', $publicaciones->fetchAll(PDO::FETCH_COLUMN));
        if ($publicacionIds !== []) {
            $mp = implode(',', array_fill(0, count($publicacionIds), '?'));
            $db->prepare("DELETE FROM tbanimalpublicacionestadoperiodo WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
            $db->prepare("DELETE FROM tbanimalpublicacion WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
        }
        $db->prepare("DELETE FROM tbanimalproduccionsalud WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $db->prepare("DELETE FROM tbanimal WHERE tbanimalid IN ({$m})")->execute($animalIds);
    }

    $transportistas = "SELECT t.tbtransportistaid FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $ofertas = $db->prepare("SELECT tbtransportistaofertaid, tbdireccionid FROM tbtransportistaoferta WHERE tbtransportistaid IN ({$transportistas})");
    $ofertas->execute($todas);
    $filasOferta = $ofertas->fetchAll(PDO::FETCH_NUM);
    if ($filasOferta !== []) {
        $ofertaIds = array_map(static fn (array $f): string => (string) $f[0], $filasOferta);
        $direccionIds = array_map(static fn (array $f): int => (int) $f[1], $filasOferta);
        $m = implode(',', array_fill(0, count($ofertaIds), '?'));
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'OFERTA_FLETE' AND tbbitacoraregistroidentificacionnumero IN ({$m})")->execute($ofertaIds);
        $db->prepare("DELETE FROM tbtransportistaoferta WHERE tbtransportistaofertaid IN ({$m})")->execute($ofertaIds);
        $db->prepare("DELETE FROM tbdireccion WHERE tbdireccionid IN ({$m})")->execute($direccionIds);
    }
    $db->prepare("DELETE v FROM tbvehiculo v INNER JOIN tbtransportistavehiculo tv ON tv.tbvehiculoid = v.tbvehiculoid WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($todas);
    $db->prepare("DELETE tv FROM tbtransportistavehiculo tv WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($todas);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($todas);
    $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);

    $db->prepare("DELETE h FROM tbcompradorpersonatelefonohistorico h WHERE h.tbcompradorid IN ({$compradores})")->execute($todas);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
    test_cleanup_productores($vendedores);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
}

try {
    $db = test_db();

    // Vendedor con finca y una publicación; comprador A; transportista T con dos ofertas.
    $vendedor = test_create_completo(['fincas' => [['nombre' => 'Finca AdminFlete']]]);
    $vendedores[] = $vendedor['identificacionNumero'];
    test_same(201, test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $vendedor['identificacionNumero'], 'nombreFinca' => 'Finca AdminFlete',
        'direccionFinca' => test_direccion_payload(['latitud' => 9.9281, 'longitud' => -84.0907]),
    ])['status'], 'La fixture registra la dirección de la finca');
    $persona = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $persona->execute(['id' => $vendedor['identificacionNumero']]);
    $filaVendedor = $persona->fetch();
    $actorVendedor = ActorContext::personaAutenticada((int) $filaVendedor['tbpersonaid'], 'supabase-' . test_token('vendedor'), $filaVendedor['tbpersonacorreoelectronico'], 'authenticated');
    $titulo = 'AdminFlete ' . test_token('titulo');
    $creada = (new AnimalPublicacionController($db, test_token('publicar'), $actorVendedor))->procesar('POST', [], [
        'fincaNombre' => 'Finca AdminFlete', 'animalIdentificacion' => 'AF-' . test_token('animal'), 'raza' => 'Brahman',
        'sexo' => 'HEMBRA', 'proposito' => 'CRIA', 'edadMeses' => 24, 'peso' => 410.5, 'titulo' => $titulo, 'precio' => 500000,
    ]);
    test_same(201, $creada['status'], 'La fixture publica un animal');
    $animalIds[] = (int) $creada['body']['data']['animalId'];
    $publicacionId = (int) $creada['body']['data']['publicacionId'];

    $idA = test_document(); $identificaciones[] = $idA;
    [, $actorA] = af_registro($idA, ['COMPRADOR']);
    $idT = test_document(); $identificaciones[] = $idT;
    [, $actorT] = af_registro($idT, ['TRANSPORTISTA']);
    $vehiculoT = (int) (new MiVehiculosController($db, $actorT, test_token('veh-t')))
        ->procesar('POST', ['placa' => 'AF-' . strtoupper(bin2hex(random_bytes(3))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión AdminFlete'])['body']['data']['vehiculo']['vehiculoId'];
    $misOfertas = new MiOfertasController($db, $actorT, test_token('ofertas-t'));
    $cuerpoOferta = static fn (array $cambios = []): array => $cambios + [
        'vehiculoId' => $vehiculoT,
        'direccion' => ['provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen', 'pueblo' => 'Centro',
            'senas' => 'Señas privadas', 'latitud' => 9.9350, 'longitud' => -84.0800],
        'radioKm' => 50, 'capacidad' => 8, 'precio' => 120000,
    ];
    $ofertaUno = (int) $misOfertas->procesar('POST', $cuerpoOferta())['body']['data']['oferta']['ofertaId'];
    $ofertaDos = (int) $misOfertas->procesar('POST', $cuerpoOferta(['capacidad' => 3]))['body']['data']['oferta']['ofertaId'];
    $solicitud = (new SolicitudesCompraController($db, $actorA, test_token('sol-a')))
        ->procesar('POST', ['publicacionId' => $publicacionId, 'ofertaId' => $ofertaUno])['body']['data']['solicitud'];

    $admin = new AdminFletesController($db, test_token('admin'), $actorT);

    // Lectura de ofertas: transportista, vehículo, zona general; sin teléfonos ni señas.
    $lista = $admin->procesar('GET', ['vista' => 'OFERTAS', 'q' => 'AdminFlete', 'estado' => 'TODOS'], []);
    test_same(200, $lista['status'], 'El admin lista ofertas');
    test_same('OFERTAS', $lista['body']['data']['vista'], 'Devuelve la vista pedida');
    $ids = array_column($lista['body']['data']['ofertas'], 'ofertaId');
    sort($ids);
    test_same([$ofertaUno, $ofertaDos], $ids, 'El buscador encuentra las dos ofertas por transportista o vehículo');
    $primera = $lista['body']['data']['ofertas'][0];
    test_assert(is_string($primera['transportista']['nombre']) && $primera['vehiculo']['modelo'] === 'Camión AdminFlete', 'Trae transportista y vehículo');
    test_same('San José', $primera['zona']['provincia'], 'Trae la zona general');
    $texto = json_encode($lista['body']['data'], JSON_UNESCAPED_UNICODE);
    test_assert(!str_contains($texto, 'Señas privadas') && !str_contains($texto, '8888-4444'), 'No expone señas ni teléfonos');
    test_same(2, $lista['body']['data']['total'], 'El total cuenta las dos ofertas');
    test_same(1, count($admin->procesar('GET', ['vista' => 'OFERTAS', 'q' => 'AdminFlete', 'tamanoPagina' => 1], [])['body']['data']['ofertas']), 'Pagina de a una');
    test_same(2, count($admin->procesar('GET', ['q' => 'AdminFlete', 'estado' => 'ACTIVA'], [])['body']['data']['ofertas']), 'La vista por defecto es OFERTAS y filtra por estado');
    test_same([], $admin->procesar('GET', ['q' => 'AdminFlete', 'estado' => 'RETIRADA'], [])['body']['data']['ofertas'], 'Aún no hay retiradas');
    test_same(422, $admin->procesar('GET', ['vista' => 'OTRA'], [])['status'], 'Vista inválida es 422');
    test_same(422, $admin->procesar('GET', ['vista' => 'OFERTAS', 'estado' => 'PENDIENTE'], [])['status'], 'El estado se valida contra la vista');
    test_same(422, $admin->procesar('GET', ['vista' => 'SOLICITUDES', 'estado' => 'RETIRADA'], [])['status'], 'RETIRADA no es estado de solicitud');
    test_same(422, $admin->procesar('GET', ['pagina' => '0'], [])['status'], 'Página inválida es 422');

    // Lectura de solicitudes: solo nombres y estado del flete.
    $solicitudes = $admin->procesar('GET', ['vista' => 'SOLICITUDES', 'q' => $titulo], []);
    test_same(200, $solicitudes['status'], 'El admin lista solicitudes');
    test_same(1, $solicitudes['body']['data']['total'], 'Encuentra la solicitud por título');
    $fila = $solicitudes['body']['data']['solicitudes'][0];
    test_same($solicitud['solicitudId'], $fila['solicitudId'], 'Es la solicitud creada');
    test_same('PENDIENTE', $fila['estado'], 'Trae el estado');
    test_same('PENDIENTE', $fila['flete']['estado'], 'Trae el estado del flete');
    test_assert(is_string($fila['comprador']['nombre']) && is_string($fila['vendedor']['nombre']), 'Trae comprador y vendedor');
    $texto = json_encode($solicitudes['body']['data'], JSON_UNESCAPED_UNICODE);
    test_assert(!str_contains($texto, 'telefono') && !str_contains($texto, '8888-4444'), 'La lista de solicitudes no expone teléfonos');
    test_same(1, $admin->procesar('GET', ['vista' => 'SOLICITUDES', 'q' => $titulo, 'estado' => 'PENDIENTE'], [])['body']['data']['total'], 'Filtra por estado');
    test_same(0, $admin->procesar('GET', ['vista' => 'SOLICITUDES', 'q' => $titulo, 'estado' => 'ACEPTADA'], [])['body']['data']['total'], 'Filtra por otro estado');

    // Moderación: validaciones.
    test_same(422, $admin->procesar('PATCH', [], ['ofertaId' => 'x', 'estado' => 'RETIRADA', 'motivo' => 'm'])['status'], 'ofertaId inválido');
    test_same(422, $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'PAUSADA', 'motivo' => 'm'])['status'], 'El admin no pausa: solo RETIRADA o ACTIVA');
    $sinMotivo = $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'RETIRADA']);
    test_same(422, $sinMotivo['status'], 'Retirar exige motivo');
    test_assert(isset($sinMotivo['body']['errors']['motivo']), 'El error apunta al motivo');
    test_same(422, $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'RETIRADA', 'motivo' => str_repeat('a', 251)])['status'], 'Motivo de hasta 250');
    test_same(404, $admin->procesar('PATCH', [], ['ofertaId' => 999999, 'estado' => 'RETIRADA', 'motivo' => 'm'])['status'], 'Oferta inexistente es 404');
    test_same(200, $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'ACTIVA'])['status'], 'Reactivar una activa es idempotente (200)');

    // Retirar una oferta ACTIVA y una PAUSADA.
    $retirada = $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'RETIRADA', 'motivo' => 'Precio engañoso']);
    test_same(200, $retirada['status'], 'El admin retira una oferta activa');
    test_same('RETIRADA', $retirada['body']['data']['oferta']['estado'], 'Queda RETIRADA');
    test_same(200, $misOfertas->procesar('PATCH', ['ofertaId' => $ofertaDos, 'estado' => 'PAUSADA'])['status'], 'El transportista pausa la otra');
    test_same('RETIRADA', $admin->procesar('PATCH', [], ['ofertaId' => $ofertaDos, 'estado' => 'RETIRADA', 'motivo' => 'Incumple reglas'])['body']['data']['oferta']['estado'], 'También se retira una pausada');
    test_same(200, $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'RETIRADA', 'motivo' => 'otra vez'])['status'], 'Retirar de nuevo es idempotente (200)');
    test_same(2, $admin->procesar('GET', ['q' => 'AdminFlete', 'estado' => 'RETIRADA'], [])['body']['data']['total'], 'El filtro de retiradas las encuentra');

    // El transportista no puede cambiarlas, y ve el estado.
    test_same(409, $misOfertas->procesar('PATCH', ['ofertaId' => $ofertaUno, 'estado' => 'ACTIVA'])['status'], 'El transportista no reactiva una retirada');
    test_same(409, $misOfertas->procesar('PATCH', ['ofertaId' => $ofertaUno, 'estado' => 'PAUSADA'])['status'], 'Ni la pausa');
    test_same(409, $misOfertas->procesar('PUT', $cuerpoOferta(['ofertaId' => $ofertaUno, 'capacidad' => 9]))['status'], 'Ni la edita');
    $propias = $misOfertas->procesar('GET')['body']['data']['ofertas'];
    test_same(['RETIRADA', 'RETIRADA'], array_column($propias, 'estado'), 'Mis ofertas la muestra como RETIRADA');

    // Una retirada no sale en Fletes ni se puede pedir con una solicitud nueva.
    $fletes = (new FletesController($db, $actorA))->procesar('GET', ['latitud' => '9.9350', 'longitud' => '-84.0800']);
    test_same(200, $fletes['status'], 'Fletes responde');
    $visibles = array_column($fletes['body']['data']['ofertas'], 'ofertaId');
    test_assert(!in_array($ofertaUno, $visibles, true) && !in_array($ofertaDos, $visibles, true), 'Una retirada no aparece en Fletes');
    $otra = (new SolicitudesCompraController($db, $actorA, test_token('sol-a2')))
        ->procesar('POST', ['publicacionId' => $publicacionId, 'ofertaId' => $ofertaDos]);
    test_assert(in_array($otra['status'], [409, 422], true), 'No se pide un flete retirado (' . $otra['status'] . ')');

    // Reactivar: solo desde RETIRADA, y solo el admin.
    $reactivada = $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'ACTIVA']);
    test_same(200, $reactivada['status'], 'El admin reactiva una retirada (sin motivo)');
    test_same('ACTIVA', $reactivada['body']['data']['oferta']['estado'], 'Vuelve a ACTIVA');
    $visibles = array_column((new FletesController($db, $actorA))->procesar('GET', ['latitud' => '9.9350', 'longitud' => '-84.0800'])['body']['data']['ofertas'], 'ofertaId');
    test_assert(in_array($ofertaUno, $visibles, true), 'Reactivada vuelve a Fletes');
    test_same(200, $misOfertas->procesar('PATCH', ['ofertaId' => $ofertaUno, 'estado' => 'PAUSADA'])['status'], 'El transportista vuelve a manejarla');
    test_same(409, $admin->procesar('PATCH', [], ['ofertaId' => $ofertaUno, 'estado' => 'ACTIVA'])['status'], 'El admin no reactiva una pausada por su dueño');

    // Bitácora: MODERAR sobre OFERTA_FLETE con el motivo.
    $bitacora = $db->prepare("SELECT tbbitacoraaccion, tbbitacoraorigen, tbbitacoradatosnuevos FROM tbbitacora
        WHERE tbbitacoraentidad = 'OFERTA_FLETE' AND tbbitacoraregistroidentificacionnumero = :id AND tbbitacoraaccion = 'MODERAR'
        ORDER BY tbbitacoraid");
    $bitacora->execute(['id' => (string) $ofertaUno]);
    $eventos = $bitacora->fetchAll();
    test_same(2, count($eventos), 'Retirar y reactivar dejan un evento MODERAR cada uno');
    test_same('API_ADMIN_FLETES', $eventos[0]['tbbitacoraorigen'], 'El origen es API_ADMIN_FLETES');
    test_assert(str_contains((string) $eventos[0]['tbbitacoradatosnuevos'], 'Precio enga'), 'El motivo queda en la bitácora');
    test_same(405, $admin->procesar('DELETE', [], [])['status'], 'DELETE no existe');
} finally {
    af_cleanup($identificaciones, $vendedores, $animalIds);
}

echo "OK api_admin_fletes_test: listas de ofertas y solicitudes, retirar y reactivar con motivo, candado al transportista, Fletes sin retiradas y bitácora.\n";
