<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/MiFincasController.php';

use Application\Auth\ActorContext;
use Application\Controller\MiFincasController;

$identificaciones = [];
$fincaIds = [];

try {
    $creado = test_create([
        'direccionPrincipal' => test_direccion_payload(),
        'fincas' => [['nombre' => 'Finca Autogestión'] ],
    ]);
    $identificaciones[] = $creado['identificacionNumero'];
    $consultaPersona = test_db()->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacion');
    $consultaPersona->execute(['identificacion' => $creado['identificacionNumero']]);
    $personaId = (int) $consultaPersona->fetchColumn();
    $actor = ActorContext::personaAutenticada($personaId, 'supabase-finca-' . test_token('actor'), $creado['correoElectronico'], 'authenticated');
    $controller = new MiFincasController(test_db(), $actor, test_token('mi-fincas'));

    $inicio = $controller->procesar('GET');
    test_same(200, $inicio['status'], 'La cuenta debe consultar sus fincas');
    test_same(1, count($inicio['body']['data']['fincas']), 'GET debe devolver la finca propia');
    $fincaIds[] = $inicio['body']['data']['fincas'][0]['fincaId'];

    $nueva = $controller->procesar('POST', [
        'nombreFinca' => 'Finca Con Mapa',
        'direccionFinca' => [
            'provincia' => 'Heredia', 'canton' => 'Barva', 'distrito' => 'Barva',
            'pueblo' => 'Centro', 'senas' => 'Frente al parque', 'latitud' => 10.0347, 'longitud' => -84.0907,
        ],
    ]);
    test_same(201, $nueva['status'], 'Debe agregar una finca desde Mi actividad');
    $nuevaId = (int) $nueva['body']['data']['finca']['fincaId'];
    $fincaIds[] = $nuevaId;
    test_same('Finca Con Mapa', $nueva['body']['data']['finca']['nombre'], 'La nueva finca debe conservar su nombre');
    test_same('10.0347000', $nueva['body']['data']['finca']['direccion']['latitud'], 'La dirección debe conservar el punto validado');

    $editada = $controller->procesar('PUT', ['fincaId' => $nuevaId, 'nombreFinca' => 'Finca Editada']);
    test_same(200, $editada['status'], 'Debe editar una finca propia');
    test_same('Finca Editada', $editada['body']['data']['finca']['nombre'], 'PUT debe cambiar el nombre');
    test_same('10.0347000', $editada['body']['data']['finca']['direccion']['latitud'], 'PUT sin dirección debe conservarla');

    test_same(422, $controller->procesar('POST', ['nombreFinca' => 'No', 'productorId' => 999])['status'], 'El productor objetivo no puede venir del JSON');
    test_same(422, $controller->procesar('PUT', ['fincaId' => $nuevaId, 'nombreFinca' => 'No', 'personaId' => 999])['status'], 'La persona objetivo no puede venir del JSON');

    $desactivada = $controller->procesar('DELETE', ['fincaId' => $nuevaId]);
    test_same(200, $desactivada['status'], 'Debe desactivar lógicamente la finca');
    test_same('INACTIVO', $desactivada['body']['data']['finca']['estado'], 'La desactivación debe conservar la fila');
    $reactivada = $controller->procesar('PATCH', ['fincaId' => $nuevaId]);
    test_same(200, $reactivada['status'], 'Debe reactivar la finca propia');

    $otro = test_create(['fincas' => [['nombre' => 'Finca De Otra Persona']]]);
    $identificaciones[] = $otro['identificacionNumero'];
    $personaBStatement = test_db()->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacion');
    $personaBStatement->execute(['identificacion' => $otro['identificacionNumero']]);
    $actorB = ActorContext::personaAutenticada((int) $personaBStatement->fetchColumn(), 'supabase-finca-b-' . test_token('actor'), $otro['correoElectronico'], 'authenticated');
    $fincaAjena = (new MiFincasController(test_db(), $actorB, test_token('mi-fincas-b')))->procesar('GET')['body']['data']['fincas'][0]['fincaId'];
    test_same(404, $controller->procesar('PUT', ['fincaId' => $fincaAjena, 'nombreFinca' => 'Intento IDOR'])['status'], 'Una cuenta no puede editar la finca de otra');
    test_same(404, $controller->procesar('DELETE', ['fincaId' => $fincaAjena])['status'], 'Una cuenta no puede desactivar la finca de otra');

    $bitacora = test_db()->prepare('SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraentidad = :entidad AND tbbitacoraorigen = :origen AND tbbitacoraregistroidentificacionnumero = :id');
    $bitacora->execute(['entidad' => 'FINCA', 'origen' => 'API_MI_FINCAS', 'id' => (string) $nuevaId]);
    test_assert((int) $bitacora->fetchColumn() >= 4, 'Las operaciones propias deben quedar auditadas');
} finally {
    $db = test_db();
    if ($fincaIds !== []) {
        $marks = implode(',', array_fill(0, count($fincaIds), '?'));
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute(array_map('strval', $fincaIds));
    }
    test_cleanup_productores($identificaciones);
}

echo "OK mi_fincas_test: ownership por ActorContext, CRUD de fincas, dirección, estados y bitácora.\n";
