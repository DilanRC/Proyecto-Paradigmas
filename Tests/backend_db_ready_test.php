<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\AnimalComercial;
use Application\Model\ProductorClasificacionPeriodo;
use Application\Model\ProductorFinca;
use Application\Model\TransportistaHistorico;

$db = test_db();
$vendedorDocumento = test_document();
$compradorDocumento = test_document();
$transportistaDocumento = test_document();
$nombreFinca = 'Finca Comercial ' . strtoupper(bin2hex(random_bytes(3)));

$idsAnimal = [];
$idsObservacion = [];
$idsPublicacion = [];
$idsCompra = [];
$idsVenta = [];
$idsInteraccion = [];
$idsCarrito = [];
$idsCarritoAnimal = [];
$idsTransportista = [];
$idsPersonaTransportista = [];
$idsTransportistaEstado = [];
$idsFlete = [];
$idsResena = [];

function backend_db_next_id(PDO $db, string $tabla, string $columna): int
{
    $sentencia = $db->prepare("SELECT COALESCE(MAX({$columna}), 0) + 1 FROM {$tabla}");
    $sentencia->execute();

    return (int) $sentencia->fetchColumn();
}

function backend_db_insert_transportista(PDO $db, string $documento): array
{
    $personaId = backend_db_next_id($db, 'tbpersona', 'tbpersonaid');
    $transportistaId = backend_db_next_id($db, 'tbtransportista', 'tbtransportistaid');
    $db->prepare(
        'INSERT INTO tbpersona
         (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo,
          tbpersonanombre, tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
         VALUES (:personaId, :documento, :tipo, :nombre, :telefono, :correo, 1)'
    )->execute([
        'personaId' => $personaId,
        'documento' => $documento,
        'tipo' => 'PASAPORTE',
        'nombre' => 'Transportista Histórico de Prueba',
        'telefono' => '+506 8888-6666',
        'correo' => 'transportista.hist.test@example.test',
    ]);
    $db->prepare(
        'INSERT INTO tbtransportista (tbtransportistaid, tbpersonaid, tbtransportistaestado)
         VALUES (:transportistaId, :personaId, 1)'
    )->execute(['transportistaId' => $transportistaId, 'personaId' => $personaId]);

    return ['personaId' => $personaId, 'transportistaId' => $transportistaId];
}

function backend_db_transaccion(PDO $db, callable $operacion): mixed
{
    $db->beginTransaction();
    try {
        $resultado = $operacion();
        $db->commit();

        return $resultado;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

try {
    $vendedor = test_create(['fincas' => [['nombre' => $nombreFinca]]], $vendedorDocumento);
    $comprador = test_create([], $compradorDocumento);
    $vendedorId = (int) $vendedor['productorId'];
    $compradorId = (int) $comprador['productorId'];
    $fincaId = (new ProductorFinca($db))->buscarIdActivo($vendedorId, $nombreFinca);
    test_assert($fincaId !== null, 'La fixture debe resolver la finca activa del vendedor.');

    $transportista = backend_db_insert_transportista($db, $transportistaDocumento);
    $idsPersonaTransportista[] = $transportista['personaId'];
    $idsTransportista[] = $transportista['transportistaId'];

    $clasificacion = new ProductorClasificacionPeriodo($db);
    $animal = new AnimalComercial($db);
    $transporte = new TransportistaHistorico($db);

    $rechazaSinLock = false;
    try {
        $clasificacion->abrir($vendedorId, 'VENDEDOR', 'Intento sin lock');
    } catch (LogicException) {
        $rechazaSinLock = true;
    }
    test_assert($rechazaSinLock, 'ProductorClasificacionPeriodo::abrir exige lock.');

    $idsClasificacion = [];
    $idsClasificacion[] = $clasificacion->ejecutarConBloqueo(
        $vendedorId,
        'VENDEDOR',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $clasificacion->abrir($vendedorId, 'VENDEDOR', 'Alta comercial de prueba'),
        ),
    );
    $idsClasificacion[] = $clasificacion->ejecutarConBloqueo(
        $vendedorId,
        'COMPRADOR',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $clasificacion->abrir($vendedorId, 'COMPRADOR', 'Compra simultánea de prueba'),
        ),
    );
    test_same(2, count($clasificacion->listarAbiertas($vendedorId)),
        'Un mismo productor puede tener COMPRADOR y VENDEDOR abiertos simultáneamente.');

    $duplicadoTipo = false;
    try {
        $clasificacion->ejecutarConBloqueo(
            $vendedorId,
            'VENDEDOR',
            fn (): int => backend_db_transaccion(
                $db,
                fn (): int => $clasificacion->abrir($vendedorId, 'VENDEDOR', 'Duplicado'),
            ),
        );
    } catch (RuntimeException) {
        $duplicadoTipo = true;
    }
    test_assert($duplicadoTipo, 'La capa PHP rechaza periodo abierto duplicado para productor+tipo.');

    $tipoInvalido = false;
    try {
        $clasificacion->ejecutarConBloqueo($vendedorId, 'INTERMEDIARIO', fn (): int => 0);
    } catch (InvalidArgumentException) {
        $tipoInvalido = true;
    }
    test_assert($tipoInvalido, 'La capa PHP conserva el vocabulario COMPRADOR/VENDEDOR.');

    $idsAnimal[] = $animal->ejecutarConBloqueoAlta(
        'tbanimal',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->crearAnimal('ARETE-' . bin2hex(random_bytes(2)), 'HEMBRA', 'Brahman', 'TEST_BACKEND_DB'),
        ),
    );
    $animalId = $idsAnimal[0];
    $idsObservacion[] = $animal->ejecutarConBloqueoAlta(
        'tbanimalobservacion',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->registrarObservacion($animalId, [
                'origen' => 'TEST_BACKEND_DB',
                'contexto' => 'Ingreso manual',
                'edadMeses' => 24,
                'peso' => '410.50',
                'proposito' => 'LECHE',
                'estadoReproductivo' => 'GESTANTE',
                'partos' => 1,
                'litrosLeche' => '12.75',
                'produccion' => ['turno' => 'AM'],
                'salud' => ['vacuna' => 'registrada'],
            ]),
        ),
    );
    $idsPublicacion[] = $animal->ejecutarConBloqueoAlta(
        'tbanimalpublicacion',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->publicarAnimal($animalId, $vendedorId, (int) $fincaId, [
                'precio' => '950000.00',
                'titulo' => 'Animal de prueba',
                'descripcion' => 'Publicación sin endpoint.',
                'estado' => 'PUBLICADA',
                'origen' => 'TEST_BACKEND_DB',
            ]),
        ),
    );
    $idsCompra[] = $animal->ejecutarConBloqueoAlta(
        'tbcompra',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->registrarCompra($animalId, $compradorId, (int) $fincaId, [
                'fecha' => '2026-09-01',
                'hora' => '10:15:00',
                'lugar' => 'Subasta de prueba',
                'precio' => '900000.00',
                'pagoMetodoId' => 1,
                'origen' => 'TEST_BACKEND_DB',
            ]),
        ),
    );
    $idsVenta[] = $animal->ejecutarConBloqueoAlta(
        'tbventa',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->registrarVenta($animalId, $vendedorId, $compradorId, (int) $fincaId, null, [
                'fecha' => '2026-09-01',
                'hora' => null,
                'lugar' => 'Finca origen',
                'precio' => '950000.00',
                'pagoMetodoId' => 1,
                'edadMeses' => 24,
                'peso' => '410.50',
                'razaSnapshot' => 'Brahman declarado en publicación',
                'origen' => 'TEST_BACKEND_DB',
            ]),
        ),
    );
    $idsInteraccion[] = $animal->ejecutarConBloqueoAlta(
        'tbanimalinteraccion',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->registrarInteraccion($compradorId, $animalId, 'ME_GUSTA', 'AGREGAR', 'TEST_BACKEND_DB'),
        ),
    );
    $idsCarrito[] = $animal->ejecutarConBloqueoAlta(
        'tbcarrito',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->crearCarrito($compradorId, 'ABIERTO'),
        ),
    );
    $idsCarritoAnimal[] = $animal->ejecutarConBloqueoAlta(
        'tbcarritoanimal',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->registrarCarritoAnimal($idsCarrito[0], $animalId, 'AGREGAR', 'TEST_BACKEND_DB'),
        ),
    );
    $idsCarritoAnimal[] = $animal->ejecutarConBloqueoAlta(
        'tbcarritoanimal',
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $animal->registrarCarritoAnimal($idsCarrito[0], $animalId, 'RETIRAR', 'TEST_BACKEND_DB'),
        ),
    );

    $venta = $db->prepare('SELECT tbcompraid, tbventarazasnapshot FROM tbventa WHERE tbventaid = :id');
    $venta->execute(['id' => $idsVenta[0]]);
    $ventaFila = $venta->fetch();
    test_same(null, $ventaFila['tbcompraid'], 'tbventa permite tbcompraid NULL.');
    test_same('Brahman declarado en publicación', $ventaFila['tbventarazasnapshot'],
        'La raza en venta queda como snapshot declarado.');

    $historialCarrito = $db->prepare('SELECT tbcarritoanimalaccion FROM tbcarritoanimal WHERE tbcarritoid = :id ORDER BY tbcarritoanimalid');
    $historialCarrito->execute(['id' => $idsCarrito[0]]);
    test_same(['AGREGAR', 'RETIRAR'], array_column($historialCarrito->fetchAll(), 'tbcarritoanimalaccion'),
        'El carrito conserva historial de agregar y retirar sin borrar pasado.');

    $idsTransportistaEstado[] = $transporte->ejecutarConBloqueoEstado(
        $transportista['transportistaId'],
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $transporte->abrirEstado($transportista['transportistaId'], 1, null, 'Sin evidencia de inicio real'),
        ),
    );
    $abiertoTransporte = $transporte->consultarEstadoAbierto($transportista['transportistaId']);
    test_assert($abiertoTransporte !== null, 'Transportista conserva periodo de estado abierto.');
    test_same(null, $abiertoTransporte['tbtransportistaestadoperiodofechainicio'],
        'No se inventa fecha real de inicio de trabajo.');
    test_assert($abiertoTransporte['tbtransportistaestadoperiodofecharegistroensistema'] !== null,
        'Sí se registra fecha de ingreso al sistema.');

    $idsFlete[] = $transporte->ejecutarConBloqueoFlete(
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $transporte->registrarFlete($transportista['transportistaId'], [
                'productorOrigenId' => $vendedorId,
                'fincaOrigenId' => (int) $fincaId,
                'fecha' => '2026-09-01',
                'hora' => '12:00:00',
                'descripcion' => 'Flete de prueba',
                'precio' => '35000.00',
                'pagoMetodoId' => 1,
                'origen' => 'TEST_BACKEND_DB',
            ]),
        ),
    );
    $idsResena[] = $transporte->ejecutarConBloqueoResena(
        fn (): int => backend_db_transaccion(
            $db,
            fn (): int => $transporte->registrarResena($transportista['transportistaId'], $compradorId, $idsFlete[0], [
                'calificacion' => 5,
                'comentario' => 'Servicio correcto.',
                'origen' => 'TEST_BACKEND_DB',
            ]),
        ),
    );

    $agregados = $db->prepare(
        'SELECT COUNT(*) AS fletes, AVG(tbtransportistaresenacalificacion) AS promedio
         FROM tbtransportistaflete f
         LEFT JOIN tbtransportistaresena r ON r.tbtransportistafleteid = f.tbtransportistafleteid
         WHERE f.tbtransportistaid = :id'
    );
    $agregados->execute(['id' => $transportista['transportistaId']]);
    $agregadoFila = $agregados->fetch();
    test_same(1, (int) $agregadoFila['fletes'], 'Cantidad de fletes se deriva con COUNT.');
    test_same(5.0, (float) $agregadoFila['promedio'], 'Calificación promedio se deriva con AVG.');

    $writesSinLock = [
        'tbanimal' => fn (): int => $animal->crearAnimal(null, null, null, 'TEST_BACKEND_DB'),
        'tbanimalobservacion' => fn (): int => $animal->registrarObservacion($animalId, ['origen' => 'TEST_BACKEND_DB']),
        'tbanimalpublicacion' => fn (): int => $animal->publicarAnimal($animalId, $vendedorId, (int) $fincaId, [
            'estado' => 'PUBLICADA', 'origen' => 'TEST_BACKEND_DB',
        ]),
        'tbcompra' => fn (): int => $animal->registrarCompra($animalId, $compradorId, null, [
            'fecha' => '2026-09-01', 'precio' => '1.00', 'pagoMetodoId' => 1, 'origen' => 'TEST_BACKEND_DB',
        ]),
        'tbventa' => fn (): int => $animal->registrarVenta($animalId, $vendedorId, $compradorId, null, null, [
            'fecha' => '2026-09-01', 'precio' => '1.00', 'pagoMetodoId' => 1, 'origen' => 'TEST_BACKEND_DB',
        ]),
        'tbanimalinteraccion' => fn (): int => $animal->registrarInteraccion($compradorId, $animalId, 'SEGUIR', 'AGREGAR', 'TEST_BACKEND_DB'),
        'tbcarrito' => fn (): int => $animal->crearCarrito($compradorId, 'ABIERTO'),
        'tbcarritoanimal' => fn (): int => $animal->registrarCarritoAnimal($idsCarrito[0], $animalId, 'AGREGAR', 'TEST_BACKEND_DB'),
        'tbtransportistaflete' => fn (): int => $transporte->registrarFlete($transportista['transportistaId'], [
            'fecha' => '2026-09-01', 'pagoMetodoId' => 1, 'origen' => 'TEST_BACKEND_DB',
        ]),
        'tbtransportistaresena' => fn (): int => $transporte->registrarResena($transportista['transportistaId'], $compradorId, null, [
            'calificacion' => 4, 'origen' => 'TEST_BACKEND_DB',
        ]),
    ];
    foreach ($writesSinLock as $tabla => $write) {
        $rechazo = false;
        try {
            $write();
        } catch (LogicException) {
            $rechazo = true;
        }
        test_assert($rechazo, "{$tabla} debe exigir lock antes de escritura directa.");
    }
} finally {
    $deleteGroups = [
        ['tbtransportistaresena', 'tbtransportistaresenaid', $idsResena],
        ['tbtransportistaflete', 'tbtransportistafleteid', $idsFlete],
        ['tbtransportistaestadoperiodo', 'tbtransportistaestadoperiodoid', $idsTransportistaEstado],
        ['tbcarritoanimal', 'tbcarritoanimalid', $idsCarritoAnimal],
        ['tbcarrito', 'tbcarritoid', $idsCarrito],
        ['tbanimalinteraccion', 'tbanimalinteraccionid', $idsInteraccion],
        ['tbventa', 'tbventaid', $idsVenta],
        ['tbcompra', 'tbcompraid', $idsCompra],
        ['tbanimalpublicacion', 'tbanimalpublicacionid', $idsPublicacion],
        ['tbanimalobservacion', 'tbanimalobservacionid', $idsObservacion],
        ['tbanimal', 'tbanimalid', $idsAnimal],
        ['tbtransportista', 'tbtransportistaid', $idsTransportista],
        ['tbpersona', 'tbpersonaid', $idsPersonaTransportista],
    ];
    foreach ($deleteGroups as [$tabla, $columna, $ids]) {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) continue;
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM {$tabla} WHERE {$columna} IN ({$marcadores})")->execute($ids);
    }
    if (isset($idsClasificacion)) {
        $db->prepare('DELETE FROM tbproductorclasificacionperiodo WHERE tbproductorid IN (?, ?)')
            ->execute([$vendedorId ?? 0, $compradorId ?? 0]);
    }
    test_cleanup_productores([$vendedorDocumento, $compradorDocumento]);
}

echo "OK backend_db_ready_test: modelos DB para clasificación, animal, compra, venta, funnel, carrito y transporte escriben con locks y preservan contrato.\n";
