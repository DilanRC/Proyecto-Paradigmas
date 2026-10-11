<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';
foreach (['Vehiculo', 'TransportistaOferta', 'CompraSolicitud', 'AnimalVacunacion', 'Notificacion', 'Subasta'] as $modelo) {
    require_once dirname(__DIR__) . "/Application/Model/{$modelo}.php";
}
foreach (['MiVehiculosController', 'MiOfertasController', 'MiAnimalesController', 'FletesController', 'RegistroPublicoController',
    'SubastasController', 'NotificacionesController', 'AdminSubastasController'] as $controlador) {
    require_once dirname(__DIR__) . "/Application/Controller/{$controlador}.php";
}
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';

use Application\Auth\ActorContext;
use Application\Controller\AdminSubastasController;
use Application\Controller\FletesController;
use Application\Controller\MiAnimalesController;
use Application\Controller\MiOfertasController;
use Application\Controller\MiVehiculosController;
use Application\Controller\NotificacionesController;
use Application\Controller\RegistroPublicoController;
use Application\Controller\SubastasController;
use Application\Service\TiempoReal;

/** Subastas (DEC-SUBASTA-001): abrir, pujar, comentar, cerrar con ganador o desierta, avisos y fletes del ganador. */

$identificaciones = [];
$vendedores = [];
$animalIds = [];
$subastaIds = [];
$adminCorreo = null;

function sub_registro(string $identificacion, array $capacidades, string $alias): array
{
    $correo = strtolower(test_token('subasta')) . '@example.test';
    $actor = ActorContext::usuarioVerificado(null, 'supabase-' . test_token('subasta'), $correo, 'authenticated');
    $respuesta = (new RegistroPublicoController(test_db(), $actor, test_token('registro-subasta')))->procesar('POST', [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $identificacion,
            'nombres' => 'Subasta', 'apellidos' => 'Prueba', 'alias' => $alias,
            'telefono' => '+506 8888-4444', 'correoElectronico' => $correo,
        ],
        'capacidades' => $capacidades,
        'fincas' => [],
    ]);
    test_same(201, $respuesta['status'], 'La fixture debe registrarse');
    $consulta = test_db()->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $consulta->execute(['id' => $identificacion]);
    $personaId = (int) $consulta->fetchColumn();
    return [$personaId, ActorContext::personaAutenticada($personaId, 'supabase-' . test_token('actor'), $correo, 'authenticated'), $correo];
}

function sub_contar(string $sql, array $parametros = []): int
{
    $sentencia = test_db()->prepare($sql);
    $sentencia->execute($parametros);
    return (int) $sentencia->fetchColumn();
}

function sub_cleanup(array $identificaciones, array $vendedores, array $animalIds, array $subastaIds, ?string $adminCorreo): void
{
    $db = test_db();
    if ($adminCorreo !== null) $db->prepare('DELETE FROM tbadministrador WHERE tbadministradorcorreoelectronico = :c')->execute(['c' => $adminCorreo]);
    if ($subastaIds !== []) {
        $m = implode(',', array_fill(0, count($subastaIds), '?'));
        $db->prepare("DELETE FROM tbsubastapuja WHERE tbsubastaid IN ({$m})")->execute($subastaIds);
        $db->prepare("DELETE FROM tbsubastacomentario WHERE tbsubastaid IN ({$m})")->execute($subastaIds);
        $db->prepare("DELETE FROM tbsubasta WHERE tbsubastaid IN ({$m})")->execute($subastaIds);
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'SUBASTA' AND tbbitacoraregistroidentificacionnumero IN ({$m})")
            ->execute(array_map('strval', $subastaIds));
    }
    $todas = array_values(array_unique(array_merge($identificaciones, $vendedores)));
    if ($todas === []) return;
    $marks = implode(',', array_fill(0, count($todas), '?'));
    $personas = "SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})";
    $db->prepare("DELETE FROM tbnotificacion WHERE tbpersonaid IN ({$personas})")->execute($todas);
    $animalIds = array_values(array_unique(array_filter($animalIds)));
    if ($animalIds !== []) {
        $m = implode(',', array_fill(0, count($animalIds), '?'));
        foreach (['tbventa', 'tbcompra', 'tbanimalproduccionsalud', 'tbanimal'] as $tabla) {
            $db->prepare("DELETE FROM {$tabla} WHERE tbanimalid IN ({$m})")->execute($animalIds);
        }
    }
    $transportistas = "SELECT t.tbtransportistaid FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $ofertas = $db->prepare("SELECT tbtransportistaofertaid, tbdireccionid FROM tbtransportistaoferta WHERE tbtransportistaid IN ({$transportistas})");
    $ofertas->execute($todas);
    foreach ($ofertas->fetchAll(PDO::FETCH_NUM) as [$ofertaId, $direccionId]) {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'OFERTA_FLETE' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => (string) $ofertaId]);
        $db->prepare('DELETE FROM tbtransportistaoferta WHERE tbtransportistaofertaid = :id')->execute(['id' => $ofertaId]);
        $db->prepare('DELETE FROM tbdireccion WHERE tbdireccionid = :id')->execute(['id' => $direccionId]);
    }
    $db->prepare("DELETE v FROM tbvehiculo v INNER JOIN tbtransportistavehiculo tv ON tv.tbvehiculoid = v.tbvehiculoid WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($todas);
    $db->prepare("DELETE tv FROM tbtransportistavehiculo tv WHERE tv.tbtransportistaid IN ({$transportistas})")->execute($todas);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacorausuarioid IN ({$personas})")->execute($todas);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($todas);
    $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
    $compradores = "SELECT c.tbcompradorid FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $db->prepare("DELETE h FROM tbcompradorpersonatelefonohistorico h WHERE h.tbcompradorid IN ({$compradores})")->execute($todas);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
    test_cleanup_productores($vendedores);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
}

try {
    $db = test_db();
    if (!getenv('SUPABASE_URL')) putenv('SUPABASE_URL=https://ejemplo.supabase.co');
    if (!getenv('SUPABASE_SECRET_KEY')) putenv('SUPABASE_SECRET_KEY=sb_secret_prueba');
    $avisos = [];
    $tiempoReal = new TiempoReal(function (string $url, string $clave, string $cuerpo) use (&$avisos): void {
        foreach (json_decode($cuerpo, true)['messages'] as $mensaje) $avisos[] = $mensaje['topic'];
    });

    // Vendedor V con finca en el mapa y dos animales en su inventario.
    $vendedor = test_create_completo(['fincas' => [['nombre' => 'Finca Subasta']]]);
    $vendedores[] = $vendedor['identificacionNumero'];
    test_same(201, test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $vendedor['identificacionNumero'], 'nombreFinca' => 'Finca Subasta',
        'direccionFinca' => test_direccion_payload(['provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen', 'latitud' => 9.9281, 'longitud' => -84.0907]),
    ])['status'], 'La fixture ubica la finca');
    $fila = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $fila->execute(['id' => $vendedor['identificacionNumero']]);
    $filaV = $fila->fetch();
    $personaV = (int) $filaV['tbpersonaid'];
    $actorV = ActorContext::personaAutenticada($personaV, 'supabase-' . test_token('v'), $filaV['tbpersonacorreoelectronico'], 'authenticated');
    $inventario = new MiAnimalesController($db, $actorV, test_token('inv'));
    $animal = static function () use ($inventario, &$animalIds): int {
        $r = $inventario->procesar('POST', ['especieId' => 1, 'tipoId' => 6, 'razaId' => 1, 'peso' => 420, 'proposito' => 'Leche']);
        test_same(201, $r['status'], 'La fixture registra un animal');
        return $animalIds[] = (int) $r['body']['data']['animal']['animalId'];
    };
    $animal1 = $animal();
    $animal2 = $animal();
    $animal3 = $animal();

    $idA = test_document(); $identificaciones[] = $idA;
    [$personaA, $actorA] = sub_registro($idA, ['COMPRADOR'], 'Pujador A');
    $idB = test_document(); $identificaciones[] = $idB;
    [$personaB, $actorB] = sub_registro($idB, ['COMPRADOR'], 'Pujador B');
    $idC = test_document(); $identificaciones[] = $idC;
    [, $actorC, $correoC] = sub_registro($idC, ['COMPRADOR'], 'Admin C');
    $db->prepare('INSERT INTO tbadministrador (tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado)
        SELECT COALESCE(MAX(tbadministradorid), 0) + 1, :c, 1 FROM tbadministrador')->execute(['c' => $correoC]);
    $adminCorreo = $correoC;
    $idT = test_document(); $identificaciones[] = $idT;
    [, $actorT] = sub_registro($idT, ['TRANSPORTISTA'], 'Fletes T');
    $vehiculoT = (int) (new MiVehiculosController($db, $actorT, test_token('veh')))
        ->procesar('POST', ['placa' => 'SB-' . strtoupper(bin2hex(random_bytes(3))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión jaula'])['body']['data']['vehiculo']['vehiculoId'];
    $ofertaCerca = (int) (new MiOfertasController($db, $actorT, test_token('of')))->procesar('POST', ['vehiculoId' => $vehiculoT,
        'direccion' => ['provincia' => 'X', 'canton' => 'Central', 'distrito' => 'Y', 'latitud' => 9.9350, 'longitud' => -84.0800],
        'radioKm' => 50, 'capacidad' => 8])['body']['data']['oferta']['ofertaId'];

    $v = new SubastasController($db, $actorV, test_token('sv'), $tiempoReal);
    $a = new SubastasController($db, $actorA, test_token('sa'), $tiempoReal);
    $b = new SubastasController($db, $actorB, test_token('sb'), $tiempoReal);
    $admin = new SubastasController($db, $actorC, test_token('sc'), $tiempoReal);

    // Abrir: sesión, validación, dueño y estado del animal.
    test_same(401, (new SubastasController($db, ActorContext::noAutenticado(), null, $tiempoReal))->procesar('GET')['status'], 'Sin sesión es 401');
    $nueva = ['animalId' => $animal1, 'fincaNombre' => 'Finca Subasta', 'titulo' => 'Vaca Jersey en subasta', 'precioBase' => 500000,
        'incrementoMinimo' => 25000, 'duracionMinutos' => 60];
    $mala = $v->procesar('POST', [], ['titulo' => '', 'precioBase' => 0, 'duracionMinutos' => 1] + $nueva);
    test_same(422, $mala['status'], 'Datos inválidos son 422');
    test_assert(isset($mala['body']['errors']['titulo'], $mala['body']['errors']['precioBase'], $mala['body']['errors']['duracionMinutos']), 'Un error por campo');
    test_same(422, $v->procesar('POST', [], ['ganador' => 1] + $nueva)['status'], 'Campos desconocidos son 422');
    test_same(422, $v->procesar('POST', [], ['fincaNombre' => 'Otra'] + $nueva)['status'], 'La finca debe ser propia');
    test_same(409, $a->procesar('POST', [], $nueva)['status'], 'Sin la actividad Vendedor es 409');
    test_same(403, $admin->procesar('POST', [], $nueva)['status'], 'Un administrador no abre subastas');
    $creada = $v->procesar('POST', [], $nueva);
    test_same(201, $creada['status'], 'Abre la subasta: ' . json_encode($creada['body']));
    $s = $creada['body']['data']['subasta'];
    $subastaIds[] = $subastaId = $s['subastaId'];
    test_same('ACTIVA', $s['estado'], 'Queda activa');
    test_same(500000.0, $s['minimoSiguiente'], 'La primera puja parte del precio base');
    test_assert($s['soyVendedor'] && !$s['puedoPujar'], 'El vendedor ve la subasta pero no puja');
    test_assert(in_array('subastas', $avisos, true), 'Abrir avisa a la lista en tiempo real');
    test_same('SUBASTA', $inventario->procesar('GET')['body']['data']['animales'][2]['estado'] ?? null, 'El animal queda En subasta');
    test_same(409, $v->procesar('POST', [], $nueva)['status'], 'El mismo animal no entra en dos subastas');
    test_same(409, $inventario->procesar('PATCH', ['animalId' => $animal1, 'accion' => 'PESAR', 'peso' => 500])['status'],
        'Un animal en subasta no cambia sus datos (quien puja los vio así)');

    // Listas y detalle.
    $ids = static fn (array $r): array => array_column($r['body']['data']['subastas'], 'subastaId');
    test_assert(in_array($subastaId, $ids($a->procesar('GET', ['vista' => 'ACTIVAS'])), true), 'Aparece en las activas para todos');
    test_assert(in_array($subastaId, $ids($v->procesar('GET', ['vista' => 'MIAS'])), true), 'Aparece en Mis subastas del vendedor');
    test_same(422, $a->procesar('GET', ['vista' => 'TODAS'])['status'], 'Vista desconocida es 422');
    $verA = $a->procesar('GET', ['id' => (string) $subastaId])['body']['data']['subasta'];
    test_assert($verA['puedoPujar'] && $verA['trato'] === null, 'Un comprador puede pujar y no ve el trato');
    test_same(404, $a->procesar('GET', ['id' => '999999'])['status'], 'Subasta inexistente es 404');

    // Pujas.
    $pujar = static fn (SubastasController $c, float $monto): array => $c->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'PUJAR', 'monto' => $monto]);
    test_same(403, $pujar($admin, 600000)['status'], 'Un administrador no puja');
    test_assert(!$admin->procesar('GET', ['id' => (string) $subastaId])['body']['data']['subasta']['puedoPujar'], 'El administrador ve la subasta sin poder pujar');
    test_same(409, $pujar($v, 600000)['status'], 'El vendedor no puja en su subasta');
    $baja = $pujar($a, 499999);
    test_same(422, $baja['status'], 'Bajo el precio base es 422');
    test_same('La puja mínima es ₡500.000.', $baja['body']['errors']['monto'] ?? null, 'El error dice el mínimo');
    $avisos = [];
    test_same(201, $pujar($a, 500000)['status'], 'A puja el precio base');
    test_assert(in_array("subasta-{$subastaId}", $avisos, true), 'Pujar avisa a la subasta en tiempo real');
    test_same(409, $pujar($a, 600000)['status'], 'Quien va ganando no se supera a sí mismo');
    test_same(422, $pujar($b, 524999)['status'], 'Debe superar la más alta más el incremento');
    $avisos = [];
    $pujaB = $pujar($b, 525000);
    test_same(201, $pujaB['status'], 'B supera a A');
    test_assert(in_array("notificaciones-{$personaA}", $avisos, true), 'A recibe el aviso de que lo superaron');
    test_same(1, sub_contar("SELECT COUNT(*) FROM tbnotificacion WHERE tbpersonaid = :p AND tbnotificaciontipo = 'SUBASTA_SUPERADA'", ['p' => $personaA]), 'Queda la notificación de superada');
    $verB = $pujaB['body']['data']['subasta'];
    test_assert($verB['voyGanando'] && !$verB['puedoPujar'], 'B va ganando');
    test_same(['Tú', 'Pujador A'], array_column($verB['pujas'], 'participante'), 'Las pujas muestran el alias, no el nombre completo');
    test_same(409, $v->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'CANCELAR'])['status'], 'Con pujas no se cancela');
    test_same(422, $a->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'REMATAR'])['status'], 'Acción desconocida es 422');

    // Comentarios.
    test_same(422, $a->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'COMENTAR', 'texto' => ' '])['status'], 'El comentario no puede estar vacío');
    test_same(403, $admin->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'COMENTAR', 'texto' => 'Hola'])['status'], 'Un administrador no comenta');
    $comentario = $a->procesar('PATCH', [], ['subastaId' => $subastaId, 'accion' => 'COMENTAR', 'texto' => '¿Tiene las vacunas al día?']);
    test_same(201, $comentario['status'], 'A comenta');
    test_same('Tú', end($comentario['body']['data']['comentarios'])['autor'], 'El autor se ve a sí mismo como Tú');
    test_same('Pujador A', end($v->procesar('GET', ['id' => (string) $subastaId])['body']['data']['subasta']['comentarios'])['autor'], 'Los demás ven el alias');

    // Cierre: al vencer, cualquier consulta la cierra; gana B, se registra la venta y llegan los avisos.
    $db->prepare('UPDATE tbsubasta SET tbsubastafechafin = :f WHERE tbsubastaid = :id')->execute(['f' => gmdate('Y-m-d H:i:s', time() - 5), 'id' => $subastaId]);
    $avisos = [];
    $a->procesar('GET', ['vista' => 'ACTIVAS']);
    $finB = $b->procesar('GET', ['id' => (string) $subastaId])['body']['data']['subasta'];
    test_same('FINALIZADA', $finB['estado'], 'La subasta vencida se cierra al consultarla');
    test_assert($finB['soyGanador'], 'B es el ganador');
    test_same(525000.0, $finB['trato']['precioFinal'] ?? null, 'El ganador ve el precio final');
    test_assert(is_string($finB['trato']['vendedor']['telefono'] ?? null) && $finB['trato']['vendedor']['telefono'] !== '', 'El ganador ve el teléfono del vendedor');
    test_same($filaV['tbpersonacorreoelectronico'], $finB['trato']['vendedor']['correo'] ?? null, 'Y su correo');
    test_same('+50688884444', $v->procesar('GET', ['id' => (string) $subastaId])['body']['data']['subasta']['trato']['ganador']['telefono'] ?? null,
        'El vendedor ve el teléfono del ganador');
    test_same('Carmen', $finB['trato']['finca']['distrito'] ?? null, 'El ganador ve la dirección de la finca');
    test_same(null, $a->procesar('GET', ['id' => (string) $subastaId])['body']['data']['subasta']['trato'], 'Quien perdió no ve el trato');
    test_assert(in_array("notificaciones-{$personaB}", $avisos, true) && in_array("notificaciones-{$personaV}", $avisos, true), 'Avisa al ganador y al vendedor');
    test_same(1, sub_contar("SELECT COUNT(*) FROM tbnotificacion WHERE tbpersonaid = :p AND tbnotificaciontipo = 'SUBASTA_GANADA'", ['p' => $personaB]), 'El ganador tiene su notificación');
    test_same(1, sub_contar("SELECT COUNT(*) FROM tbnotificacion WHERE tbpersonaid = :p AND tbnotificaciontipo = 'SUBASTA_FINALIZADA'", ['p' => $personaV]), 'El vendedor tiene su notificación');
    test_same(1, sub_contar('SELECT COUNT(*) FROM tbventa WHERE tbanimalid = :a AND tbventaprecio = 525000', ['a' => $animal1]), 'Se registra la venta');
    test_same(1, sub_contar('SELECT COUNT(*) FROM tbcompra WHERE tbanimalid = :a', ['a' => $animal1]), 'Se registra la compra');
    test_same('VENDIDO', $db->query("SELECT tbanimalestado FROM tbanimal WHERE tbanimalid = {$animal1}")->fetchColumn(), 'El animal queda vendido');
    test_same(409, $pujar($a, 900000)['status'], 'No se puja en una subasta cerrada');
    test_assert(in_array($subastaId, $ids($a->procesar('GET', ['vista' => 'HISTORIAL'])), true), 'Pasa al historial');
    test_assert(in_array($subastaId, $ids($a->procesar('GET', ['vista' => 'PARTICIPO'])), true), 'Aparece en las que A participó');
    test_same(1, sub_contar("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraentidad = 'SUBASTA' AND tbbitacoraaccion = 'FINALIZAR' AND tbbitacoraregistroidentificacionnumero = :id", ['id' => (string) $subastaId]), 'El cierre queda en la bitácora');

    // Fletes del ganador: los que cubren la finca, con el teléfono del transportista. Nadie más los pide así.
    $fletes = (new FletesController($db, $actorB))->procesar('GET', ['subastaId' => (string) $subastaId]);
    test_same(200, $fletes['status'], 'El ganador ve los fletes de la finca');
    $oferta = array_values(array_filter($fletes['body']['data']['ofertas'], static fn (array $o): bool => $o['ofertaId'] === $ofertaCerca))[0] ?? null;
    test_assert($oferta !== null && is_string($oferta['telefono'] ?? null), 'Con el teléfono del transportista');
    test_same(404, (new FletesController($db, $actorA))->procesar('GET', ['subastaId' => (string) $subastaId])['status'], 'Quien no ganó no los ve');

    // Cancelar sin pujas y cierre desierto: el animal vuelve al inventario.
    $cancelable = $v->procesar('POST', [], ['animalId' => $animal2] + $nueva)['body']['data']['subasta']['subastaId'];
    $subastaIds[] = $cancelable;
    test_same(404, $a->procesar('PATCH', [], ['subastaId' => $cancelable, 'accion' => 'CANCELAR'])['status'], 'Solo su dueño la cancela');
    test_same('CANCELADA', $v->procesar('PATCH', [], ['subastaId' => $cancelable, 'accion' => 'CANCELAR'])['body']['data']['subasta']['estado'], 'El dueño la cancela sin pujas');
    test_same('ACTIVO', $db->query("SELECT tbanimalestado FROM tbanimal WHERE tbanimalid = {$animal2}")->fetchColumn(), 'El animal vuelve al inventario');
    $desierta = $v->procesar('POST', [], ['animalId' => $animal3] + $nueva)['body']['data']['subasta']['subastaId'];
    $subastaIds[] = $desierta;
    $db->prepare('UPDATE tbsubasta SET tbsubastafechafin = :f WHERE tbsubastaid = :id')->execute(['f' => gmdate('Y-m-d H:i:s', time() - 5), 'id' => $desierta]);
    $avisosAntes = (new NotificacionesController($db, $actorV, $tiempoReal))->procesar('GET');
    test_same('DESIERTA', $v->procesar('GET', ['id' => (string) $desierta])['body']['data']['subasta']['estado'], 'Sin pujas queda desierta (la cierra hasta la campana)');
    test_same('ACTIVO', $db->query("SELECT tbanimalestado FROM tbanimal WHERE tbanimalid = {$animal3}")->fetchColumn(), 'El animal desierto vuelve al inventario');
    test_same('SUBASTA_DESIERTA', $avisosAntes['body']['data']['notificaciones'][0]['tipo'] ?? null, 'El vendedor recibe el aviso de desierta');

    // Campana: leer marca todo como leído.
    $campana = new NotificacionesController($db, $actorB, $tiempoReal);
    test_assert($campana->procesar('GET')['body']['data']['sinLeer'] >= 1, 'El ganador tiene avisos sin leer');
    test_same(0, $campana->procesar('PATCH', ['accion' => 'LEER'])['body']['data']['sinLeer'], 'Leer los marca todos');
    test_same(422, $campana->procesar('PATCH', ['accion' => 'BORRAR'])['status'], 'Acción desconocida es 422');
    test_same(401, (new NotificacionesController($db, ActorContext::noAutenticado(), $tiempoReal))->procesar('GET')['status'], 'Sin sesión es 401');

    // Panel admin: solo lectura, con nombres completos.
    $panel = new AdminSubastasController($db, $tiempoReal);
    test_assert(in_array($subastaId, array_column($panel->procesar('POST', ['consulta' => ['vista' => 'HISTORIAL']])['body']['data']['subastas'], 'subastaId'), true), 'El admin ve el historial');
    $detalleAdmin = $panel->procesar('POST', ['subastaId' => $subastaId])['body']['data']['subasta'];
    test_same('Subasta Prueba', $detalleAdmin['pujas'][0]['participante'] ?? null, 'El admin ve el nombre completo de quien pujó');
    test_same(405, $panel->procesar('PATCH', ['subastaId' => $subastaId])['status'], 'El admin no modifica subastas');
} finally {
    sub_cleanup($identificaciones, $vendedores, $animalIds, $subastaIds, $adminCorreo);
}

echo "OK subastas_test: abrir, pujar, admin sin participar, comentar, cerrar con ganador (venta y avisos), desierta, campana y fletes del ganador.\n";
