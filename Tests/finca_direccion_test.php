<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\Direccion;
use Application\Model\FincaDireccion;
use Application\Model\ProductorFinca;

$id = test_document();
$nombreFinca = 'Finca Prueba ' . strtoupper(bin2hex(random_bytes(3)));

try {
    // ============================================================
    // Fixture: productor activo con una finca activa
    // ============================================================
    $productor = test_create(['fincas' => [['nombre' => $nombreFinca]]], $id);
    $productorId = $productor['productorId'];
    $db = test_db();
    $fincas = new ProductorFinca($db);
    $direccion = new Direccion($db);
    $fincaDireccion = new FincaDireccion($db, $direccion);

    // ============================================================
    // ProductorFinca::buscarIdActivo
    // ============================================================
    $fincaId = $fincas->buscarIdActivo($productorId, $nombreFinca);
    test_assert($fincaId !== null, 'buscarIdActivo debe resolver la finca activa recién creada');

    test_same(null, $fincas->buscarIdActivo($productorId, 'Finca Que No Existe'),
        'buscarIdActivo debe devolver null si el nombre no coincide con ninguna finca activa');

    $fincas->sincronizar($productorId, []); // desactiva todas
    test_same(null, $fincas->buscarIdActivo($productorId, $nombreFinca),
        'Una finca desactivada no debe resolverse como activa');

    $fincas->sincronizar($productorId, [$nombreFinca]); // reactiva (mismo tbfincaid)
    $fincaIdReactivada = $fincas->buscarIdActivo($productorId, $nombreFinca);
    test_same($fincaId, $fincaIdReactivada, 'Reactivar debe conservar el mismo tbfincaid, no crear uno nuevo');

    // ============================================================
    // Direccion (unitario) — dirección suelta, sin enlace todavía.
    // crear() ya NO se autobloquea: exige ejecutarConBloqueoAlta() explícito
    // para que el lock cubra el cálculo de MAX(id)+1 hasta después del uso.
    // ============================================================
    $direccionSueltaId = $direccion->ejecutarConBloqueoAlta(
        fn (): int => $direccion->crear(test_direccion_payload(['provincia' => 'Cartago']))
    );
    test_assert($direccionSueltaId > 0, 'Direccion::crear debe devolver un id positivo');
    test_same('Cartago', $direccion->buscar($direccionSueltaId)['provincia'], 'Direccion::buscar debe reflejar lo insertado');
    $direccion->actualizar($direccionSueltaId, test_direccion_payload(['provincia' => 'Alajuela']));
    test_same('Alajuela', $direccion->buscar($direccionSueltaId)['provincia'], 'Direccion::actualizar debe persistir el cambio');
    test_same(null, $direccion->buscar(999999999), 'Direccion::buscar con id inexistente debe devolver null');
    $db->prepare('DELETE FROM tbdireccion WHERE tbdireccionid = :id')->execute(['id' => $direccionSueltaId]);

    // ============================================================
    // FincaDireccion (unitario) — crear()/actualizar()/vaciar() ya delegan
    // en Direccion, cuyo lock ahora vive dentro de
    // FincaDireccion::ejecutarConBloqueoAlta(); por eso crear() se envuelve así.
    // ============================================================
   $fincaDireccion->ejecutarConBloqueoAlta(function () use ($fincaDireccion, $fincaId): void {
        $fincaDireccion->crear($fincaId, test_direccion_payload(['provincia' => 'Puntarenas']));
    });
    test_same('Puntarenas', $fincaDireccion->buscar($fincaId)['provincia'],
        'FincaDireccion::crear + buscar debe reflejar el valor insertado');

    $creacionDuplicada = false;
    try {
        $fincaDireccion->ejecutarConBloqueoAlta(function () use ($fincaDireccion, $fincaId): void {
            $fincaDireccion->crear($fincaId, test_direccion_payload());
        });
    } catch (RuntimeException $excepcion) {
        $creacionDuplicada = true;
        test_same('La finca ya tiene una dirección registrada; use actualizar.', $excepcion->getMessage(),
            'crear() sobre una finca que ya tiene enlace debe rechazarse con este mensaje');
    }
    test_assert($creacionDuplicada, 'FincaDireccion::crear no debe permitir un segundo enlace para la misma finca');

    $fincaDireccion->actualizar($fincaId, test_direccion_payload(['provincia' => 'Guanacaste']));
    test_same('Guanacaste', $fincaDireccion->buscar($fincaId)['provincia'], 'FincaDireccion::actualizar debe persistir el cambio');

    $fincaDireccion->vaciar($fincaId);
    $vacia = $fincaDireccion->buscar($fincaId);
    test_same('', $vacia['provincia'], 'FincaDireccion::vaciar debe dejar provincia en cadena vacía');
    test_same(null, $vacia['pueblo'], 'FincaDireccion::vaciar debe dejar pueblo en NULL');

    test_same(null, $fincaDireccion->buscar(999999999), 'FincaDireccion::buscar con fincaId inexistente debe devolver null');
    $sinEnlaceActualizar = false;
    try {
        $fincaDireccion->actualizar(999999999, test_direccion_payload());
    } catch (RuntimeException $excepcion) {
        $sinEnlaceActualizar = true;
        test_same('La finca no conserva exactamente una dirección.', $excepcion->getMessage(),
            'actualizar() sin enlace debe rechazarse con este mensaje');
    }
    test_assert($sinEnlaceActualizar, 'FincaDireccion::actualizar sobre una finca sin enlace debe lanzar excepción');

    // Reset: quitamos el enlace para probar POST (crear) desde el controller.
    $db->prepare('DELETE FROM tbfincadireccion WHERE tbfincaid = :id')->execute(['id' => $fincaId]);

    // ============================================================
    // FincaController::procesarDireccion — GET
    // ============================================================
    $sinParametros = test_finca_controller()->procesarDireccion('GET', [], []);
    test_same(422, $sinParametros['status'], 'GET sin identificacionNumero/nombreFinca debe rechazarse');

    $productorInexistente = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => 'NOEXISTE999', 'nombreFinca' => $nombreFinca,
    ], []);
    test_same(404, $productorInexistente['status'], 'GET con productor inexistente debe responder 404');

    $fincaInexistente = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $id, 'nombreFinca' => 'Finca Que No Existe',
    ], []);
    test_same(404, $fincaInexistente['status'], 'GET con finca inexistente/inactiva debe responder 404');

    $sinDireccion = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
    ], []);
    test_same(404, $sinDireccion['status'], 'GET de finca sin dirección registrada debe responder 404');

    // ============================================================
    // POST — crearDireccion()
    // ============================================================
    $postCampoDesconocido = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(), 'campoInventado' => 'x',
    ]);
    test_same(422, $postCampoDesconocido['status'], 'POST con campo desconocido en el cuerpo debe rechazarse');

    $postDireccionInvalida = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        'direccionFinca' => ['provincia' => '', 'canton' => 'X', 'distrito' => 'X'],
    ]);
    test_same(422, $postDireccionInvalida['status'], 'POST con provincia vacía debe rechazarse');

    $postProductorInexistente = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => 'NOEXISTE999', 'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(),
    ]);
    test_same(404, $postProductorInexistente['status'], 'POST con productor inexistente debe responder 404');

    $postFincaInexistente = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id, 'nombreFinca' => 'Finca Que No Existe',
        'direccionFinca' => test_direccion_payload(),
    ]);
    test_same(404, $postFincaInexistente['status'], 'POST con finca inexistente/inactiva debe responder 404');

    $postValido = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(['provincia' => 'San José']),
    ]);
    test_same(201, $postValido['status'], 'POST válido debe responder 201');
    test_same('San José', $postValido['body']['data']['direccionFinca']['provincia'], 'POST debe persistir el valor enviado');

    $postDuplicado = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(),
    ]);
    test_same(409, $postDuplicado['status'], 'POST sobre una finca que ya tiene dirección debe responder 409');

    // ============================================================
    // PUT — actualizarDireccion()
    // ============================================================
    $putValido = test_finca_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(['provincia' => 'Heredia']),
    ]);
    test_same(200, $putValido['status'], 'PUT válido debe responder 200');
    test_same('Heredia', $putValido['body']['data']['direccionFinca']['provincia'], 'PUT debe persistir el nuevo valor');

    // ============================================================
    // Productor inactivo — 409 en POST/PUT/DELETE de dirección de finca
    // ============================================================
    test_controller()->procesar('DELETE', [], ['identificacionNumero' => $id]); // desactivar productor
    $putInactivo = test_finca_controller()->procesarDireccion('PUT', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        'direccionFinca' => test_direccion_payload(),
    ]);
    test_same(409, $putInactivo['status'], 'PUT sobre finca de productor inactivo debe responder 409');
    test_controller()->procesar('PATCH', [], ['identificacionNumero' => $id]); // reactivar productor

    // ============================================================
    // Finca inactiva — 404, y la dirección debe sobrevivir al ciclo desactivar/reactivar
    // ============================================================
    $fincas->sincronizar($productorId, []); // desactiva todas las fincas del productor
    $getFincaInactiva = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
    ], []);
    test_same(404, $getFincaInactiva['status'], 'GET sobre finca inactiva debe responder 404');

    $fincas->sincronizar($productorId, [$nombreFinca]); // reactiva (mismo tbfincaid)
    $getFincaReactivada = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
    ], []);
    test_same(200, $getFincaReactivada['status'], 'GET tras reactivar la finca debe volver a responder 200');
    test_same('Heredia', $getFincaReactivada['body']['data']['direccionFinca']['provincia'],
        'La dirección debe sobrevivir intacta al ciclo desactivar/reactivar (mismo tbfincaid)');

    // ============================================================
    // DELETE — vaciarDireccion()
    // ============================================================
    $delValido = test_finca_controller()->procesarDireccion('DELETE', [], [
        'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
    ]);
    test_same(200, $delValido['status'], 'DELETE válido debe responder 200');
    test_same('', $delValido['body']['data']['direccionFinca']['provincia'], 'DELETE debe vaciar provincia');
    test_same(null, $delValido['body']['data']['direccionFinca']['pueblo'], 'DELETE debe vaciar pueblo (NULL)');

    $conteoEnlace = $db->prepare('SELECT COUNT(*) FROM tbfincadireccion WHERE tbfincaid = :id');
    $conteoEnlace->execute(['id' => $fincaId]);
    test_same(1, (int) $conteoEnlace->fetchColumn(),
        'DELETE no debe borrar físicamente el enlace; preserva la invariante 1:1 (vacía, no ausente)');

    $delSinFila = test_finca_controller()->procesarDireccion('DELETE', [], [
        'identificacionNumero' => 'NOEXISTE999', 'nombreFinca' => $nombreFinca,
    ]);
    test_same(404, $delSinFila['status'], 'DELETE con productor inexistente debe responder 404');

    // ============================================================
    // Método no permitido
    // ============================================================
    $metodoInvalido = test_finca_controller()->procesarDireccion('TRACE', [], []);
    test_same(405, $metodoInvalido['status'], 'Método no permitido debe responder 405');

    // ============================================================
    // buscar() — no debe ocultar más de un enlace por finca
    // ============================================================
    $direccionDuplicadaId = $direccion->ejecutarConBloqueoAlta(
        fn (): int => $direccion->crear(test_direccion_payload(['provincia' => 'Duplicada']))
    );
    $enlaceDuplicadoId = (int) $db->query(
        'SELECT COALESCE(MAX(tbfincadireccionid), 0) + 1 FROM tbfincadireccion'
    )->fetchColumn();
    $db->prepare('INSERT INTO tbfincadireccion (tbfincadireccionid, tbfincaid, tbdireccionid)
        VALUES (:enlaceId, :fincaId, :direccionId)')
        ->execute(['enlaceId' => $enlaceDuplicadoId, 'fincaId' => $fincaId, 'direccionId' => $direccionDuplicadaId]);

    $lanzoExcepcion = false;
    try {
        test_finca_controller()->procesarDireccion('GET', [
            'identificacionNumero' => $id, 'nombreFinca' => $nombreFinca,
        ], []);
    } catch (RuntimeException $excepcion) {
        $lanzoExcepcion = true;
        test_same(
            'La finca tiene más de una dirección registrada; revise la integridad de los datos.',
            $excepcion->getMessage(),
            'Con un duplicado, FincaDireccion::buscar() debe detectarlo'
        );
    }
    test_assert($lanzoExcepcion, 'Un duplicado de enlace debe hacer explotar la consulta, no devolver la primera fila');

    $db->prepare('DELETE FROM tbfincadireccion WHERE tbfincadireccionid = :enlaceId')->execute(['enlaceId' => $enlaceDuplicadoId]);
    $db->prepare('DELETE FROM tbdireccion WHERE tbdireccionid = :direccionId')->execute(['direccionId' => $direccionDuplicadaId]);
} finally {
    test_cleanup_productores([$id]);
}

echo "OK finca_direccion_test: buscarIdActivo, Direccion, FincaDireccion y "
    . "FincaController::procesarDireccion (GET/POST/PUT/DELETE) — casos válidos, productor/finca "
    . "inexistente o inactiva, validaciones y detección de duplicados.\n";