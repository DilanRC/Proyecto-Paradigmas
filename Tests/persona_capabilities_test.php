<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/Comprador.php';
require_once dirname(__DIR__) . '/Application/Model/TransportistaVehiculo.php';
require_once dirname(__DIR__) . '/Application/Model/Transportista.php';
require_once dirname(__DIR__) . '/Application/Controller/CompradorController.php';
require_once dirname(__DIR__) . '/Application/Controller/TransportistaController.php';

use Application\Controller\CompradorController;
use Application\Controller\TransportistaController;

$db = test_db();
$id = test_document();
$persona = [
    'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $id],
    'nombre' => 'Persona Multi Capacidad',
    'telefono' => '+506 8888-7777',
    'correoElectronico' => 'persona.capacidades@example.test',
];
$compradores = new CompradorController($db, test_token('persona-comprador'));
$transportistas = new TransportistaController($db, test_token('persona-transportista'));
try {
    test_same(201, $compradores->procesar('POST', [], $persona)['status'], 'Crea capacidad comprador');
    test_same(409, $compradores->procesar('POST', [], $persona)['status'], 'Rechaza capacidad comprador duplicada');
    test_same(409, $transportistas->procesar('POST', [], array_replace($persona, [
        'nombre' => 'Nombre personal incompatible',
    ]))['status'], 'Rechaza reutilizar identificación con datos personales distintos');
    test_same(201, $transportistas->procesar('POST', [], $persona)['status'], 'Reutiliza persona al crear transportista');
    $consulta = $db->prepare('SELECT COUNT(*) FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $consulta->execute(['id' => $id]);
    test_same(1, (int) $consulta->fetchColumn(), 'Una identificación conserva una persona');

    $actualizada = $persona;
    $actualizada['telefono'] = '+506 2222-3333';
    $actualizada['identificacionNumeroOriginal'] = $id;
    test_same(200, $compradores->procesar('PUT', [], $actualizada)['status'], 'Actualiza identidad desde comprador');
    test_same('+50622223333', $transportistas->procesar('GET', ['identificacionNumero'=>$id], [])['body']['data']['telefono'], 'Transportista refleja contacto compartido');

    test_same(200, $compradores->procesar('DELETE', [], ['identificacionNumero'=>$id])['status'], 'Desactiva solo comprador');
    test_same('ACTIVO', $transportistas->procesar('GET', ['identificacionNumero'=>$id], [])['body']['data']['estado'], 'Transportista permanece activo');
    $db->prepare('UPDATE tbpersona SET tbpersonaestado=0 WHERE tbpersonaidentificacionnumero=:id')->execute(['id'=>$id]);
    test_same('INACTIVO', $transportistas->procesar('GET', ['identificacionNumero'=>$id], [])['body']['data']['estado'], 'Estado global inactiva capacidad efectiva');
    test_same(409, $transportistas->procesar('DELETE', [], ['identificacionNumero'=>$id])['status'], 'Persona inactiva no desactiva perfil mediante endpoint');
    test_same(409, $compradores->procesar('PATCH', [], ['identificacionNumero'=>$id])['status'], 'Persona inactiva no reactiva perfil');
    echo "persona_capabilities_test: OK\n";
} finally {
    $consulta = $db->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $consulta->execute(['id' => $id]);
    $personaId = $consulta->fetchColumn();
    if ($personaId !== false) {
        $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id')
            ->execute(['id' => $id]);
        foreach (['tbcomprador', 'tbtransportista'] as $tabla) {
            $db->prepare("DELETE FROM {$tabla} WHERE tbpersonaid = :id")->execute(['id' => $personaId]);
        }
        $db->prepare('DELETE FROM tbpersona WHERE tbpersonaid = :id')->execute(['id' => $personaId]);
    }
}
