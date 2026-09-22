<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/MiVehiculosController.php';
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';
require_once dirname(__DIR__) . '/Application/Model/Vehiculo.php';

use Application\Auth\ActorContext;
use Application\Controller\MiVehiculosController;
use Application\Controller\RegistroPublicoController;

$identificaciones = [];
$vehiculoIds = [];

function mi_vehiculo_payload(string $identificacion, string $correo): array
{
    return [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $identificacion,
            'nombres' => 'Transportista', 'apellidos' => 'Autogestión',
            'alias' => 'Prueba vehículo', 'telefono' => '+506 8888-1111',
            'correoElectronico' => $correo,
        ],
        'capacidades' => ['TRANSPORTISTA'],
        'fincas' => [],
    ];
}

function mi_vehiculo_registro(string $identificacion, string $correo): array
{
    $actor = ActorContext::usuarioVerificado(null, 'supabase-' . test_token('mi-vehiculo'), $correo, 'authenticated');
    $respuesta = (new RegistroPublicoController(test_db(), $actor, test_token('registro-vehiculo')))
        ->procesar('POST', mi_vehiculo_payload($identificacion, $correo));
    test_same(201, $respuesta['status'], 'La fixture Transportista debe registrarse');
    $consulta = test_db()->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacion');
    $consulta->execute(['identificacion' => $identificacion]);
    $personaId = (int) $consulta->fetchColumn();
    test_assert($personaId > 0, 'La fixture debe crear Persona');
    return [$personaId, ActorContext::personaAutenticada($personaId, 'supabase-' . test_token('actor'), $correo, 'authenticated')];
}

function mi_vehiculos_cleanup(array $identificaciones, array $vehiculoIds): void
{
    $db = test_db();
    $idsVehiculo = array_values(array_unique(array_filter(array_map('intval', $vehiculoIds))));
    if ($idsVehiculo !== []) {
        $marks = implode(',', array_fill(0, count($idsVehiculo), '?'));
        $db->prepare("DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid IN ({$marks})")->execute($idsVehiculo);
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute(array_map('strval', $idsVehiculo));
        $db->prepare("DELETE FROM tbvehiculo WHERE tbvehiculoid IN ({$marks})")->execute($idsVehiculo);
    }
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE b FROM tbbitacora b WHERE b.tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($ids);
    $db->prepare("DELETE tv FROM tbtransportistavehiculo tv INNER JOIN tbtransportista t ON t.tbtransportistaid = tv.tbtransportistaid INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($ids);
    $db->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($ids);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($ids);
}

try {
    $idA = test_document();
    $correoA = strtolower(test_token('transportista-a')) . '@example.test';
    $identificaciones[] = $idA;
    [$personaA, $actorA] = mi_vehiculo_registro($idA, $correoA);
    $controllerA = new MiVehiculosController(test_db(), $actorA, test_token('mi-vehiculos'));

    test_same(200, $controllerA->procesar('GET')['status'], 'La persona debe consultar sus vehículos');
    $creado = $controllerA->procesar('POST', ['placa' => 'AUTO-A-' . strtoupper(bin2hex(random_bytes(2))), 'vin' => 'VIN-A-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión A']);
    test_same(201, $creado['status'], 'Debe crear un vehículo propio');
    $vehiculoIds[] = $creado['body']['data']['vehiculo']['vehiculoId'];
    test_same(1, count($creado['body']['data']['vehiculos']), 'El vehículo nuevo debe quedar enlazado al dueño autenticado');

    $camposAjeno = $controllerA->procesar('POST', ['placa' => 'X', 'vin' => 'Y', 'modelo' => 'Z', 'transportistaId' => 999]);
    test_same(422, $camposAjeno['status'], 'No se puede elegir dueño por JSON');

    $idB = test_document();
    $correoB = strtolower(test_token('transportista-b')) . '@example.test';
    $identificaciones[] = $idB;
    [$personaB, $actorB] = mi_vehiculo_registro($idB, $correoB);
    $controllerB = new MiVehiculosController(test_db(), $actorB, test_token('mi-vehiculos-b'));
    $idOtro = $controllerB->procesar('POST', ['placa' => 'AUTO-B-' . strtoupper(bin2hex(random_bytes(2))), 'vin' => 'VIN-B-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión B']);
    test_same(201, $idOtro['status'], 'La segunda persona debe tener su propio vehículo');
    $vehiculoIds[] = $idOtro['body']['data']['vehiculo']['vehiculoId'];
    $idOtroNumero = $idOtro['body']['data']['vehiculo']['vehiculoId'];

    test_same(404, $controllerA->procesar('PUT', ['vehiculoId' => $idOtroNumero, 'placa' => 'NO', 'vin' => 'NO', 'modelo' => 'NO'])['status'], 'Una persona no puede actualizar el vehículo de otra');
    test_same(404, $controllerA->procesar('DELETE', ['vehiculoId' => $idOtroNumero])['status'], 'Una persona no puede desactivar el vehículo de otra');
    test_same(422, $controllerA->procesar('POST', ['placa' => 'A', 'vin' => 'B', 'modelo' => 'C', 'personaId' => $personaB])['status'], 'personaId no forma parte del contrato');

    $desactivado = $controllerA->procesar('DELETE', ['vehiculoId' => $vehiculoIds[0]]);
    test_same(200, $desactivado['status'], 'El dueño puede desactivar su vehículo');
    test_same('INACTIVO', $desactivado['body']['data']['vehiculo']['estado'], 'La desactivación es lógica');
    $reactivado = $controllerA->procesar('PATCH', ['vehiculoId' => $vehiculoIds[0]]);
    test_same(200, $reactivado['status'], 'El dueño puede reactivar su vehículo');
    test_same('ACTIVO', $reactivado['body']['data']['vehiculo']['estado'], 'La reactivación conserva la misma fila');

    $bitacora = test_db()->prepare('SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraentidad = :entidad AND tbbitacoraorigen = :origen AND tbbitacoraregistroidentificacionnumero = :id');
    $bitacora->execute(['entidad' => 'VEHICULO', 'origen' => 'API_MI_VEHICULOS', 'id' => (string) $vehiculoIds[0]]);
    test_assert((int) $bitacora->fetchColumn() >= 3, 'Las escrituras propias deben quedar auditadas');
} finally {
    mi_vehiculos_cleanup($identificaciones, $vehiculoIds);
}

echo "OK mi_vehiculos_test: ownership por ActorContext, anti-IDOR, estados y bitácora.\n";
