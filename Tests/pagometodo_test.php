<?php

declare(strict_types=1);

use Application\Controller\PagoMetodoController;
use Application\Model\PagoMetodo;
use Configuration\Database;

require __DIR__ . '/bootstrap.php';

// ─────────────────────────────────────────────────────────────
// Cargar dependencias de PagoMetodo (Modelo + Controlador)
// ─────────────────────────────────────────────────────────────
$testRoot = dirname(__DIR__);
foreach (['NamedLock', 'Bitacora', 'PagoMetodo'] as $modelo) {
    require_once $testRoot . "/Application/Model/{$modelo}.php";
}
require_once $testRoot . '/Application/Controller/PagoMetodoController.php';

// ─────────────────────────────────────────────────────────────
// Helpers específicos para PagoMetodo
// ─────────────────────────────────────────────────────────────

function pagometodo_controller(?string $requestId = null): PagoMetodoController
{
    return new PagoMetodoController(test_db(), $requestId ?? test_token('request'));
}

function pagometodo_payload(array $overrides = []): array
{
    $base = [
        'nombre' => 'Método de Pago Prueba',
        'descripcion' => 'Descripción ficticia generada por Tests.',
        'activo' => true,
    ];
    return array_replace($base, $overrides);
}

function pagometodo_create(array $overrides = []): array
{
    $response = pagometodo_controller()->procesar('POST', [], pagometodo_payload($overrides));
    test_same(201, $response['status'], 'La fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La fixture debe ser exitosa.');
    test_assert(is_int($response['body']['data']['pagoMetodoId'] ?? null), 'La API debe devolver pagoMetodoId entero.');
    return $response['body']['data'];
}

function pagometodo_cleanup(array $ids): void
{
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        // Limpiar bitácora relacionada
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marcadores})")->execute(array_map('strval', $ids));
        // Limpiar métodos de pago creados por el test
        $db->prepare("DELETE FROM tbpagometodo WHERE tbpagometodoid IN ({$marcadores})")->execute($ids);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}

// ─────────────────────────────────────────────────────────────
// 1. Test de Estructura de Base de Datos
// ─────────────────────────────────────────────────────────────

$db = test_db();

// Verificar que la tabla tbpagometodo existe y tiene las columnas correctas
$columnasStatement = $db->prepare('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName 
    ORDER BY ORDINAL_POSITION');
$columnasStatement->execute(['tableName' => 'tbpagometodo']);
$columnas = $columnasStatement->fetchAll();

$columnasEsperadas = [
    ['COLUMN_NAME' => 'tbpagometodoid', 'DATA_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'COLUMN_KEY' => ''],
    ['COLUMN_NAME' => 'tbpagometodonombre', 'DATA_TYPE' => 'varchar', 'IS_NULLABLE' => 'NO', 'COLUMN_KEY' => ''],
    ['COLUMN_NAME' => 'tbpagometododescripcion', 'DATA_TYPE' => 'varchar', 'IS_NULLABLE' => 'NO', 'COLUMN_KEY' => ''],
    ['COLUMN_NAME' => 'tbpagometodoactivo', 'DATA_TYPE' => 'tinyint', 'IS_NULLABLE' => 'NO', 'COLUMN_KEY' => ''],
];

test_same($columnasEsperadas, $columnas, 'tbpagometodo debe tener exactamente 4 columnas con los tipos correctos');

// Verificar datos iniciales (solo "Efectivo")
$datosIniciales = $db->prepare('SELECT tbpagometodoid, tbpagometodonombre, tbpagometododescripcion, tbpagometodoactivo 
    FROM tbpagometodo ORDER BY tbpagometodoid');
$datosIniciales->execute();
$filasIniciales = $datosIniciales->fetchAll();

test_same(1, count($filasIniciales), 'Debe existir exactamente 1 método de pago inicial');
test_same([
    'tbpagometodoid' => 1,
    'tbpagometodonombre' => 'Efectivo',
    'tbpagometododescripcion' => 'Pago realizado en efectivo',
    'tbpagometodoactivo' => 1,
], $filasIniciales[0], 'El dato inicial debe ser Efectivo activo');

echo "OK pagometodo_test (estructura): tabla tbpagometodo con 4 columnas y dato inicial Efectivo.\n";

// ─────────────────────────────────────────────────────────────
// 2. Test de CRUD Completo
// ─────────────────────────────────────────────────────────────

$idsCreados = [];

try {
    // --- CREATE ---
    $creado1 = pagometodo_create(['nombre' => 'Tarjeta de Crédito', 'descripcion' => 'Pago con tarjeta']);
    $idsCreados[] = $creado1['pagoMetodoId'];
    
    $creado2 = pagometodo_create(['nombre' => 'Transferencia', 'descripcion' => 'Transferencia bancaria', 'activo' => false]);
    $idsCreados[] = $creado2['pagoMetodoId'];
    
    // Verificar que los IDs son consecutivos
    test_same($creado1['pagoMetodoId'] + 1, $creado2['pagoMetodoId'], 
        'Los IDs deben ser consecutivos bajo bloqueo NamedLock');
    
    // Verificar que el primero está activo y el segundo inactivo
    test_same(true, $creado1['activo'], 'El primer método debe estar activo');
    test_same(false, $creado2['activo'], 'El segundo método debe estar inactivo');
    test_same('ACTIVO', $creado1['estado'], 'El estado debe ser ACTIVO');
    test_same('INACTIVO', $creado2['estado'], 'El estado debe ser INACTIVO');

    // --- GET (listar todos) ---
    $listaResponse = pagometodo_controller()->procesar('GET', [], []);
    test_same(200, $listaResponse['status'], 'GET listar debe responder 200');
    test_assert(($listaResponse['body']['success'] ?? false) === true, 'GET listar debe ser exitoso');
    test_assert(is_array($listaResponse['body']['data']['pagoMetodos'] ?? null), 'Debe devolver array de pagoMetodos');
    test_assert($listaResponse['body']['data']['total'] >= 3, 'Debe haber al menos 3 métodos (1 inicial + 2 creados)');

    // --- GET (consultar por ID) ---
    $consultaResponse = pagometodo_controller()->procesar('GET', ['id' => (string) $creado1['pagoMetodoId']], []);
    test_same(200, $consultaResponse['status'], 'GET por ID debe responder 200');
    test_same($creado1['pagoMetodoId'], $consultaResponse['body']['data']['pagoMetodoId'], 'Debe devolver el método correcto');
    test_same('Tarjeta de Crédito', $consultaResponse['body']['data']['nombre'], 'El nombre debe coincidir');

    // --- GET (ID no existente) ---
    $noExisteResponse = pagometodo_controller()->procesar('GET', ['id' => '999999'], []);
    test_same(404, $noExisteResponse['status'], 'GET por ID no existente debe responder 404');

    // --- PUT (actualizar) ---
    $actualizarPayload = [
        'id' => $creado1['pagoMetodoId'],
        'nombre' => 'Tarjeta de Crédito/Débito',
        'descripcion' => 'Pago con tarjeta actualizado',
    ];
    $actualizarResponse = pagometodo_controller()->procesar('PUT', [], $actualizarPayload);
    test_same(200, $actualizarResponse['status'], 'PUT debe responder 200');
    test_same('Tarjeta de Crédito/Débito', $actualizarResponse['body']['data']['nombre'], 'El nombre debe actualizarse');
    test_same('Pago con tarjeta actualizado', $actualizarResponse['body']['data']['descripcion'], 'La descripción debe actualizarse');

    // --- PUT (método inactivo no se puede actualizar) ---
    $actualizarInactivoPayload = [
        'id' => $creado2['pagoMetodoId'],
        'nombre' => 'Transferencia Actualizada',
        'descripcion' => 'No debería funcionar',
    ];
    $actualizarInactivoResponse = pagometodo_controller()->procesar('PUT', [], $actualizarInactivoPayload);
    test_same(409, $actualizarInactivoResponse['status'], 'PUT en método inactivo debe responder 409');

    // --- DELETE (desactivar) ---
    $desactivarPayload = ['id' => $creado1['pagoMetodoId']];
    $desactivarResponse = pagometodo_controller()->procesar('DELETE', [], $desactivarPayload);
    test_same(200, $desactivarResponse['status'], 'DELETE debe responder 200');
    test_same(false, $desactivarResponse['body']['data']['activo'], 'El método debe quedar inactivo');
    test_same('INACTIVO', $desactivarResponse['body']['data']['estado'], 'El estado debe ser INACTIVO');

    // --- PATCH (reactivar) ---
    $reactivarPayload = ['id' => $creado2['pagoMetodoId']];
    $reactivarResponse = pagometodo_controller()->procesar('PATCH', [], $reactivarPayload);
    test_same(200, $reactivarResponse['status'], 'PATCH debe responder 200');
    test_same(true, $reactivarResponse['body']['data']['activo'], 'El método debe quedar activo');
    test_same('ACTIVO', $reactivarResponse['body']['data']['estado'], 'El estado debe ser ACTIVO');

    // --- Validaciones de campos ---
    // Nombre vacío
    $nombreVacioResponse = pagometodo_controller()->procesar('POST', [], pagometodo_payload(['nombre' => '']));
    test_same(422, $nombreVacioResponse['status'], 'Nombre vacío debe responder 422');
    test_assert(isset($nombreVacioResponse['body']['errors']['nombre']), 'Debe haber error en campo nombre');

    // Descripción muy larga
    $descLargaResponse = pagometodo_controller()->procesar('POST', [], pagometodo_payload(['descripcion' => str_repeat('a', 251)]));
    test_same(422, $descLargaResponse['status'], 'Descripción > 250 chars debe responder 422');
    test_assert(isset($descLargaResponse['body']['errors']['descripcion']), 'Debe haber error en campo descripcion');

    // Campo desconocido
    $campoDesconocidoResponse = pagometodo_controller()->procesar('POST', [], pagometodo_payload(['campoInvalido' => 'valor']));
    test_same(422, $campoDesconocidoResponse['status'], 'Campo desconocido debe responder 422');
    test_assert(isset($campoDesconocidoResponse['body']['errors']['campoInvalido']), 'Debe haber error de campo no permitido');

    // --- Filtros de listado ---
    // Filtrar por estado ACTIVO
    $filtroActivoResponse = pagometodo_controller()->procesar('GET', ['estado' => 'ACTIVO'], []);
    test_same(200, $filtroActivoResponse['status'], 'Filtro ACTIVO debe responder 200');
    foreach ($filtroActivoResponse['body']['data']['pagoMetodos'] as $metodo) {
        test_same('ACTIVO', $metodo['estado'], 'Todos los métodos filtrados deben estar ACTIVO');
    }

    // Filtrar por búsqueda
    $filtroBusquedaResponse = pagometodo_controller()->procesar('GET', ['q' => 'Efectivo'], []);
    test_same(200, $filtroBusquedaResponse['status'], 'Filtro búsqueda debe responder 200');
    test_assert($filtroBusquedaResponse['body']['data']['total'] >= 1, 'Debe encontrar al menos Efectivo');

    // --- Bitácora ---
    // Verificar que se registraron las acciones en la bitácora
    $bitacoraCount = $db->prepare('SELECT COUNT(*) FROM tbbitacora 
        WHERE tbbitacoraentidad = :entidad 
        AND tbbitacoraregistroidentificacionnumero IN (:id1, :id2)');
    $bitacoraCount->execute([
        'entidad' => 'PAGOMETODO', 
        'id1' => (string) $creado1['pagoMetodoId'], 
        'id2' => (string) $creado2['pagoMetodoId']
    ]);
    test_assert((int) $bitacoraCount->fetchColumn() >= 4, 'Debe haber al menos 4 registros en bitácora (CREAR x2, ACTUALIZAR, DESACTIVAR, REACTIVAR)');

    echo "OK pagometodo_test (CRUD): crear, listar, consultar, actualizar, desactivar, reactivar y validaciones.\n";

} finally {
    // Limpieza automática
    pagometodo_cleanup($idsCreados);
}

// ─────────────────────────────────────────────────────────────
// 3. Test de Concurrencia (NamedLock)
// ─────────────────────────────────────────────────────────────

$a = test_new_db();
$b = test_new_db();
$lockName = 'tindercows_pagometodo_alta';

$modelA = new PagoMetodo($a);
$modelB = new PagoMetodo($b);

function pagometodo_try_lock(PDO $db, string $name): int
{
    $statement = $db->prepare('SELECT GET_LOCK(:name, 0)');
    $statement->execute(['name' => $name]);
    return (int) $statement->fetchColumn();
}

function pagometodo_release_lock(PDO $db, string $name): void
{
    $statement = $db->prepare('SELECT RELEASE_LOCK(:name)');
    $statement->execute(['name' => $name]);
}

$idsConcurrencia = [];

try {
    // Verificar visibilidad de métodos
    $reflection = new ReflectionMethod(PagoMetodo::class, 'ejecutarConBloqueoAlta');
    test_same(true, $reflection->isPublic(), 'ejecutarConBloqueoAlta debe ser público');
    
    $reflectionPrivate = new ReflectionMethod(PagoMetodo::class, 'adquirirBloqueoAlta');
    test_same(true, $reflectionPrivate->isPrivate(), 'adquirirBloqueoAlta debe ser privado');

    // Test de bloqueo concurrente
    $idA = $modelA->ejecutarConBloqueoAlta(function () use ($a, $b, $modelA, $lockName): int {
        $a->beginTransaction();
        try {
            // Crear directamente en DB para probar el lock
            $sentencia = $a->prepare('INSERT INTO tbpagometodo (tbpagometodoid, tbpagometodonombre, tbpagometododescripcion, tbpagometodoactivo) 
                VALUES ((SELECT COALESCE(MAX(tbpagometodoid), 0) + 1 FROM tbpagometodo pm2), :nombre, :desc, 1)');
            $sentencia->execute(['nombre' => 'Concurrente A', 'desc' => 'Test concurrencia A']);
            
            $ultimoId = $a->prepare('SELECT MAX(tbpagometodoid) FROM tbpagometodo');
            $ultimoId->execute();
            $id = (int) $ultimoId->fetchColumn();

            // B no debe poder adquirir el lock
            test_same(0, pagometodo_try_lock($b, $lockName), 'B no obtiene el bloqueo antes del COMMIT de A');
            
            // B no debe ver la inserción sin confirmar
            $countB = $b->prepare('SELECT COUNT(*) FROM tbpagometodo WHERE tbpagometodoid = :id');
            $countB->execute(['id' => $id]);
            test_same(0, (int) $countB->fetchColumn(), 'B no observa la inserción sin confirmar de A');

            $a->commit();

            // B ahora debe ver la inserción
            $countB->execute(['id' => $id]);
            test_same(1, (int) $countB->fetchColumn(), 'B observa la inserción después del COMMIT de A');

            // A conserva el lock hasta salir del wrapper
            test_same(0, pagometodo_try_lock($b, $lockName), 'A conserva el bloqueo después del COMMIT');

            return $id;
        } catch (Throwable $exception) {
            if ($a->inTransaction()) $a->rollBack();
            throw $exception;
        }
    });
    $idsConcurrencia[] = $idA;

    // Después de liberar, B puede adquirir el lock
    test_same(1, pagometodo_try_lock($b, $lockName), 'B obtiene el bloqueo después de que A lo libera');
    pagometodo_release_lock($b, $lockName);

    // Test de consecutivo de IDs
    $idB = $modelB->ejecutarConBloqueoAlta(function () use ($b, $modelB): int {
        $b->beginTransaction();
        try {
            $sentencia = $b->prepare('INSERT INTO tbpagometodo (tbpagometodoid, tbpagometodonombre, tbpagometododescripcion, tbpagometodoactivo) 
                VALUES ((SELECT COALESCE(MAX(tbpagometodoid), 0) + 1 FROM tbpagometodo pm2), :nombre, :desc, 1)');
            $sentencia->execute(['nombre' => 'Concurrente B', 'desc' => 'Test concurrencia B']);
            
            $ultimoId = $b->prepare('SELECT MAX(tbpagometodoid) FROM tbpagometodo');
            $ultimoId->execute();
            $id = (int) $ultimoId->fetchColumn();
            
            $b->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($b->inTransaction()) $b->rollBack();
            throw $exception;
        }
    });
    $idsConcurrencia[] = $idB;

    test_same($idA + 1, $idB, 'B calcula tbpagometodoid como el ID confirmado de A + 1');

    // Test de excepción dentro del wrapper
    $tokenExcepcion = test_token('lock_exception');
    try {
        $modelA->ejecutarConBloqueoAlta(function () use ($b, $lockName, $tokenExcepcion): never {
            test_same(0, pagometodo_try_lock($b, $lockName), 'El bloqueo está retenido dentro del callable');
            throw new RuntimeException($tokenExcepcion);
        });
        throw new RuntimeException('El wrapper debía propagar la excepción del callable.');
    } catch (RuntimeException $exception) {
        test_same($tokenExcepcion, $exception->getMessage(), 'El wrapper conserva la excepción original');
    }
    test_same(1, pagometodo_try_lock($b, $lockName), 'El finally libera el bloqueo ante una excepción');
    pagometodo_release_lock($b, $lockName);

    echo "OK pagometodo_test (concurrencia): NamedLock retiene GET_LOCK hasta después de COMMIT y libera en finally.\n";

} finally {
    if ($a->inTransaction()) $a->rollBack();
    if ($b->inTransaction()) $b->rollBack();
    
    try {
        pagometodo_release_lock($a, $lockName);
        pagometodo_release_lock($b, $lockName);
    } catch (Throwable) {}

    pagometodo_cleanup($idsConcurrencia);
}

// ─────────────────────────────────────────────────────────────
// 4. Test de Transacción (Rollback si falla bitácora)
// ─────────────────────────────────────────────────────────────

$idFallaBitacora = 'PM_FAIL_' . bin2hex(random_bytes(4));
$productorIdEsperado = 0;

try {
    // Obtener el siguiente ID esperado
    $siguiente = $db->prepare('SELECT COALESCE(MAX(tbpagometodoid), 0) + 1 FROM tbpagometodo');
    $siguiente->execute();
    $productorIdEsperado = (int) $siguiente->fetchColumn();

    // Purgar residuos de corridas anteriores
    $db->prepare('DELETE FROM tbbitacora WHERE LENGTH(tbbitacorasolicitudid) > 5')->execute();
    
    // Reducir temporalmente el tamaño de la columna para forzar fallo
    $db->prepare('ALTER TABLE tbbitacora MODIFY tbbitacorasolicitudid VARCHAR(5) NOT NULL')->execute();
    
    try {
        pagometodo_controller('FORZAR_FALLO_BITACORA_PM')->procesar('POST', [], pagometodo_payload([
            'nombre' => 'Método Falla Bitácora',
            'descripcion' => 'Este registro debe hacer rollback',
        ]));
        throw new RuntimeException('La bitácora debía rechazar la solicitud más larga que la columna temporal.');
    } catch (PDOException $exception) {
        test_same(1406, (int) ($exception->errorInfo[1] ?? 0), 'La columna temporal debe forzar el rollback');
    }

    // Verificar que NO se creó el método de pago (rollback funcionó)
    $conteo = $db->prepare('SELECT COUNT(*) FROM tbpagometodo WHERE tbpagometodoid = :id');
    $conteo->execute(['id' => $productorIdEsperado]);
    test_same(0, (int) $conteo->fetchColumn(), 'Rollback elimina método de pago si falla bitácora');

    // Verificar que los locks se liberaron
    $segundaConexion = test_new_db();
    $adquirir = $segundaConexion->prepare('SELECT GET_LOCK(:bloqueo, 0)');
    $adquirir->execute(['bloqueo' => $lockName]);
    test_same(1, (int) $adquirir->fetchColumn(), 'Rollback libera tindercows_pagometodo_alta');
    $segundaConexion->prepare('SELECT RELEASE_LOCK(:bloqueo)')->execute(['bloqueo' => $lockName]);

    echo "OK pagometodo_test (transacción): rollback forzado sin CHECK ni triggers privilegiados.\n";

} finally {
    try {
        $db->prepare('ALTER TABLE tbbitacora MODIFY tbbitacorasolicitudid VARCHAR(100) NOT NULL')->execute();
    } catch (Throwable) {}
    
    // Limpiar por si acaso quedó algo
    $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id')->execute(['id' => (string) $productorIdEsperado]);
    $db->prepare('DELETE FROM tbpagometodo WHERE tbpagometodoid = :id')->execute(['id' => $productorIdEsperado]);
}

echo "\n✅ Todos los tests de PagoMetodo pasaron correctamente.\n";