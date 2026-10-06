<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/AnimalVacunacion.php';
require_once dirname(__DIR__) . '/Application/Controller/MiAnimalesVacunasController.php';
require_once dirname(__DIR__) . '/Application/Controller/CatalogosController.php';
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';

use Application\Auth\ActorContext;
use Application\Controller\AnimalPublicacionController;
use Application\Controller\CatalogosController;
use Application\Controller\MiAnimalesVacunasController;
use Application\Controller\RegistroPublicoController;
use Application\Model\AnimalComercial;

/** Historial de vacunación (P2-3): registrar, listar y corregir un animal propio, y su lectura pública acotada. */

$vendedores = [];
$compradores = [];
$animalIds = [];

function va_actor(string $identificacion): array
{
    $db = test_db();
    $buscar = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $buscar->execute(['id' => $identificacion]);
    $fila = $buscar->fetch();
    return [(int) $fila['tbpersonaid'], ActorContext::personaAutenticada((int) $fila['tbpersonaid'], 'supabase-' . test_token('va'), $fila['tbpersonacorreoelectronico'], 'authenticated')];
}

function va_cleanup(array $vendedores, array $compradores, array $animalIds): void
{
    $db = test_db();
    $animalIds = array_values(array_unique(array_filter($animalIds)));
    if ($animalIds !== []) {
        $m = implode(',', array_fill(0, count($animalIds), '?'));
        $db->prepare("DELETE FROM tbanimalvacunacion WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $pubs = $db->prepare("SELECT tbanimalpublicacionid FROM tbanimalpublicacion WHERE tbanimalid IN ({$m})");
        $pubs->execute($animalIds);
        $publicacionIds = array_map('intval', $pubs->fetchAll(PDO::FETCH_COLUMN));
        if ($publicacionIds !== []) {
            $mp = implode(',', array_fill(0, count($publicacionIds), '?'));
            $db->prepare("DELETE FROM tbanimalpublicacionanimal WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
            $db->prepare("DELETE FROM tbanimalpublicacionestadoperiodo WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
            $db->prepare("DELETE FROM tbanimalpublicacion WHERE tbanimalpublicacionid IN ({$mp})")->execute($publicacionIds);
        }
        $db->prepare("DELETE FROM tbanimalproduccionsalud WHERE tbanimalid IN ({$m})")->execute($animalIds);
        $db->prepare("DELETE FROM tbanimal WHERE tbanimalid IN ({$m})")->execute($animalIds);
    }
    $todas = array_merge($vendedores, $compradores);
    if ($todas === []) return;
    $marks = implode(',', array_fill(0, count($todas), '?'));
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacorausuarioid IN (SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks}))")->execute($todas);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($todas);
    $compradoresSql = "SELECT c.tbcompradorid FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})";
    $db->prepare("DELETE h FROM tbcompradorpersonatelefonohistorico h WHERE h.tbcompradorid IN ({$compradoresSql})")->execute($todas);
    $db->prepare("DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid WHERE p.tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
    test_cleanup_productores($vendedores);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($todas);
}

try {
    $db = test_db();
    $animales = new AnimalComercial($db);
    $crearVendedor = static function (string $finca) use (&$vendedores, $db): array {
        $v = test_create_completo(['fincas' => [['nombre' => $finca]]]);
        $vendedores[] = $v['identificacionNumero'];
        $r = test_finca_controller()->procesarDireccion('POST', [], [
            'identificacionNumero' => $v['identificacionNumero'], 'nombreFinca' => $finca,
            'direccionFinca' => test_direccion_payload(['provincia' => 'San José', 'canton' => 'Central', 'distrito' => 'Carmen']),
        ]);
        test_same(201, $r['status'], 'La fixture registra la finca');
        [$personaId, $actor] = va_actor($v['identificacionNumero']);
        $productor = $db->prepare('SELECT tbproductorid FROM tbproductor WHERE tbpersonaid = :id');
        $productor->execute(['id' => $personaId]);
        return ['actor' => $actor, 'productorId' => (int) $productor->fetchColumn(), 'finca' => $finca, 'id' => $v['identificacionNumero']];
    };
    $a = $crearVendedor('Finca Vacunas A');
    $b = $crearVendedor('Finca Vacunas B');
    $publicadorA = new AnimalPublicacionController($db, test_token('pub-a'), $a['actor']);
    $publicar = static function (AnimalPublicacionController $p, string $finca, array $extra) use (&$animalIds): array {
        $r = $p->procesar('POST', [], $extra + ['fincaNombre' => $finca, 'titulo' => 'Vacunas ' . test_token('t'), 'precio' => 100000]);
        test_same(201, $r['status'], 'La fixture publica: ' . json_encode($r['body']));
        foreach (($r['body']['data']['animalIds'] ?? [$r['body']['data']['animalId']]) as $id) $animalIds[] = (int) $id;
        return $r['body']['data'];
    };
    $uno = $publicar($publicadorA, 'Finca Vacunas A', ['animalIdentificacion' => 'VAC-' . test_token('a')]);
    $lote = $publicar($publicadorA, 'Finca Vacunas A', ['loteCantidad' => 3, 'titulo' => 'Lote vacunado']);
    $ajeno = $publicar(new AnimalPublicacionController($db, test_token('pub-b'), $b['actor']), 'Finca Vacunas B', ['animalIdentificacion' => 'VAC-' . test_token('b')]);
    $animalUno = (int) $uno['animalId'];

    $va = new MiAnimalesVacunasController($db, $a['actor'], test_token('va-a'));
    $vb = new MiAnimalesVacunasController($db, $b['actor'], test_token('va-b'));
    $catalogos = (new CatalogosController($db))->procesar('GET')['body']['data'];
    test_assert(count($catalogos['vacunas']) >= 8, 'La semilla trae las vacunas comunes');
    $nombres = array_column($catalogos['vacunas'], 'nombre', 'vacunaId');
    $aftosa = (int) array_search('Fiebre aftosa', $nombres, true);
    $brucelosis = (int) array_search('Brucelosis', $nombres, true);
    $rabia = (int) array_search('Rabia paralítica bovina', $nombres, true);
    test_assert($aftosa > 0 && $brucelosis > 0 && $rabia > 0, 'Aftosa, brucelosis y rabia están en el catálogo');
    $hoy = gmdate('Y-m-d');
    $ayer = gmdate('Y-m-d', time() - 86400);

    // Sesión y actividad.
    test_same(401, (new MiAnimalesVacunasController($db, ActorContext::noAutenticado()))->procesar('GET', ['animalId' => $animalUno])['status'], 'Sin sesión es 401');
    $idComprador = test_document();
    $compradores[] = $idComprador;
    $correo = strtolower(test_token('comprador')) . '@example.test';
    $registro = (new RegistroPublicoController($db, ActorContext::usuarioVerificado(null, 'supabase-' . test_token('c'), $correo, 'authenticated'), test_token('reg')))->procesar('POST', [
        'persona' => ['identificacionTipo' => 'PASAPORTE', 'identificacionNumero' => $idComprador, 'nombres' => 'Solo', 'apellidos' => 'Comprador',
            'alias' => 'Solo comprador', 'telefono' => '+506 8888-5555', 'correoElectronico' => $correo],
        'capacidades' => ['COMPRADOR'], 'fincas' => [],
    ]);
    test_same(201, $registro['status'], 'La fixture registra a un comprador sin la actividad Vendedor');
    [, $actorSinProductor] = va_actor($idComprador);
    test_same(409, (new MiAnimalesVacunasController($db, $actorSinProductor))->procesar('GET', ['animalId' => $animalUno])['status'], 'Sin la actividad Vendedor es 409');
    test_same(422, $va->procesar('GET')['status'], 'animalId es obligatorio');

    // Validación del alta.
    $base = ['animalId' => $animalUno, 'vacunaId' => $aftosa, 'fecha' => $ayer];
    foreach ([
        'fecha' => [['fecha' => gmdate('Y-m-d', time() + 5 * 86400)], ['fecha' => '2024-13-40'], ['fecha' => '']],
        'vacunaId' => [['vacunaId' => 999999], ['vacunaId' => 'x'], ['vacunaId' => null]],
        'dosis' => [['dosis' => str_repeat('a', 51)]], 'lote' => [['lote' => str_repeat('a', 51)]],
        'aplicadaPor' => [['aplicadaPor' => str_repeat('a', 151)]], 'observaciones' => [['observaciones' => str_repeat('a', 501)]],
        'proximaDosis' => [['proximaDosis' => gmdate('Y-m-d', time() - 3 * 86400)], ['proximaDosis' => 'mañana']],
        'animalId' => [['animalId' => null]], 'compradorId' => [['compradorId' => 1]],
    ] as $campo => $casos) {
        foreach ($casos as $caso) {
            $r = $va->procesar('POST', [], array_merge($base, $caso));
            test_same(422, $r['status'], "{$campo} inválido: " . json_encode($caso));
            test_assert(isset($r['body']['errors'][$campo]), "El error apunta a {$campo}");
        }
    }
    // Vacuna desactivada por un administrador: ya no se acepta.
    $db->prepare('UPDATE tbvacuna SET tbvacunaactivo = 0 WHERE tbvacunaid = :id')->execute(['id' => $rabia]);
    test_same(422, $va->procesar('POST', [], array_merge($base, ['vacunaId' => $rabia]))['status'], 'Una vacuna inactiva se rechaza');
    $db->prepare('UPDATE tbvacuna SET tbvacunaactivo = 1 WHERE tbvacunaid = :id')->execute(['id' => $rabia]);

    // Anti-IDOR: un animal ajeno o inexistente responde 404.
    test_same(404, $vb->procesar('POST', [], $base)['status'], 'B no registra en el animal de A');
    test_same(404, $vb->procesar('GET', ['animalId' => $animalUno])['status'], 'B no lee el historial de A');
    test_same(404, $va->procesar('POST', [], ['animalId' => (int) $ajeno['animalId']] + $base)['status'], 'A no registra en el animal de B');
    test_same(404, $va->procesar('GET', ['animalId' => 999999])['status'], 'Animal inexistente es 404');

    // Alta y lectura.
    $r1 = $va->procesar('POST', [], $base + ['dosis' => '2 ml', 'lote' => 'L-77', 'aplicadaPor' => 'Dr. Mora', 'proximaDosis' => gmdate('Y-m-d', time() + 180 * 86400), 'observaciones' => 'Sin reacción']);
    test_same(201, $r1['status'], 'Registrar una vacuna: ' . json_encode($r1['body']));
    $v1 = $r1['body']['data']['vacunacion'];
    test_same('Fiebre aftosa', $v1['vacuna'], 'Devuelve el nombre de la vacuna');
    test_same('2 ml', $v1['dosis'], 'Guarda la dosis');
    test_same('Dr. Mora', $v1['aplicadaPor'], 'Guarda quién la aplicó');
    $r2 = $va->procesar('POST', [], ['animalId' => $animalUno, 'vacunaId' => $brucelosis, 'fecha' => $hoy]);
    test_same(201, $r2['status'], 'Registrar otra con solo lo obligatorio');
    test_same(null, $r2['body']['data']['vacunacion']['lote'], 'Lo opcional queda null');
    $lista = $va->procesar('GET', ['animalId' => (string) $animalUno]);
    test_same(200, $lista['status'], 'Listar');
    test_same(['Brucelosis', 'Fiebre aftosa'], array_column($lista['body']['data']['vacunas'], 'vacuna'), 'Más reciente primero');

    // Animales del lote y dueño explícito también son propios.
    $segundoDelLote = (int) $lote['animalIds'][1];
    test_same(201, $va->procesar('POST', [], ['animalId' => $segundoDelLote, 'vacunaId' => $rabia, 'fecha' => $ayer])['status'], 'Un animal del lote (no el principal) es propio');
    $db->beginTransaction();
    $animalLibre = $animales->ejecutarConBloqueoAlta('tbanimal', static fn (): int => $animales->crearAnimal('VAC-' . test_token('libre'), 'MACHO', 'Criollo', 'PRUEBA', null, ['estado' => 'ACTIVO', 'productorId' => $a['productorId']]));
    $db->commit();
    $animalIds[] = $animalLibre;
    test_same(201, $va->procesar('POST', [], ['animalId' => $animalLibre, 'vacunaId' => $aftosa, 'fecha' => $ayer])['status'], 'Un animal sin publicar con dueño explícito es propio');
    test_same(404, $vb->procesar('GET', ['animalId' => $animalLibre])['status'], 'Y ajeno para B');

    // Corregir.
    $id1 = $v1['vacunacionId'];
    test_same(404, $vb->procesar('PATCH', [], ['vacunacionId' => $id1, 'dosis' => 'x'])['status'], 'B no corrige el registro de A');
    test_same(404, $va->procesar('PATCH', [], ['vacunacionId' => 999999, 'dosis' => 'x'])['status'], 'Registro inexistente es 404');
    test_same(422, $va->procesar('PATCH', [], ['vacunacionId' => $id1])['status'], 'Hay que indicar qué corregir');
    test_same(422, $va->procesar('PATCH', [], ['vacunacionId' => $id1, 'animalId' => $animalLibre])['status'], 'No se cambia de animal');
    test_same(422, $va->procesar('PATCH', [], ['vacunacionId' => $id1, 'fecha' => $hoy, 'proximaDosis' => $ayer])['status'], 'La próxima no es anterior a la aplicación');
    test_same(422, $va->procesar('PATCH', [], ['vacunacionId' => $id1, 'fecha' => gmdate('Y-m-d', time() + 4 * 86400)])['status'], 'La fecha corregida tampoco es futura');
    $c = $va->procesar('PATCH', [], ['vacunacionId' => $id1, 'dosis' => '3 ml', 'observaciones' => null, 'vacunaId' => $brucelosis]);
    test_same(200, $c['status'], 'Corregir: ' . json_encode($c['body']));
    $corregida = $c['body']['data']['vacunacion'];
    test_same('3 ml', $corregida['dosis'], 'Cambia lo enviado');
    test_same('Brucelosis', $corregida['vacuna'], 'Cambia la vacuna');
    test_same(null, $corregida['observaciones'], 'null borra el campo');
    test_same('L-77', $corregida['lote'], 'Lo no enviado se conserva');
    test_same($ayer, $corregida['fecha'], 'La fecha no enviada se conserva');

    // Bitácora.
    $acciones = $db->prepare("SELECT tbbitacoraaccion FROM tbbitacora WHERE tbbitacoraentidad = 'ANIMAL_VACUNACION' AND tbbitacoraregistroidentificacionnumero = :id ORDER BY tbbitacoraid");
    $acciones->execute(['id' => (string) $id1]);
    test_same(['CREAR', 'ACTUALIZAR'], $acciones->fetchAll(PDO::FETCH_COLUMN), 'Cada cambio queda en la bitácora');

    // Lectura pública acotada.
    $publica = $publicadorA->procesar('GET', ['mias' => 'true', 'q' => 'Lote vacunado'], []);
    $item = $publica['body']['data']['publicaciones'][0];
    test_same(['Rabia paralítica bovina'], array_column($item['vacunas'], 'vacuna'), 'El lote muestra las vacunas de todos sus animales');
    $deUno = array_values(array_filter($publicadorA->procesar('GET', ['mias' => 'true'], [])['body']['data']['publicaciones'], static fn (array $p): bool => $p['animalId'] === $animalUno))[0];
    test_same(['Brucelosis'], array_column($deUno['vacunas'], 'vacuna'), 'Una fila por vacuna');
    test_same($hoy, $deUno['vacunas'][0]['fecha'], 'Con la fecha de la aplicación más reciente');
    test_same(['vacuna', 'fecha', 'proximaDosis'], array_keys($deUno['vacunas'][0]), 'Solo vacuna, fecha y próxima dosis: nada de lote, aplicador ni observaciones');
    test_same([], $publicadorA->procesar('GET', ['mias' => 'true', 'q' => 'zzz-nada'], [])['body']['data']['publicaciones'], 'Sin resultados no hay error');
    // Acotada a 8 por publicación.
    foreach ($nombres as $vacunaId => $unused) {
        $va->procesar('POST', [], ['animalId' => $animalUno, 'vacunaId' => $vacunaId, 'fecha' => $hoy]);
    }
    $deUno = array_values(array_filter($publicadorA->procesar('GET', ['mias' => 'true'], [])['body']['data']['publicaciones'], static fn (array $p): bool => $p['animalId'] === $animalUno))[0];
    test_assert(count($deUno['vacunas']) <= AnimalComercial::VACUNAS_POR_PUBLICACION, 'A lo sumo ' . AnimalComercial::VACUNAS_POR_PUBLICACION . ' vacunas por publicación');
    $db->beginTransaction();
    $animales->marcarEstadoAnimales([$animalUno], 'VENDIDO');
    $db->commit();
    test_same(409, $va->procesar('POST', [], $base)['status'], 'Un animal vendido ya no recibe vacunas');
} finally {
    va_cleanup($vendedores, $compradores, $animalIds);
}

echo "OK mi_animales_vacunas_test: catálogo, validación, anti-IDOR, alta, lectura, corrección, bitácora y lectura pública acotada.\n";
