<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * El id de tbbitacora sale de MAX()+1 bajo NamedLock. Si un controlador escribe
 * en la bitácora dentro de una transacción sin envolverla con
 * Bitacora::ejecutarConBloqueoAlta, el bloqueo se suelta antes del COMMIT y otra
 * conexión calcula el mismo id (demostrado: dos eventos con id 56). FOR UPDATE no
 * lo evita en Postgres (producción), por eso el bloqueo debe durar hasta el COMMIT.
 *
 * Los nueve controladores antiguos que faltaban se envolvieron en octubre de 2026:
 * PENDIENTES queda vacía y esta prueba impide que aparezcan casos nuevos.
 */
const PENDIENTES = [];

$sinEnvolver = [];
foreach (glob(dirname(__DIR__) . '/Application/Controller/*.php') as $archivo) {
    $codigo = file_get_contents($archivo);
    $escribeEnTransaccion = str_contains($codigo, 'bitacora->registrar(')
        && (str_contains($codigo, 'beginTransaction') || str_contains($codigo, 'transaccion('));
    if ($escribeEnTransaccion && !str_contains($codigo, 'bitacora->ejecutarConBloqueoAlta(')) {
        $sinEnvolver[] = basename($archivo);
    }
}
sort($sinEnvolver);

$nuevos = array_values(array_diff($sinEnvolver, PENDIENTES));
test_same([], $nuevos, 'Estos controladores escriben la bitácora en una transacción sin Bitacora::ejecutarConBloqueoAlta');
$arreglados = array_values(array_diff(PENDIENTES, $sinEnvolver));
test_same([], $arreglados, 'Estos controladores ya envuelven la bitácora: sácalos de PENDIENTES');

// Los de la revisión de P1-1, P1-4 y P1-6 (y el histórico de teléfono de P1-4).
foreach (['PublicacionInteraccionController.php', 'MiPerfilController.php', 'AdminPublicacionController.php'] as $controlador) {
    test_assert(!in_array($controlador, $sinEnvolver, true), "{$controlador} envuelve la bitácora");
}
test_assert(str_contains(file_get_contents(dirname(__DIR__) . '/Application/Controller/MiPerfilController.php'), 'ejecutarConBloqueoTelefono('),
    'MiPerfil mantiene el bloqueo del histórico de teléfono hasta el COMMIT');

echo "OK bitacora_bloqueo_test: todos los controladores envuelven la bitácora hasta el COMMIT.\n";
