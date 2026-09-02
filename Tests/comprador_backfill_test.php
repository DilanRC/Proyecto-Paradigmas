<?php

declare(strict_types=1);

/**
 * Paso (b) del retiro de la tabla legacy de comprador: expandir -> migrar ->
 * cortar (DEC-DBREADY-007). Cubre el backfill y el cambio de escrituras.
 *
 * El orden importa y aquí se prueba en ese orden: primero se fabrica el estado
 * legacy (filas escritas directamente, como las que ya existen en producción),
 * después se audita, después se migra y solo entonces se ejercita el CRUD.
 */

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Tools/backfill-clasificacion-comprador.php';

use Application\Model\ProductorClasificacionPeriodo;
use Application\Service\CompradorClasificacionService;

$db = test_db();
$clasificacion = new ProductorClasificacionPeriodo($db);
$servicio = new CompradorClasificacionService($db);

/** Inserta una fila legacy de tbcomprador tal como la escribía el CRUD viejo. */
function backfill_test_insertar_legacy(PDO $db, int $personaId, int $estado): int
{
    $siguiente = $db->prepare('SELECT COALESCE(MAX(tbcompradorid), 0) + 1 FROM tbcomprador');
    $siguiente->execute();
    $id = (int) $siguiente->fetchColumn();
    $db->prepare('INSERT INTO tbcomprador (tbcompradorid, tbpersonaid, tbcompradorestado)
                  VALUES (:id, :personaId, :estado)')
        ->execute(['id' => $id, 'personaId' => $personaId, 'estado' => $estado]);

    return $id;
}

function backfill_test_persona_id(PDO $db, string $identificacion): int
{
    $sentencia = $db->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $sentencia->execute(['id' => $identificacion]);

    return (int) $sentencia->fetchColumn();
}

/**
 * Transiciones de estado registradas en bitácora para esa identificación, con
 * el `estado` que quedó guardado en datosanteriores y datosnuevos.
 *
 * @return array<int,array{accion: string, anterior: ?string, nuevo: ?string}>
 */
function backfill_test_bitacora(PDO $db, string $identificacion): array
{
    $sentencia = $db->prepare(
        'SELECT tbbitacoraaccion, tbbitacoradatosanteriores, tbbitacoradatosnuevos
         FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id
           AND tbbitacoraorigen = :origen
           AND tbbitacoraaccion IN (:desactivar, :reactivar)
         ORDER BY tbbitacoraid'
    );
    $sentencia->execute([
        'id' => $identificacion,
        'origen' => 'API_COMPRADORES',
        'desactivar' => 'DESACTIVAR',
        'reactivar' => 'REACTIVAR',
    ]);
    $estado = static function (mixed $json): ?string {
        $decodificado = json_decode((string) $json, true);

        return is_array($decodificado) ? ($decodificado['estado'] ?? null) : null;
    };

    return array_map(static fn (array $fila): array => [
        'accion' => $fila['tbbitacoraaccion'],
        'anterior' => $estado($fila['tbbitacoradatosanteriores']),
        'nuevo' => $estado($fila['tbbitacoradatosnuevos']),
    ], $sentencia->fetchAll());
}

/**
 * El servicio escribe dentro de la transacción de quien llama, igual que hará
 * T10. Esta envoltura reproduce ese contrato en las pruebas.
 */
function backfill_test_transaccion(PDO $db, callable $operacion): mixed
{
    $db->beginTransaction();
    try {
        $resultado = $operacion();
        $db->commit();

        return $resultado;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function backfill_test_periodos(PDO $db, int $productorId): array
{
    $sentencia = $db->prepare(
        'SELECT tbproductorclasificacionperiodoid AS id, tbproductorclasificacionperiodofechafin AS fin,
                tbproductorclasificacionperiodomotivo AS motivo
         FROM tbproductorclasificacionperiodo
         WHERE tbproductorid = :id AND tbproductorclasificacionperiodotipo = :tipo
         ORDER BY tbproductorclasificacionperiodoid'
    );
    $sentencia->execute(['id' => $productorId, 'tipo' => 'COMPRADOR']);

    return $sentencia->fetchAll();
}

$documentoActivo = test_document();
$documentoInactivo = test_document();
$documentoHuerfano = test_document();
$documentoCrud = 'CO-BF-' . strtoupper(bin2hex(random_bytes(4)));
$identificaciones = [];
$personaHuerfanaId = null;

try {
    // ------------------------------------------------------------------
    // Estado legacy previo: dos productores que ya son compradores en la
    // tabla vieja (uno activo, otro dado de baja) y ninguna clasificación.
    // ------------------------------------------------------------------
    $activo = test_create([], $documentoActivo);
    $inactivo = test_create([], $documentoInactivo);
    $identificaciones[] = $activo['identificacionNumero'];
    $identificaciones[] = $inactivo['identificacionNumero'];
    $productorActivo = (int) $activo['productorId'];
    $productorInactivo = (int) $inactivo['productorId'];
    backfill_test_insertar_legacy($db, backfill_test_persona_id($db, $activo['identificacionNumero']), 1);
    backfill_test_insertar_legacy($db, backfill_test_persona_id($db, $inactivo['identificacionNumero']), 0);

    // Persona con fila legacy pero SIN productor: el caso incompatible.
    $db->prepare(
        'INSERT INTO tbpersona (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo,
          tbpersonanombre, tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
         VALUES ((SELECT siguiente FROM (SELECT COALESCE(MAX(tbpersonaid), 0) + 1 AS siguiente FROM tbpersona) AS calculo),
                 :documento, :tipo, :nombre, :telefono, :correo, 1)'
    )->execute([
        'documento' => $documentoHuerfano,
        'tipo' => 'PASAPORTE',
        'nombre' => 'Comprador Legacy Sin Productor',
        'telefono' => '+506 8888-5555',
        'correo' => 'comprador.huerfano@example.test',
    ]);
    $personaHuerfanaId = backfill_test_persona_id($db, $documentoHuerfano);
    backfill_test_insertar_legacy($db, $personaHuerfanaId, 1);

    // ------------------------------------------------------------------
    // 1. PRECHECK: el comprador legacy sin productor se reporta y bloquea.
    // ------------------------------------------------------------------
    $auditoria = backfill_auditar($db);
    $huerfanos = array_column($auditoria['sin_productor'], 'tbpersonaidentificacionnumero');
    test_assert(in_array($documentoHuerfano, $huerfanos, true),
        'El precheck debe reportar el comprador legacy sin productor.');
    $migrables = array_column($auditoria['migrables'], 'tbpersonaidentificacionnumero');
    test_assert(!in_array($documentoHuerfano, $migrables, true),
        'El comprador sin productor nunca entra al backfill.');
    test_assert(in_array($activo['identificacionNumero'], $migrables, true),
        'El comprador legacy activo con productor sí es migrable.');
    $inactivos = array_column($auditoria['inactivos'], 'tbpersonaidentificacionnumero');
    test_assert(in_array($inactivo['identificacionNumero'], $inactivos, true),
        'El comprador legacy inactivo se clasifica aparte, no como migrable.');

    // El diagnóstico SQL ve lo mismo que el script.
    $d22 = $db->prepare(
        'SELECT COUNT(*) FROM tbcomprador c
         INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
         LEFT JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
         WHERE p.tbproductorid IS NULL AND pe.tbpersonaidentificacionnumero = :id'
    );
    $d22->execute(['id' => $documentoHuerfano]);
    test_same(1, (int) $d22->fetchColumn(), 'D-22 debe ver el comprador legacy sin productor.');

    // Con una fila incompatible presente, el script se niega a migrar.
    test_same(1, backfill_main(['backfill', '--apply']),
        'Con compradores sin productor, el backfill aborta con código 1.');
    test_same(0, count(backfill_test_periodos($db, $productorActivo)),
        'Un backfill abortado no abre ningún periodo.');

    // ------------------------------------------------------------------
    // 2. BACKFILL, ya sin la fila incompatible.
    // ------------------------------------------------------------------
    // Se retira solo la FILA legacy incompatible; la persona sin productor
    // queda viva para el caso 9.
    $db->prepare('DELETE FROM tbcomprador WHERE tbpersonaid = :id')->execute(['id' => $personaHuerfanaId]);
    test_same(0, backfill_main(['backfill', '--apply']), 'El backfill limpio debe terminar en 0.');

    $periodosActivo = backfill_test_periodos($db, $productorActivo);
    test_same(1, count($periodosActivo), 'El comprador legacy activo recibe exactamente un periodo.');
    test_same(null, $periodosActivo[0]['fin'], 'Ese periodo queda abierto.');
    test_same(CompradorClasificacionService::MOTIVO_MIGRACION, $periodosActivo[0]['motivo'],
        'El periodo migrado declara su origen: MIGRACION_TBCOMPRADOR_LEGACY.');
    test_same(true, $clasificacion->esComprador($productorActivo),
        'Después del backfill el comprador legacy activo es comprador.');

    test_same(0, count(backfill_test_periodos($db, $productorInactivo)),
        'El comprador legacy inactivo no recibe periodo: no se inventa historia cerrada.');
    test_same(false, $clasificacion->esComprador($productorInactivo),
        'El comprador legacy inactivo no queda clasificado.');

    // 3. Idempotencia del backfill.
    test_same(0, backfill_main(['backfill', '--apply']), 'El segundo backfill también termina en 0.');
    test_same(1, count(backfill_test_periodos($db, $productorActivo)),
        'Ejecutar el backfill dos veces no abre dos periodos.');

    // ------------------------------------------------------------------
    // 3bis. Carreras: la auditoría es una foto y la base es compartida.
    // ------------------------------------------------------------------
    // Carrera 1: la auditoría la ve activa y alguien la desactiva antes de
    // aplicar. El backfill no debe abrir clasificación con datos viejos.
    $documentoCarreraA = test_document();
    $carreraA = test_create([], $documentoCarreraA);
    $identificaciones[] = $carreraA['identificacionNumero'];
    $productorCarreraA = (int) $carreraA['productorId'];
    $compradorCarreraA = backfill_test_insertar_legacy(
        $db, backfill_test_persona_id($db, $carreraA['identificacionNumero']), 1);

    $fotoA = backfill_auditar($db);
    test_assert(in_array($carreraA['identificacionNumero'],
        array_column($fotoA['migrables'], 'tbpersonaidentificacionnumero'), true),
        'La auditoría ve la fila de la carrera 1 como migrable.');

    // Otra conexión, como haría el CRUD desde otra petición.
    $otra = test_new_db();
    $otra->prepare('UPDATE tbcomprador SET tbcompradorestado = 0 WHERE tbcompradorid = :id')
        ->execute(['id' => $compradorCarreraA]);

    $resultadoA = backfill_aplicar($db, $fotoA);
    test_same(0, count(backfill_test_periodos($db, $productorCarreraA)),
        'El backfill no abre clasificación de una fila desactivada después de la auditoría.');
    test_same(1, count($resultadoA['omitidos']), 'La fila se reporta como omitida por cambio concurrente.');
    test_same($compradorCarreraA, (int) $resultadoA['omitidos'][0]['tbcompradorid'],
        'El reporte identifica exactamente qué fila se omitió.');
    test_assert(str_contains($resultadoA['omitidos'][0]['motivo_backfill'], 'desactivado'),
        'El reporte dice por qué se omitió.');

    // Carrera 2: otra conexión abre COMPRADOR entre la auditoría y el apply.
    $documentoCarreraB = test_document();
    $carreraB = test_create([], $documentoCarreraB);
    $identificaciones[] = $carreraB['identificacionNumero'];
    $productorCarreraB = (int) $carreraB['productorId'];
    backfill_test_insertar_legacy($db, backfill_test_persona_id($db, $carreraB['identificacionNumero']), 1);

    $fotoB = backfill_auditar($db);
    test_assert(in_array($carreraB['identificacionNumero'],
        array_column($fotoB['migrables'], 'tbpersonaidentificacionnumero'), true),
        'La auditoría ve la fila de la carrera 2 como migrable.');

    $clasificacionOtra = new ProductorClasificacionPeriodo($otra);
    $clasificacionOtra->ejecutarConBloqueo($productorCarreraB, 'COMPRADOR',
        function () use ($otra, $clasificacionOtra, $productorCarreraB): int {
            $otra->beginTransaction();
            try {
                $id = $clasificacionOtra->abrir($productorCarreraB, 'COMPRADOR', 'Alta concurrente');
                $otra->commit();

                return $id;
            } catch (Throwable $error) {
                if ($otra->inTransaction()) {
                    $otra->rollBack();
                }
                throw $error;
            }
        });

    $resultadoB = backfill_aplicar($db, $fotoB);
    test_same(1, count(backfill_test_periodos($db, $productorCarreraB)),
        'El backfill no duplica un periodo que otra conexión ya abrió.');
    test_same(0, $resultadoB['migrados'], 'Nada que migrar: la clasificación ya existía.');
    test_same(1, $resultadoB['ya_migrados'], 'La fila se cuenta como ya migrada, no como error.');

    // Limpieza de las dos filas de carrera antes de seguir.
    foreach ([$productorCarreraA, $productorCarreraB] as $productorCarrera) {
        $db->prepare('DELETE FROM tbproductorclasificacionperiodo WHERE tbproductorid = :id')
            ->execute(['id' => $productorCarrera]);
    }
    foreach ([$carreraA['identificacionNumero'], $carreraB['identificacionNumero']] as $documentoCarrera) {
        $db->prepare('DELETE c FROM tbcomprador c
            INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $documentoCarrera]);
    }

    // ------------------------------------------------------------------
    // 3. Escritura de la clasificación después del backfill.
    //
    // Tras el paso (d) (DEC-DBREADY-008) ya no hay CRUD de comprador: el único
    // punto de escritura es CompradorClasificacionService, por donde entrará
    // T10 cuando exista. Estos casos ejercitan ese servicio directamente, que
    // es exactamente el contrato que T10 va a usar.
    // ------------------------------------------------------------------
    $productorCrud = test_create([], $documentoCrud);
    $identificaciones[] = $productorCrud['identificacionNumero'];
    $productorCrudId = (int) $productorCrud['productorId'];
    $identificacionCrud = $productorCrud['identificacionNumero'];

    // 9. Sin productor no se escribe clasificación y nunca se inventa uno.
    $personaSinProductor = backfill_test_persona_id($db, $documentoHuerfano);
    $servicioHuerfano = new CompradorClasificacionService($db);
    test_same(null, $servicioHuerfano->productorDePersona($personaSinProductor),
        'Una persona sin productor no resuelve productor, y nadie se lo inventa.');
    $productoresHuerfano = $db->prepare('SELECT COUNT(*) FROM tbproductor WHERE tbpersonaid = :id');
    $productoresHuerfano->execute(['id' => $personaSinProductor]);
    test_same(0, (int) $productoresHuerfano->fetchColumn(),
        'Consultar por una persona sin productor no crea ninguno.');

    // Alta de la clasificación.
    test_same(true, backfill_test_transaccion($db, fn (): bool => $servicio->activar(
        $productorCrudId, CompradorClasificacionService::MOTIVO_MIGRACION)),
        'Abrir la clasificación de un productor sin periodo devuelve true.');
    test_same(1, count(backfill_test_periodos($db, $productorCrudId)), 'Se abrió un periodo COMPRADOR.');

    // 4. Activar dos veces: un solo periodo abierto.
    test_same(false, backfill_test_transaccion($db, fn (): bool => $servicio->activar(
        $productorCrudId, CompradorClasificacionService::MOTIVO_MIGRACION)),
        'Activar de nuevo no abre otro periodo y lo dice.');
    $periodos = backfill_test_periodos($db, $productorCrudId);
    test_same(1, count($periodos), 'Activar dos veces no abre un segundo periodo.');
    test_same(null, $periodos[0]['fin'], 'El único periodo sigue abierto.');

    // 5. Desactivar cierra y conserva.
    test_same(true, backfill_test_transaccion($db, fn (): bool => $servicio->desactivar($productorCrudId)),
        'Cerrar la clasificación abierta devuelve true.');
    $periodos = backfill_test_periodos($db, $productorCrudId);
    test_same(1, count($periodos), 'Desactivar no borra el periodo: lo cierra.');
    test_assert($periodos[0]['fin'] !== null, 'El periodo queda cerrado con fecha de fin.');
    $cerradoId = (int) $periodos[0]['id'];
    $cerradoFin = $periodos[0]['fin'];

    // Desactivar de nuevo no toca la historia.
    test_same(false, backfill_test_transaccion($db, fn (): bool => $servicio->desactivar($productorCrudId)),
        'Desactivar dos veces no vuelve a cerrar.');
    $periodos = backfill_test_periodos($db, $productorCrudId);
    test_same(1, count($periodos), 'Desactivar dos veces no crea historia nueva.');
    test_same($cerradoFin, $periodos[0]['fin'], 'La fecha de cierre original no se modifica.');

    // 6. Reabrir abre un periodo NUEVO; el anterior no se reabre.
    backfill_test_transaccion($db, fn (): bool => $servicio->activar(
        $productorCrudId, CompradorClasificacionService::MOTIVO_MIGRACION));
    $periodos = backfill_test_periodos($db, $productorCrudId);
    test_same(2, count($periodos), 'Volver a clasificar abre un periodo nuevo.');
    test_same($cerradoId, (int) $periodos[0]['id'], 'El periodo viejo conserva su identificador.');
    test_same($cerradoFin, $periodos[0]['fin'], 'El periodo viejo sigue cerrado: no se reabre.');
    test_same(null, $periodos[1]['fin'], 'El periodo nuevo queda abierto.');

    // 7. COMPRADOR y VENDEDOR simultáneos siguen siendo independientes.
    $clasificacion->ejecutarConBloqueo($productorCrudId, 'VENDEDOR', function () use ($db, $clasificacion, $productorCrudId): int {
        $db->beginTransaction();
        try {
            $id = $clasificacion->abrir($productorCrudId, 'VENDEDOR', 'Prueba de independencia');
            $db->commit();

            return $id;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    });
    test_same(2, count($clasificacion->listarAbiertas($productorCrudId)),
        'COMPRADOR y VENDEDOR quedan abiertos a la vez.');
    backfill_test_transaccion($db, fn (): bool => $servicio->desactivar($productorCrudId));
    test_same(false, $clasificacion->esComprador($productorCrudId), 'Cerrar COMPRADOR cierra solo COMPRADOR.');
    test_assert($clasificacion->consultarAbierto($productorCrudId, 'VENDEDOR') !== null,
        'Cerrar COMPRADOR no toca VENDEDOR.');

    // 8. Fallo entre cierre y apertura: ROLLBACK deja el estado original.
    backfill_test_transaccion($db, fn (): bool => $servicio->activar(
        $productorCrudId, CompradorClasificacionService::MOTIVO_MIGRACION));
    $antesDelFallo = backfill_test_periodos($db, $productorCrudId);
    $fallo = false;
    $db->beginTransaction();
    try {
        $servicio->desactivar($productorCrudId);
        throw new RuntimeException('Fallo simulado entre cierre y apertura.');
    } catch (RuntimeException) {
        $fallo = true;
        $db->rollBack();
    }
    test_assert($fallo, 'La prueba debe haber forzado el fallo.');
    test_same($antesDelFallo, backfill_test_periodos($db, $productorCrudId),
        'El rollback deja los periodos exactamente como estaban.');
    test_same(true, $clasificacion->esComprador($productorCrudId),
        'Tras el rollback el comprador sigue clasificado.');
} finally {
    foreach ([$documentoActivo, $documentoInactivo, $documentoCrud,
        $documentoCarreraA ?? null, $documentoCarreraB ?? null] as $documento) {
        if ($documento === null) {
            continue;
        }
        $canonico = str_replace('-', '', $documento);
        $db->prepare('DELETE cp FROM tbproductorclasificacionperiodo cp
            INNER JOIN tbproductor p ON p.tbproductorid = cp.tbproductorid
            INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $canonico]);
        $db->prepare('DELETE c FROM tbcomprador c
            INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $canonico]);
        $db->prepare('DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :id')
            ->execute(['id' => $canonico]);
    }
    if ($personaHuerfanaId !== null) {
        $db->prepare('DELETE FROM tbcomprador WHERE tbpersonaid = :id')->execute(['id' => $personaHuerfanaId]);
        $db->prepare('DELETE FROM tbpersona WHERE tbpersonaid = :id')->execute(['id' => $personaHuerfanaId]);
    }
    test_cleanup_productores(array_map(static fn (string $d): string => str_replace('-', '', $d),
        array_filter([$documentoActivo, $documentoInactivo, $documentoCrud,
            $documentoCarreraA ?? null, $documentoCarreraB ?? null])));
}

echo "OK comprador_backfill_test: precheck reporta legacy sin productor, backfill idempotente y a prueba de "
    . "carreras, y la clasificación se abre/cierra sin reabrir historia ni inventar productores.\n";
