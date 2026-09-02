<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/TransportistaVehiculo.php';
require_once dirname(__DIR__) . '/Application/Model/Transportista.php';
require_once dirname(__DIR__) . '/Application/Controller/CompradorConsultaController.php';
require_once dirname(__DIR__) . '/Application/Controller/TransportistaController.php';

use Application\Controller\CompradorConsultaController;
use Application\Controller\TransportistaController;
use Application\Service\CompradorClasificacionService;

$db = test_db();
$id = test_document();
$persona = [
    'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $id],
    'nombre' => 'Persona Multi Capacidad',
    'telefono' => '+506 8888-7777',
    'correoElectronico' => 'persona.capacidades@example.test',
];
$compradores = new CompradorConsultaController($db);
$transportistas = new TransportistaController($db, test_token('persona-transportista'));
try {
    // Comprador dejó de ser una capacidad: es una clasificación del Productor y
    // no se administra a mano (DEC-DBREADY-008). La API solo lee.
    test_same(405, $compradores->procesar('POST', [])['status'],
        'No existe alta de comprador: la clasificación se deriva del comportamiento');
    $productor = test_create([
        'nombre' => $persona['nombre'],
        'telefono' => $persona['telefono'],
        'correoElectronico' => $persona['correoElectronico'],
    ], $id);
    $servicioClasificacion = new CompradorClasificacionService($db);
    $db->beginTransaction();
    $servicioClasificacion->activar((int) $productor['productorId'],
        CompradorClasificacionService::MOTIVO_MIGRACION);
    $db->commit();
    test_same(200, $compradores->procesar('GET', ['identificacionNumero' => $id])['status'],
        'La persona clasificada aparece como comprador en la consulta');
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
    test_same(200, $transportistas->procesar('PUT', [], $actualizada)['status'], 'Actualiza identidad desde transportista');
    test_same('+50622223333', $transportistas->procesar('GET', ['identificacionNumero'=>$id], [])['body']['data']['telefono'], 'Transportista refleja contacto compartido');

    test_same('ACTIVO', $transportistas->procesar('GET', ['identificacionNumero'=>$id], [])['body']['data']['estado'], 'Transportista permanece activo');
    $db->prepare('UPDATE tbpersona SET tbpersonaestado=0 WHERE tbpersonaidentificacionnumero=:id')->execute(['id'=>$id]);
    test_same('INACTIVO', $transportistas->procesar('GET', ['identificacionNumero'=>$id], [])['body']['data']['estado'], 'Estado global inactiva capacidad efectiva');
    test_same(409, $transportistas->procesar('DELETE', [], ['identificacionNumero'=>$id])['status'], 'Persona inactiva no desactiva perfil mediante endpoint');
    // La clasificación no se cierra por desactivar a la persona: sigue abierta
    // como historia, pero la consulta la muestra con la persona inactiva.
    test_same('INACTIVO', $compradores->procesar('GET', ['identificacionNumero'=>$id])['body']['data']['estado'],
        'La clasificación de una persona inactiva no se presenta como activa');
    echo "persona_capabilities_test: OK\n";
} finally {
    $consulta = $db->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $consulta->execute(['id' => $id]);
    $personaId = $consulta->fetchColumn();
    if ($personaId !== false) {
        $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id')
            ->execute(['id' => $id]);
        $db->prepare('DELETE cp FROM tbproductorclasificacionperiodo cp
            INNER JOIN tbproductor p ON p.tbproductorid = cp.tbproductorid
            WHERE p.tbpersonaid = :id')->execute(['id' => $personaId]);
        foreach (['tbtransportista'] as $tabla) {
            $db->prepare("DELETE FROM {$tabla} WHERE tbpersonaid = :id")->execute(['id' => $personaId]);
        }
        $db->prepare('UPDATE tbpersona SET tbpersonaestado = 1 WHERE tbpersonaid = :id')->execute(['id' => $personaId]);
        test_cleanup_productores([$id]);
        $db->prepare('DELETE FROM tbpersona WHERE tbpersonaid = :id')->execute(['id' => $personaId]);
    }
}
