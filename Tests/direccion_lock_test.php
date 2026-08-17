<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\Direccion;

$db = test_new_db();
$direccion = new Direccion($db);
$ids = [];
$payload = test_direccion_payload(['provincia' => 'Heredia']);

try {
    $rechazoSinLock = false;
    try {
        $direccion->crearConBloqueoExistente($payload);
    } catch (LogicException $excepcion) {
        $rechazoSinLock = true;
        test_assert(str_contains($excepcion->getMessage(), 'debe poseer el lock'),
            'La creación sin lock debe explicar la precondición incumplida.');
    }
    test_same(true, $rechazoSinLock,
        'crearConBloqueoExistente debe impedir una creación si la conexión no posee el lock.');

    $db->beginTransaction();
    $rechazoTransaccionExterna = false;
    try {
        $direccion->crearConBloqueo($payload);
    } catch (LogicException $excepcion) {
        $rechazoTransaccionExterna = true;
        test_assert(str_contains($excepcion->getMessage(), 'transacción ya abierta'),
            'crearConBloqueo debe explicar por qué no puede liberar el lock antes del COMMIT exterior.');
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    test_same(true, $rechazoTransaccionExterna,
        'crearConBloqueo debe rechazar una transacción exterior que pueda liberar el lock prematuramente.');

    $idConLockExistente = $direccion->ejecutarConBloqueoAlta(
        fn (): int => $direccion->crearConBloqueoExistente($payload)
    );
    $ids[] = $idConLockExistente;
    test_same('Heredia', $direccion->buscar($idConLockExistente)['provincia'],
        'La creación con lock ya adquirido debe persistir la dirección.');

    $idAutoprotegido = $direccion->crearConBloqueo(test_direccion_payload(['provincia' => 'Cartago']));
    $ids[] = $idAutoprotegido;
    test_assert($idConLockExistente !== $idAutoprotegido,
        'Dos creaciones protegidas deben conservar IDs distintos.');
} finally {
    foreach ($ids as $id) {
        $db->prepare('DELETE FROM tbdireccion WHERE tbdireccionid = :id')->execute(['id' => $id]);
    }
}

echo "OK direccion_lock_test: no existe ruta pública de creación que pueda ignorar el lock requerido.\n";
