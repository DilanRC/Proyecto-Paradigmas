<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';
require_once dirname(__DIR__) . '/Application/Model/CompraSolicitud.php';
require_once dirname(__DIR__) . '/Application/Model/PagoMetodo.php';
require_once dirname(__DIR__) . '/Application/Model/TransportistaOferta.php';
require_once dirname(__DIR__) . '/Application/Model/Vehiculo.php';
require_once dirname(__DIR__) . '/Application/Controller/SolicitudesCompraController.php';
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';
require_once dirname(__DIR__) . '/Application/Controller/CatalogosController.php';

use Application\Auth\ActorContext;
use Application\Controller\AnimalPublicacionController;
use Application\Controller\CatalogosController;
use Application\Controller\RegistroPublicoController;
use Application\Controller\SolicitudesCompraController;
use Application\Model\AnimalCatalogo;
use Application\Service\AnimalValidacionService;

/**
 * Modelo de animal (P2-2, DEC-ANIMAL-001): catálogos, validaciones, arete SENASA, lote y venta de lote.
 */

$identificaciones = [];
$vendedores = [];
$animalIds = [];

function ma_ids(string $sql, array $parametros = []): array
{
    $sentencia = test_db()->prepare($sql);
    $sentencia->execute($parametros);
    return array_map('intval', $sentencia->fetchAll(PDO::FETCH_COLUMN));
}

function ma_cleanup(array $identificaciones, array $vendedores, array $animalIds): void
{
    $db = test_db();
    $todas = array_values(array_unique(array_merge($identificaciones, $vendedores)));
    $animalIds = array_values(array_unique(array_filter($animalIds)));
    if ($animalIds !== []) {
        $m = implode(',', array_fill(0, count($animalIds), '?'));
        $publicacionIds = ma_ids("SELECT tbanimalpublicacionid FROM tbanimalpublicacion WHERE tbanimalid IN ({$m})", $animalIds);
        if ($publicacionIds !== []) {
            $mp = implode(',', array_fill(0, count($publicacionIds), '?'));
            $solicitudes = array_map('strval', ma_ids("SELECT tbcomprasolicitudid FROM tbcomprasolicitud WHERE tbanimalpublicacionid IN ({$mp})", $publicacionIds));
            if ($solicitudes !== []) {
                $ms = implode(',', array_fill(0, count($solicitudes), '?'));
                $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'COMPRA_SOLICITUD' AND tbbitacoraregistroidentificacionnumero IN ({$ms})")->execute($solicitudes);
                $db->prepare("DELETE FROM tbcomprasolicitud WHERE tbcomprasolicitudid IN ({$ms})")->execute($solicitudes);
            }
            $db->prepare("DELETE FROM tbanimalpublicacionanimal WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
            $db->prepare("DELETE FROM tbanimalpublicacionestadoperiodo WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
            $db->prepare("DELETE FROM tbanimalpublicacion WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
        }
        $db->prepare("DELETE FROM tbventa WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $db->prepare("DELETE FROM tbcompra WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $db->prepare("DELETE FROM tbanimalproduccionsalud WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $db->prepare("DELETE FROM tbanimal WHERE tbanimalid IN ({$m})")->execute($animalIds);
    }
    if ($todas === []) return;
    $marks = implode(',', array_fill(0, count($todas), '?'));
    $compradores = "SELECT c.tbcompradorid FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($todas);
    $db->prepare("DELETE h FROM tbcompradorpersonatelefonohistorico h WHERE h.tbcompradorid IN ({$compradores})")->execute($todas);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
    test_cleanup_productores($vendedores);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
}

try {
    // --- Arete SENASA: reglas puras ---
    foreach (['1880010002345', '188 0 01 0002345', '188-0-01-0002345', '1880070000001'] as $bueno) {
        $r = AnimalValidacionService::arete($bueno);
        test_assert($r['error'] === null && strlen((string) $r['arete']) === 13 && ctype_digit((string) $r['arete']), "Arete válido: {$bueno}");
    }
    test_same('1880010002345', AnimalValidacionService::arete('188-0-01-0002345')['arete'], 'Se guardan solo los dígitos');
    test_same(null, AnimalValidacionService::arete('   ')['arete'], 'El arete vacío es opcional');
    foreach (['188001000234', '18800100023456', '1990010002345', '1880000002345', '1880080002345', '188001000234A', 'SOL-0001'] as $malo) {
        test_assert(AnimalValidacionService::arete($malo)['error'] !== null, "Arete inválido: {$malo}");
    }

    $db = test_db();
    $catalogo = new AnimalCatalogo($db);

    // --- Catálogos ---
    $nombres = static fn (array $filas): array => array_column($filas, 'nombre');
    $bovino = array_values(array_filter($catalogo->especies(), static fn (array $e): bool => $e['nombre'] === 'Bovino'))[0] ?? null;
    test_assert($bovino !== null, 'La semilla trae la especie Bovino');
    $porcino = array_values(array_filter($catalogo->especies(), static fn (array $e): bool => $e['nombre'] === 'Porcino'))[0];
    $tiposBovino = $catalogo->tipos($bovino['especieId']);
    $razasBovino = $catalogo->razas($bovino['especieId']);
    foreach (['Vaca', 'Toro', 'Ternero', 'Novillo', 'Vaquilla'] as $tipo) test_assert(in_array($tipo, $nombres($tiposBovino), true), "Tipo bovino {$tipo}");
    foreach (['Brahman', 'Holstein', 'Jersey', 'Pardo Suizo', 'Angus', 'Nelore', 'Gyr', 'Girolando', 'Criollo', 'Mestizo'] as $raza) test_assert(in_array($raza, $nombres($razasBovino), true), "Raza bovina {$raza}");
    $porId = static fn (array $filas, string $nombre): array => array_values(array_filter($filas, static fn (array $f): bool => $f['nombre'] === $nombre))[0];
    $vaca = $porId($tiposBovino, 'Vaca');
    $toro = $porId($tiposBovino, 'Toro');
    $brahman = $porId($razasBovino, 'Brahman');
    $landrace = $porId($catalogo->razas($porcino['especieId']), 'Landrace');
    test_same('H', $vaca['sexo'], 'Vaca es hembra');
    test_same('M', $toro['sexo'], 'Toro es macho');
    test_assert(count($catalogo->tipos()) > count($tiposBovino), 'Sin especieId vienen los tipos de todas');

    $catalogos = new CatalogosController($db);
    $respuesta = $catalogos->procesar('GET', ['especieId' => (string) $bovino['especieId']]);
    test_same(200, $respuesta['status'], 'GET catálogos');
    test_same(count($tiposBovino), count($respuesta['body']['data']['tipos']), 'Filtra los tipos por especie');
    test_same(count($catalogo->especies()), count($respuesta['body']['data']['especies']), 'Las especies vienen completas');
    test_same(422, $catalogos->procesar('GET', ['especieId' => 'x'])['status'], 'especieId inválido es 422');
    test_same(405, $catalogos->procesar('POST')['status'], 'Solo GET');

    // Una raza desactivada por un administrador deja de ofrecerse y de aceptarse.
    $db->prepare('UPDATE tbraza SET tbrazaactivo = 0 WHERE tbrazaid = :id')->execute(['id' => $brahman['razaId']]);
    test_assert(!in_array('Brahman', $nombres($catalogo->razas($bovino['especieId'])), true), 'Una raza inactiva no se ofrece');
    $db->prepare('UPDATE tbraza SET tbrazaactivo = 1 WHERE tbrazaid = :id')->execute(['id' => $brahman['razaId']]);

    // --- Vendedor con finca y publicador ---
    $vendedor = test_create_completo(['fincas' => [['nombre' => 'Finca Modelo']]]);
    $vendedores[] = $vendedor['identificacionNumero'];
    $respuestaFinca = test_finca_controller()->procesarDireccion('POST', [], [
        'identificacionNumero' => $vendedor['identificacionNumero'], 'nombreFinca' => 'Finca Modelo',
        'direccionFinca' => test_direccion_payload(['provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen']),
    ]);
    test_same(201, $respuestaFinca['status'], 'La fixture registra la dirección de la finca');
    $persona = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $persona->execute(['id' => $vendedor['identificacionNumero']]);
    $filaVendedor = $persona->fetch();
    $actor = ActorContext::personaAutenticada((int) $filaVendedor['tbpersonaid'], 'supabase-' . test_token('vendedor'), $filaVendedor['tbpersonacorreoelectronico'], 'authenticated');
    $publicador = new AnimalPublicacionController($db, test_token('publicar'), $actor);
    $base = ['fincaNombre' => 'Finca Modelo', 'titulo' => 'Modelo animal', 'precio' => 900000];
    $publicar = static function (array $extra) use ($publicador, $base, &$animalIds): array {
        $r = $publicador->procesar('POST', [], $extra + $base);
        foreach (($r['body']['data']['animalIds'] ?? []) as $id) $animalIds[] = (int) $id;
        if (isset($r['body']['data']['animalId'])) $animalIds[] = (int) $r['body']['data']['animalId'];
        return $r;
    };
    $rechaza = static function (array $r, string $campo, int $estado, string $mensaje): void {
        test_same($estado, $r['status'], $mensaje);
        test_assert(isset($r['body']['errors'][$campo]), "{$mensaje}: el error apunta a {$campo}");
    };

    // --- Validaciones ---
    $rechaza($publicar(['tipoId' => $vaca['tipoId']]), 'especieId', 422, 'El tipo exige la especie');
    $rechaza($publicar(['especieId' => $porcino['especieId'], 'tipoId' => $vaca['tipoId']]), 'tipoId', 422, 'El tipo debe pertenecer a la especie');
    $rechaza($publicar(['especieId' => $bovino['especieId'], 'razaId' => $landrace['razaId']]), 'razaId', 422, 'La raza debe pertenecer a la especie');
    $rechaza($publicar(['especieId' => 999999]), 'especieId', 422, 'Especie inexistente');
    $rechaza($publicar(['especieId' => $bovino['especieId'], 'tipoId' => $toro['tipoId'], 'sexo' => 'HEMBRA']), 'sexo', 422, 'El sexo debe coincidir con el tipo');
    $rechaza($publicar(['especieId' => $bovino['especieId'], 'tipoId' => $toro['tipoId'], 'partos' => 1]), 'partos', 422, 'Un macho no tiene partos');
    $rechaza($publicar(['sexo' => 'HEMBRA', 'partos' => -1]), 'partos', 422, 'Los partos no son negativos');
    $rechaza($publicar(['sexo' => 'HEMBRA', 'partos' => 'dos']), 'partos', 422, 'Los partos son un entero');
    $rechaza($publicar(['partos' => 2]), 'partos', 422, 'Sin sexo hembra no hay partos');
    $rechaza($publicar(['fechaNacimiento' => gmdate('Y-m-d', time() + 3 * 86400)]), 'fechaNacimiento', 422, 'La fecha de nacimiento no es futura');
    $rechaza($publicar(['fechaNacimiento' => '2024-02-31']), 'fechaNacimiento', 422, 'Fecha imposible');
    $rechaza($publicar(['fechaNacimientoEstimada' => true]), 'fechaNacimientoEstimada', 422, 'La estimación necesita fecha');
    $rechaza($publicar(['arete' => '123']), 'arete', 422, 'Arete con formato inválido');
    $rechaza($publicar(['arete' => '1880890002345']), 'arete', 422, 'Provincia de arete inválida');
    $rechaza($publicar(['arete' => '1880010002345', 'loteCantidad' => 3]), 'arete', 422, 'Un lote no lleva arete');
    $rechaza($publicar(['loteCantidad' => 1000]), 'loteCantidad', 422, 'Lote demasiado grande');
    $rechaza($publicar(['loteCantidad' => 0]), 'loteCantidad', 422, 'El lote es de al menos 1');
    $rechaza($publicar(['sexo' => 'HEMBRA', 'partos' => 1, 'loteCantidad' => 2]), 'partos', 422, 'Los partos no son de un lote');

    // --- Animal completo ---
    $arete = '1880010' . substr(str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 0, 6);
    $completo = $publicar([
        'titulo' => 'Vaca Brahman con todo', 'especieId' => $bovino['especieId'], 'tipoId' => $vaca['tipoId'], 'razaId' => $brahman['razaId'],
        'fechaNacimiento' => '2022-05-10', 'fechaNacimientoEstimada' => true, 'partos' => 2, 'arete' => substr_replace($arete, '-', 3, 0),
        'proposito' => 'LECHE', 'peso' => 420.5,
    ]);
    test_same(201, $completo['status'], 'Un animal con el modelo completo se publica: ' . json_encode($completo['body']));
    test_same(1, $completo['body']['data']['loteCantidad'], 'Un animal no es lote');
    test_assert(!isset($completo['body']['data']['animalIds']), 'Un animal no lista ids de lote');
    $animalId = (int) $completo['body']['data']['animalId'];
    $fila = $db->prepare('SELECT * FROM tbanimal WHERE tbanimalid = :id');
    $fila->execute(['id' => $animalId]);
    $fila = $fila->fetch();
    test_same((int) $bovino['especieId'], (int) $fila['tbespecieid'], 'Guarda la especie');
    test_same((int) $vaca['tipoId'], (int) $fila['tbanimaltipoid'], 'Guarda el tipo');
    test_same((int) $brahman['razaId'], (int) $fila['tbrazaid'], 'Guarda la raza de catálogo');
    test_same('Brahman', $fila['tbanimalraza'], 'El texto de raza se llena con la del catálogo (compatibilidad)');
    test_same('HEMBRA', $fila['tbanimalsexo'], 'El sexo sale del tipo');
    test_same('2022-05-10', $fila['tbanimalfechanacimiento'], 'Guarda la fecha de nacimiento');
    test_same(1, (int) $fila['tbanimalfechanacimientoestimada'], 'Marca la fecha como estimada');
    test_same(2, (int) $fila['tbanimalpartos'], 'Guarda los partos');
    test_same(substr($arete, 0, 13), $fila['tbanimalidentificacion'], 'El arete se guarda solo con dígitos');
    test_same('PUBLICADO', $fila['tbanimalestado'], 'El animal publicado queda PUBLICADO');
    test_assert((int) $fila['tbproductorid'] > 0, 'Guarda el dueño explícito');

    // Mismo arete vigente: 409; ya vendido: se permite de nuevo.
    $repetido = $publicar(['arete' => $arete, 'titulo' => 'Otra con el mismo arete']);
    $rechaza($repetido, 'arete', 409, 'No hay dos animales vigentes con el mismo arete');
    $animales = new Application\Model\AnimalComercial($db);
    $db->beginTransaction();
    $animales->marcarEstadoAnimales([$animalId], 'VENDIDO');
    $db->commit();
    test_assert(!$animales->existeAreteVigente($arete), 'Un animal vendido libera su arete');

    // La API de lectura devuelve el modelo y es compatible hacia atrás.
    $lista = $publicador->procesar('GET', ['mias' => 'true', 'estado' => 'ACTIVO', 'q' => 'Vaca Brahman con todo'], []);
    test_same(1, count($lista['body']['data']['publicaciones']), 'La lectura devuelve la publicación');
    $item = $lista['body']['data']['publicaciones'][0];
    test_same(1, $item['loteCantidad'], 'loteCantidad = 1 para un animal');
    test_same('Bovino', $item['animal']['especie'], 'Devuelve la especie');
    test_same('Vaca', $item['animal']['tipo'], 'Devuelve el tipo');
    test_same('Brahman', $item['animal']['raza'], 'La raza sigue en animal.raza');
    test_same('2022-05-10', $item['animal']['fechaNacimiento'], 'Devuelve la fecha de nacimiento');
    test_same(true, $item['animal']['fechaNacimientoEstimada'], 'Devuelve si es estimada');
    test_same(2, $item['animal']['partos'], 'Devuelve los partos');
    foreach (['identificacion', 'sexo', 'edadMeses', 'peso', 'proposito'] as $clave) test_assert(array_key_exists($clave, $item['animal']), "Conserva animal.{$clave}");

    // Un animal sin el modelo sigue publicándose como antes.
    $viejo = $publicar(['titulo' => 'Sin modelo', 'animalIdentificacion' => 'SOL-' . test_token('x'), 'raza' => 'Criolla', 'sexo' => 'MACHO']);
    test_same(201, $viejo['status'], 'Sin campos nuevos todo sigue igual');
    $vistaVieja = $publicador->procesar('GET', ['mias' => 'true', 'q' => 'Sin modelo'], [])['body']['data']['publicaciones'][0];
    test_same(null, $vistaVieja['animal']['especie'], 'Sin catálogo, especie es null');
    test_same(null, $vistaVieja['animal']['partos'], 'Sin partos, null');
    test_same('Criolla', $vistaVieja['animal']['raza'], 'La raza libre se conserva');

    // --- Lote ---
    $lote = $publicar([
        'titulo' => 'Lote de terneros', 'especieId' => $bovino['especieId'], 'tipoId' => $porId($tiposBovino, 'Ternero')['tipoId'],
        'razaId' => $brahman['razaId'], 'loteCantidad' => 3, 'precio' => 1500000, 'peso' => 180,
    ]);
    test_same(201, $lote['status'], 'Un lote se publica: ' . json_encode($lote['body']));
    test_same(3, $lote['body']['data']['loteCantidad'], 'Lote de 3');
    test_same(3, count($lote['body']['data']['animalIds']), 'Devuelve los 3 animales');
    $publicacionLote = (int) $lote['body']['data']['publicacionId'];
    test_same($lote['body']['data']['animalIds'], $animales->animalesDePublicacion($publicacionLote), 'El enlace guarda los N animales en orden');
    test_same((int) $lote['body']['data']['animalId'], $animales->animalesDePublicacion($publicacionLote)[0], 'La publicación apunta al primero');
    test_same(3, count(array_unique($lote['body']['data']['animalIds'])), 'Son 3 animales distintos');
    $vistaLote = $publicador->procesar('GET', ['mias' => 'true', 'q' => 'Lote de terneros'], []);
    test_same(1, $vistaLote['body']['data']['total'], 'Un lote es UNA publicación (sin duplicar filas)');
    test_same(3, $vistaLote['body']['data']['publicaciones'][0]['loteCantidad'], 'La lectura trae la cantidad del lote');
    test_same('MACHO', $db->query('SELECT tbanimalsexo FROM tbanimal WHERE tbanimalid = ' . (int) $lote['body']['data']['animalIds'][2])->fetchColumn(), 'Cada animal del lote hereda el sexo del tipo');
    $unico = $publicar(['titulo' => 'Individual']);
    test_same([(int) $unico['body']['data']['animalId']], $animales->animalesDePublicacion((int) $unico['body']['data']['publicacionId']), 'Un animal: solo el principal');
    test_same(0, (int) $db->query('SELECT COUNT(*) FROM tbanimalpublicacionanimal WHERE tbanimalpublicacionid = ' . (int) $unico['body']['data']['publicacionId'])->fetchColumn(), 'Un animal no usa la tabla de enlace');

    // --- Venta de un lote: compra y venta POR ANIMAL ---
    $idComprador = test_document(); $identificaciones[] = $idComprador;
    $correo = strtolower(test_token('comprador')) . '@example.test';
    $registro = (new RegistroPublicoController($db, ActorContext::usuarioVerificado(null, 'supabase-' . test_token('c'), $correo, 'authenticated'), test_token('reg')))->procesar('POST', [
        'persona' => ['identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $idComprador, 'nombres' => 'Lote', 'apellidos' => 'Comprador',
            'alias' => 'Comprador lote', 'telefono' => '+506 8888-4444', 'correoElectronico' => $correo],
        'capacidades' => ['COMPRADOR'], 'fincas' => [],
    ]);
    test_same(201, $registro['status'], 'La fixture registra al comprador');
    $buscar = $db->prepare('SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $buscar->execute(['id' => $idComprador]);
    $actorComprador = ActorContext::personaAutenticada((int) $buscar->fetchColumn(), 'supabase-' . test_token('comprador'), $correo, 'authenticated');
    $comprador = new SolicitudesCompraController($db, $actorComprador, test_token('sol-c'));
    $vendedorSolicitudes = new SolicitudesCompraController($db, $actor, test_token('sol-v'));
    $solicitud = $comprador->procesar('POST', ['publicacionId' => $publicacionLote]);
    test_same(201, $solicitud['status'], 'El comprador solicita el lote');
    $aceptada = $vendedorSolicitudes->procesar('PATCH', ['solicitudId' => $solicitud['body']['data']['solicitud']['solicitudId'], 'accion' => 'ACEPTAR']);
    test_same(200, $aceptada['status'], 'El vendedor acepta: ' . json_encode($aceptada['body']));
    $idsLote = $lote['body']['data']['animalIds'];
    $marcas = implode(',', array_fill(0, 3, '?'));
    $ventasLote = $db->prepare("SELECT tbventaprecio, tbanimalid, tbventaproposito, tbventapeso FROM tbventa WHERE tbanimalid IN ({$marcas}) ORDER BY tbanimalid");
    $ventasLote->execute($idsLote);
    $ventasLote = $ventasLote->fetchAll();
    test_same(3, count($ventasLote), 'Una venta por animal del lote');
    $comprasLote = $db->prepare("SELECT tbcompraprecio FROM tbcompra WHERE tbanimalid IN ({$marcas})");
    $comprasLote->execute($idsLote);
    test_same(3, count($comprasLote->fetchAll()), 'Una compra por animal del lote');
    $suma = array_sum(array_map(static fn (array $v): int => (int) round((float) $v['tbventaprecio'] * 100), $ventasLote));
    test_same(150000000, $suma, 'El precio del lote se reparte sin perder centavos');
    foreach ($ventasLote as $venta) test_same(180.0, (float) $venta['tbventapeso'], 'Cada venta lleva su snapshot de peso');
    $estados = $db->prepare("SELECT DISTINCT tbanimalestado FROM tbanimal WHERE tbanimalid IN ({$marcas})");
    $estados->execute($idsLote);
    test_same(['VENDIDO'], $estados->fetchAll(PDO::FETCH_COLUMN), 'Los animales del lote quedan VENDIDO');
    test_same(1, (int) $db->query('SELECT COUNT(*) FROM tbanimalpublicacionestadoperiodo WHERE tbanimalpublicacionestadoperiodoestado = \'VENDIDO\' AND tbanimalpublicacionid = ' . $publicacionLote)->fetchColumn(), 'La publicación pasa a VENDIDO una vez');
} finally {
    ma_cleanup($identificaciones, $vendedores, $animalIds);
}

echo "OK api_modelo_animal_test: catálogos, validaciones del modelo, arete SENASA, lote y venta de lote por animal.\n";
