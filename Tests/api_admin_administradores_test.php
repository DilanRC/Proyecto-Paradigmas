<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/Administrador.php';
require_once dirname(__DIR__) . '/Application/Controller/AdminAdministradorController.php';

use Application\Auth\ActorContext;
use Application\Controller\AdminAdministradorController;

/**
 * P3-4: gestionar administradores desde el panel. Cubre: agregar (normaliza el
 * correo), duplicado 409, reactivar en vez de duplicar, no desactivarse a sí
 * mismo, no dejar el panel sin administradores activos y bitácora con el
 * correo de quien hizo el cambio.
 */

$db = test_db();
$yo = strtolower(test_token('admin-yo')) . '@example.test';
$otro = strtolower(test_token('admin-otro')) . '@example.test';
$correos = [$yo, $otro];
$estadosOriginales = $db->query('SELECT tbadministradorid, tbadministradorestado FROM tbadministrador')->fetchAll();

try {
    $db->prepare('INSERT INTO tbadministrador (tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado)
        SELECT COALESCE(MAX(tbadministradorid), 0) + 1, :correo, 1 FROM tbadministrador')->execute(['correo' => $yo]);
    $actor = ActorContext::usuarioVerificado(null, 'test-admin-' . test_token('sub'), $yo, 'authenticated');
    $admins = new AdminAdministradorController($db, test_token('admins'), $actor);

    $lista = $admins->procesar('GET', []);
    test_same(200, $lista['status'], 'Se listan los administradores');
    $propio = array_values(array_filter($lista['body']['data']['administradores'], fn ($a) => $a['correoElectronico'] === $yo))[0] ?? null;
    test_same(true, $propio['esUsted'] ?? null, 'La lista marca la cuenta propia');

    test_same(422, $admins->procesar('POST', ['correoElectronico' => 'no-es-correo'])['status'], 'Un correo inválido es 422');
    test_same(422, $admins->procesar('POST', ['correoElectronico' => $otro, 'estado' => 1])['status'], 'Campos de más son 422');
    $agregado = $admins->procesar('POST', ['correoElectronico' => '  ' . strtoupper($otro) . ' ']);
    test_same(201, $agregado['status'], 'Se agrega un administrador');
    test_same($otro, $agregado['body']['data']['administrador']['correoElectronico'], 'El correo se guarda normalizado');
    $otroId = $agregado['body']['data']['administrador']['administradorId'];
    test_same(409, $admins->procesar('POST', ['correoElectronico' => $otro])['status'], 'Un correo que ya es administrador es 409');

    $desactivado = $admins->procesar('PATCH', ['administradorId' => $otroId, 'activo' => false]);
    test_same('INACTIVO', $desactivado['body']['data']['administrador']['estado'], 'Se desactiva a otro administrador');
    $reactivado = $admins->procesar('POST', ['correoElectronico' => $otro]);
    test_same(200, $reactivado['status'], 'Agregar un correo inactivo lo reactiva');
    test_same($otroId, $reactivado['body']['data']['administrador']['administradorId'], 'Se reactiva la misma fila, sin duplicar');
    $conteo = $db->prepare('SELECT COUNT(*) FROM tbadministrador WHERE LOWER(tbadministradorcorreoelectronico) = :c');
    $conteo->execute(['c' => $otro]);
    test_same(1, (int) $conteo->fetchColumn(), 'El correo no queda duplicado');

    $propioId = $propio['administradorId'];
    test_same(409, $admins->procesar('PATCH', ['administradorId' => $propioId, 'activo' => false])['status'],
        'Nadie puede desactivar su propio acceso');
    test_same(422, $admins->procesar('PATCH', ['administradorId' => $otroId, 'activo' => 'no'])['status'], 'activo debe ser booleano');
    test_same(404, $admins->procesar('PATCH', ['administradorId' => 999999, 'activo' => false])['status'], 'Un id inexistente es 404');

    // Último activo: se deja solo a $otro activo (además de mí, que no puedo desactivarme).
    $db->prepare('UPDATE tbadministrador SET tbadministradorestado = 0 WHERE LOWER(tbadministradorcorreoelectronico) NOT IN (:a, :b)')
        ->execute(['a' => $yo, 'b' => $otro]);
    $db->prepare('UPDATE tbadministrador SET tbadministradorestado = 0 WHERE LOWER(tbadministradorcorreoelectronico) = :a')->execute(['a' => $yo]);
    test_same(409, $admins->procesar('PATCH', ['administradorId' => $otroId, 'activo' => false])['status'],
        'No se puede desactivar al último administrador activo');

    $bitacora = $db->prepare("SELECT tbbitacoraaccion, tbbitacoradatosnuevos FROM tbbitacora
        WHERE tbbitacoraentidad = 'ADMINISTRADOR' AND tbbitacoraregistroidentificacionnumero = :r ORDER BY tbbitacoraid");
    $bitacora->execute(['r' => 'ADMINISTRADOR:' . $otroId]);
    $eventos = $bitacora->fetchAll();
    test_same(['CREAR', 'DESACTIVAR', 'REACTIVAR'], array_column($eventos, 'tbbitacoraaccion'), 'Cada cambio queda en la bitácora');
    test_assert(str_contains((string) $eventos[0]['tbbitacoradatosnuevos'], $yo), 'La bitácora guarda el correo de quien hizo el cambio');

    echo "OK api_admin_administradores_test: agregar, duplicado, reactivar, no desactivarse, último activo y bitácora.\n";
} finally {
    foreach ($estadosOriginales as $fila) {
        $db->prepare('UPDATE tbadministrador SET tbadministradorestado = :e WHERE tbadministradorid = :id')
            ->execute(['e' => $fila['tbadministradorestado'], 'id' => $fila['tbadministradorid']]);
    }
    $marcas = implode(',', array_fill(0, count($correos), '?'));
    $ids = $db->prepare("SELECT tbadministradorid FROM tbadministrador WHERE LOWER(tbadministradorcorreoelectronico) IN ({$marcas})");
    $ids->execute($correos);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'ADMINISTRADOR' AND tbbitacoraregistroidentificacionnumero = :r")
            ->execute(['r' => 'ADMINISTRADOR:' . $id]);
    }
    $db->prepare("DELETE FROM tbadministrador WHERE LOWER(tbadministradorcorreoelectronico) IN ({$marcas})")->execute($correos);
}
