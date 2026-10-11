<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/AnimalVacunacion.php';
require_once dirname(__DIR__) . '/Application/Controller/MiAnimalesController.php';
require_once dirname(__DIR__) . '/Application/Controller/MiAnimalesVacunasController.php';
require_once dirname(__DIR__) . '/Application/Controller/AdminCatalogoController.php';
require_once dirname(__DIR__) . '/Application/Service/SupabaseStorage.php';
require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';
foreach (['Vehiculo', 'CompraSolicitud', 'TransportistaOferta', 'PagoMetodo', 'PublicacionInteraccion', 'PersonaTelefonoHistorico', 'RegistroConsulta'] as $modelo) {
    require_once dirname(__DIR__) . "/Application/Model/{$modelo}.php";
}
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';
foreach (['Notificacion', 'Subasta'] as $modelo) require_once dirname(__DIR__) . "/Application/Model/{$modelo}.php";
require_once dirname(__DIR__) . '/Application/Controller/SubastasController.php';
foreach (['MiVehiculosController', 'MiOfertasController', 'AdminPublicacionController', 'AdminDocumentoController', 'MiPerfilController', 'MiFincasController', 'SolicitudesCompraController', 'PublicacionInteraccionController',
          'FletesController', 'RegistroPublicoController'] as $controlador) {
    require_once dirname(__DIR__) . "/Application/Controller/{$controlador}.php";
}

use Application\Auth\ActorContext;
use Application\Controller\AdminCatalogoController;
use Application\Controller\AnimalPublicacionController;
use Application\Controller\MiAnimalesController;
use Application\Controller\MiAnimalesVacunasController;
use Application\Controller\FletesController;
use Application\Controller\MiVehiculosController;
use Application\Controller\MiOfertasController;
use Application\Controller\AdminPublicacionController;
use Application\Controller\AdminDocumentoController;
use Application\Controller\MiFincasController;
use Application\Controller\MiPerfilController;
use Application\Controller\PublicacionInteraccionController;
use Application\Controller\RegistroPublicoController;
use Application\Controller\SolicitudesCompraController;

/**
 * Entradas hostiles en los formularios del cliente y del admin. Por cada campo se manda, uno a la vez, un valor
 * que ningún formulario envía (lista, objeto, booleano, texto enorme y, en los números, valores fuera de rango).
 * Ninguno puede: terminar en error de servidor, dejar un aviso de PHP (rompe el JSON), guardarse, o responder
 * sin decir qué campo está mal y por qué.
 */

$identificaciones = [];
$fallas = [];
set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    throw new ErrorException("Aviso de PHP: {$mensaje} en " . basename($archivo) . ":{$linea}", 0, $nivel, $archivo, $linea);
});

$MALOS = ['lista' => ['x'], 'objeto' => ['a' => 1], 'booleano' => true, 'texto enorme' => str_repeat('9', 5000)];
$MALOS_NUMERO = $MALOS + ['negativo' => -1, 'enorme' => 1e15];
// Los ids son INTEGER en Postgres: uno más grande hace fallar la consulta.
$MALOS_ID = $MALOS_NUMERO + ['decimal' => 1.5, 'mayor que INTEGER' => 2147483648];

/** Manda cada valor malo en cada campo y anota lo que no se rechace bien. */
$atacar = static function (string $nombre, callable $llamar, array $base, array $campos, array $numericos = []) use (&$fallas, $MALOS, $MALOS_NUMERO, $MALOS_ID): void {
    foreach ($campos as $campo) {
        $valores = str_ends_with($campo, 'Id') || $campo === 'id' ? $MALOS_ID : (in_array($campo, $numericos, true) ? $MALOS_NUMERO : $MALOS);
        if (in_array($campo, ['latitud', 'longitud'], true)) unset($valores['negativo']);
        foreach ($valores as $tipo => $valor) {
            $caso = "{$nombre} · {$campo} = {$tipo}";
            try {
                $r = $llamar([$campo => $valor] + $base);
            } catch (Throwable $e) {
                $fallas[] = "{$caso}: excepción " . get_class($e) . ' — ' . $e->getMessage();
                continue;
            }
            $estado = $r['status'];
            if ($estado < 400 || $estado >= 500) {
                $fallas[] = "{$caso}: respondió {$estado} (debía rechazarse con 4xx)";
                continue;
            }
            $errores = $r['body']['errors'] ?? [];
            $mensajes = array_merge([(string) ($r['body']['message'] ?? '')], array_map('strval', array_values($errores)));
            foreach ($mensajes as $mensaje) {
                if (trim($mensaje) === '' || preg_match('/Array|SQLSTATE|Exception|stdClass|null given/i', $mensaje)) {
                    $fallas[] = "{$caso}: mensaje poco claro «{$mensaje}»";
                }
            }
            $apunta = array_filter(array_keys($errores), static fn ($k): bool => $k === $campo
                || str_starts_with((string) $k, "{$campo}.") || str_ends_with((string) $k, ".{$campo}"));
            if ($estado === 422 && $apunta === []) {
                $fallas[] = "{$caso}: el 422 no dice que el error es de «{$campo}» (" . json_encode($errores, JSON_UNESCAPED_UNICODE) . ')';
            }
        }
    }
};

try {
    $db = test_db();
    $v = test_create_completo(['fincas' => [['nombre' => 'Finca Validación']]]);
    $identificaciones[] = $v['identificacionNumero'];
    $fila = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $fila->execute(['id' => $v['identificacionNumero']]);
    $persona = $fila->fetch();
    $actor = ActorContext::personaAutenticada((int) $persona['tbpersonaid'], 'supabase-' . test_token('val'), $persona['tbpersonacorreoelectronico'], 'authenticated');
    $productorId = (int) $db->query('SELECT tbproductorid FROM tbproductor WHERE tbpersonaid = ' . (int) $persona['tbpersonaid'])->fetchColumn();

    $publicaciones = new AnimalPublicacionController($db, test_token('val-pub'), $actor);
    $animales = new MiAnimalesController($db, $actor, test_token('val-ani'));
    $vacunas = new MiAnimalesVacunasController($db, $actor, test_token('val-vac'));
    $catalogos = new AdminCatalogoController($db, test_token('val-cat'));

    // Fixtures legítimas: una publicación y un animal en inventario.
    $publicada = $publicaciones->procesar('POST', [], ['fincaNombre' => 'Finca Validación', 'titulo' => 'Validación', 'precio' => 1000]);
    test_same(201, $publicada['status'], 'La fixture publica');
    $publicacionId = $publicada['body']['data']['publicacionId'];
    $registrado = $animales->procesar('POST', ['peso' => 300]);
    test_same(201, $registrado['status'], 'La fixture registra un animal');
    $animalId = $registrado['body']['data']['animal']['animalId'];

    $atacar('Publicar', fn (array $c): array => $publicaciones->procesar('POST', [], $c),
        ['fincaNombre' => 'Finca Validación', 'titulo' => 'Validación'],
        ['fincaNombre', 'titulo', 'descripcion', 'precio', 'peso', 'edadMeses', 'proposito', 'raza', 'sexo', 'especieId',
         'tipoId', 'razaId', 'fechaNacimiento', 'partos', 'arete', 'loteCantidad', 'imagenUrl', 'animalIdentificacion'],
        ['precio', 'peso', 'edadMeses', 'especieId', 'tipoId', 'razaId', 'partos', 'loteCantidad']);
    $atacar('Editar publicación', fn (array $c): array => $publicaciones->procesar('PATCH', [], $c),
        ['publicacionId' => $publicacionId, 'titulo' => 'Validación'],
        ['publicacionId', 'titulo', 'descripcion', 'precio', 'imagenUrl', 'estado', 'motivo'], ['publicacionId', 'precio']);
    $atacar('Registrar animal', fn (array $c): array => $animales->procesar('POST', $c), [],
        ['especieId', 'tipoId', 'razaId', 'sexo', 'arete', 'fechaNacimiento', 'partos', 'peso', 'edadMeses', 'proposito', 'raza', 'loteCantidad'],
        ['especieId', 'tipoId', 'razaId', 'partos', 'peso', 'edadMeses', 'loteCantidad']);
    $atacar('Publicar animal', fn (array $c): array => $animales->procesar('PATCH', $c),
        ['animalId' => $animalId, 'accion' => 'PUBLICAR', 'fincaNombre' => 'Finca Validación', 'titulo' => 'Validación'],
        ['animalId', 'accion', 'fincaNombre', 'titulo', 'precio', 'descripcion', 'imagenUrl'], ['animalId', 'precio']);
    $atacar('Editar animal', fn (array $c): array => $animales->procesar('PATCH', $c), ['animalId' => $animalId, 'accion' => 'EDITAR'],
        ['animalId', 'especieId', 'tipoId', 'razaId', 'sexo', 'arete', 'fechaNacimiento', 'partos', 'peso', 'edadMeses', 'proposito', 'raza'],
        ['animalId', 'especieId', 'tipoId', 'razaId', 'partos', 'peso', 'edadMeses']);
    $atacar('Pesar animal', fn (array $c): array => $animales->procesar('PATCH', $c), ['animalId' => $animalId, 'accion' => 'PESAR', 'peso' => 310],
        ['animalId', 'peso', 'fecha'], ['animalId', 'peso']);
    $atacar('Vacuna', fn (array $c): array => $vacunas->procesar('POST', [], $c),
        ['animalId' => $animalId, 'vacunaId' => 1, 'fecha' => gmdate('Y-m-d', time() - 86400)],
        ['animalId', 'vacunaId', 'fecha', 'dosis', 'lote', 'aplicadaPor', 'proximaDosis', 'observaciones'], ['animalId', 'vacunaId']);
    $atacar('Catálogo admin', fn (array $c): array => $catalogos->procesar('POST', $c),
        ['catalogo' => 'RAZA', 'nombre' => 'Validación ' . test_token('r'), 'especieId' => 1],
        ['catalogo', 'nombre', 'especieId'], ['especieId']);
    $atacar('Editar catálogo admin', fn (array $c): array => $catalogos->procesar('PATCH', $c),
        ['catalogo' => 'VACUNA', 'id' => 1], ['id', 'nombre'], ['id']);

    // Subastas: abrir, pujar y comentar (sin avisos reales a Supabase).
    $subastas = new Application\Controller\SubastasController($db, $actor, test_token('val-sub'), new Application\Service\TiempoReal(static function (): void {}));
    $atacar('Abrir subasta', fn (array $c): array => $subastas->procesar('POST', [], $c),
        ['animalId' => $animalId, 'fincaNombre' => 'Finca Validación', 'titulo' => 'Validación', 'precioBase' => 1000, 'incrementoMinimo' => 100, 'duracionMinutos' => 60],
        ['animalId', 'fincaNombre', 'titulo', 'descripcion', 'imagenUrl', 'precioBase', 'incrementoMinimo', 'duracionMinutos'],
        ['animalId', 'precioBase', 'incrementoMinimo', 'duracionMinutos']);
    $atacar('Pujar', fn (array $c): array => $subastas->procesar('PATCH', [], $c), ['subastaId' => 999999, 'accion' => 'PUJAR', 'monto' => 1000],
        ['subastaId', 'monto'], ['subastaId', 'monto']);
    $atacar('Comentar subasta', fn (array $c): array => $subastas->procesar('PATCH', [], $c), ['subastaId' => 999999, 'accion' => 'COMENTAR', 'texto' => 'Hola'],
        ['subastaId', 'texto'], ['subastaId']);
    $atacar('Lista de subastas', fn (array $c): array => $subastas->procesar('GET', $c), [], ['vista', 'q', 'pagina', 'tamanoPagina'], ['pagina', 'tamanoPagina']);

    $perfil = new MiPerfilController($db, $actor, test_token('val-perfil'));
    $atacar('Mi perfil', fn (array $c): array => $perfil->procesar('PATCH', $c), [],
        ['nombre', 'identificacion', 'correoElectronico', 'alias', 'telefono', 'fotoUrl', 'documentoRuta', 'documentoLectura']);
    $fincas = new MiFincasController($db, $actor, test_token('val-finca'));
    $atacar('Agregar finca', fn (array $c): array => $fincas->procesar('POST', $c), ['nombreFinca' => 'Finca ' . test_token('f')],
        ['nombreFinca', 'direccionFinca']);
    $solicitudes = new SolicitudesCompraController($db, $actor, test_token('val-sol'));
    $atacar('Solicitar compra', fn (array $c): array => $solicitudes->procesar('POST', $c), ['publicacionId' => $publicacionId],
        ['publicacionId', 'ofertaId', 'pagoMetodoId', 'mensaje']);
    $interacciones = new PublicacionInteraccionController($db, $actor, test_token('val-int'));
    $atacar('Me interesa', fn (array $c): array => $interacciones->procesar('POST', $c), ['publicacionId' => $publicacionId, 'tipo' => 'ME_INTERESA'],
        ['publicacionId', 'tipo', 'accion']);
    // Las consultas (?a[]=x) también pueden traer listas.
    $fletes = new FletesController($db, $actor);
    $atacar('Buscar fletes', fn (array $c): array => $fletes->procesar('GET', $c), ['latitud' => '9.93', 'longitud' => '-84.08'],
        ['latitud', 'longitud', 'capacidadMinima', 'pagina', 'tamanoPagina', 'publicacionId'],
        ['latitud', 'longitud', 'capacidadMinima', 'pagina', 'tamanoPagina']);
    $atacar('Explorar', fn (array $c): array => $publicaciones->procesar('GET', $c, []), [],
        ['q', 'estado', 'pagina', 'tamanoPagina', 'latitud', 'longitud', 'ubicacion', 'precioMin', 'precioMax'],
        ['pagina', 'tamanoPagina', 'latitud', 'longitud', 'precioMin', 'precioMax']);

    // Registro público: cada dato de la persona, uno a la vez.
    $correo = strtolower(test_token('val-reg')) . '@example.test';
    $registro = new RegistroPublicoController($db, ActorContext::usuarioVerificado(null, 'supabase-' . test_token('reg'), $correo, 'authenticated'), test_token('val-reg'));
    $personaBase = ['identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => test_document(), 'nombres' => 'Val', 'apellidos' => 'Idación',
        'alias' => 'Val', 'telefono' => '+506 8888-1111', 'correoElectronico' => $correo];
    $identificaciones[] = $personaBase['identificacionNumero'];
    $atacar('Registro', fn (array $c): array => $registro->procesar('POST', ['persona' => array_merge($personaBase, $c), 'capacidades' => ['COMPRADOR'], 'fincas' => []]),
        [], array_keys($personaBase));

    // Transportista: vehículos y ofertas de flete.
    $correoT = strtolower(test_token('val-t')) . '@example.test';
    $idT = test_document();
    $identificaciones[] = $idT;
    $alta = (new RegistroPublicoController($db, ActorContext::usuarioVerificado(null, 'supabase-' . test_token('t'), $correoT, 'authenticated'), test_token('val-t')))
        ->procesar('POST', ['persona' => ['identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $idT, 'nombres' => 'Val', 'apellidos' => 'Transporte',
            'alias' => 'Val T', 'telefono' => '+506 8888-2222', 'correoElectronico' => $correoT], 'capacidades' => ['TRANSPORTISTA'], 'fincas' => []]);
    test_assert(in_array($alta['status'], [200, 201], true), 'La fixture registra un transportista: ' . json_encode($alta['body'], JSON_UNESCAPED_UNICODE));
    $personaT = (int) $db->query("SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = " . $db->quote($idT))->fetchColumn();
    $actorT = ActorContext::personaAutenticada($personaT, 'supabase-' . test_token('t2'), $correoT, 'authenticated');
    $vehiculos = new MiVehiculosController($db, $actorT, test_token('val-veh'));
    $vehiculo = $vehiculos->procesar('POST', ['placa' => 'VAL-' . strtoupper(bin2hex(random_bytes(2))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión']);
    test_same(201, $vehiculo['status'], 'La fixture registra un vehículo');
    $vehiculoId = $vehiculo['body']['data']['vehiculo']['vehiculoId'];
    $atacar('Agregar vehículo', fn (array $c): array => $vehiculos->procesar('POST', $c),
        ['placa' => 'VAL-' . strtoupper(bin2hex(random_bytes(2))), 'vin' => 'VIN-' . bin2hex(random_bytes(4)), 'modelo' => 'Camión'],
        ['placa', 'vin', 'modelo', 'fotoUrl']);
    $ofertas = new MiOfertasController($db, $actorT, test_token('val-of'));
    $zona = ['provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen', 'latitud' => 9.93, 'longitud' => -84.08];
    $atacar('Ofrecer flete', fn (array $c): array => $ofertas->procesar('POST', $c),
        ['vehiculoId' => $vehiculoId, 'direccion' => $zona, 'radioKm' => 20, 'capacidad' => 5],
        ['vehiculoId', 'direccion', 'radioKm', 'capacidad', 'precio', 'descripcion'], ['radioKm', 'capacidad', 'precio']);

    // Moderación del admin.
    $moderar = new AdminPublicacionController($db, test_token('val-mod'));
    $atacar('Moderar publicación', fn (array $c): array => $moderar->procesar('PATCH', [], $c),
        ['publicacionId' => $publicacionId, 'estado' => 'PAUSADO', 'motivo' => 'Prueba'], ['publicacionId', 'estado', 'motivo']);
    $documentos = new AdminDocumentoController($db, test_token('val-doc'));
    $atacar('Revisar documento', fn (array $c): array => $documentos->procesar('PATCH', [], $c),
        ['personaId' => (int) $persona['tbpersonaid'], 'estado' => 'RECHAZADO', 'motivo' => 'Prueba'], ['personaId', 'estado', 'motivo']);

    // Un formulario vacío dice qué falta (antes: "El cuerpo debe ser un objeto JSON.").
    $vacio = $animales->procesar('POST', []);
    test_same(422, $vacio['status'], 'Registrar un animal sin datos se rechaza');
    test_assert(str_contains($vacio['body']['message'], 'sin datos'), 'El mensaje dice que faltan datos');
    test_same(422, $animales->procesar('POST', ['color' => 'café'])['status'], 'Un campo desconocido se rechaza');

    // Todos los errores llegan juntos (antes solo el primero: el peso, y el precio recién al reenviar).
    $varios = $publicaciones->procesar('POST', [], ['fincaNombre' => 'Finca Validación', 'titulo' => '', 'peso' => 6000, 'precio' => 99999999999]);
    $claves = array_keys($varios['body']['errors'] ?? []);
    sort($claves);
    test_same(['peso', 'precio', 'titulo'], $claves, 'Publicar devuelve todos los errores a la vez');
    test_same('No puede ser mayor que 9.999.999.999,99.', $varios['body']['errors']['precio'] ?? null, 'El precio dice su máximo');
    // Los números en su borde: el tope se acepta, uno más se rechaza con el máximo en el mensaje.
    $tope = $animales->procesar('POST', ['peso' => 5001]);
    test_same('No puede ser mayor que 5.000.', $tope['body']['errors']['peso'] ?? null, 'El peso dice cuál es el máximo');

    test_same([], $fallas, "Entradas mal rechazadas:\n  - " . implode("\n  - ", $fallas) . "\n");
} finally {
    restore_error_handler();
    $db = test_db();
    $ids = array_map('intval', $db->query('SELECT tbanimalid FROM tbanimal WHERE tbproductorid = ' . (int) ($productorId ?? 0)
        . ' UNION SELECT tbanimalid FROM tbanimalpublicacion WHERE tbproductorvendedorid = ' . (int) ($productorId ?? 0))->fetchAll(PDO::FETCH_COLUMN));
    if ($ids !== []) {
        $m = implode(',', $ids);
        $pubs = implode(',', array_map('intval', $db->query("SELECT tbanimalpublicacionid FROM tbanimalpublicacion WHERE tbanimalid IN ({$m})")->fetchAll(PDO::FETCH_COLUMN)) ?: [0]);
        foreach (["tbanimalpublicacionanimal WHERE tbanimalpublicacionid IN ({$pubs})", "tbanimalpublicacionestadoperiodo WHERE tbanimalpublicacionid IN ({$pubs})",
                  "tbanimalpublicacion WHERE tbanimalpublicacionid IN ({$pubs})", "tbanimalvacunacion WHERE tbanimalid IN ({$m})",
                  "tbanimalproduccionsalud WHERE tbanimalid IN ({$m})", "tbanimal WHERE tbanimalid IN ({$m})"] as $donde) {
            $db->exec("DELETE FROM {$donde}");
        }
    }
    $db->exec("DELETE FROM tbraza WHERE tbrazanombre LIKE 'Validación %'");
    $db->exec("DELETE FROM tbbitacora WHERE tbbitacoraorigen IN ('API_ADMIN_CATALOGOS') AND tbbitacoradatosnuevos LIKE '%Validación %'");
    if (isset($persona)) $db->exec('DELETE FROM tbbitacora WHERE tbbitacorausuarioid = ' . (int) $persona['tbpersonaid']);
    // Transportista y comprador que crea el registro (con su vehículo, su oferta y su histórico de teléfono).
    $marcas = implode(',', array_map([$db, 'quote'], $identificaciones ?: ['-']));
    $personas = "SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marcas})";
    $transportistas = "SELECT tbtransportistaid FROM tbtransportista WHERE tbpersonaid IN ({$personas})";
    $compradores = "SELECT tbcompradorid FROM tbcomprador WHERE tbpersonaid IN ({$personas})";
    foreach (["DELETE FROM tbbitacora WHERE tbbitacorausuarioid IN ({$personas})",
              "DELETE FROM tbtransportistaoferta WHERE tbtransportistaid IN ({$transportistas})",
              "DELETE v FROM tbvehiculo v INNER JOIN tbtransportistavehiculo tv ON tv.tbvehiculoid = v.tbvehiculoid WHERE tv.tbtransportistaid IN ({$transportistas})",
              "DELETE FROM tbtransportistavehiculo WHERE tbtransportistaid IN ({$transportistas})",
              "DELETE FROM tbtransportistaestadoperiodo WHERE tbtransportistaid IN ({$transportistas})",
              "DELETE FROM tbtransportista WHERE tbpersonaid IN ({$personas})",
              "DELETE FROM tbcomprasolicitud WHERE tbcompradorid IN ({$compradores})",
              "DELETE FROM tbcompradorpersonatelefonohistorico WHERE tbcompradorid IN ({$compradores})",
              "DELETE FROM tbcomprador WHERE tbpersonaid IN ({$personas})"] as $sentencia) {
        $db->exec($sentencia);
    }
    test_cleanup_productores($identificaciones);
}

echo "OK validacion_entradas_test: listas, objetos, booleanos, textos enormes y números fuera de rango se rechazan con 4xx y un mensaje por campo.\n";
