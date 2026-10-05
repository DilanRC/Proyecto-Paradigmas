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
$personaOtroId = null;
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

    $buscarPersona = $db->prepare(
        'SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona
         WHERE tbpersonaidentificacionnumero = :identificacion'
    );
    $buscarPersona->execute(['identificacion' => $vendedor['identificacionNumero']]);
    $persona = $buscarPersona->fetch();
    test_assert(is_array($persona), 'La fixture debe dejar una Persona autenticable');
    $actor = Application\Auth\ActorContext::usuarioVerificado(
        (int) $persona['tbpersonaid'],
        'test-subject-' . test_token('subject'),
        $persona['tbpersonacorreoelectronico'],
        null,
    );
    $publicador = new Application\Controller\AnimalPublicacionController(
        $db,
        test_token('publicar'),
        $actor,
    );
    $publicadoPorApi = $publicador->procesar('POST', [], [
        'fincaNombre' => 'Finca Publicaciones',
        'animalIdentificacion' => 'API-' . test_token('animal'),
        'raza' => 'Jersey',
        'sexo' => 'HEMBRA',
        'proposito' => 'LECHE',
        'edadMeses' => 20,
        'peso' => 330.5,
        'titulo' => 'Publicación API ' . test_token('titulo'),
        'descripcion' => 'Creada por el contrato autenticado.',
        'precio' => 875000,
    ]);
    test_same(201, $publicadoPorApi['status'], 'La API autenticada debe crear una publicación');
    test_assert(($publicadoPorApi['body']['data']['publicacionId'] ?? 0) > 0,
        'La API debe devolver el identificador persistido');
    $bitacora = $db->prepare(
        'SELECT tbbitacoraentidad, tbbitacoraorigen FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :registro
         ORDER BY tbbitacoraid DESC LIMIT 1'
    );
    $bitacora->execute(['registro' => 'PUBLICACION:' . $publicadoPorApi['body']['data']['publicacionId']]);
    $evento = $bitacora->fetch();
    test_same('PUBLICACION', $evento['tbbitacoraentidad'] ?? null,
        'La publicación debe registrar una bitácora propia');
    test_same('API_PUBLICACIONES', $evento['tbbitacoraorigen'] ?? null,
        'La bitácora debe identificar el origen de publicación');
    $animalIds[] = (int) $publicadoPorApi['body']['data']['animalId'];

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
    test_same(401, test_publicacion_controller()->procesar('POST', [], [])['status'],
        'La creación de publicaciones exige una Persona autenticada');

    // Mis publicaciones: filtro mias, edición y cambio de estado (P0-2).
    $miaId = (int) $publicadoPorApi['body']['data']['publicacionId'];
    $propias = $publicador->procesar('GET', ['mias' => 'true'], []);
    test_same(200, $propias['status'], 'mias=true con sesión debe responder 200');
    test_same([$miaId], array_column($propias['body']['data']['publicaciones'], 'publicacionId'),
        'mias=true solo devuelve las publicaciones del vendedor autenticado');
    test_same(401, test_publicacion_controller()->procesar('GET', ['mias' => 'true'], [])['status'],
        'mias=true sin sesión debe ser 401');
    test_same(401, test_publicacion_controller()->procesar('PATCH', [], ['publicacionId' => $miaId, 'titulo' => 'x'])['status'],
        'Editar sin sesión debe ser 401');

    $editada = $publicador->procesar('PATCH', [], ['publicacionId' => $miaId, 'titulo' => 'Título nuevo', 'precio' => 900000]);
    test_same(200, $editada['status'], 'El dueño puede editar su publicación');
    test_same('Título nuevo', $editada['body']['data']['publicacion']['titulo'], 'El título editado se guarda');
    test_same(900000.0, $editada['body']['data']['publicacion']['precio'], 'El precio editado se guarda');
    test_same(422, $publicador->procesar('PATCH', [], ['publicacionId' => $miaId, 'titulo' => ' '])['status'],
        'El título no puede quedar vacío');
    test_same(422, $publicador->procesar('PATCH', [], ['publicacionId' => $miaId])['status'],
        'Un PATCH sin cambios es 422');
    test_same(422, $publicador->procesar('PATCH', [], ['publicacionId' => $miaId, 'estado' => 'CERRADO'])['status'],
        'Un estado fuera del catálogo es 422');

    $pausada = $publicador->procesar('PATCH', [], ['publicacionId' => $miaId, 'estado' => 'PAUSADO', 'motivo' => 'Revisión']);
    test_same('PAUSADO', $pausada['body']['data']['publicacion']['estado'], 'Pausar cambia el estado vigente');
    test_same(0, test_publicacion_controller()->procesar('GET', ['q' => 'Título nuevo'], [])['body']['data']['total'],
        'Una publicación pausada no sale en Explorar');
    $pausadas = $publicador->procesar('GET', ['mias' => 'true', 'estado' => 'PAUSADO'], []);
    test_same(1, $pausadas['body']['data']['total'], 'La pausada sigue en mis publicaciones');
    $abiertos = $db->prepare('SELECT COUNT(*) FROM tbanimalpublicacionestadoperiodo
        WHERE tbanimalpublicacionid = :id AND tbanimalpublicacionestadoperiodofechafin IS NULL');
    $abiertos->execute(['id' => $miaId]);
    test_same(1, (int) $abiertos->fetchColumn(), 'Siempre hay exactamente un periodo abierto');

    // Otro vendedor no puede tocar publicaciones ajenas (404, sin revelar que existen).
    $otro = test_create_completo(['fincas' => [['nombre' => 'Finca Ajena']]]);
    $identificaciones[] = $otro['identificacionNumero'];
    $buscarPersona->execute(['identificacion' => $otro['identificacionNumero']]);
    $personaOtro = $buscarPersona->fetch();
    $ajeno = new Application\Controller\AnimalPublicacionController($db, test_token('ajeno'),
        Application\Auth\ActorContext::usuarioVerificado((int) $personaOtro['tbpersonaid'],
            'test-subject-' . test_token('subject'), $personaOtro['tbpersonacorreoelectronico'], null));
    test_same(404, $ajeno->procesar('PATCH', [], ['publicacionId' => $miaId, 'titulo' => 'Robada'])['status'],
        'Editar una publicación ajena debe ser 404');
    test_same(0, $ajeno->procesar('GET', ['mias' => 'true'], [])['body']['data']['total'],
        'mias=true no mezcla publicaciones de otros');

    $vendida = $publicador->procesar('PATCH', [], ['publicacionId' => $miaId, 'estado' => 'VENDIDO']);
    test_same('VENDIDO', $vendida['body']['data']['publicacion']['estado'], 'Marcar como vendida cambia el estado');
    test_same(409, $publicador->procesar('PATCH', [], ['publicacionId' => $miaId, 'estado' => 'ACTIVO'])['status'],
        'Una publicación vendida no se reabre');

    // Me interesa (P1-1): lista propia, RETIRAR idempotente y marca meInteresa.
    require_once dirname(__DIR__) . '/Application/Model/PublicacionInteraccion.php';
    require_once dirname(__DIR__) . '/Application/Controller/PublicacionInteraccionController.php';
    $segunda = $publicador->procesar('POST', [], [
        'fincaNombre' => 'Finca Publicaciones',
        'animalIdentificacion' => 'API-' . test_token('animal'),
        'titulo' => 'Para guardar ' . test_token('titulo'),
        'precio' => 500000,
    ]);
    test_same(201, $segunda['status'], 'La fixture de Me interesa debe publicar');
    $guardableId = (int) $segunda['body']['data']['publicacionId'];
    $animalIds[] = (int) $segunda['body']['data']['animalId'];
    $personaOtroId = (int) $personaOtro['tbpersonaid'];
    $comprador = new Application\Controller\PublicacionInteraccionController($db, Application\Auth\ActorContext::usuarioVerificado(
        $personaOtroId, 'test-subject-' . test_token('subject'), $personaOtro['tbpersonacorreoelectronico'], null), test_token('interes'));
    $ids = static fn (array $r): array => array_column($r['body']['data']['publicaciones'], 'publicacionId');

    test_same(401, (new Application\Controller\PublicacionInteraccionController($db, Application\Auth\ActorContext::noAutenticado()))
        ->procesar('GET', [], ['tipo' => 'ME_INTERESA'])['status'], 'Listar Me interesa exige sesión');
    test_same([], $ids($comprador->procesar('GET', [], [])), 'Sin marcas la lista está vacía');

    test_same(201, $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA'])['status'], 'Marcar Me interesa');
    test_same([$guardableId], $ids($comprador->procesar('GET', [], ['tipo' => 'ME_INTERESA'])), 'La marcada aparece en la lista');
    $conMarca = $ajeno->procesar('GET', ['q' => 'Para guardar'], [])['body']['data']['publicaciones'];
    test_same(true, $conMarca[0]['meInteresa'] ?? null, 'El listado trae meInteresa=true con sesión');
    $propiaMarca = $publicador->procesar('GET', ['q' => 'Para guardar'], [])['body']['data']['publicaciones'];
    test_same(false, $propiaMarca[0]['meInteresa'] ?? null, 'Otra persona ve meInteresa=false');
    test_assert(!array_key_exists('meInteresa', test_publicacion_controller()->procesar('GET', ['q' => 'Para guardar'], [])['body']['data']['publicaciones'][0]),
        'Sin sesión no hay campo meInteresa');

    test_same(201, $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA', 'accion' => 'RETIRAR'])['status'], 'Retirar de Me interesa');
    test_same([], $ids($comprador->procesar('GET', [], [])), 'Retirada ya no aparece');
    $repetido = $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA', 'accion' => 'RETIRAR']);
    test_same(200, $repetido['status'], 'Retirar dos veces es idempotente');
    test_same(false, $repetido['body']['data']['cambiado'], 'El segundo RETIRAR no escribe');
    test_same(422, $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'PASAR', 'accion' => 'RETIRAR'])['status'], 'RETIRAR solo vale para ME_INTERESA');
    test_same(422, $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA', 'accion' => 'BORRAR'])['status'], 'Acción desconocida es 422');

    // Una publicación vendida sigue en la lista (como "No disponible") y se puede quitar.
    $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA']);
    $publicador->procesar('PATCH', [], ['publicacionId' => $guardableId, 'estado' => 'VENDIDO']);
    $lista = $comprador->procesar('GET', [], [])['body']['data']['publicaciones'];
    test_same('VENDIDO', $lista[0]['estado'] ?? null, 'La vendida sigue en la lista con su estado');
    test_same(201, $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA', 'accion' => 'RETIRAR'])['status'], 'Se puede quitar una vendida');
    test_same(409, $comprador->procesar('POST', ['publicacionId' => $guardableId, 'tipo' => 'ME_INTERESA'])['status'], 'No se puede marcar una vendida');

    // Moderación de administrador (P1-6): lista todo, pausa/retira con motivo, bitácora.
    require_once dirname(__DIR__) . '/Application/Controller/AdminPublicacionController.php';
    $modera = $publicador->procesar('POST', [], [
        'fincaNombre' => 'Finca Publicaciones',
        'animalIdentificacion' => 'API-' . test_token('animal'),
        'titulo' => 'Para moderar ' . test_token('titulo'),
        'precio' => 400000,
    ]);
    $moderarId = (int) $modera['body']['data']['publicacionId'];
    $animalIds[] = (int) $modera['body']['data']['animalId'];
    $admin = new Application\Controller\AdminPublicacionController($db, test_token('admin'), $actor);

    $lista = $admin->procesar('GET', ['q' => 'Para moderar', 'estado' => 'TODOS'], []);
    test_same(200, $lista['status'], 'El admin lista publicaciones');
    test_same([$moderarId], array_column($lista['body']['data']['publicaciones'], 'publicacionId'), 'El buscador del admin encuentra la publicación');
    test_assert(is_string($lista['body']['data']['publicaciones'][0]['vendedor']['nombre'] ?? null), 'La lista trae el vendedor');
    test_same(422, $admin->procesar('GET', ['estado' => 'CERRADO'], [])['status'], 'Filtro de estado inválido es 422');

    test_same(422, $admin->procesar('PATCH', [], ['publicacionId' => $moderarId, 'estado' => 'PAUSADO'])['status'], 'Pausar exige motivo');
    test_same(422, $admin->procesar('PATCH', [], ['publicacionId' => $moderarId, 'estado' => 'VENDIDO', 'motivo' => 'x'])['status'], 'El admin no marca vendida');
    test_same(404, $admin->procesar('PATCH', [], ['publicacionId' => 99999999, 'estado' => 'PAUSADO', 'motivo' => 'x'])['status'], 'Publicación inexistente es 404');
    $pausadaAdmin = $admin->procesar('PATCH', [], ['publicacionId' => $moderarId, 'estado' => 'PAUSADO', 'motivo' => 'Foto incorrecta']);
    test_same('PAUSADO', $pausadaAdmin['body']['data']['publicacion']['estado'], 'El admin pausa con motivo');
    $motivoGuardado = $db->prepare('SELECT tbanimalpublicacionestadoperiodomotivo FROM tbanimalpublicacionestadoperiodo
        WHERE tbanimalpublicacionid = :id AND tbanimalpublicacionestadoperiodofechafin IS NULL');
    $motivoGuardado->execute(['id' => $moderarId]);
    test_same('Foto incorrecta', $motivoGuardado->fetchColumn(), 'El motivo queda en el periodo de estado');
    $bitacoraAdmin = $db->prepare('SELECT tbbitacoraaccion, tbbitacoraorigen FROM tbbitacora
        WHERE tbbitacoraregistroidentificacionnumero = :r ORDER BY tbbitacoraid DESC LIMIT 1');
    $bitacoraAdmin->execute(['r' => 'PUBLICACION:' . $moderarId]);
    $eventoAdmin = $bitacoraAdmin->fetch();
    test_same('MODERAR', $eventoAdmin['tbbitacoraaccion'] ?? null, 'La moderación deja bitácora MODERAR');
    test_same('API_ADMIN_PUBLICACIONES', $eventoAdmin['tbbitacoraorigen'] ?? null, 'La bitácora indica el origen admin');

    test_same('ACTIVO', $admin->procesar('PATCH', [], ['publicacionId' => $moderarId, 'estado' => 'ACTIVO'])['body']['data']['publicacion']['estado'], 'El admin reactiva sin motivo');
    test_same('RETIRADO', $admin->procesar('PATCH', [], ['publicacionId' => $moderarId, 'estado' => 'RETIRADO', 'motivo' => 'Incumple las reglas'])['body']['data']['publicacion']['estado'], 'El admin retira con motivo');
    test_same(409, $admin->procesar('PATCH', [], ['publicacionId' => $moderarId, 'estado' => 'ACTIVO'])['status'], 'Una retirada no se reabre');
    test_same(409, $publicador->procesar('PATCH', [], ['publicacionId' => $moderarId, 'titulo' => 'x'])['status'], 'El vendedor tampoco edita una retirada');

    // Imagen de la publicación: solo URLs https; nada ejecutable llega a un <img>.
    $imagen = [\Application\Controller\AnimalPublicacionController::class, 'imagenUrl'];
    test_same(null, $imagen(null), 'Sin imagen la publicación sigue siendo válida');
    test_same(null, $imagen('  '), 'Una imagen vacía equivale a no tener imagen');
    test_same('https://upload.wikimedia.org/a.jpg', $imagen(' https://upload.wikimedia.org/a.jpg '),
        'Una URL https se guarda recortada');
    foreach (['http://example.com/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AAAA',
        'https://usuario:clave@example.com/a.jpg', 'https://' . str_repeat('a', 500) . '.com/a.jpg', 'no es url'] as $mala) {
        try {
            $imagen($mala);
            test_assert(false, "Debe rechazar la imagen {$mala}");
        } catch (Application\HttpException $error) {
            test_same(422, $error->estadoHttp, "La imagen {$mala} debe responder 422");
        }
    }

    echo "OK api_publicaciones_test: listado, observación vigente, estado por periodo, mis publicaciones (editar y cambiar estado), Me interesa, moderación admin, validaciones e imagen https.\n";
} finally {
    if ($personaOtroId !== null) {
        test_db()->prepare('DELETE FROM tbanimalpublicacioninteraccion WHERE tbpersonaid = :id')->execute(['id' => $personaOtroId]);
    }
    limpiar_animales($animalIds);
    test_cleanup_productores($identificaciones);
}
