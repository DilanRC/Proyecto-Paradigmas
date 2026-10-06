<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/AdminBitacoraController.php';

use Application\Auth\ActorContext;
use Application\Controller\AdminBitacoraController;
use Application\Model\Bitacora;

/**
 * P3-4: visor de la bitácora. Cubre: filtro por entidad, por persona (nombre e
 * identificación, sin distinguir mayúsculas), por fecha, orden del más reciente,
 * datos JSON decodificados y validación de filtros.
 */

$db = test_db();
$identificaciones = [];
$entidad = 'PRUEBA_VISOR_' . strtoupper(bin2hex(random_bytes(3)));

try {
    $persona = test_create_completo(['fincas' => [['nombre' => 'Finca Visor']]]);
    $identificaciones[] = $persona['identificacionNumero'];
    $buscar = $db->prepare('SELECT tbpersonaid, tbpersonanombre FROM tbpersona WHERE tbpersonaidentificacionnumero = :i');
    $buscar->execute(['i' => $persona['identificacionNumero']]);
    $fila = $buscar->fetch();
    $actor = ActorContext::usuarioVerificado((int) $fila['tbpersonaid'], 'visor-' . test_token('s'), 'visor@example.test', null);
    $bitacora = new Bitacora($db, $actor);
    $bitacora->registrar('CREAR', 'VISOR:1', null, ['nombre' => 'Primero', 'precio' => 100], test_token('v1'), $entidad, 'PRUEBA');
    $bitacora->registrar('ACTUALIZAR', 'VISOR:1', ['nombre' => 'Primero'], ['nombre' => 'Segundo'], test_token('v2'), $entidad, 'PRUEBA');

    $visor = new AdminBitacoraController($db);
    $porEntidad = $visor->procesar('GET', ['entidad' => strtolower($entidad)]);
    test_same(200, $porEntidad['status'], 'Se consulta la bitácora por entidad');
    test_same(2, $porEntidad['body']['data']['total'], 'El filtro de entidad trae solo sus eventos');
    $eventos = $porEntidad['body']['data']['eventos'];
    test_same('ACTUALIZAR', $eventos[0]['accion'], 'El más reciente va primero');
    test_same(['nombre' => 'Segundo'], $eventos[0]['datosNuevos'], 'Los datos JSON llegan decodificados');
    test_same(['nombre' => 'Primero'], $eventos[0]['datosAnteriores'], 'También los datos anteriores');
    test_same(null, $eventos[1]['datosAnteriores'], 'Un alta no tiene datos anteriores');
    test_same($fila['tbpersonanombre'], $eventos[0]['actor']['nombre'], 'Se muestra el nombre de quien hizo el cambio');
    test_assert(in_array($entidad, $porEntidad['body']['data']['entidades'], true), 'La lista de entidades del filtro incluye la nueva');

    $nombre = mb_strtoupper(mb_substr($fila['tbpersonanombre'], 0, 6, 'UTF-8'), 'UTF-8');
    test_same(2, $visor->procesar('GET', ['entidad' => $entidad, 'q' => $nombre])['body']['data']['total'],
        'Se filtra por el nombre de la persona sin distinguir mayúsculas');
    test_same(2, $visor->procesar('GET', ['entidad' => $entidad, 'q' => $persona['identificacionNumero']])['body']['data']['total'],
        'Se filtra por la identificación de la persona');
    test_same(0, $visor->procesar('GET', ['entidad' => $entidad, 'q' => 'nadie-' . test_token('x')])['body']['data']['total'],
        'Un texto que no coincide no trae nada');

    $hoy = gmdate('Y-m-d');
    test_same(2, $visor->procesar('GET', ['entidad' => $entidad, 'desde' => $hoy, 'hasta' => $hoy])['body']['data']['total'],
        'Desde y hasta el mismo día incluye todo ese día (UTC)');
    test_same(0, $visor->procesar('GET', ['entidad' => $entidad, 'hasta' => gmdate('Y-m-d', time() - 86400)])['body']['data']['total'],
        'Hasta ayer no incluye lo de hoy');
    test_same(1, count($visor->procesar('GET', ['entidad' => $entidad, 'tamanoPagina' => 1, 'pagina' => 2])['body']['data']['eventos']),
        'La paginación funciona');

    foreach ([
        [['desde' => '2026-02-31'], 'fecha inexistente'],
        [['desde' => '05/10/2026'], 'formato de fecha'],
        [['desde' => '2026-10-05', 'hasta' => '2026-10-01'], 'rango invertido'],
        [['entidad' => "PERSONA' OR 1=1"], 'entidad con símbolos'],
        [['tamanoPagina' => 500], 'página demasiado grande'],
    ] as [$filtros, $motivo]) {
        test_same(422, $visor->procesar('GET', $filtros)['status'], "Filtro inválido: {$motivo}");
    }
    test_same(405, $visor->procesar('DELETE', [])['status'], 'El visor es solo lectura');

    echo "OK api_admin_bitacora_test: filtros por entidad, persona y fecha, orden, JSON y validación.\n";
} finally {
    $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraentidad = :e')->execute(['e' => $entidad]);
    test_cleanup_productores($identificaciones);
}
