<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\ProductorDireccion;
use Application\Model\Direccion;

$id = test_document();

try {
    // Fixture: producto con dirección vacía creada por POST (un periodo abierto).
    $productor = test_create([], $id);
    $productorId = (int) $productor['productorId'];
    $db = test_db();
    $modelo = new ProductorDireccion($db, new Direccion($db));
    $pid = $productorId;

    $periodos = static function () use ($db, $pid): int {
        return (int) $db->query("SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = {$pid}")->fetchColumn();
    };
    $abiertos = static function () use ($db, $pid): int {
        return (int) $db->query("SELECT COUNT(*) FROM tbproductordireccion
            WHERE tbproductorid = {$pid} AND tbproductordireccionfechafin IS NULL")->fetchColumn();
    };

    test_same(1, $periodos(), 'Estado de partida: un periodo de dirección consultable');
    test_same(1, $abiertos(), 'Estado de partida: exactamente un periodo abierto');

    // ============================================================
    // actualizar() como cierre+alta: dos cambios de residencia
    // ============================================================
    $modelo->ejecutarConBloqueoProducto(
        $productorId,
        fn () => $modelo->actualizar($productorId, test_direccion_payload(['provincia' => 'Limón'])),
    );
    test_same(2, $periodos(), 'Primer cambio cierra el periodo anterior y abre uno nuevo');
    test_same(1, $abiertos(), 'Tras el primer cambio solo hay un periodo abierto');
    test_same('Limón', $modelo->buscar($productorId)['provincia'], 'El periodo abierto es la nueva residencia');

    $modelo->ejecutarConBloqueoProducto(
        $productorId,
        fn () => $modelo->actualizar($productorId, test_direccion_payload(['provincia' => 'Heredia'])),
    );
    test_same(3, $periodos(), 'Dos cambios dejan tres periodos consultables');
    test_same(1, $abiertos(), 'Nunca más de un periodo abierto');
    test_same('Heredia', $modelo->buscar($productorId)['provincia'], 'El periodo abierto es el último cambio');

    // ============================================================
    // La primera dirección permanece consultable en el pasado
    // ============================================================
    $enlaces = $db->query("SELECT tbproductordireccionid FROM tbproductordireccion
        WHERE tbproductorid = {$pid} ORDER BY tbproductordireccionid ASC")->fetchAll();
    $db->prepare('UPDATE tbproductordireccion
            SET tbproductordireccionfechainicio = :inicio, tbproductordireccionfechafin = :fin
            WHERE tbproductordireccionid = :id')
        ->execute(['inicio' => '2020-01-01 00:00:00', 'fin' => '2021-01-01 00:00:00', 'id' => $enlaces[0]['tbproductordireccionid']]);
    $db->prepare('UPDATE tbproductordireccion
            SET tbproductordireccionfechainicio = :inicio, tbproductordireccionfechafin = :fin
            WHERE tbproductordireccionid = :id')
        ->execute(['inicio' => '2021-01-01 00:00:00', 'fin' => '2022-01-01 00:00:00', 'id' => $enlaces[1]['tbproductordireccionid']]);
    test_same('', $modelo->consultarVigenteEn($productorId, '2020-06-01 00:00:00')['provincia'],
        'En 2020 la residencia vigente era la dirección original, que permanece intacta');
    test_same('Heredia', $modelo->consultarVigenteEn($productorId, '2999-01-01 00:00:00')['provincia'],
        'Una fecha futura resuelve al periodo abierto');

    // ============================================================
    // ROLLBACK: un fallo entre cierre e INSERT deja todo como estaba
    // ============================================================
    $fallo = false;
    $db->beginTransaction();
    try {
        $modelo->ejecutarConBloqueoProducto(
            $productorId,
            function () use ($modelo, $productorId): void {
                $modelo->actualizar($productorId, test_direccion_payload(['provincia' => 'Puntarenas']));
                throw new \RuntimeException('fallo forzado entre cierre e INSERT');
            },
        );
    } catch (\RuntimeException $excepcion) {
        $fallo = str_contains($excepcion->getMessage(), 'fallo forzado');
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    test_same(true, $fallo, 'La excepción forzada debe propagarse y deshacer la transacción');
    test_same(3, $periodos(), 'ROLLBACK impide que el cambio fallido deje un cuarto periodo');
    test_same('Heredia', $modelo->buscar($productorId)['provincia'],
        'ROLLBACK deja la dirección abierta sin el cambio fallido');
    test_same(1, $abiertos(), 'Sigue habiendo exactamente un periodo abierto');
} finally {
    test_cleanup_productores([$id]);
}

echo "OK direccion_historico_test: cierre+alta por productor, tres periodos, primera "
    . "dirección conservada en el pasado y ROLLBACK a mitad deja el estado original.\n";
