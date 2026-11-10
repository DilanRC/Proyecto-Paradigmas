<?php

declare(strict_types=1);

/**
 * Datos de demostración para Mi dashboard (DEC-DASHBOARD-001). Solo para la base LOCAL.
 *
 *  - Como VENDEDOR, la persona indicada subasta 8 animales en todos los estados (en vivo, adjudicadas, sin ofertas y
 *    cancelada) y tres compradores de demostración pujan.
 *  - Como COMPRADOR, la misma persona puja en las subastas de un segundo vendedor de demostración: gana dos, pierde una,
 *    va ganando una y otra la superan; además hay dos subastas abiertas cerca (oportunidades) y un transportista con
 *    una oferta con precio que cubre esa finca (costo estimado de adquisición).
 *
 * Fotos de Wikimedia Commons (las de seed-publicaciones-demo.php). Las cerradas se mueven al pasado para que el filtro
 * de período tenga qué mostrar.
 *
 *   docker compose exec -T app php Tools/seed-dashboard-demo.php --correo=tu@correo.com   (tu cuenta debe ser Vendedor con finca)
 *   docker compose exec -T app php Tools/seed-dashboard-demo.php                         (todo con personas de demostración)
 *   docker compose exec -T app php Tools/seed-dashboard-demo.php --limpiar
 *
 * Lo creado se reconoce por la marca [demo-dashboard] en la descripción de la subasta y por las identificaciones
 * DMDASH* de las personas de demostración; --limpiar borra solo eso.
 */

use Application\Auth\ActorContext;
use Application\Controller\MiAnimalesController;
use Application\Controller\MiOfertasController;
use Application\Controller\MiVehiculosController;
use Application\Controller\RegistroPublicoController;
use Application\Controller\SubastasController;
use Application\Service\SubastaCierreService;
use Application\Service\TiempoReal;

if (getenv('VERCEL') || getenv('POSTGRES_URL')) {
    fwrite(STDERR, "Este script es solo para la base local.\n");
    exit(1);
}

require dirname(__DIR__) . '/Tests/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';
require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';
foreach (['AnimalVacunacion', 'Notificacion', 'Subasta', 'Administrador', 'Vehiculo', 'TransportistaOferta'] as $modelo) {
    require_once dirname(__DIR__) . "/Application/Model/{$modelo}.php";
}
foreach (['RegistroPublicoController', 'MiAnimalesController', 'SubastasController', 'MiVehiculosController', 'MiOfertasController'] as $controlador) {
    require_once dirname(__DIR__) . "/Application/Controller/{$controlador}.php";
}

const MARCA = '[demo-dashboard]';
const VENDEDOR_DEMO = 'DMDASH01';
const VENDEDOR_DEMO_2 = 'DMDASH02';
const VENDEDOR_DEMO_3 = 'DMDASH03';
const TRANSPORTISTA_DEMO = 'DMDASH21';
const COMPRADORES_DEMO = ['DMDASH11' => 'Ganadera La Esperanza', 'DMDASH12' => 'Hacienda El Roble', 'DMDASH13' => 'Lechería San Rafael'];
// Finca del segundo vendedor (San Carlos, Alajuela): con "Usar mi ubicación" cerca de aquí aparecen las oportunidades.
const PUNTO_DEMO = ['latitud' => '10.3239000', 'longitud' => '-84.4309000'];

$db = test_db();
// Los avisos de tiempo real no salen de la máquina: la demo no necesita llegar a Supabase.
$tiempoReal = new TiempoReal(static function (): void {});
$opciones = getopt('', ['correo:', 'limpiar']);

function demo_persona_id(PDO $db, string $campo, string $valor): ?int
{
    $columna = $campo === 'correo' ? 'LOWER(tbpersonacorreoelectronico)' : 'tbpersonaidentificacionnumero';
    $sentencia = $db->prepare("SELECT tbpersonaid FROM tbpersona WHERE {$columna} = :v");
    $sentencia->execute(['v' => $campo === 'correo' ? strtolower($valor) : $valor]);
    $id = $sentencia->fetchColumn();
    return $id === false ? null : (int) $id;
}

function demo_actor(PDO $db, int $personaId): ActorContext
{
    $correo = $db->prepare('SELECT tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaid = :id');
    $correo->execute(['id' => $personaId]);
    return ActorContext::personaAutenticada($personaId, 'demo-dashboard-' . $personaId, (string) $correo->fetchColumn(), 'authenticated');
}

function demo_finca(string $nombre, ?array $direccion = null): array
{
    return [[
        'nombre' => $nombre,
        'direccion' => ($direccion ?? ['provincia' => 'Alajuela', 'canton' => 'San Carlos', 'distrito' => 'Quesada'] + PUNTO_DEMO)
            + ['pueblo' => null, 'senas' => 'Finca de demostración del dashboard'],
    ]];
}

/** Registra (si no existe) una persona de demostración y devuelve su id. */
function demo_registrar(PDO $db, string $identificacion, string $nombre, array $capacidades, array $fincas = []): int
{
    $existente = demo_persona_id($db, 'identificacion', $identificacion);
    if ($existente !== null) return $existente;
    $correo = 'demo.' . strtolower($identificacion) . '@example.test';
    $actor = ActorContext::usuarioVerificado(null, 'demo-dashboard-' . $identificacion, $correo, 'authenticated');
    [$nombres, $apellidos] = explode(' ', $nombre, 2) + [1 => 'Demo'];
    $r = (new RegistroPublicoController($db, $actor, 'DEMO-DASHBOARD-' . $identificacion))->procesar('POST', [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $identificacion,
            'nombres' => $nombres, 'apellidos' => $apellidos, 'alias' => $nombre,
            'telefono' => '+506 8888-1234', 'correoElectronico' => $correo,
        ],
        'capacidades' => $capacidades,
        'fincas' => $fincas,
    ]);
    if ($r['status'] !== 201) throw new RuntimeException("No se pudo registrar {$identificacion}: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE));
    return (int) demo_persona_id($db, 'identificacion', $identificacion);
}

function demo_fallo(string $que, array $r): never
{
    throw new RuntimeException("No se pudo {$que}: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE));
}

/**
 * Abre las subastas de un plan con el vendedor dado. Cada puja es [quién, monto], donde quién es una clave de $postores.
 * `estado` CANCELADA las cancela; `hace` (días) y `horas` (duración) las dejan vencidas en el pasado para cerrarlas.
 *
 * @return array<int,array> subastas que hay que mover al pasado (id => plan)
 */
function demo_abrir(PDO $db, int $vendedorId, array $postores, array $planes, TiempoReal $tiempoReal): array
{
    $finca = $db->prepare(
        'SELECT f.tbfincanombre FROM tbfinca f INNER JOIN tbproductor p ON p.tbproductorid = f.tbproductorid
         WHERE p.tbpersonaid = :id AND f.tbfincaestado = 1 ORDER BY f.tbfincaid LIMIT 1'
    );
    $finca->execute(['id' => $vendedorId]);
    $fincaNombre = $finca->fetchColumn() ?: throw new RuntimeException('El vendedor necesita la actividad Vendedor y una finca activa.');
    $actor = demo_actor($db, $vendedorId);
    $inventario = new MiAnimalesController($db, $actor, 'DEMO-DASHBOARD-INV');
    $vendedor = new SubastasController($db, $actor, null, $tiempoReal);
    $foto = static fn (string $ruta): string => "https://upload.wikimedia.org/wikipedia/commons/thumb/{$ruta}/960px-" . basename($ruta);

    $moverAlPasado = [];
    foreach ($planes as $plan) {
        $animal = $inventario->procesar('POST', ['especieId' => 1, 'tipoId' => $plan['tipo'], 'razaId' => $plan['raza'], 'peso' => 420, 'proposito' => 'Carne']);
        if ($animal['status'] !== 201) demo_fallo('registrar el animal', $animal);
        $abierta = $vendedor->procesar('POST', [], [
            'animalId' => (int) $animal['body']['data']['animal']['animalId'], 'fincaNombre' => $fincaNombre, 'titulo' => $plan['titulo'],
            'descripcion' => 'Subasta de demostración ' . MARCA, 'imagenUrl' => $foto($plan['img']),
            'precioBase' => $plan['base'], 'incrementoMinimo' => 10000, 'duracionMinutos' => 10080,
        ]);
        if ($abierta['status'] !== 201) demo_fallo('abrir la subasta', $abierta);
        $subastaId = (int) $abierta['body']['data']['subasta']['subastaId'];
        foreach ($plan['pujas'] as [$quien, $monto]) {
            $r = $postores[$quien]->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'PUJAR', 'monto' => $monto]);
            if (!in_array($r['status'], [200, 201], true)) demo_fallo('pujar', $r);
        }
        if (($plan['estado'] ?? null) === 'CANCELADA') {
            $vendedor->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'CANCELAR']);
            $db->prepare('UPDATE tbsubasta SET tbsubastafechainicio = :i, tbsubastafechacierre = :c WHERE tbsubastaid = :id')->execute([
                'i' => gmdate('Y-m-d H:i:s', time() - 5 * 86400), 'c' => gmdate('Y-m-d H:i:s', time() - 5 * 86400 + 7200), 'id' => $subastaId,
            ]);
        } elseif (isset($plan['hace'])) {
            $moverAlPasado[$subastaId] = $plan;
        }
        echo "Subasta {$subastaId}: {$plan['titulo']}\n";
    }
    return $moverAlPasado;
}

function demo_limpiar(PDO $db): void
{
    $ids = $db->query("SELECT tbsubastaid, tbanimalid FROM tbsubasta WHERE tbsubastadescripcion LIKE '%" . MARCA . "%'")->fetchAll(PDO::FETCH_NUM);
    foreach ($ids as [$subastaId, $animalId]) {
        $db->prepare('DELETE FROM tbsubastapuja WHERE tbsubastaid = :id')->execute(['id' => $subastaId]);
        $db->prepare('DELETE FROM tbsubastacomentario WHERE tbsubastaid = :id')->execute(['id' => $subastaId]);
        $db->prepare('DELETE FROM tbsubasta WHERE tbsubastaid = :id')->execute(['id' => $subastaId]);
        $db->prepare('DELETE FROM tbnotificacion WHERE tbnotificacionenlace LIKE :enlace')->execute(['enlace' => "%subastas?id={$subastaId}"]);
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'SUBASTA' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => (string) $subastaId]);
        foreach (['tbventa', 'tbcompra', 'tbanimalproduccionsalud', 'tbanimal'] as $tabla) {
            $db->prepare("DELETE FROM {$tabla} WHERE tbanimalid = :id")->execute(['id' => $animalId]);
        }
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'ANIMAL' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => (string) $animalId]);
    }
    $demo = array_merge([VENDEDOR_DEMO, VENDEDOR_DEMO_2, VENDEDOR_DEMO_3, TRANSPORTISTA_DEMO], array_keys(COMPRADORES_DEMO));
    $marcas = implode(',', array_fill(0, count($demo), '?'));
    $personas = "SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marcas})";
    // Transportista de demostración: oferta, su dirección, vehículo y vínculo.
    $transportistas = "SELECT t.tbtransportistaid FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marcas})";
    $ofertas = $db->prepare("SELECT tbtransportistaofertaid, tbdireccionid FROM tbtransportistaoferta WHERE tbtransportistaid IN ({$transportistas})");
    $ofertas->execute($demo);
    foreach ($ofertas->fetchAll(PDO::FETCH_NUM) as [$ofertaId, $direccionId]) {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'OFERTA_FLETE' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => (string) $ofertaId]);
        $db->prepare('DELETE FROM tbtransportistaoferta WHERE tbtransportistaofertaid = :id')->execute(['id' => $ofertaId]);
        $db->prepare('DELETE FROM tbdireccion WHERE tbdireccionid = :id')->execute(['id' => $direccionId]);
    }
    $db->prepare("DELETE v FROM tbvehiculo v INNER JOIN tbtransportistavehiculo tv ON tv.tbvehiculoid = v.tbvehiculoid WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($demo);
    $db->prepare("DELETE tv FROM tbtransportistavehiculo tv WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($demo);
    $db->prepare("DELETE FROM tbnotificacion WHERE tbpersonaid IN ({$personas})")->execute($demo);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacorausuarioid IN ({$personas})")->execute($demo);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcas})")->execute($demo);
    $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marcas})")->execute($demo);
    $compradores = "SELECT c.tbcompradorid FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marcas})";
    $db->prepare("DELETE h FROM tbcompradorpersonatelefonohistorico h WHERE h.tbcompradorid IN ({$compradores})")->execute($demo);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marcas})")->execute($demo);
    test_cleanup_productores([VENDEDOR_DEMO, VENDEDOR_DEMO_2, VENDEDOR_DEMO_3]);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marcas})")->execute($demo);
    echo 'Datos de demostración del dashboard borrados (' . count($ids) . " subastas).\n";
}

if (isset($opciones['limpiar'])) {
    demo_limpiar($db);
    exit(0);
}

// 1) La persona que mira el dashboard: la cuenta indicada o una de demostración (Vendedor y Comprador).
if (isset($opciones['correo'])) {
    $yoId = demo_persona_id($db, 'correo', (string) $opciones['correo'])
        ?? throw new RuntimeException('No hay una Persona con ese correo. Regístrate primero en la app local como Vendedor con una finca.');
} else {
    $yoId = demo_registrar($db, VENDEDOR_DEMO, 'Vendedor Demo', ['COMPRADOR', 'PRODUCTOR'], demo_finca('Finca Demo Dashboard'));
}

// 2) Compradores que pujan (los de demostración y la propia persona, `yo`).
$postores = [];
foreach (array_keys(COMPRADORES_DEMO) as $i => $identificacion) {
    $postores[$i] = new SubastasController($db, demo_actor($db, demo_registrar($db, $identificacion, COMPRADORES_DEMO[$identificacion], ['COMPRADOR'])), null, $tiempoReal);
}
$postores['yo'] = new SubastasController($db, demo_actor($db, $yoId), null, $tiempoReal);

// 3) Dashboard del VENDEDOR: subastas de la persona.
$moverAlPasado = demo_abrir($db, $yoId, $postores, [
    ['titulo' => 'Vaca Jersey de 4 años', 'tipo' => 6, 'raza' => 3, 'img' => '9/9c/Jersey_Cow_01.jpg', 'base' => 900000, 'pujas' => [[0, 900000], [1, 950000]]],
    ['titulo' => 'Vaquilla Holstein', 'tipo' => 5, 'raza' => 2, 'img' => '9/98/Milchkuh_auf_Weide_in_NRW.jpg', 'base' => 750000, 'pujas' => []],
    ['titulo' => 'Novilla Brahman de engorde', 'tipo' => 5, 'raza' => 1, 'img' => '4/4f/Brahman_cattle_in_Costa_Rica.jpg', 'base' => 600000, 'pujas' => [[0, 600000], [1, 650000], [2, 720000]], 'hace' => 3, 'horas' => 24],
    ['titulo' => 'Toro reproductor Gyr', 'tipo' => 7, 'raza' => 7, 'img' => 'd/d9/Gyr_cattle_-_01.jpg', 'base' => 2000000, 'pujas' => [[0, 2000000], [1, 2150000]], 'hace' => 10, 'horas' => 3],
    ['titulo' => 'Vaca de cría Simmental', 'tipo' => 6, 'raza' => 9, 'img' => '0/03/Simmental_calf_cow.jpg', 'base' => 1100000, 'pujas' => [[0, 1100000], [1, 1200000], [2, 1300000], [0, 1450000]], 'hace' => 20, 'horas' => 48],
    ['titulo' => 'Ternero Nelore destetado', 'tipo' => 1, 'raza' => 6, 'img' => '1/1c/Nelore_cattle_Brasil.jpg', 'base' => 450000, 'pujas' => [], 'hace' => 15, 'horas' => 12],
    ['titulo' => 'Vaca Holstein lechera', 'tipo' => 6, 'raza' => 2, 'img' => '9/98/Milchkuh_auf_Weide_in_NRW.jpg', 'base' => 1000000, 'pujas' => [], 'estado' => 'CANCELADA'],
    ['titulo' => 'Vaca Jersey primeriza', 'tipo' => 6, 'raza' => 3, 'img' => '9/9c/Jersey_Cow_01.jpg', 'base' => 800000, 'pujas' => [[0, 800000], [1, 880000]], 'hace' => 60, 'horas' => 24],
], $tiempoReal);

// 4) Dashboard del COMPRADOR: un segundo vendedor subasta y la persona puja.
$vendedor2 = demo_registrar($db, VENDEDOR_DEMO_2, 'Hacienda Demo Norte', ['COMPRADOR', 'PRODUCTOR'], demo_finca('Hacienda Demo Norte'));
$moverAlPasado += demo_abrir($db, $vendedor2, $postores, [
    // Va ganando.
    ['titulo' => 'Novilla Brahman cebada', 'tipo' => 5, 'raza' => 1, 'img' => '4/4f/Brahman_cattle_in_Costa_Rica.jpg', 'base' => 620000, 'pujas' => [[0, 620000], ['yo', 680000]]],
    // La superaron.
    ['titulo' => 'Vaca Jersey en producción', 'tipo' => 6, 'raza' => 3, 'img' => '9/9c/Jersey_Cow_01.jpg', 'base' => 850000, 'pujas' => [['yo', 850000], [1, 900000]]],
    // Ganadas y perdida, en el pasado.
    ['titulo' => 'Toro Gyr de 3 años', 'tipo' => 7, 'raza' => 7, 'img' => 'd/d9/Gyr_cattle_-_01.jpg', 'base' => 1800000, 'pujas' => [[2, 1800000], ['yo', 1900000]], 'hace' => 5, 'horas' => 24],
    ['titulo' => 'Vaca Simmental parida', 'tipo' => 6, 'raza' => 9, 'img' => '0/03/Simmental_calf_cow.jpg', 'base' => 1000000, 'pujas' => [['yo', 1000000], [1, 1100000]], 'hace' => 12, 'horas' => 24],
    ['titulo' => 'Vaquilla Holstein de reemplazo', 'tipo' => 5, 'raza' => 2, 'img' => '9/98/Milchkuh_auf_Weide_in_NRW.jpg', 'base' => 700000, 'pujas' => [['yo', 700000]], 'hace' => 25, 'horas' => 48],
    // Oportunidades cercanas (la persona no puja).
    ['titulo' => 'Ternero Nelore de engorde', 'tipo' => 1, 'raza' => 6, 'img' => '1/1c/Nelore_cattle_Brasil.jpg', 'base' => 400000, 'pujas' => [[0, 400000]]],
    ['titulo' => 'Vaca Jersey joven', 'tipo' => 6, 'raza' => 3, 'img' => '9/9c/Jersey_Cow_01.jpg', 'base' => 780000, 'pujas' => []],
], $tiempoReal);

// 4b) Un tercer vendedor en San José centro: así hay oportunidades cerca tanto en San Carlos como en San José. La persona
// también puja en una de sus subastas (ganada) para que el monto por mes tenga más de un mes.
$vendedor3 = demo_registrar($db, VENDEDOR_DEMO_3, 'Lechería Demo Central', ['COMPRADOR', 'PRODUCTOR'], demo_finca('Lechería Demo Central',
    ['provincia' => 'San José', 'canton' => 'San José', 'distrito' => 'Carmen', 'latitud' => '9.9281000', 'longitud' => '-84.0907000']));
$moverAlPasado += demo_abrir($db, $vendedor3, $postores, [
    ['titulo' => 'Vaca Holstein de alta producción', 'tipo' => 6, 'raza' => 2, 'img' => '9/98/Milchkuh_auf_Weide_in_NRW.jpg', 'base' => 1150000, 'pujas' => [[2, 1150000]]],
    ['titulo' => 'Toro Gyr lechero', 'tipo' => 7, 'raza' => 7, 'img' => 'd/d9/Gyr_cattle_-_01.jpg', 'base' => 1700000, 'pujas' => []],
    ['titulo' => 'Novilla Brahman de cría', 'tipo' => 5, 'raza' => 1, 'img' => '4/4f/Brahman_cattle_in_Costa_Rica.jpg', 'base' => 640000, 'pujas' => [['yo', 640000], [0, 660000], ['yo', 700000]], 'hace' => 45, 'horas' => 24],
], $tiempoReal);

// 5) Un transportista con una oferta con precio que cubre la finca del segundo vendedor.
$transportista = demo_actor($db, demo_registrar($db, TRANSPORTISTA_DEMO, 'Fletes Demo Norte', ['TRANSPORTISTA']));
$vehiculo = (new MiVehiculosController($db, $transportista, 'DEMO-DASHBOARD-VEH'))->procesar('POST', [
    'placa' => 'DM-' . strtoupper(bin2hex(random_bytes(2))), 'vin' => 'VIN-DEMO-' . bin2hex(random_bytes(3)), 'modelo' => 'Camión ganadero',
]);
if ($vehiculo['status'] !== 201) demo_fallo('registrar el vehículo', $vehiculo);
$oferta = (new MiOfertasController($db, $transportista, 'DEMO-DASHBOARD-OF'))->procesar('POST', [
    'vehiculoId' => (int) $vehiculo['body']['data']['vehiculo']['vehiculoId'],
    'direccion' => ['provincia' => 'Alajuela', 'canton' => 'San Carlos', 'distrito' => 'Florencia', 'latitud' => 10.36, 'longitud' => -84.48],
    'radioKm' => 80, 'capacidad' => 10, 'precio' => 85000, 'descripcion' => 'Flete de demostración del dashboard',
]);
if ($oferta['status'] !== 201) demo_fallo('publicar la oferta de flete', $oferta);

// 6) Las cerradas: inicio y pujas en el pasado y fin vencido; el cierre de siempre las adjudica o las deja desiertas.
foreach ($moverAlPasado as $subastaId => $plan) {
    $inicio = time() - $plan['hace'] * 86400;
    $fin = $inicio + $plan['horas'] * 3600;
    $db->prepare('UPDATE tbsubasta SET tbsubastafechainicio = :i, tbsubastafechafin = :f WHERE tbsubastaid = :id')
        ->execute(['i' => gmdate('Y-m-d H:i:s', $inicio), 'f' => gmdate('Y-m-d H:i:s', $fin), 'id' => $subastaId]);
    $pujas = $db->prepare('SELECT tbsubastapujaid FROM tbsubastapuja WHERE tbsubastaid = :id ORDER BY tbsubastapujaid');
    $pujas->execute(['id' => $subastaId]);
    foreach ($pujas->fetchAll(PDO::FETCH_COLUMN) as $n => $pujaId) {
        $db->prepare('UPDATE tbsubastapuja SET tbsubastapujafecha = :f WHERE tbsubastapujaid = :id')
            ->execute(['f' => gmdate('Y-m-d H:i:s', $inicio + ($n + 1) * 600), 'id' => $pujaId]);
    }
}
$cerradas = (new SubastaCierreService($db, $tiempoReal))->cerrarVencidas();
// El cierre anota la hora de hoy; la demo usa la hora en que venció cada una.
foreach ($moverAlPasado as $subastaId => $plan) {
    $db->prepare('UPDATE tbsubasta SET tbsubastafechacierre = tbsubastafechafin WHERE tbsubastaid = :id')->execute(['id' => $subastaId]);
}
echo 'Listo: ' . count($cerradas) . " subastas cerradas. Abre http://localhost:8082/mi-dashboard\n";
