<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$id = test_document();
$requestPrefix = test_token('audit');
try {
    $productor = test_controller($requestPrefix . '-crear')->procesar('POST', [], test_payload($id))['body']['data'];
    $actualizar = test_payload($id, [
        'telefono' => '+506 2777-1111',
        'direccionPrincipal' => test_direccion_payload(),
    ]);
    $actualizar['identificacionNumeroOriginal'] = $productor['identificacionNumero'];
    test_controller($requestPrefix . '-actualizar')->procesar('PUT', [], $actualizar);
    test_controller($requestPrefix . '-desactivar')->procesar('DELETE', [], ['identificacionNumero' => $id]);
    test_controller($requestPrefix . '-reactivar')->procesar('PATCH', [], ['identificacionNumero' => $id]);

    $sentencia = test_db()->prepare('SELECT * FROM tbbitacora
        WHERE tbbitacoraregistroidentificacionnumero = :id ORDER BY tbbitacoraid');
    $sentencia->execute(['id' => $productor['identificacionNumero']]);
    $eventos = $sentencia->fetchAll();
    test_same(['CREAR', 'ACTUALIZAR', 'DESACTIVAR', 'REACTIVAR'], array_column($eventos, 'tbbitacoraaccion'), 'Ciclo auditado');
    foreach ($eventos as $evento) {
        test_same('PRODUCTOR', $evento['tbbitacoraentidad'], 'Entidad simplificada');
        test_same('NO_AUTENTICADO', $evento['tbbitacoraactortipo'], 'Actor real disponible');
        test_same(null, $evento['tbbitacorausuarioid'], 'Sin usuario ficticio');
        test_same('API_PRODUCTORES', $evento['tbbitacoraorigen'], 'Origen técnico');
    }
} finally {
    test_cleanup_productores([$id]);
}

echo "OK audit_test: bitácora textual, cuatro acciones, JSON y actor no autenticado.\n";
