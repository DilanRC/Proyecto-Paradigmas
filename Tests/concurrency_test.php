<?php

declare(strict_types=1);

use Application\Model\Direccion;
use Application\Model\Productor;
use Application\Model\ProductorDireccion;
use Application\Model\ProductorFinca;
use Application\Model\ProductorUbicacion;

require __DIR__ . '/bootstrap.php';

function concurrency_try_lock(PDO $db, string $name): int
{
    $statement = $db->prepare('SELECT GET_LOCK(:name, 0)');
    $statement->execute(['name' => $name]);
    return (int) $statement->fetchColumn();
}

function concurrency_release_lock(PDO $db, string $name): void
{
    $statement = $db->prepare('SELECT RELEASE_LOCK(:name)');
    $statement->execute(['name' => $name]);
}

function concurrency_count(PDO $db, string $table, string $column, int|string $value): int
{
    $statement = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :value");
    $statement->execute(['value' => $value]);
    return (int) $statement->fetchColumn();
}

function concurrency_id(PDO $db, string $table, string $idColumn, int $productorId): int
{
    $statement = $db->prepare(
        "SELECT {$idColumn} FROM {$table} WHERE tbproductorid = :productorId ORDER BY {$idColumn} DESC LIMIT 1"
    );
    $statement->execute(['productorId' => $productorId]);
    return (int) $statement->fetchColumn();
}

function concurrency_productor_data(string $identification, string $suffix): array
{
    return [
        'identificacionNumero' => $identification,
        'identificacionTipo' => 'PASAPORTE',
        'nombre' => "Concurrente {$suffix}",
        'telefono' => '88887777',
        'correoElectronico' => strtolower($suffix) . '@example.test',
    ];
}

function concurrency_assert_commit_before_release(
    object $modelA,
    PDO $a,
    PDO $b,
    string $lockName,
    callable $insertA,
    callable $visibleFromB,
): int {
    $idA = $modelA->ejecutarConBloqueoAlta(function () use (
        $a,
        $b,
        $lockName,
        $insertA,
        $visibleFromB,
    ): int {
        $a->beginTransaction();
        try {
            $id = $insertA();
            test_same(0, concurrency_try_lock($b, $lockName), 'B no obtiene el bloqueo antes del COMMIT de A');
            test_same(0, $visibleFromB(), 'B no observa la inserción sin confirmar de A');
            $a->commit();
            test_same(1, $visibleFromB(), 'B observa la inserción después del COMMIT de A');
            test_same(0, concurrency_try_lock($b, $lockName),
                'A conserva el bloqueo después del COMMIT y antes de salir del wrapper');
            return $id;
        } catch (Throwable $exception) {
            if ($a->inTransaction()) {
                $a->rollBack();
            }
            throw $exception;
        }
    });

    test_same(1, concurrency_try_lock($b, $lockName), 'B obtiene el bloqueo después de que A lo libera');
    concurrency_release_lock($b, $lockName);
    return $idA;
}

$a = test_new_db();
$b = test_new_db();
$identificationA = test_document();
$identificationB = test_document();
$directionProductorIds = [-random_int(3000000, 3999999), -random_int(4000000, 4999999)];
$farmProductorIds = [-random_int(5000000, 5999999), -random_int(6000000, 6999999)];
$lockNames = [
    Productor::class => 'tindercows_persona_alta',
    ProductorDireccion::class => 'tindercows_productor_direccion_alta',
    ProductorFinca::class => 'tindercows_finca_alta',
];

$farmsA = new ProductorFinca($a);
$farmsB = new ProductorFinca($b);
$productorA = new Productor($a, $farmsA);
$productorB = new Productor($b, $farmsB);
$direccionModeloA = new Direccion($a);
$direccionModeloB = new Direccion($b);
$directionA = new ProductorDireccion($a, $direccionModeloA);
$directionB = new ProductorDireccion($b, $direccionModeloB);

try {
    $expectedMethods = [
        Productor::class => ['ejecutarConBloqueoAlta' => true, 'adquirirBloqueoAlta' => false,
            'liberarBloqueoAlta' => false, 'siguienteId' => false],
        ProductorDireccion::class => ['ejecutarConBloqueoAlta' => true, 'adquirirBloqueoAlta' => false,
            'liberarBloqueoAlta' => false, 'siguienteEnlaceId' => false],
        ProductorFinca::class => ['ejecutarConBloqueoAlta' => true, 'adquirirBloqueoAlta' => false,
            'liberarBloqueoAlta' => false, 'siguienteId' => false],
    ];
    foreach ($expectedMethods as $class => $metodos) {
        foreach ($metodos as $method => $public) {
            $reflection = new ReflectionMethod($class, $method);
            test_same($public, $reflection->isPublic(), "Visibilidad incorrecta de {$class}::{$method}");
            test_same(!$public, $reflection->isPrivate(), "Encapsulación incorrecta de {$class}::{$method}");
        }
    }

    $productorIdA = concurrency_assert_commit_before_release(
        $productorA,
        $a,
        $b,
        $lockNames[Productor::class],
        fn (): int => $productorA->crear(concurrency_productor_data($identificationA, 'A')),
        function () use ($b, $identificationA): int {
            $sentencia = $b->prepare('SELECT COUNT(*) FROM tbproductor p
                INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid
                WHERE pe.tbpersonaidentificacionnumero=:identificacion');
            $sentencia->execute(['identificacion' => $identificationA]);
            return (int) $sentencia->fetchColumn();
        },
    );
    $productorIdB = $productorB->ejecutarConBloqueoAlta(function () use (
        $b,
        $productorB,
        $identificationB,
    ): int {
        $b->beginTransaction();
        try {
            $id = $productorB->crear(concurrency_productor_data($identificationB, 'B'));
            $b->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($b->inTransaction()) {
                $b->rollBack();
            }
            throw $exception;
        }
    });
    test_same($productorIdA + 1, $productorIdB, 'B calcula tbproductorid como el ID confirmado de A + 1');

    $directionIdA = concurrency_assert_commit_before_release(
        $directionA,
        $a,
        $b,
        $lockNames[ProductorDireccion::class],
        function () use ($directionA, $directionProductorIds, $a): int {
            $directionA->crearVacia($directionProductorIds[0]);
            return concurrency_id($a, 'tbproductordireccion', 'tbproductordireccionid', $directionProductorIds[0]);
        },
        fn (): int => concurrency_count($b, 'tbproductordireccion', 'tbproductorid', $directionProductorIds[0]),
    );
    $directionIdB = $directionB->ejecutarConBloqueoAlta(function () use (
        $b,
        $directionB,
        $directionProductorIds,
    ): int {
        $b->beginTransaction();
        try {
            $directionB->crearVacia($directionProductorIds[1]);
            $id = concurrency_id($b, 'tbproductordireccion', 'tbproductordireccionid', $directionProductorIds[1]);
            $b->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($b->inTransaction()) {
                $b->rollBack();
            }
            throw $exception;
        }
    });
    test_same($directionIdA + 1, $directionIdB,
        'B calcula tbproductordireccionid como el ID confirmado de A + 1');

    $farmIdA = concurrency_assert_commit_before_release(
        $farmsA,
        $a,
        $b,
        $lockNames[ProductorFinca::class],
        function () use ($farmsA, $farmProductorIds, $a): int {
            $farmsA->sincronizar($farmProductorIds[0], ['Finca Concurrente A']);
            return concurrency_id($a, 'tbfinca', 'tbfincaid', $farmProductorIds[0]);
        },
        fn (): int => concurrency_count($b, 'tbfinca', 'tbproductorid', $farmProductorIds[0]),
    );
    $farmIdB = $farmsB->ejecutarConBloqueoAlta(function () use ($b, $farmsB, $farmProductorIds): int {
        $b->beginTransaction();
        try {
            $farmsB->sincronizar($farmProductorIds[1], ['Finca Concurrente B']);
            $id = concurrency_id($b, 'tbfinca', 'tbfincaid', $farmProductorIds[1]);
            $b->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($b->inTransaction()) {
                $b->rollBack();
            }
            throw $exception;
        }
    });
    test_same($farmIdA + 1, $farmIdB, 'B calcula tbfincaid como el ID confirmado de A + 1');

    foreach ([
        [$productorA, $lockNames[Productor::class]],
        [$directionA, $lockNames[ProductorDireccion::class]],
        [$farmsA, $lockNames[ProductorFinca::class]],
    ] as [$model, $lockName]) {
        $token = test_token('lock_exception');
        try {
            $model->ejecutarConBloqueoAlta(function () use ($b, $lockName, $token): never {
                test_same(0, concurrency_try_lock($b, $lockName), 'El bloqueo está retenido dentro del callable');
                throw new RuntimeException($token);
            });
            throw new RuntimeException('El wrapper debía propagar la excepción del callable.');
        } catch (RuntimeException $exception) {
            test_same($token, $exception->getMessage(), 'El wrapper conserva la excepción original');
        }
        test_same(1, concurrency_try_lock($b, $lockName), 'El finally libera el bloqueo ante una excepción');
        concurrency_release_lock($b, $lockName);
        test_same($token, $model->ejecutarConBloqueoAlta(fn (): string => $token),
            'El wrapper devuelve el resultado mixed del callable');
    }

    // ============================================================
    // Ráfaga simultánea de ubicaciones del mismo productor: N procesos
    // PHP en paralelo compiten por el lock de alta; los IDs deben salir
    // únicos, consecutivos y con exactamente N filas nuevas.
    // ============================================================
    $identificationUbicaciones = test_document();
    $productorUbicaciones = test_create([], $identificationUbicaciones);
    $productorIdUbicaciones = (int) $productorUbicaciones['productorId'];
    $rafaga = 6;
    $dbLectura = test_db();
    $baseRafaga = (int) $dbLectura->query(
        'SELECT COALESCE(MAX(tbproductorubicacionid), 0) FROM tbproductorubicacion'
    )->fetchColumn();

    $codigoWorker = static fn (int $productorId): string => sprintf(
        "require %s;\n"
        . "\$modelo = new Application\\Model\\ProductorUbicacion(test_db());\n"
        . "echo \$modelo->ejecutarConBloqueoAlta(\n"
        . "    fn (): int => \$modelo->registrar(%d, '9.9943210', '-84.0976543', null, 'NAVEGADOR'),\n"
        . ");",
        var_export(__DIR__ . '/bootstrap.php', true),
        $productorId,
    );

    $procesos = [];
    for ($i = 0; $i < $rafaga; $i++) {
        $tuberias = [];
        $proceso = proc_open(
            [PHP_BINARY, '-r', $codigoWorker($productorIdUbicaciones)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
        );
        test_assert(is_resource($proceso), 'Cada worker de la ráfaga debe poder iniciarse');
        $procesos[] = [$proceso, $tuberias];
    }

    $idsRafaga = [];
    foreach ($procesos as [$proceso, $tuberias]) {
        $salida = stream_get_contents($tuberias[1]);
        $error = stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        test_same(0, proc_close($proceso),
            'El worker no debe fallar' . ($error !== '' ? ": {$error}" : '.'));
        test_assert(ctype_digit(trim((string) $salida)) && trim((string) $salida) !== '',
            'Cada worker debe reportar el ID asignado');
        $idsRafaga[] = (int) trim((string) $salida);
    }

    test_same($rafaga, count(array_unique($idsRafaga)),
        'Bajo escrituras simultáneas ningún ID de ubicación puede duplicarse');
    sort($idsRafaga);
    test_same(range($baseRafaga + 1, $baseRafaga + $rafaga), $idsRafaga,
        'Los IDs de la ráfaga deben quedar consecutivos sin huecos');
    $conteoRafaga = $dbLectura->prepare('SELECT COUNT(*) FROM tbproductorubicacion WHERE tbproductorid = :id');
    $conteoRafaga->execute(['id' => $productorIdUbicaciones]);
    test_same($rafaga, (int) $conteoRafaga->fetchColumn(),
        'La ráfaga debe producir exactamente N filas nuevas para el productor');
} finally {
    if ($a->inTransaction()) {
        $a->rollBack();
    }
    if ($b->inTransaction()) {
        $b->rollBack();
    }
    foreach (array_values($lockNames) as $lockName) {
        try {
            concurrency_release_lock($a, $lockName);
            concurrency_release_lock($b, $lockName);
        } catch (Throwable) {
        }
    }
    $cleanup = test_db();
    $direccionIdsStmt = $cleanup->prepare('SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid IN (?, ?)');
    $direccionIdsStmt->execute($directionProductorIds);
    $idsDireccion = $direccionIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($idsDireccion !== []) {
        $marcadores = implode(',', array_fill(0, count($idsDireccion), '?'));
        $cleanup->prepare("DELETE FROM tbdireccion WHERE tbdireccionid IN ({$marcadores})")->execute($idsDireccion);
    }
    $cleanup->prepare('DELETE FROM tbproductordireccion WHERE tbproductorid IN (?, ?)')->execute($directionProductorIds);
    $cleanup->prepare('DELETE FROM tbfinca WHERE tbproductorid IN (?, ?)')->execute($farmProductorIds);
    if (isset($productorIdUbicaciones)) {
        test_cleanup_ubicaciones([$productorIdUbicaciones]);
    }
    test_cleanup_productores([$identificationA, $identificationB, $identificationUbicaciones ?? '']);
}

echo "OK concurrency_test: los tres consecutivos retienen GET_LOCK hasta después de COMMIT, liberan en finally "
    . "y la ráfaga simultánea de ubicaciones produce IDs únicos y consecutivos.\n";
