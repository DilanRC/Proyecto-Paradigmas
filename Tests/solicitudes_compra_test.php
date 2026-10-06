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
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';

use Application\Auth\ActorContext;
use Application\Controller\AnimalPublicacionController;
use Application\Controller\MiOfertasController;
use Application\Controller\MiVehiculosController;
use Application\Controller\RegistroPublicoController;
use Application\Controller\SolicitudesCompraController;

$identificaciones = [];   // personas registradas por la prueba (no el vendedor)
$vendedores = [];
$animalIds = [];

/** @return array{0:int,1:ActorContext} */
function sol_registro(string $identificacion, array $capacidades): array
{
    $correo = strtolower(test_token('solicitud')) . '@example.test';
    $actor = ActorContext::usuarioVerificado(null, 'supabase-' . test_token('solicitud'), $correo, 'authenticated');
    $respuesta = (new RegistroPublicoController(test_db(), $actor, test_token('registro-solicitud')))->procesar('POST', [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $identificacion,
            'nombres' => 'Solicitud', 'apellidos' => 'Compra', 'alias' => 'Prueba solicitud',
            'telefono' => '+506 8888-3333', 'correoElectronico' => $correo,
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

function sol_contar(string $sql, array $parametros = []): int
{
    $sentencia = test_db()->prepare($sql);
    $sentencia->execute($parametros);
    return (int) $sentencia->fetchColumn();
}

function sol_cleanup(array $identificaciones, array $vendedores, array $animalIds): void
{
    $db = test_db();
    $todas = array_values(array_unique(array_merge($identificaciones, $vendedores)));
    if ($todas === []) return;
    $marks = implode(',', array_fill(0, count($todas), '?'));

    // Solicitudes, compras y ventas de lo que creó la prueba.
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

    // Ofertas, direcciones, vehículos y transportistas.
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

    // Compradores (incluido el que se le agregó al vendedor) y su histórico de teléfono.
    $db->prepare("DELETE h FROM tbcompradorpersonatelefonohistorico h WHERE h.tbcompradorid IN ({$compradores})")->execute($todas);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
    test_cleanup_productores($vendedores);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
}

try {
    $db = test_db();

    // Vendedor con finca (con punto en el mapa) y tres publicaciones; también es Comprador para probar "no a lo propio".
    $vendedor = test_create_completo(['fincas' => [['nombre' => 'Finca Solicitud']]]);
    $vendedores[] = $vendedor['identificacionNumero'];
    $respuestaFinca = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $vendedor['identificacionNumero'], 'nombreFinca' => 'Finca Solicitud',
        'direccionFinca' => test_direccion_payload(['provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen', 'latitud' => 9.9281, 'longitud' => -84.0907]),
    ]);
    test_same(201, $respuestaFinca['status'], 'La fixture registra la dirección de la finca');
    $persona = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $persona->execute(['id' => $vendedor['identificacionNumero']]);
    $filaVendedor = $persona->fetch();
    $personaVendedor = (int) $filaVendedor['tbpersonaid'];
    $actorVendedor = ActorContext::personaAutenticada($personaVendedor, 'supabase-' . test_token('vendedor'), $filaVendedor['tbpersonacorreoelectronico'], 'authenticated');
    $publicador = new AnimalPublicacionController($db, test_token('publicar'), $actorVendedor);
    $publicar = function (string $titulo, ?int $precio) use ($publicador, &$animalIds): int {
        $cuerpo = ['fincaNombre' => 'Finca Solicitud', 'animalIdentificacion' => 'SOL-' . test_token('animal'), 'raza' => 'Brahman',
            'sexo' => 'HEMBRA', 'proposito' => 'CRIA', 'edadMeses' => 24, 'peso' => 410.5, 'titulo' => $titulo];
        if ($precio !== null) $cuerpo['precio'] = $precio;
        $creada = $publicador->procesar('POST', [], $cuerpo);
        test_same(201, $creada['status'], 'La fixture publica un animal');
        $animalIds[] = (int) $creada['body']['data']['animalId'];
        return (int) $creada['body']['data']['publicacionId'];
    };
    $pubConPrecio = $publicar('Novilla con precio', 500000);
    $pubAConvenir = $publicar('Novilla a convenir', null);
    $pubRechazo = $publicar('Novilla para rechazo', 300000);

    // Sin la actividad Comprador no se puede solicitar; después se le agrega para probar "no a lo propio".
    test_same(409, (new SolicitudesCompraController($db, $actorVendedor))->procesar('POST', ['publicacionId' => $pubConPrecio])['status'], 'Sin la actividad Comprador es 409');
    $db->prepare('INSERT INTO tbcomprador (tbcompradorid, tbpersonaid, tbcompradorestado)
        SELECT COALESCE(MAX(tbcompradorid), 0) + 1, :persona, 1 FROM tbcomprador')->execute(['persona' => $personaVendedor]);

    // Compradores A y B; transportista T con una oferta que cubre la finca y otra lejana.
    $idA = test_document(); $identificaciones[] = $idA;
    [, $actorA] = sol_registro($idA, ['COMPRADOR']);
    $idB = test_document(); $identificaciones[] = $idB;
    [, $actorB] = sol_registro($idB, ['COMPRADOR']);
    $idT = test_document(); $identificaciones[] = $idT;
    [, $actorT] = sol_registro($idT, ['TRANSPORTISTA']);
    $vehiculoT = (int) (new MiVehiculosController($db, $actorT, test_token('veh-t')))
        ->procesar('POST', ['placa' => 'SC-' . strtoupper(bin2hex(random_bytes(3))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión jaula'])['body']['data']['vehiculo']['vehiculoId'];
    $ofertasT = new MiOfertasController($db, $actorT, test_token('ofertas-t'));
    $zona = fn (float $lat, float $lng, string $canton): array => ['provincia' => 'X', 'canton' => $canton, 'distrito' => 'Y', 'latitud' => $lat, 'longitud' => $lng];
    $ofertaCerca = (int) $ofertasT->procesar('POST', ['vehiculoId' => $vehiculoT, 'direccion' => $zona(9.9350, -84.0800, 'Central'), 'radioKm' => 50, 'capacidad' => 8])['body']['data']['oferta']['ofertaId'];
    $ofertaLejos = (int) $ofertasT->procesar('POST', ['vehiculoId' => $vehiculoT, 'direccion' => $zona(10.6350, -85.4377, 'Liberia'), 'radioKm' => 5, 'capacidad' => 8])['body']['data']['oferta']['ofertaId'];

    $comprasA = new SolicitudesCompraController($db, $actorA, test_token('sol-a'));
    $comprasB = new SolicitudesCompraController($db, $actorB, test_token('sol-b'));
    $ventas = new SolicitudesCompraController($db, $actorVendedor, test_token('sol-v'));
    $fletes = new SolicitudesCompraController($db, $actorT, test_token('sol-t'));

    // Fletes cerca de la finca de una publicación: el punto lo resuelve el servidor.
    $fletesDeFinca = new Application\Controller\FletesController($db, $actorA);
    $cercaDeFinca = $fletesDeFinca->procesar('GET', ['publicacionId' => (string) $pubConPrecio]);
    test_same(200, $cercaDeFinca['status'], 'Fletes cerca de la finca de la publicación');
    $idsFinca = array_column($cercaDeFinca['body']['data']['ofertas'], 'ofertaId');
    test_assert(in_array($ofertaCerca, $idsFinca, true), 'Aparece el flete que cubre la finca');
    test_assert(!in_array($ofertaLejos, $idsFinca, true), 'No aparece el que no la cubre');
    test_same(422, $fletesDeFinca->procesar('GET', ['publicacionId' => '999999'])['status'], 'Publicación sin finca ubicada o inexistente es 422');
    test_same(422, $fletesDeFinca->procesar('GET', ['publicacionId' => 'x'])['status'], 'publicacionId debe ser entero');

    // Sin sesión, sin Comprador o sobre lo propio.
    test_same(401, (new SolicitudesCompraController($db, ActorContext::noAutenticado()))->procesar('GET')['status'], 'Sin sesión es 401');
    test_same(409, $ventas->procesar('POST', ['publicacionId' => $pubConPrecio])['status'], 'No se solicita la publicación propia');

    // Validación.
    test_same(422, $comprasA->procesar('POST', [])['status'], 'publicacionId es obligatorio');
    test_same(422, $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio, 'compradorId' => 1])['status'], 'No se elige el comprador por JSON');
    test_same(422, $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio, 'mensaje' => str_repeat('a', 501)])['status'], 'Mensaje de hasta 500');
    test_same(404, $comprasA->procesar('POST', ['publicacionId' => 999999])['status'], 'Publicación inexistente es 404');
    test_same(422, $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio, 'pagoMetodoId' => 999999])['status'], 'Método de pago inexistente es 422');
    test_same(422, $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio, 'ofertaId' => 999999])['status'], 'Flete inexistente es 422');
    $lejos = $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio, 'ofertaId' => $ofertaLejos]);
    test_same(422, $lejos['status'], 'Un flete que no cubre la finca es 422');
    test_assert(isset($lejos['body']['errors']['ofertaId']), 'El error apunta al flete');

    // A pide sin flete; B pide con flete.
    $a1 = $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio, 'mensaje' => 'Me interesa']);
    test_same(201, $a1['status'], 'A solicita el animal');
    $solA1 = $a1['body']['data']['solicitud'];
    test_same('PENDIENTE', $solA1['estado'], 'Nace PENDIENTE');
    test_same(500000.0, $solA1['precio'], 'Copia el precio de la publicación');
    test_same(null, $solA1['flete'], 'Sin flete pedido');
    test_same(null, $solA1['vendedor']['telefono'], 'El teléfono del vendedor no se comparte aún');
    test_same(409, $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio])['status'], 'No se repite una pendiente');
    $b1 = $comprasB->procesar('POST', ['publicacionId' => $pubConPrecio, 'ofertaId' => $ofertaCerca]);
    test_same(201, $b1['status'], 'B solicita con flete');
    $solB1 = $b1['body']['data']['solicitud'];
    test_same('PENDIENTE', $solB1['flete']['estado'], 'El flete nace PENDIENTE');

    // Listados por rol.
    test_same(1, count($comprasA->procesar('GET')['body']['data']['hechas']), 'A ve su solicitud');
    test_same(2, count($ventas->procesar('GET')['body']['data']['recibidas']), 'El vendedor recibe las dos');
    test_same([], $fletes->procesar('GET')['body']['data']['fletes'], 'El transportista no ve el flete hasta que se acepte la venta');

    // Cancelar: solo el dueño, solo pendiente.
    test_same(404, $comprasB->procesar('PATCH', ['solicitudId' => $solA1['solicitudId'], 'accion' => 'CANCELAR'])['status'], 'B no cancela la de A');
    test_same(404, $ventas->procesar('PATCH', ['solicitudId' => $solA1['solicitudId'], 'accion' => 'CANCELAR'])['status'], 'El vendedor no cancela por el comprador');
    test_same('CANCELADA', $comprasA->procesar('PATCH', ['solicitudId' => $solA1['solicitudId'], 'accion' => 'CANCELAR'])['body']['data']['solicitud']['estado'], 'A cancela la suya');
    test_same(409, $comprasA->procesar('PATCH', ['solicitudId' => $solA1['solicitudId'], 'accion' => 'CANCELAR'])['status'], 'No se cancela dos veces');
    $a2 = $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio]);
    test_same(201, $a2['status'], 'Tras cancelar, A puede volver a pedir');
    $solA2 = $a2['body']['data']['solicitud'];

    // Rechazar: solo el vendedor, con motivo opcional.
    $r = $comprasA->procesar('POST', ['publicacionId' => $pubRechazo]);
    $solRechazo = $r['body']['data']['solicitud'];
    test_same(404, $comprasA->procesar('PATCH', ['solicitudId' => $solRechazo['solicitudId'], 'accion' => 'RECHAZAR'])['status'], 'El comprador no rechaza');
    $rechazada = $ventas->procesar('PATCH', ['solicitudId' => $solRechazo['solicitudId'], 'accion' => 'RECHAZAR', 'motivo' => 'Ya está reservado']);
    test_same('RECHAZADA', $rechazada['body']['data']['solicitud']['estado'], 'El vendedor rechaza');
    test_same('Ya está reservado', $rechazada['body']['data']['solicitud']['respuestaMotivo'], 'Guarda el motivo');
    test_same(409, $ventas->procesar('PATCH', ['solicitudId' => $solRechazo['solicitudId'], 'accion' => 'ACEPTAR'])['status'], 'Una rechazada no se acepta');
    test_same('ACTIVO', sol_estado_publicacion($pubRechazo), 'Rechazar no vende la publicación');

    // Aceptar la de B: vende, registra compra y venta, rechaza la de A y comparte teléfonos.
    test_same(404, $comprasA->procesar('PATCH', ['solicitudId' => $solB1['solicitudId'], 'accion' => 'ACEPTAR'])['status'], 'Un comprador no acepta ventas');
    $aceptada = $ventas->procesar('PATCH', ['solicitudId' => $solB1['solicitudId'], 'accion' => 'ACEPTAR']);
    test_same(200, $aceptada['status'], 'El vendedor acepta');
    test_same('ACEPTADA', $aceptada['body']['data']['solicitud']['estado'], 'Queda ACEPTADA');
    test_assert($aceptada['body']['data']['solicitud']['comprador']['telefono'] !== null, 'El vendedor ve el teléfono del comprador');
    test_same('VENDIDO', sol_estado_publicacion($pubConPrecio), 'La publicación pasa a VENDIDO');
    test_same(1, sol_contar('SELECT COUNT(*) FROM tbventa WHERE tbcomprasolicitudid = :id AND tbcompradorid IS NOT NULL AND tbproductorcompradorid IS NULL AND tbventaprecio = 500000', ['id' => $solB1['solicitudId']]), 'Se registra la venta con el Comprador y el precio');
    test_same(1, sol_contar('SELECT COUNT(*) FROM tbcompra c INNER JOIN tbventa v ON v.tbcompraid = c.tbcompraid WHERE v.tbcomprasolicitudid = :id AND c.tbcompradorid IS NOT NULL AND c.tbpagometodoid IS NULL', ['id' => $solB1['solicitudId']]), 'Se registra la compra enlazada, sin pago');
    test_same(1, sol_contar('SELECT COUNT(*) FROM tbventa WHERE tbcomprasolicitudid = :id AND tbventarazasnapshot = :raza AND tbventaedadmeses = 24', ['id' => $solB1['solicitudId'], 'raza' => 'Brahman']), 'La venta guarda los snapshots del animal');
    test_same('RECHAZADA', $comprasA->procesar('GET')['body']['data']['hechas'][0]['estado'] ?? null, 'La otra pendiente se rechaza sola');
    test_same(409, $ventas->procesar('PATCH', ['solicitudId' => $solB1['solicitudId'], 'accion' => 'ACEPTAR'])['status'], 'No se acepta dos veces');
    test_same(409, $comprasA->procesar('POST', ['publicacionId' => $pubConPrecio])['status'], 'Una publicación vendida ya no se solicita');
    $solB1b = $comprasB->procesar('GET')['body']['data']['hechas'][0];
    test_same(null, $solB1b['flete']['transportista']['telefono'], 'El teléfono del transportista espera su respuesta');
    test_assert($solB1b['vendedor']['telefono'] !== null, 'El comprador ve el teléfono del vendedor');

    // El flete: ahora lo ve el transportista y lo responde por separado.
    $pedidos = $fletes->procesar('GET')['body']['data']['fletes'];
    test_same(1, count($pedidos), 'El transportista ve el flete pedido');
    test_same(null, $pedidos[0]['comprador']['telefono'], 'Sin aceptar el flete, no hay teléfonos');
    test_same(404, $comprasB->procesar('PATCH', ['solicitudId' => $solB1['solicitudId'], 'accion' => 'ACEPTAR_FLETE'])['status'], 'Solo el transportista responde el flete');
    $fleteOk = $fletes->procesar('PATCH', ['solicitudId' => $solB1['solicitudId'], 'accion' => 'ACEPTAR_FLETE']);
    test_same('ACEPTADA', $fleteOk['body']['data']['solicitud']['flete']['estado'], 'El transportista acepta el flete');
    test_assert($fleteOk['body']['data']['solicitud']['comprador']['telefono'] !== null, 'Con el flete aceptado comparte teléfonos');
    test_same(409, $fletes->procesar('PATCH', ['solicitudId' => $solB1['solicitudId'], 'accion' => 'RECHAZAR_FLETE'])['status'], 'El flete no se responde dos veces');
    test_assert($comprasB->procesar('GET')['body']['data']['hechas'][0]['flete']['transportista']['telefono'] !== null, 'El comprador ve el teléfono del transportista');

    // Publicación "a convenir": el vendedor fija el precio al aceptar.
    $c = $comprasA->procesar('POST', ['publicacionId' => $pubAConvenir]);
    $solConvenir = $c['body']['data']['solicitud'];
    test_same(null, $solConvenir['precio'], 'Sin precio en la publicación, la solicitud no lo trae');
    $sinPrecio = $ventas->procesar('PATCH', ['solicitudId' => $solConvenir['solicitudId'], 'accion' => 'ACEPTAR']);
    test_same(422, $sinPrecio['status'], 'Aceptar una "a convenir" exige el precio');
    test_assert(isset($sinPrecio['body']['errors']['precio']), 'El error apunta al precio');
    test_same('ACTIVO', sol_estado_publicacion($pubAConvenir), 'Un 422 no vende la publicación (transacción revertida)');
    test_same(422, $ventas->procesar('PATCH', ['solicitudId' => $solConvenir['solicitudId'], 'accion' => 'ACEPTAR', 'precio' => -5])['status'], 'Precio no válido');
    $conPrecio = $ventas->procesar('PATCH', ['solicitudId' => $solConvenir['solicitudId'], 'accion' => 'ACEPTAR', 'precio' => 700000]);
    test_same(700000.0, $conPrecio['body']['data']['solicitud']['precio'], 'Se guarda el precio acordado');
    test_same(1, sol_contar('SELECT COUNT(*) FROM tbventa WHERE tbcomprasolicitudid = :id AND tbventaprecio = 700000', ['id' => $solConvenir['solicitudId']]), 'La venta usa el precio acordado');

    // Acciones inválidas y bitácora.
    test_same(422, $ventas->procesar('PATCH', ['solicitudId' => $solConvenir['solicitudId'], 'accion' => 'BORRAR'])['status'], 'Acción fuera del catálogo');
    $acciones = $db->prepare("SELECT tbbitacoraaccion FROM tbbitacora WHERE tbbitacoraentidad = 'COMPRA_SOLICITUD' AND tbbitacoraorigen = 'API_SOLICITUDES_COMPRA' AND tbbitacoraregistroidentificacionnumero IN (?, ?)");
    $acciones->execute([(string) $solB1['solicitudId'], (string) $solA1['solicitudId']]);
    $registradas = $acciones->fetchAll(PDO::FETCH_COLUMN);
    foreach (['CREAR', 'ACEPTAR', 'ACEPTAR_FLETE', 'CANCELAR'] as $accion) {
        test_assert(in_array($accion, $registradas, true), "La bitácora debe registrar {$accion}");
    }
    test_assert($solA2['solicitudId'] > 0, 'Fixture A2');
} finally {
    sol_cleanup($identificaciones, $vendedores, $animalIds);
}

function sol_estado_publicacion(int $publicacionId): ?string
{
    $sentencia = test_db()->prepare('SELECT tbanimalpublicacionestadoperiodoestado FROM tbanimalpublicacionestadoperiodo
        WHERE tbanimalpublicacionid = :id AND tbanimalpublicacionestadoperiodofechafin IS NULL');
    $sentencia->execute(['id' => $publicacionId]);
    $estado = $sentencia->fetchColumn();
    return $estado === false ? null : (string) $estado;
}

echo "OK solicitudes_compra_test: crear, validar, cancelar, rechazar, aceptar (venta, compra, precio a convenir), flete aparte, contactos y bitácora.\n";
