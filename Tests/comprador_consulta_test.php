<?php

declare(strict_types=1);

/**
 * Paso (d) (DEC-DBREADY-008): Comprador quedó como clasificación de solo lectura.
 *
 * La prueba fija cuatro invariantes: no existe escritura administrativa, la
 * fuente es el periodo COMPRADOR abierto, VENDEDOR es independiente y una
 * Persona inactiva no borra/cierra la clasificación histórica.
 */

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/CompradorConsultaController.php';

use Application\Controller\CompradorConsultaController;
use Application\Model\ProductorClasificacionPeriodo;
use Application\Service\CompradorClasificacionService;

$db = test_db();
$documentoClasificado = test_document();
$documentoSinClasificar = test_document();
$controlador = new CompradorConsultaController($db);
$clasificacion = new ProductorClasificacionPeriodo($db);
$servicio = new CompradorClasificacionService($db);

/** Ejecuta una operación en la transacción que exige el modelo de periodos. */
function comprador_consulta_transaccion(PDO $db, callable $operacion): mixed
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

/** Abre/cierra VENDEDOR bajo el lock de esa clasificación. */
function comprador_consulta_vendedor(
    PDO $db,
    ProductorClasificacionPeriodo $clasificacion,
    int $productorId,
    bool $abrir,
): void {
    $clasificacion->ejecutarConBloqueo(
        $productorId,
        'VENDEDOR',
        function () use ($db, $clasificacion, $productorId, $abrir): void {
            comprador_consulta_transaccion($db, function () use ($clasificacion, $productorId, $abrir): void {
                if ($abrir) {
                    $clasificacion->abrir($productorId, 'VENDEDOR', 'Prueba de independencia');
                    return;
                }
                $clasificacion->cerrar($productorId, 'VENDEDOR');
            });
        },
    );
}

try {
    $clasificado = test_create([], $documentoClasificado);
    $sinClasificar = test_create([], $documentoSinClasificar);
    $productorClasificado = (int) $clasificado['productorId'];

    // La clasificación nueva no depende de una fila legacy. La fixture solo es
    // Productor; aun así debe poder ser reconocida como Comprador por el periodo.
    $legacy = $db->prepare(
        'SELECT COUNT(*) FROM tbcomprador c
         INNER JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
         WHERE p.tbproductorid = :id'
    );
    $legacy->execute(['id' => $productorClasificado]);
    test_same(0, (int) $legacy->fetchColumn(),
        'El productor de prueba no tiene fila legacy de comprador.');

    comprador_consulta_transaccion(
        $db,
        fn (): bool => $servicio->activar(
            $productorClasificado,
            CompradorClasificacionService::MOTIVO_MIGRACION,
        ),
    );

    // --- Escrituras: no existen, y lo dicen ---------------------------------
    foreach (['POST', 'PUT', 'DELETE', 'PATCH'] as $metodo) {
        $respuesta = $controlador->procesar($metodo, []);
        test_same(405, $respuesta['status'], "{$metodo} sobre compradores debe responder 405.");
        test_assert(str_contains($respuesta['body']['message'], 'se deriva del comportamiento'),
            "{$metodo} debe explicar por qué no se administra a mano.");
    }

    // --- Consulta por identificación ----------------------------------------
    $ficha = $controlador->procesar('GET', ['identificacionNumero' => $clasificado['identificacionNumero']]);
    test_same(200, $ficha['status'], 'El productor clasificado se consulta por identificación.');
    test_same($productorClasificado, $ficha['body']['data']['productorId'],
        'La ficha identifica al Productor, no a una entidad Comprador.');
    test_assert($ficha['body']['data']['clasificadoDesde'] !== null,
        'La ficha dice desde cuándo está clasificado.');
    test_same('ACTIVO', $ficha['body']['data']['estado'],
        'Periodo abierto + Persona activa produce estado efectivo ACTIVO.');

    $ausente = $controlador->procesar('GET',
        ['identificacionNumero' => $sinClasificar['identificacionNumero']]);
    test_same(404, $ausente['status'], 'Un Productor sin clasificación abierta responde 404.');

    // COMPRADOR y VENDEDOR pueden coexistir: abrir/cerrar VENDEDOR no modifica
    // la respuesta de Comprador ni su periodo.
    comprador_consulta_vendedor($db, $clasificacion, $productorClasificado, true);
    test_same(2, count($clasificacion->listarAbiertas($productorClasificado)),
        'COMPRADOR y VENDEDOR pueden estar abiertos simultáneamente.');
    test_same(200, $controlador->procesar('GET',
        ['identificacionNumero' => $clasificado['identificacionNumero']])['status'],
        'Tener VENDEDOR abierto no altera la consulta como Comprador.');
    comprador_consulta_vendedor($db, $clasificacion, $productorClasificado, false);
    test_same(true, $clasificacion->esComprador($productorClasificado),
        'Cerrar VENDEDOR no cierra COMPRADOR.');

    // --- Listado -------------------------------------------------------------
    $lista = $controlador->procesar('GET', ['q' => $clasificado['identificacionNumero']]);
    test_same(200, $lista['status'], 'El listado responde 200.');
    test_same('tbproductorclasificacionperiodo', $lista['body']['data']['fuente'],
        'El listado declara la fuente de verdad.');
    test_same(1, $lista['body']['data']['total'], 'La búsqueda encuentra al clasificado.');
    $fila = $lista['body']['data']['clasificados'][0];
    test_same($clasificado['identificacionNumero'], $fila['identificacionNumero'],
        'La fila corresponde al Productor buscado.');
    test_same(CompradorClasificacionService::MOTIVO_MIGRACION, $fila['motivo'],
        'La fila conserva el origen de la clasificación.');
    test_assert($fila['clasificadoDesde'] !== null, 'El listado conserva fecha de inicio.');
    test_same('ACTIVA', $fila['personaEstado'], 'El listado explicita disponibilidad de Persona.');

    // Desactivar Persona NO cierra COMPRADOR. La ficha continúa existiendo
    // porque la clasificación histórica sigue abierta, pero el estado efectivo
    // se presenta como INACTIVO y el listado lo hace visible.
    $db->prepare('UPDATE tbpersona SET tbpersonaestado = 0 WHERE tbpersonaid = (
        SELECT tbpersonaid FROM tbproductor WHERE tbproductorid = :id
    )')->execute(['id' => $productorClasificado]);

    $fichaPersonaInactiva = $controlador->procesar('GET',
        ['identificacionNumero' => $clasificado['identificacionNumero']]);
    test_same(200, $fichaPersonaInactiva['status'],
        'Persona inactiva no hace desaparecer una clasificación abierta.');
    test_same('INACTIVO', $fichaPersonaInactiva['body']['data']['estado'],
        'La ficha distingue clasificación abierta de disponibilidad de Persona.');
    test_same(true, $clasificacion->esComprador($productorClasificado),
        'Desactivar Persona no cierra el periodo COMPRADOR.');

    $listaPersonaInactiva = $controlador->procesar('GET', ['q' => $clasificado['identificacionNumero']]);
    test_same(1, $listaPersonaInactiva['body']['data']['total'],
        'La clasificación abierta sigue visible con Persona inactiva.');
    test_same('INACTIVA', $listaPersonaInactiva['body']['data']['clasificados'][0]['personaEstado'],
        'El listado marca la Persona como inactiva sin alterar la clasificación.');

    // Restaurar disponibilidad global para probar el cierre propio de COMPRADOR.
    $db->prepare('UPDATE tbpersona SET tbpersonaestado = 1 WHERE tbpersonaid = (
        SELECT tbpersonaid FROM tbproductor WHERE tbproductorid = :id
    )')->execute(['id' => $productorClasificado]);

    // Al cerrar COMPRADOR desaparece del listado; el hecho histórico permanece.
    comprador_consulta_transaccion($db, fn (): bool => $servicio->desactivar($productorClasificado));
    $listaTrasCierre = $controlador->procesar('GET', ['q' => $clasificado['identificacionNumero']]);
    test_same(0, $listaTrasCierre['body']['data']['total'],
        'Cerrada COMPRADOR, el Productor sale del listado actual.');
    test_same(404, $controlador->procesar('GET',
        ['identificacionNumero' => $clasificado['identificacionNumero']])['status'],
        'Cerrada COMPRADOR, la ficha vigente responde 404.');

    $historial = $db->prepare(
        'SELECT COUNT(*) FROM tbproductorclasificacionperiodo
         WHERE tbproductorid = :id AND tbproductorclasificacionperiodotipo = :tipo'
    );
    $historial->execute(['id' => $productorClasificado, 'tipo' => 'COMPRADOR']);
    test_same(1, (int) $historial->fetchColumn(),
        'Cerrar COMPRADOR conserva el periodo como historia.');

    // --- Validaciones de consulta -------------------------------------------
    test_same(422, $controlador->procesar('GET', ['pagina' => '0'])['status'],
        'La paginación se valida.');
    test_same(422, $controlador->procesar('GET', ['tamanoPagina' => '500'])['status'],
        'El tamaño de página se valida.');
    test_same(405, $controlador->procesar('OPTIONS', [])['status'],
        'El controlador de dominio no acepta otros métodos; el endpoint HTTP atiende OPTIONS.');
} finally {
    foreach ([$documentoClasificado, $documentoSinClasificar] as $documento) {
        $db->prepare('DELETE cp FROM tbproductorclasificacionperiodo cp
            INNER JOIN tbproductor p ON p.tbproductorid = cp.tbproductorid
            INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :id')->execute(['id' => $documento]);
        $db->prepare('UPDATE tbpersona SET tbpersonaestado = 1 WHERE tbpersonaidentificacionnumero = :id')
            ->execute(['id' => $documento]);
    }
    test_cleanup_productores([$documentoClasificado, $documentoSinClasificar]);
}

echo "OK comprador_consulta_test: solo lectura, COMPRADOR/VENDEDOR independientes, Persona inactiva visible y sin dependencia de tbcomprador.\n";
