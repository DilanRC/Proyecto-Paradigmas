<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Application\Model\AnimalComercial;

$db = test_db();
$model = new AnimalComercial($db);
$id = -random_int(10000000, 1000000000);
$db->beginTransaction();
try {
    $db->prepare("INSERT INTO tbpersona
        (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo, tbpersonanombre,
         tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
        VALUES (?, ?, 'PASAPORTE', 'Observacion portable', '+50688887777', 'portable@example.test', 1)")
        ->execute([$id, test_token('observacion_portable')]);
    $db->prepare('INSERT INTO tbproductor (tbproductorid, tbpersonaid) VALUES (?, ?)')->execute([$id, $id]);
    $db->prepare("INSERT INTO tbfinca (tbfincaid, tbproductorid, tbfincanombre, tbfincaestado) VALUES (?, ?, 'Fixture', 1)")
        ->execute([$id, $id]);
    for ($offset = 0; $offset < 2; $offset++) {
        $animalId = $id + $offset;
        $db->prepare("INSERT INTO tbanimal (tbanimalid, tbproductorid, tbanimalfecharegistroensistema, tbanimalorigenregistro)
            VALUES (?, ?, '2024-01-01 00:00:00', 'PRUEBA')")->execute([$animalId, $id]);
        $db->prepare("INSERT INTO tbanimalpublicacion
            (tbanimalpublicacionid, tbanimalid, tbproductorvendedorid, tbfincaid, tbanimalpublicacionfecha, tbanimalpublicacionorigen)
            VALUES (?, ?, ?, ?, '2024-01-01 00:00:00', 'PRUEBA')")->execute([$animalId, $animalId, $id, $id]);
        $db->prepare("INSERT INTO tbanimalpublicacionestadoperiodo
            (tbanimalpublicacionestadoperiodoid, tbanimalpublicacionid, tbanimalpublicacionestadoperiodoestado,
             tbanimalpublicacionestadoperiodofechainicio, tbanimalpublicacionestadoperiodoorigen)
            VALUES (?, ?, 'ACTIVO', '2024-01-01 00:00:00', 'PRUEBA')")->execute([$animalId, $animalId]);
    }
    $insert = $db->prepare("INSERT INTO tbanimalproduccionsalud
        (tbanimalproduccionsaludid, tbanimalid, tbanimalproduccionsaludfecha, tbanimalproduccionsaludorigen,
         tbanimalproduccionsaludedadmeses, tbanimalproduccionsaludpeso, tbanimalproduccionsaludproposito,
         tbanimalproduccionsaludestadoreproductivo)
        VALUES (?, ?, ?, 'PRUEBA', ?, ?, ?, ?)");
    // Un id mayor con fecha antigua debe perder; a igual fecha gana el id mayor.
    $insert->execute([$id + 30, $id, '2020-01-01 00:00:00', 9, 99, 'VIEJA', 'VIEJO']);
    $insert->execute([$id + 10, $id, '2024-01-01 00:00:00', 20, 200, 'EMPATE', 'EMPATE']);
    $insert->execute([$id + 20, $id, '2024-01-01 00:00:00', 21, 210, 'VIGENTE', 'VIGENTE']);

    $inventory = $model->inventarioPropio($id);
    test_same(2, count($inventory), 'Inventario no multiplica animales por observaciones');
    $byAnimal = array_column($inventory, null, 'animalId');
    test_same(21, $byAnimal[$id]['edadMeses'], 'Inventario desempata por id');
    test_same(210.0, $byAnimal[$id]['peso'], 'Inventario mantiene peso de la misma fila');
    test_same('VIGENTE', $byAnimal[$id]['proposito'], 'Inventario mantiene proposito de la misma fila');
    test_same(null, $byAnimal[$id + 1]['peso'], 'Inventario conserva animales sin observaciones');

    $list = $model->listarPublicaciones('', 'ACTIVO', 1, 25, $id);
    test_same(2, $list['total'], 'Total no multiplica publicaciones por observaciones');
    test_same(2, count($list['publicaciones']), 'Listado conserva una fila por publicacion');
    $byAnimal = array_column($list['publicaciones'], null, 'animalId');
    test_same(21, $byAnimal[$id]['animal']['edadMeses'], 'Publicaciones desempatan por id');
    test_same(210.0, $byAnimal[$id]['animal']['peso'], 'Publicaciones mantienen peso de la misma fila');
    test_same('VIGENTE', $byAnimal[$id]['animal']['proposito'], 'Publicaciones mantienen proposito de la misma fila');
    test_same('VIGENTE', $byAnimal[$id]['animal']['estadoReproductivo'], 'Estado reproductivo viene de la misma fila');
    test_same(null, $byAnimal[$id + 1]['animal']['peso'], 'Publicaciones conservan animales sin observaciones');
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
echo "OK latest_observation_portability_test: fecha/id, misma fila, sin multiplicacion y sin observaciones.\n";
