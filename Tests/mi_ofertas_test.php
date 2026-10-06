<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';
require_once dirname(__DIR__) . '/Application/Model/Vehiculo.php';
require_once dirname(__DIR__) . '/Application/Model/TransportistaOferta.php';
require_once dirname(__DIR__) . '/Application/Model/CompraSolicitud.php';
require_once dirname(__DIR__) . '/Application/Controller/MiVehiculosController.php';
require_once dirname(__DIR__) . '/Application/Controller/MiOfertasController.php';
require_once dirname(__DIR__) . '/Application/Controller/FletesController.php';
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';

use Application\Auth\ActorContext;
use Application\Controller\FletesController;
use Application\Controller\MiOfertasController;
use Application\Controller\MiVehiculosController;
use Application\Controller\RegistroPublicoController;

$identificaciones = [];

/** @return array{0:int,1:ActorContext} */
function oferta_registro(string $identificacion, array $capacidades): array
{
    $correo = strtolower(test_token('oferta')) . '@example.test';
    $actor = ActorContext::usuarioVerificado(null, 'supabase-' . test_token('oferta'), $correo, 'authenticated');
    $respuesta = (new RegistroPublicoController(test_db(), $actor, test_token('registro-oferta')))->procesar('POST', [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $identificacion,
            'nombres' => 'Oferta', 'apellidos' => 'Flete', 'alias' => 'Prueba oferta',
            'telefono' => '+506 8888-2222', 'correoElectronico' => $correo,
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

function oferta_cuerpo(int $vehiculoId, array $cambios = []): array
{
    return $cambios + [
        'vehiculoId' => $vehiculoId,
        'direccion' => [
            'provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen', 'pueblo' => 'Centro',
            'senas' => 'Frente al parque', 'latitud' => 9.9281, 'longitud' => -84.0907,
        ],
        'radioKm' => 30, 'capacidad' => 10, 'precio' => 150000, 'descripcion' => 'Lunes a viernes',
    ];
}

function oferta_cleanup(array $identificaciones): void
{
    $db = test_db();
    if ($identificaciones === []) return;
    $marks = implode(',', array_fill(0, count($identificaciones), '?'));
    $transportistas = "SELECT t.tbtransportistaid FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $filas = $db->prepare("SELECT tbtransportistaofertaid, tbdireccionid FROM tbtransportistaoferta WHERE tbtransportistaid IN ({$transportistas})");
    $filas->execute($identificaciones);
    $ofertas = $filas->fetchAll(PDO::FETCH_NUM);
    if ($ofertas !== []) {
        $ofertaIds = array_map(static fn (array $f): string => (string) $f[0], $ofertas);
        $direccionIds = array_map(static fn (array $f): int => (int) $f[1], $ofertas);
        $ofertaMarks = implode(',', array_fill(0, count($ofertaIds), '?'));
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'OFERTA_FLETE' AND tbbitacoraregistroidentificacionnumero IN ({$ofertaMarks})")->execute($ofertaIds);
        $db->prepare("DELETE FROM tbtransportistaoferta WHERE tbtransportistaofertaid IN ({$ofertaMarks})")->execute($ofertaIds);
        $db->prepare("DELETE FROM tbdireccion WHERE tbdireccionid IN ({$ofertaMarks})")->execute($direccionIds);
    }
    $db->prepare("DELETE v FROM tbvehiculo v INNER JOIN tbtransportistavehiculo tv ON tv.tbvehiculoid = v.tbvehiculoid WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($identificaciones);
    $db->prepare("DELETE tv FROM tbtransportistavehiculo tv WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($identificaciones);
    $db->prepare("DELETE b FROM tbbitacora b WHERE b.tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($identificaciones);
    $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($identificaciones);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($identificaciones);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($identificaciones);
}

try {
    $db = test_db();
    $idA = test_document();
    $identificaciones[] = $idA;
    [, $actorA] = oferta_registro($idA, ['TRANSPORTISTA']);
    $vehiculosA = new MiVehiculosController($db, $actorA, test_token('veh-a'));
    $foto = 'https://example.test/camion.jpg';
    $vehiculo = $vehiculosA->procesar('POST', ['placa' => 'OF-' . strtoupper(bin2hex(random_bytes(3))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión jaula', 'fotoUrl' => $foto]);
    test_same(201, $vehiculo['status'], 'El transportista debe tener un vehículo');
    $vehiculoA = (int) $vehiculo['body']['data']['vehiculo']['vehiculoId'];
    $ofertasA = new MiOfertasController($db, $actorA, test_token('ofertas-a'));

    test_same([], $ofertasA->procesar('GET')['body']['data']['ofertas'], 'Sin ofertas la lista propia está vacía');

    // Alta: estado ACTIVA, zona exacta solo para el dueño.
    $creada = $ofertasA->procesar('POST', oferta_cuerpo($vehiculoA));
    test_same(201, $creada['status'], 'El transportista publica una oferta');
    $oferta = $creada['body']['data']['oferta'];
    $ofertaId = $oferta['ofertaId'];
    test_same('ACTIVA', $oferta['estado'], 'Una oferta nueva queda ACTIVA');
    test_same(150000.0, $oferta['precio'], 'El precio base se guarda');
    test_same(9.9281, $oferta['zona']['latitud'], 'El dueño ve las coordenadas de su zona');
    test_same($foto, $oferta['vehiculo']['fotoUrl'], 'La oferta trae la foto del vehículo');

    // Validaciones.
    $sinPunto = oferta_cuerpo($vehiculoA);
    unset($sinPunto['direccion']['latitud'], $sinPunto['direccion']['longitud']);
    $rechazo = $ofertasA->procesar('POST', $sinPunto);
    test_same(422, $rechazo['status'], 'La zona base exige el punto en el mapa');
    test_assert(isset($rechazo['body']['errors']['direccion.latitud']), 'El error apunta a la latitud');
    foreach ([['radioKm' => 0], ['radioKm' => 501], ['capacidad' => 0], ['capacidad' => 201], ['precio' => -1], ['precio' => 'caro'], ['descripcion' => str_repeat('a', 501)], ['ofertaId' => 1], ['estado' => 'PAUSADA']] as $malo) {
        test_same(422, $ofertasA->procesar('POST', oferta_cuerpo($vehiculoA, $malo))['status'], 'Debe rechazar ' . json_encode($malo));
    }
    test_same(null, $ofertasA->procesar('POST', oferta_cuerpo($vehiculoA, ['precio' => null]))['body']['data']['oferta']['precio'], 'Sin precio es "a convenir"');

    // Un vehículo ajeno no se puede usar; otra persona no toca la oferta (sin revelar que existe).
    $idB = test_document();
    $identificaciones[] = $idB;
    [, $actorB] = oferta_registro($idB, ['TRANSPORTISTA']);
    $vehiculoB = (int) (new MiVehiculosController($db, $actorB, test_token('veh-b')))
        ->procesar('POST', ['placa' => 'OB-' . strtoupper(bin2hex(random_bytes(3))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión B'])['body']['data']['vehiculo']['vehiculoId'];
    $ofertasB = new MiOfertasController($db, $actorB, test_token('ofertas-b'));
    test_same(404, $ofertasA->procesar('POST', oferta_cuerpo($vehiculoB))['status'], 'No se puede ofertar con el vehículo de otro');
    test_same(404, $ofertasB->procesar('PUT', oferta_cuerpo($vehiculoB, ['ofertaId' => $ofertaId]))['status'], 'No se edita la oferta de otro');
    test_same(404, $ofertasB->procesar('PATCH', ['ofertaId' => $ofertaId, 'estado' => 'PAUSADA'])['status'], 'No se pausa la oferta de otro');
    test_same([], $ofertasB->procesar('GET')['body']['data']['ofertas'], 'Cada quien ve solo sus ofertas');

    // Sin sesión o sin la actividad Transportista.
    test_same(401, (new MiOfertasController($db, ActorContext::noAutenticado()))->procesar('GET')['status'], 'Sin sesión es 401');
    $idC = test_document();
    $identificaciones[] = $idC;
    [, $actorC] = oferta_registro($idC, ['COMPRADOR']);
    test_same(409, (new MiOfertasController($db, $actorC))->procesar('POST', oferta_cuerpo($vehiculoA))['status'], 'Sin ser Transportista es 409');

    // Fletes cercanos: dentro del radio sí; fuera, con poca capacidad, pausada o sin vehículo activo, no.
    // Quien consulta no ve sus propias ofertas (viven en "Mis ofertas"); otra persona sí.
    $fletes = new FletesController($db, $actorB);
    $fletesDelDueno = new FletesController($db, $actorA);
    $cercaDeSanJose = ['latitud' => '9.9350', 'longitud' => '-84.0800'];
    $ids = static fn (array $r): array => array_column($r['body']['data']['ofertas'], 'ofertaId');
    $cerca = $fletes->procesar('GET', $cercaDeSanJose);
    test_same(200, $cerca['status'], 'Consultar fletes cercanos');
    test_assert(in_array($ofertaId, $ids($cerca), true), 'La oferta aparece dentro de su radio');
    $publica = $cerca['body']['data']['ofertas'][array_search($ofertaId, $ids($cerca), true)];
    test_assert($publica['distanciaKm'] < 5, 'La distancia se calcula desde la zona base');
    test_same(['modelo', 'fotoUrl'], array_keys($publica['vehiculo']), 'El cliente no ve placa ni VIN');
    test_same(['provincia', 'canton', 'distrito', 'pueblo'], array_keys($publica['zona']), 'El cliente no ve señas ni coordenadas');
    $liberia = ['latitud' => '10.6350', 'longitud' => '-85.4377'];
    test_assert(!in_array($ofertaId, $ids($fletes->procesar('GET', $liberia)), true), 'Fuera del radio no aparece');
    test_assert(!in_array($ofertaId, $ids($fletes->procesar('GET', $cercaDeSanJose + ['capacidadMinima' => '11'])), true), 'Filtra por capacidad mínima');
    test_assert(!in_array($ofertaId, $ids($fletesDelDueno->procesar('GET', $cercaDeSanJose)), true), 'El dueño no ve su propia oferta entre los fletes disponibles');
    test_same(422, $fletes->procesar('GET', [])['status'], 'Sin ubicación es 422');
    test_same(422, $fletes->procesar('GET', ['latitud' => '95', 'longitud' => '0'])['status'], 'Latitud fuera de rango es 422');
    test_same(405, $fletes->procesar('POST', [])['status'], 'Solo lectura');

    // Edición: cambia el precio y mueve la zona (se actualiza la misma dirección).
    $editada = $ofertasA->procesar('PUT', oferta_cuerpo($vehiculoA, [
        'ofertaId' => $ofertaId, 'precio' => 99000, 'radioKm' => 5,
        'direccion' => ['provincia' => 'Guanacaste', 'canton' => 'Liberia', 'distrito' => 'Liberia', 'latitud' => 10.6350, 'longitud' => -85.4377],
    ]));
    test_same(200, $editada['status'], 'El dueño edita su oferta');
    test_same(99000.0, $editada['body']['data']['oferta']['precio'], 'El precio editado se guarda');
    test_assert(!in_array($ofertaId, $ids($fletes->procesar('GET', $cercaDeSanJose)), true), 'Al mover la zona deja de verse en San José');
    test_assert(in_array($ofertaId, $ids($fletes->procesar('GET', $liberia)), true), 'Y aparece en Liberia');

    // Pausar y reactivar (idempotente).
    $pausada = $ofertasA->procesar('PATCH', ['ofertaId' => $ofertaId, 'estado' => 'PAUSADA']);
    test_same('PAUSADA', $pausada['body']['data']['oferta']['estado'], 'Pausar cambia el estado');
    test_same(200, $ofertasA->procesar('PATCH', ['ofertaId' => $ofertaId, 'estado' => 'PAUSADA'])['status'], 'Pausar dos veces es idempotente');
    test_assert(!in_array($ofertaId, $ids($fletes->procesar('GET', $liberia)), true), 'Una oferta pausada no se ofrece');
    test_same(422, $ofertasA->procesar('PATCH', ['ofertaId' => $ofertaId, 'estado' => 'BORRADA'])['status'], 'Estado fuera del catálogo es 422');
    $ofertasA->procesar('PATCH', ['ofertaId' => $ofertaId, 'estado' => 'ACTIVA']);
    test_assert(in_array($ofertaId, $ids($fletes->procesar('GET', $liberia)), true), 'Reactivada vuelve a ofrecerse');

    // Con el vehículo desactivado la oferta no se ofrece.
    $vehiculosA->procesar('DELETE', ['vehiculoId' => $vehiculoA]);
    test_assert(!in_array($ofertaId, $ids($fletes->procesar('GET', $liberia)), true), 'Sin vehículo activo no se ofrece');
    test_same(409, $ofertasA->procesar('POST', oferta_cuerpo($vehiculoA))['status'], 'No se oferta con un vehículo inactivo');
    $vehiculosA->procesar('PATCH', ['vehiculoId' => $vehiculoA]);

    // Bitácora: alta, edición y pausa.
    $bitacora = $db->prepare("SELECT tbbitacoraaccion FROM tbbitacora WHERE tbbitacoraentidad = 'OFERTA_FLETE' AND tbbitacoraorigen = 'API_MI_OFERTAS' AND tbbitacoraregistroidentificacionnumero = :id");
    $bitacora->execute(['id' => (string) $ofertaId]);
    $acciones = $bitacora->fetchAll(PDO::FETCH_COLUMN);
    foreach (['CREAR', 'ACTUALIZAR', 'PAUSAR', 'REACTIVAR'] as $accion) {
        test_assert(in_array($accion, $acciones, true), "La bitácora debe registrar {$accion}");
    }
} finally {
    oferta_cleanup($identificaciones);
}

echo "OK mi_ofertas_test: alta y validación, anti-IDOR, cercanía por radio, pausa, vehículo activo y bitácora.\n";
