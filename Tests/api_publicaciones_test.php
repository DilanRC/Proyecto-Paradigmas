<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Application\Model\AnimalComercial;

/**
 * Listado de publicaciones para Explorar.
 *
 * El riesgo real de esta consulta no es que devuelva vacío: es que devuelva
 * datos plausibles pero falsos. Las tres trampas que cubre este archivo:
 *
 * 1. Un animal tiene N observaciones históricas. Un JOIN ingenuo multiplica la
 *    publicación por N y el total miente.
 * 2. Edad, peso y propósito deben salir de la MISMA observación, la más
 *    reciente. Con subconsultas escalares separadas se puede describir un
 *    animal que no existe: la edad de hoy con el peso del año pasado.
 * 3. El estado vigente es el periodo abierto. Si se lee cualquier periodo, una
 *    publicación vendida sigue apareciendo como activa.
 */

$identificaciones = [];
$animalIds = [];

/** Crea animal + observaciones + publicación activa, todo bajo lock y transacción. */
function publicar_animal(AnimalComercial $animales, PDO $db, int $vendedorId, int $fincaId,
    array $animal, array $observaciones, array $publicacion): array
{
    $db->beginTransaction();
    try {
        $animalId = $animales->ejecutarConBloqueoAlta('tbanimal',
            static fn (): int => $animales->crearAnimal(
                $animal['codigo'], $animal['sexo'], $animal['raza'], 'PRUEBA', $animal['caracteristicas'] ?? null
            ));
        foreach ($observaciones as $observacion) {
            $animales->ejecutarConBloqueoAlta('tbanimalproduccionsalud',
                static fn (): int => $animales->registrarObservacion($animalId,
                    $observacion + ['origen' => 'PRUEBA']));
        }
        $publicacionId = $animales->ejecutarConBloqueoAlta('tbanimalpublicacion',
            static fn (): int => $animales->publicarAnimal($animalId, $vendedorId, $fincaId,
                $publicacion + ['origen' => 'PRUEBA', 'estado' => 'ACTIVO']));
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }

    return ['animalId' => $animalId, 'publicacionId' => $publicacionId];
}

function limpiar_animales(array $animalIds): void
{
    $ids = array_values(array_unique(array_filter($animalIds)));
    if ($ids === []) return;
    $db = test_db();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db->beginTransaction();
    try {
        $buscar = $db->prepare("SELECT tbanimalpublicacionid FROM tbanimalpublicacion
            WHERE tbanimalid IN ({$marcadores})");
        $buscar->execute($ids);
        $publicacionIds = array_map('intval', $buscar->fetchAll(PDO::FETCH_COLUMN));
        if ($publicacionIds !== []) {
            $marcadoresPublicacion = implode(',', array_fill(0, count($publicacionIds), '?'));
            $db->prepare("DELETE FROM tbanimalpublicacionestadoperiodo
                WHERE tbanimalpublicacionid IN ({$marcadoresPublicacion})")->execute($publicacionIds);
            $db->prepare("DELETE FROM tbanimalpublicacion
                WHERE tbanimalpublicacionid IN ({$marcadoresPublicacion})")->execute($publicacionIds);
        }
        $db->prepare("DELETE FROM tbanimalproduccionsalud WHERE tbanimalid IN ({$marcadores})")->execute($ids);
        $db->prepare("DELETE FROM tbanimal WHERE tbanimalid IN ({$marcadores})")->execute($ids);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

try {
    $db = test_db();
    $animales = new AnimalComercial($db);

    $vendedor = test_create_completo(['fincas' => [['nombre' => 'Finca Publicaciones']]]);
    $identificaciones[] = $vendedor['identificacionNumero'];
    $vendedorId = (int) $vendedor['productorId'];

    // La ubicación del animal es la de su finca, no la del productor: la
    // publicación congela tbfincaid justamente para no depender de dónde viva
    // el vendedor.
    $direccionFinca = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $vendedor['identificacionNumero'],
        'nombreFinca' => 'Finca Publicaciones',
        'direccionFinca' => test_direccion_payload([
            'provincia' => 'Alajuela', 'canton' => 'San Carlos', 'distrito' => 'Aguas Zarcas',
        ]),
    ]);
    test_same(201, $direccionFinca['status'], 'La fixture debe registrar la dirección de la finca');

    $buscarFinca = $db->prepare('SELECT tbfincaid FROM tbfinca WHERE tbproductorid = :id LIMIT 1');
    $buscarFinca->execute(['id' => $vendedorId]);
    $fincaId = (int) $buscarFinca->fetchColumn();
    test_assert($fincaId > 0, 'La fixture debe dejar una finca para publicar');

    $marca = 'Novillas ' . test_token('titulo');
    $creado = publicar_animal($animales, $db, $vendedorId, $fincaId,
        ['codigo' => 'AN-' . test_token('animal'), 'sexo' => 'HEMBRA', 'raza' => 'Brahman'],
        [
            // La vieja miente si el ORDER BY no ordena por fecha descendente.
            ['fecha' => '2020-01-01 08:00:00', 'edadMeses' => 6, 'peso' => 180.0, 'proposito' => 'CRIA'],
            ['fecha' => '2024-06-01 08:00:00', 'edadMeses' => 18, 'peso' => 320.5, 'proposito' => 'ENGORDE'],
        ],
        ['titulo' => $marca, 'descripcion' => 'Lote de prueba', 'precio' => 950000.0]);
    $animalIds[] = $creado['animalId'];

    $respuesta = test_publicacion_controller()->procesar('GET', ['q' => $marca], []);
    test_same(200, $respuesta['status'], 'El listado debe responder 200');
    test_same(true, $respuesta['body']['success'], 'El listado debe ser exitoso');

    $datos = $respuesta['body']['data'];
    test_same(1, $datos['total'], 'Dos observaciones no deben multiplicar la publicación');
    test_same(1, count($datos['publicaciones']), 'Debe devolver exactamente una publicación');

    $publicacion = $datos['publicaciones'][0];
    test_same($marca, $publicacion['titulo'], 'Debe devolver el título publicado');
    test_same('ACTIVO', $publicacion['estado'], 'El estado vigente es el periodo abierto');
    test_same(950000.0, $publicacion['precio'], 'El precio debe llegar como número, no como texto');
    test_assert(is_float($publicacion['precio']), 'El precio debe ser float y no string');

    // El corazón de la prueba: los tres campos de la observación más reciente.
    test_same(18, $publicacion['animal']['edadMeses'], 'Debe ganar la observación más reciente');
    test_same(320.5, $publicacion['animal']['peso'], 'El peso debe venir de la misma observación');
    test_same('ENGORDE', $publicacion['animal']['proposito'], 'El propósito debe venir de la misma observación');
    test_same('Brahman', $publicacion['animal']['raza'], 'La raza vive en tbanimal');

    test_same('Finca Publicaciones', $publicacion['finca']['nombre'], 'Debe resolver la finca');
    test_same('San Carlos', $publicacion['direccion']['canton'], 'Debe resolver la dirección de la finca');
    test_same('Aguas Zarcas', $publicacion['direccion']['distrito'], 'La dirección baja hasta distrito');
    test_assert(is_string($publicacion['vendedor']['nombre'] ?? null), 'Debe resolver el vendedor');

    // Cerrar el periodo abierto saca la publicación del filtro ACTIVO.
    $db->prepare('UPDATE tbanimalpublicacionestadoperiodo
        SET tbanimalpublicacionestadoperiodofechafin = :fecha
        WHERE tbanimalpublicacionid = :id')
        ->execute(['fecha' => date('Y-m-d H:i:s'), 'id' => $creado['publicacionId']]);
    $cerrada = test_publicacion_controller()->procesar('GET', ['q' => $marca], []);
    test_same(0, $cerrada['body']['data']['total'],
        'Sin periodo abierto la publicación no puede seguir apareciendo como activa');

    // Validaciones de entrada, iguales al contrato de Productor.
    test_same(422, test_publicacion_controller()->procesar('GET', ['estado' => 'INVENTADO'], [])['status'],
        'Un estado fuera del catálogo debe ser 422');
    test_same(422, test_publicacion_controller()->procesar('GET', ['tamanoPagina' => '500'], [])['status'],
        'Un tamaño de página mayor a 100 debe ser 422');
    test_same(422, test_publicacion_controller()->procesar('GET', ['pagina' => '0'], [])['status'],
        'La página debe ser un entero positivo');
    test_same(405, test_publicacion_controller()->procesar('POST', [], [])['status'],
        'El endpoint es de solo lectura');

    echo "OK api_publicaciones_test: listado, observación vigente, estado por periodo y validaciones.\n";
} finally {
    limpiar_animales($animalIds);
    test_cleanup_productores($identificaciones);
}
