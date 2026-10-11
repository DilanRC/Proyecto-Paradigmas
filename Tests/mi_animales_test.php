<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/AnimalVacunacion.php';
require_once dirname(__DIR__) . '/Application/Controller/MiAnimalesController.php';

use Application\Auth\ActorContext;
use Application\Controller\AnimalPublicacionController;
use Application\Controller\MiAnimalesController;
use Application\Model\AnimalComercial;

/** Mis animales (P2-4): inventario propio, alta sin publicar, publicar después y el estado del animal sigue a su publicación. */

$vendedores = [];
$animalIds = [];

function ma_actor(string $identificacion): ActorContext
{
    $buscar = test_db()->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $buscar->execute(['id' => $identificacion]);
    $fila = $buscar->fetch();
    return ActorContext::personaAutenticada((int) $fila['tbpersonaid'], 'supabase-' . test_token('ma'), $fila['tbpersonacorreoelectronico'], 'authenticated');
}

function ma_cleanup(array $vendedores, array $animalIds): void
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
    if ($vendedores === []) return;
    $marks = implode(',', array_fill(0, count($vendedores), '?'));
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacorausuarioid IN (SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks}))")->execute($vendedores);
    $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$marks})")->execute($vendedores);
    test_cleanup_productores($vendedores);
    $db->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marks})")->execute($vendedores);
}

try {
    $db = test_db();
    $crearVendedor = static function (string $finca) use (&$vendedores): array {
        $v = test_create_completo(['fincas' => [['nombre' => $finca]]]);
        $vendedores[] = $v['identificacionNumero'];
        return ['actor' => ma_actor($v['identificacionNumero']), 'finca' => $finca];
    };
    $a = $crearVendedor('Finca Inventario A');
    $b = $crearVendedor('Finca Inventario B');
    $ma = new MiAnimalesController($db, $a['actor'], test_token('ma-a'));
    $mb = new MiAnimalesController($db, $b['actor'], test_token('ma-b'));

    test_same(401, (new MiAnimalesController($db, ActorContext::noAutenticado()))->procesar('GET')['status'], 'Sin sesión es 401');
    test_same([], $ma->procesar('GET')['body']['data']['animales'], 'Un vendedor nuevo empieza sin animales');

    // Validación del alta: catálogo, sexo por tipo, lote y arete.
    foreach ([
        'tipoId' => ['especieId' => 1, 'tipoId' => 999999],
        'sexo' => ['especieId' => 1, 'tipoId' => 6, 'sexo' => 'MACHO'],
        'loteCantidad' => ['loteCantidad' => 3],
        'arete' => ['arete' => '123'],
        'peso' => ['peso' => -4],
    ] as $campo => $cuerpo) {
        $r = $ma->procesar('POST', $cuerpo);
        test_same(422, $r['status'], "{$campo} inválido");
        test_assert(isset($r['body']['errors'][$campo]), "El error apunta a {$campo}");
    }

    // Alta: queda ACTIVO, con el vendedor como dueño, sin publicación y en la bitácora.
    $arete = '188001' . substr(str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT), 0, 7);
    $alta = $ma->procesar('POST', ['especieId' => 1, 'tipoId' => 6, 'razaId' => 1, 'arete' => $arete, 'peso' => 420, 'proposito' => 'Leche', 'partos' => 2]);
    test_same(201, $alta['status'], 'Registra el animal: ' . json_encode($alta['body']));
    $animalId = (int) $alta['body']['data']['animal']['animalId'];
    $animalIds[] = $animalId;
    $fila = $alta['body']['data']['animales'][0];
    test_same($animalId, $fila['animalId'], 'El inventario lo devuelve');
    test_same('ACTIVO', $fila['estado'], 'Sin publicar está en inventario');
    test_same('HEMBRA', $fila['sexo'], 'El tipo Vaca fija el sexo');
    test_same(null, $fila['publicacion'], 'Todavía no tiene publicación');
    test_same(1, (int) $db->query("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraentidad = 'ANIMAL' AND tbbitacoraregistroidentificacionnumero = '{$animalId}'")->fetchColumn(), 'El alta queda en la bitácora');
    test_same(409, $ma->procesar('POST', ['arete' => $arete])['status'], 'El arete no se repite');
    test_same([], $mb->procesar('GET')['body']['data']['animales'], 'Otro vendedor no ve el animal');

    // Editar: reemplaza los datos del formulario; sin `arete` lo conserva; un peso nuevo agrega una observación.
    $editar = ['animalId' => $animalId, 'accion' => 'EDITAR', 'especieId' => 1, 'tipoId' => 6, 'razaId' => 1, 'partos' => 3, 'peso' => 430, 'proposito' => 'Leche'];
    test_same(404, $mb->procesar('PATCH', $editar)['status'], 'Editar un animal ajeno es 404');
    test_same(422, $ma->procesar('PATCH', ['sexo' => 'MACHO'] + $editar)['status'], 'El sexo debe coincidir con el tipo');
    test_same(422, $ma->procesar('PATCH', ['color' => 'negro'] + $editar)['status'], 'Campo desconocido es 422');
    $editado = $ma->procesar('PATCH', $editar);
    test_same(200, $editado['status'], 'Edita el animal: ' . json_encode($editado['body']));
    $fila = $editado['body']['data']['animales'][0];
    test_same(3, $fila['partos'], 'Los partos editados se guardan');
    test_same($arete, $fila['arete'], 'Sin la clave arete se conserva');
    test_same(430.0, $fila['peso'], 'El peso nuevo es el vigente');
    $historial = static fn ($controlador): array => $controlador->procesar('GET', ['animalId' => (string) $animalId]);
    test_same(2, count($historial($ma)['body']['data']['observaciones']), 'El peso anterior queda en el historial');
    $ma->procesar('PATCH', $editar);
    test_same(2, count($historial($ma)['body']['data']['observaciones']), 'Guardar sin cambiar el peso no agrega observación');
    test_same(404, $historial($mb)['status'], 'El historial de un animal ajeno es 404');
    test_assert((int) $db->query("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraentidad = 'ANIMAL' AND tbbitacoraaccion = 'ACTUALIZAR' AND tbbitacoraregistroidentificacionnumero = '{$animalId}'")->fetchColumn() > 0, 'La edición queda en la bitácora');

    // Pesar: con fecha pasada se ordena en su fecha; con la de hoy queda como vigente y conserva el propósito.
    $pesar = ['animalId' => $animalId, 'accion' => 'PESAR'];
    test_same(422, $ma->procesar('PATCH', $pesar)['status'], 'El peso es obligatorio');
    test_same(422, $ma->procesar('PATCH', ['peso' => 400, 'fecha' => '2999-01-01'] + $pesar)['status'], 'La fecha no puede ser futura');
    test_same(422, $ma->procesar('PATCH', ['peso' => 400, 'fecha' => '2024-02-30'] + $pesar)['status'], 'La fecha debe existir');
    test_same(404, $mb->procesar('PATCH', ['peso' => 400] + $pesar)['status'], 'Pesar un animal ajeno es 404');
    test_same(201, $ma->procesar('PATCH', ['peso' => 300, 'fecha' => '2020-01-15'] + $pesar)['status'], 'Registra un peso pasado');
    $obs = $historial($ma)['body']['data']['observaciones'];
    test_same(430.0, $obs[0]['peso'], 'Un peso pasado no reemplaza al vigente');
    test_same(300.0, $obs[2]['peso'], 'El peso pasado queda al final');
    $hoy = $ma->procesar('PATCH', ['peso' => 445.5] + $pesar);
    test_same(201, $hoy['status'], 'Registra el peso de hoy');
    test_same(445.5, $hoy['body']['data']['animales'][0]['peso'], 'El peso de hoy es el vigente');
    test_same('Leche', $hoy['body']['data']['animales'][0]['proposito'], 'Pesar conserva el propósito');

    // Publicar: ajeno 404, finca de otro 422, propio 201 y el animal pasa a PUBLICADO.
    $publicar = ['animalId' => $animalId, 'accion' => 'PUBLICAR', 'fincaNombre' => $a['finca'], 'titulo' => 'Vaca lechera', 'precio' => 650000];
    test_same(404, $mb->procesar('PATCH', ['fincaNombre' => $b['finca']] + $publicar)['status'], 'Un animal ajeno es 404');
    test_same(422, $ma->procesar('PATCH', ['fincaNombre' => $b['finca']] + $publicar)['status'], 'La finca debe ser propia');
    test_same(422, $ma->procesar('PATCH', ['accion' => 'BORRAR'] + $publicar)['status'], 'Solo existe la acción PUBLICAR');
    $pub = $ma->procesar('PATCH', $publicar);
    test_same(201, $pub['status'], 'Publica el animal: ' . json_encode($pub['body']));
    $publicacionId = (int) $pub['body']['data']['publicacionId'];
    $fila = $pub['body']['data']['animales'][0];
    test_same('PUBLICADO', $fila['estado'], 'El animal queda publicado');
    test_same($publicacionId, $fila['publicacion']['publicacionId'], 'El inventario enlaza la publicación');
    test_same(409, $ma->procesar('PATCH', $publicar)['status'], 'No se publica dos veces');

    // El estado del animal sigue al de su publicación.
    $animales = new AnimalComercial($db);
    $cambiar = static function (string $estado) use ($db, $animales, $publicacionId): void {
        $animales->ejecutarConBloqueoAlta('tbanimalpublicacionestadoperiodo', static function () use ($db, $animales, $publicacionId, $estado): void {
            $db->beginTransaction();
            $animales->cambiarEstadoPublicacion($publicacionId, $estado, null, 'TEST');
            $db->commit();
        });
    };
    $cambiar('RETIRADO');
    test_same('ACTIVO', $ma->procesar('GET')['body']['data']['animales'][0]['estado'], 'Retirada la publicación, vuelve al inventario');
    test_same(201, $ma->procesar('PATCH', $publicar)['status'], 'Y se puede volver a publicar');
    $segunda = (int) $ma->procesar('GET')['body']['data']['animales'][0]['publicacion']['publicacionId'];
    test_assert($segunda > $publicacionId, 'El inventario muestra la publicación más reciente');

    // Un animal publicado desde Publicar (sin dueño explícito antiguo) también aparece; los del lote no se listan sueltos.
    $publicador = new AnimalPublicacionController($db, test_token('pub-a'), $a['actor']);
    $lote = $publicador->procesar('POST', [], ['fincaNombre' => $a['finca'], 'titulo' => 'Lote', 'loteCantidad' => 3]);
    test_same(201, $lote['status'], 'La fixture publica un lote');
    foreach ($lote['body']['data']['animalIds'] as $id) $animalIds[] = (int) $id;
    $ids = array_column($ma->procesar('GET')['body']['data']['animales'], 'animalId');
    test_same(2, count($ids), 'El lote aparece una sola vez (su animal principal)');
    test_assert(in_array((int) $lote['body']['data']['animalId'], $ids, true), 'El principal del lote está en el inventario');
} finally {
    ma_cleanup($vendedores, $animalIds);
}

echo "OK mi_animales_test: inventario, alta sin publicar, anti-IDOR, publicar y estado sincronizado con la publicación.\n";
