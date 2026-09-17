<?php

declare(strict_types=1);

/**
 * Tramo 1 (DEC-28/29): Comprador es un contexto de Persona, no una
 * clasificación del Productor ni un rol que se administre a mano.
 *
 * Contrato de la api/compradores.php:
 *   POST   inscribe el contexto (idempotente). Nuevo -> 201 INSCRIBIR;
 *          existente activo -> 200 sin escritura; existente inactivo ->
 *          201 REACTIVAR.
 *   GET    consulta single/listado desde tbcomprador + tbpersona.
 *   DELETE desactiva (no borra), idempotente. 404 si no hay contexto.
 *   PATCH  reactiva, idempotente. 404 si no hay contexto.
 *   Persona inactiva (tbpersonaestado=0) -> 409 en cualquier escritura.
 *   PUT    prohibido en el endpoint (Allow: GET, POST, DELETE, PATCH).
 */

require __DIR__ . '/bootstrap.php';

use Application\Service\CompradorClasificacionService;

$db = test_db();
$ids = [];

function test_comprador_payload(?string $number = null, array $overrides = []): array
{
    $base = [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $number ?? test_document()],
        'nombre' => 'Comprador Ficticio de Prueba',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'comprador.tests@example.test',
    ];
    return array_replace_recursive($base, $overrides);
}

function test_create_comprador(array $overrides = [], ?string $number = null): array
{
    $response = test_comprador_controller()->procesar('POST', [], test_comprador_payload($number, $overrides));
    test_same(201, $response['status'], 'La fixture debe responder HTTP 201');
    test_assert(($response['body']['success'] ?? false) === true, 'La fixture debe ser exitosa.');
    return $response['body']['data'];
}

function test_conteo_comprador(string $identificacion): int
{
    $conteo = test_db()->prepare(
        'SELECT COUNT(*) FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid
         WHERE p.tbpersonaidentificacionnumero = :id'
    );
    $conteo->execute(['id' => $identificacion]);
    return (int) $conteo->fetchColumn();
}

function test_conteo_bitacora(string $identificacion, string $accion): int
{
    $conteo = test_db()->prepare(
        'SELECT COUNT(*) FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id AND tbbitacoraaccion = :accion'
    );
    $conteo->execute(['id' => $identificacion, 'accion' => $accion]);
    return (int) $conteo->fetchColumn();
}

try {
    // ============================================================
    // Inscripción (POST)
    // ============================================================
    $uno = 'CP-00-' . strtoupper(bin2hex(random_bytes(4)));
    $documento = str_replace('-', '', $uno);
    $ids[] = $documento;
    $creado = test_create_comprador([], $uno);
    test_same($documento, $creado['identificacionNumero'], 'La identificación debe almacenarse canónica');
    test_assert(is_int($creado['compradorId']) && $creado['compradorId'] > 0,
        'PHP debe asignar tbcompradorid sin AUTO_INCREMENT en MySQL');
    test_assert(is_int($creado['personaId']) && $creado['personaId'] > 0,
        'La inscripción debe resolver o crear la tbpersona');
    test_same('ACTIVO', $creado['estado'], 'El alta crea el contexto comprador activo');
    test_same(1, test_conteo_comprador($documento), 'Solo debe existir un contexto por identificación');
    test_same(1, test_conteo_bitacora($documento, 'INSCRIBIR'), 'El alta registra INSCRIBIR en la bitácora');

    // ============================================================
    // Idempotencia: inscribir a un comprador ya activo
    // ============================================================
    $yaActivo = test_comprador_controller()->procesar('POST', [], test_comprador_payload($uno));
    test_same(200, $yaActivo['status'], 'Inscribir a un comprador ya activo es una declaración idempotente');
    test_same('La persona ya es comprador.', $yaActivo['body']['message'], 'No debe inscribirse otra vez');
    test_same(1, test_conteo_comprador($documento), 'No se debe duplicar el contexto');
    test_same(1, test_conteo_bitacora($documento, 'INSCRIBIR'), 'No debe escribir bitácora por un alta sin cambio');

    // ============================================================
    // Conflicto por datos personales
    // ============================================================
    $otroNombre = test_comprador_controller()->procesar('POST', [], test_comprador_payload($uno, ['nombre' => 'Nombre Diferente']));
    test_same(409, $otroNombre['status'], 'La misma identificación con datos diferentes debe responder 409');
    test_assert(isset($otroNombre['body']['errors']['identificacion.numero']),
        'El 409 debe explicar el conflicto en identificación');
    test_same(1, test_conteo_comprador($documento), 'El conflicto no debe crear un segundo contexto');

    // ============================================================
    // Consulta (GET)
    // ============================================================
    $consulta = test_comprador_controller()->procesar('GET', ['identificacionNumero' => $uno], []);
    test_same(200, $consulta['status'], 'Consulta por identificación');
    test_same('ACTIVO', $consulta['body']['data']['estado'], 'La consulta refleja el estado vigente');
    test_same('tbcomprador + tbpersona', $consulta['body']['data']['fuente'],
        'La consulta debe declarar su fuente de verdad');

    $noExiste = test_comprador_controller()->procesar('GET', ['identificacionNumero' => 'NOEXISTE999'], []);
    test_same(404, $noExiste['status'], 'Identificación inexistente');
    test_same('Comprador no encontrado.', $noExiste['body']['message'], 'Mensaje de no encontrado');

    // ============================================================
    // Desactivación (DELETE) idempotente
    // ============================================================
    $desactivado = test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => $uno]);
    test_same(200, $desactivado['status'], 'Desactivación debe responder 200');
    test_same('INACTIVO', $desactivado['body']['data']['estado'], 'Desactivación lógica del contexto');
    test_same(1, test_conteo_comprador($documento), 'Desactivar no borra físicamente el contexto');
    test_same(1, test_conteo_bitacora($documento, 'DESACTIVAR'), 'La desactivación registra DESACTIVAR');

    $reintento = test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => $uno]);
    test_same(200, $reintento['status'], 'Desactivar dos veces debe ser idempotente');
    test_same(1, test_conteo_bitacora($documento, 'DESACTIVAR'), 'No debe repetirse la bitácora sin cambio real');

    // ============================================================
    // Reactivación (PATCH) idempotente
    // ============================================================
    $reactivado = test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => $uno]);
    test_same(200, $reactivado['status'], 'Reactivación debe responder 200');
    test_same('ACTIVO', $reactivado['body']['data']['estado'], 'Reactiva el mismo contexto');
    test_same(1, test_conteo_bitacora($documento, 'REACTIVAR'), 'La reactivación registra REACTIVAR');

    $reintentoReactivar = test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => $uno]);
    test_same(200, $reintentoReactivar['status'], 'Reactivar dos veces debe ser idempotente');
    test_same(1, test_conteo_bitacora($documento, 'REACTIVAR'), 'No debe repetirse la bitácora sin cambio real');

    // ============================================================
    // Desactivación/reactivación sobre contexto inexistente
    // ============================================================
    test_same(404, test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => 'NOEXISTE999'])['status'],
        'DELETE sin contexto debe responder 404');
    test_same(404, test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => 'NOEXISTE999'])['status'],
        'PATCH sin contexto debe responder 404');

    // ============================================================
    // Persona inactiva: 409 en toda escritura
    // ============================================================
    $inactivo = 'CP-01-' . strtoupper(bin2hex(random_bytes(4)));
    $inactivoDoc = str_replace('-', '', $inactivo);
    $ids[] = $inactivoDoc;
    $fila = test_create_comprador([], $inactivo);
    $db->prepare('UPDATE tbpersona SET tbpersonaestado = 0 WHERE tbpersonaid = :id')
        ->execute(['id' => $fila['personaId']]);

    $postInactivo = test_comprador_controller()->procesar('POST', [], test_comprador_payload($inactivo));
    test_same(409, $postInactivo['status'], 'Inscribir a una persona inactiva debe responder 409');
    $deleteInactivo = test_comprador_controller()->procesar('DELETE', [], ['identificacionNumero' => $inactivo]);
    test_same(409, $deleteInactivo['status'], 'Desactivar una persona inactiva debe responder 409');
    $patchInactivo = test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => $inactivo]);
    test_same(409, $patchInactivo['status'], 'Reactivar una persona inactiva debe responder 409');

    $db->prepare('UPDATE tbpersona SET tbpersonaestado = 1 WHERE tbpersonaid = :id')
        ->execute(['id' => $fila['personaId']]);
    test_same(200, test_comprador_controller()->procesar('PATCH', [], ['identificacionNumero' => $inactivo])['status'],
        'Restaurada la persona, la reactivación vuelve a ser posible');

    // ============================================================
    // Listado con filtro de estado
    // ============================================================
    $dos = 'CP-02-' . strtoupper(bin2hex(random_bytes(4)));
    $dosDoc = str_replace('-', '', $dos);
    $ids[] = $dosDoc;
    test_create_comprador([], $dos);

    $listaTodos = test_comprador_controller()->procesar('GET', [], []);
    test_same('tbcomprador + tbpersona', $listaTodos['body']['data']['fuente'], 'El listado declara su fuente');
    test_assert(is_array($listaTodos['body']['data']['catalogos']['tiposIdentificacion'] ?? null),
        'El listado debe incluir el catálogo de tipos de identificación');

    $listaActivo = test_comprador_controller()->procesar('GET', ['q' => $dos, 'estado' => 'ACTIVO'], []);
    test_same(1, $listaActivo['body']['data']['total'], 'Filtro ACTIVO por búsqueda');
    test_same('ACTIVO', $listaActivo['body']['data']['compradores'][0]['estado'], 'El resultado ACTIVO se refleja');

    $estadoInvalido = test_comprador_controller()->procesar('GET', ['estado' => 'INVENTADO'], []);
    test_same(422, $estadoInvalido['status'], 'Filtro de estado inválido');

    // ============================================================
    // Validación y método no permitido
    // ============================================================
    test_same(422, test_comprador_controller()->procesar('POST', [],
        test_comprador_payload(test_document(), ['identificacion' => ['tipoCodigo' => 'INVENTADO']]))['status'],
        'Rechaza tipo de identificación no admitido');
    test_same(422, test_comprador_controller()->procesar('POST', [],
        test_comprador_payload(test_document(), ['nombre' => 'AB']))['status'],
        'Rechaza nombre demasiado corto');
    test_same(422, test_comprador_controller()->procesar('POST', [],
        test_comprador_payload(test_document(), ['fincas' => []]))['status'],
        'Comprador no admite fincas: rechaza campos desconocidos');
    test_same(405, test_comprador_controller()->procesar('PUT', [], test_comprador_payload(test_document()))['status'],
        'Comprador no se edita con PUT');

    // ============================================================
    // Bitácora correctamente etiquetada
    // ============================================================
    $bitacora = $db->prepare(
        'SELECT tbbitacoraentidad, tbbitacoraorigen, tbbitacoraaccion FROM tbbitacora
         WHERE tbbitacoraregistroidentificacionnumero = :id ORDER BY tbbitacoraid'
    );
    $bitacora->execute(['id' => $documento]);
    foreach ($bitacora->fetchAll() as $filaBitacora) {
        test_same('COMPRADOR', $filaBitacora['tbbitacoraentidad'], 'Bitácora como entidad COMPRADOR');
        test_same('API_COMPRADORES', $filaBitacora['tbbitacoraorigen'], 'Bitácora con origen API_COMPRADORES');
    }

    // ============================================================
    // Coexistencia de contextos sobre la misma Persona (DEC-28)
    // ============================================================
    $multi = 'CP-03-' . strtoupper(bin2hex(random_bytes(4)));
    $multiDoc = str_replace('-', '', $multi);
    $ids[] = $multiDoc;
    $productor = test_create([], $multi);
    // La persona es única: el contexto Comprador repite sus datos personales.
    $compradorNuevo = test_create_comprador([
        'nombre' => $productor['nombre'],
        'correoElectronico' => $productor['correoElectronico'],
    ], $multi);
    test_same($productor['identificacionNumero'], $compradorNuevo['identificacionNumero'],
        'Productor y Comprador comparten la misma identificación de persona');
    $personaCompartida = $db->prepare(
        'SELECT pe.tbpersonaid FROM tbpersona pe
         WHERE pe.tbpersonaidentificacionnumero = :id'
    );
    $personaCompartida->execute(['id' => $multiDoc]);
    $personaIds = array_map('intval', $personaCompartida->fetchAll(PDO::FETCH_COLUMN));
    test_same(1, count($personaIds), 'No debe duplicarse la persona por tener dos contextos');
    test_same($personaIds[0], $compradorNuevo['personaId'],
        'El Comprador apunta a la misma tbpersona');
    $conteoProductores = $db->prepare('SELECT COUNT(*) FROM tbproductor WHERE tbpersonaid = :idProductor');
    $conteoProductores->execute(['idProductor' => $personaIds[0]]);
    test_same(1, (int) $conteoProductores->fetchColumn(), 'Un solo contexto Productor');
    $conteoCompradores = $db->prepare('SELECT COUNT(*) FROM tbcomprador WHERE tbpersonaid = :idComprador');
    $conteoCompradores->execute(['idComprador' => $personaIds[0]]);
    test_same(1, (int) $conteoCompradores->fetchColumn(), 'Un solo contexto Comprador');
    $productorSigue = test_controller()->procesar('GET', ['identificacionNumero' => $multi], []);
    test_same(200, $productorSigue['status'], 'El contexto Productor permanece consultable');

    // ============================================================
    // Servicio: la pregunta de contexto es independiente del Productor
    // ============================================================
    $servicio = new CompradorClasificacionService($db);
    test_assert($servicio->esComprador($documento), 'La persona inscrita es comprador');
    test_assert(!$servicio->esComprador('NOEXISTE999'), 'Una persona sin contexto no es comprador');

    // ============================================================
    // Endpoint por HTTP: superficie admin (DEC-30) y contrato de verbos
    // ============================================================
    $httpUrl = 'http://127.0.0.1/api/compradores.php';
    $httpLista = test_http_json('GET', null, 'application/json', $httpUrl);
    test_same(401, $httpLista['status'], 'GET por HTTP sin sesión debe responder 401 (superficie admin)');

    $httpPut = test_http_json('PUT', '{}', 'application/json', $httpUrl);
    test_same(405, $httpPut['status'], 'PUT por HTTP debe responder 405 (verbo prohibido antes de la sesión)');
    test_same('Método no permitido.', $httpPut['body']['message'], 'Mensaje de verbo prohibido');

    $inscribirSinSesion = test_http_json('POST', '{"a":1}', 'application/json', $httpUrl);
    test_same(401, $inscribirSinSesion['status'], 'Inscribirse por HTTP sin sesión debe responder 401');
    test_same(['auth' => 'SIN_SESION'], $inscribirSinSesion['body']['errors'] ?? null,
        'El 401 debe llevar el código SIN_SESION');

    // La fuente de verdad del contexto se conserva a nivel de controlador: la
    // consulta siempre llega desde tbcomprador + tbpersona.
    $listaControlador = test_comprador_controller()->procesar('GET', [], []);
    test_same('tbcomprador + tbpersona', $listaControlador['body']['data']['fuente'],
        'La fuente de verdad del contexto se expone en el listado');
} finally {
    if (isset($multiDoc)) {
        // Persona compartida Productor+Comprador: se retira primero el
        // contexto Comprador (evita huérfanos cuando Productor elimina la
        // tbpersona) y después el Productor con sus dependencias.
        test_db()->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :compartida")->execute(['compartida' => $multiDoc]);
        test_cleanup_productores([$multiDoc]);
    }
    foreach ($ids as $identificacion) {
        if ($identificacion !== ($multiDoc ?? null)) {
            test_cleanup_compradores([$identificacion]);
        }
    }
}

echo "OK comprador_test: contexto de Persona, inscripción idempotente, desactivación/reactivación lógica, "
    . "409 por persona inactiva, coexistencia Productor+Comprador y bitácora COMPRADOR.\n";