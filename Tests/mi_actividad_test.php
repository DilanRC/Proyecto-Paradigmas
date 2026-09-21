<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Auth\ActorContext;
use Application\Controller\MiActividadController;

$ids = [];
try {
    $creado = test_create([
        'direccionPrincipal' => test_direccion_payload(),
        'fincas' => [['nombre' => 'Finca Mi Actividad']],
    ]);
    $ids[] = $creado['identificacionNumero'];

    $actor = ActorContext::personaAutenticada(
        (int) $creado['personaId'],
        'supabase-test-' . test_token('subject'),
        (string) $creado['correoElectronico'],
        'authenticated',
    );
    $controlador = new MiActividadController(test_db(), $actor, test_token('mi-actividad'));

    $consulta = $controlador->procesar('GET');
    test_same(200, $consulta['status'], 'Mi actividad debe consultar la Persona autenticada');
    test_same($creado['identificacionNumero'], $consulta['body']['data']['persona']['identificacionNumero'],
        'La identidad debe derivarse del actor y no de un parámetro del navegador');
    test_same('ACTIVO', $consulta['body']['data']['capacidades']['PRODUCTOR']['estado'],
        'El Productor recién creado debe aparecer activo');
    test_same('NO_CONFIGURADO', $consulta['body']['data']['capacidades']['TRANSPORTISTA']['estado'],
        'Una capacidad ausente debe distinguirse de INACTIVO');
    test_same('NO_CONFIGURADO', $consulta['body']['data']['capacidades']['COMPRADOR']['estado'],
        'Comprador ausente debe distinguirse de INACTIVO');
    test_same([['nombre' => 'Finca Mi Actividad']], $consulta['body']['data']['capacidades']['PRODUCTOR']['fincas'],
        'El perfil público debe obtener las fincas desde MySQL');

    $anonimo = new MiActividadController(test_db(), ActorContext::noAutenticado(), test_token('anonimo'));
    test_same(401, $anonimo->procesar('GET')['status'],
        'Mi actividad no debe aceptar una sesión anónima');

    $actorSinPersona = ActorContext::personaAutenticada(
        (int) $creado['personaId'] + 999999,
        'supabase-test-inexistente',
        'inexistente@example.test',
        'authenticated',
    );
    $sinPersona = new MiActividadController(test_db(), $actorSinPersona, test_token('sin-persona'));
    test_same(409, $sinPersona->procesar('GET')['status'],
        'Un JWT verificado sin Persona vinculada no debe administrar actividades');

    $intentoIdor = $controlador->procesar('PATCH', [
        'contexto' => 'PRODUCTOR',
        'activo' => false,
        'identificacionNumero' => test_document(),
    ]);
    test_same(422, $intentoIdor['status'],
        'El proceso público debe rechazar una identificación objetivo enviada por el cliente');
    $despuesIdor = $controlador->procesar('GET');
    test_same('ACTIVO', $despuesIdor['body']['data']['capacidades']['PRODUCTOR']['estado'],
        'Un intento de elegir otra identidad no debe cambiar el Productor autenticado');

    $desactivado = $controlador->procesar('PATCH', [
        'contexto' => 'PRODUCTOR',
        'activo' => false,
    ]);
    test_same(200, $desactivado['status'], 'La Persona debe poder desactivar su contexto Productor');
    test_same('INACTIVO', $desactivado['body']['data']['estado'],
        'La transición pública debe usar la política de estado existente');
    $consultaInactivo = $controlador->procesar('GET');
    test_same('INACTIVO', $consultaInactivo['body']['data']['capacidades']['PRODUCTOR']['estado'],
        'GET debe reflejar el estado persistido y no un cache de navegador');

    $reactivado = $controlador->procesar('PATCH', [
        'contexto' => 'PRODUCTOR',
        'activo' => true,
    ]);
    test_same(200, $reactivado['status'], 'La Persona debe poder reactivar el mismo contexto Productor');
    test_same('ACTIVO', $reactivado['body']['data']['estado'],
        'Reactivar no debe crear una nueva Persona ni un nuevo contexto');

    $comprador = $controlador->procesar('PATCH', [
        'contexto' => 'COMPRADOR',
        'activo' => true,
    ]);
    test_same(409, $comprador['status'],
        'Comprador no debe recibir un escritor genérico mientras su proceso de negocio esté pendiente');
    test_same(false, $comprador['body']['data']['escrituraDisponible'],
        'El contrato debe declarar explícitamente que Comprador sigue solo lectura');

    $transportista = $controlador->procesar('PATCH', [
        'contexto' => 'TRANSPORTISTA',
        'activo' => true,
    ]);
    test_same(409, $transportista['status'],
        'Una capacidad no configurada no debe aparecer mágicamente por PATCH de estado');
    test_same('NO_CONFIGURADO', $transportista['body']['data']['estado'],
        'El servidor debe pedir primero el proceso de configuración correspondiente');
} finally {
    test_cleanup_productores($ids);
}

echo "OK mi_actividad_test: actor autenticado, anti-IDOR y estados públicos.\n";
