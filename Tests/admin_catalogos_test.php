<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/AnimalCatalogo.php';
require_once dirname(__DIR__) . '/Application/Controller/AdminCatalogoController.php';
require_once dirname(__DIR__) . '/Application/Controller/CatalogosController.php';

use Application\Controller\AdminCatalogoController;
use Application\Controller\CatalogosController;

/** Catálogos desde el panel (P2-6): crear, renombrar, desactivar y reactivar, con bitácora y sin duplicados. */

$db = test_db();
$creados = [];
$sufijo = test_token('cat');
$admin = new AdminCatalogoController($db, test_token('req'));
$publicos = static fn (): array => (new CatalogosController($db))->procesar('GET')['body']['data'];

try {
    $todos = $admin->procesar('GET', [])['body']['data'];
    foreach (['especies', 'tipos', 'razas', 'vacunas'] as $lista) test_assert(is_array($todos[$lista]), "GET trae {$lista}");

    // Validación.
    foreach ([
        'catalogo' => ['catalogo' => 'COLOR', 'nombre' => 'X'],
        'nombre' => ['catalogo' => 'VACUNA', 'nombre' => '   '],
        'especieId' => ['catalogo' => 'RAZA', 'nombre' => 'Raza ' . $sufijo, 'especieId' => 999999],
        'sexo' => ['catalogo' => 'TIPO', 'nombre' => 'Tipo ' . $sufijo, 'especieId' => 1, 'sexo' => 'X'],
        'especieIdExtra' => ['catalogo' => 'VACUNA', 'nombre' => 'Vacuna ' . $sufijo, 'especieId' => 1],
    ] as $campo => $cuerpo) {
        $r = $admin->procesar('POST', $cuerpo);
        test_same(422, $r['status'], "{$campo} inválido");
    }

    // Alta de cada catálogo; queda visible en el catálogo público.
    $especie = $admin->procesar('POST', ['catalogo' => 'ESPECIE', 'nombre' => "Especie {$sufijo}"]);
    test_same(201, $especie['status'], 'Crea la especie: ' . json_encode($especie['body']));
    $especieId = $especie['body']['data']['registro']['id'];
    $creados[] = ['ESPECIE', 'tbespecie', 'tbespecieid', $especieId];
    $tipo = $admin->procesar('POST', ['catalogo' => 'TIPO', 'nombre' => "Tipo {$sufijo}", 'especieId' => $especieId, 'sexo' => 'H']);
    test_same(201, $tipo['status'], 'Crea el tipo');
    $tipoId = $tipo['body']['data']['registro']['id'];
    $creados[] = ['TIPO', 'tbanimaltipo', 'tbanimaltipoid', $tipoId];
    test_same('H', $tipo['body']['data']['registro']['sexo'], 'El tipo guarda su sexo');
    $raza = $admin->procesar('POST', ['catalogo' => 'RAZA', 'nombre' => "Raza {$sufijo}", 'especieId' => $especieId]);
    test_same(201, $raza['status'], 'Crea la raza');
    $creados[] = ['RAZA', 'tbraza', 'tbrazaid', $raza['body']['data']['registro']['id']];
    $vacuna = $admin->procesar('POST', ['catalogo' => 'VACUNA', 'nombre' => "Vacuna {$sufijo}"]);
    test_same(201, $vacuna['status'], 'Crea la vacuna');
    $vacunaId = $vacuna['body']['data']['registro']['id'];
    $creados[] = ['VACUNA', 'tbvacuna', 'tbvacunaid', $vacunaId];
    test_assert(in_array($tipoId, array_column($publicos()['tipos'], 'tipoId'), true), 'El tipo nuevo sale en el catálogo público');

    // Duplicados sin distinguir mayúsculas; el mismo nombre en otra especie sí vale.
    test_same(409, $admin->procesar('POST', ['catalogo' => 'VACUNA', 'nombre' => strtoupper("vacuna {$sufijo}")])['status'], 'Vacuna duplicada');
    $otraEspecie = $admin->procesar('POST', ['catalogo' => 'RAZA', 'nombre' => "Raza {$sufijo}", 'especieId' => 1]);
    test_same(201, $otraEspecie['status'], 'La misma raza en otra especie es válida');
    $creados[] = ['RAZA', 'tbraza', 'tbrazaid', $otraEspecie['body']['data']['registro']['id']];

    // Renombrar, desactivar y reactivar.
    test_same(422, $admin->procesar('PATCH', ['catalogo' => 'TIPO', 'id' => $tipoId, 'sexo' => 'M'])['status'], 'El sexo no se cambia después');
    test_same(404, $admin->procesar('PATCH', ['catalogo' => 'VACUNA', 'id' => 999999, 'activo' => false])['status'], 'Inexistente es 404');
    $renombrada = $admin->procesar('PATCH', ['catalogo' => 'VACUNA', 'id' => $vacunaId, 'nombre' => "  Vacuna   {$sufijo} B "]);
    test_same(200, $renombrada['status'], 'Renombra');
    test_same("Vacuna {$sufijo} B", $renombrada['body']['data']['registro']['nombre'], 'El nombre se normaliza');
    test_same(200, $admin->procesar('PATCH', ['catalogo' => 'VACUNA', 'id' => $vacunaId, 'activo' => false])['status'], 'Desactiva');
    test_assert(!in_array($vacunaId, array_column($publicos()['vacunas'], 'vacunaId'), true), 'Inactiva ya no sale en el catálogo público');
    test_same(200, $admin->procesar('PATCH', ['catalogo' => 'ESPECIE', 'id' => $especieId, 'activo' => false])['status'], 'Desactiva la especie');
    test_same(200, $admin->procesar('PATCH', ['catalogo' => 'TIPO', 'id' => $tipoId, 'activo' => false])['status'], 'Desactiva el tipo');
    test_same(409, $admin->procesar('PATCH', ['catalogo' => 'TIPO', 'id' => $tipoId, 'activo' => true])['status'], 'No reactiva un tipo de una especie inactiva');

    $acciones = $db->prepare("SELECT tbbitacoraaccion FROM tbbitacora WHERE tbbitacoraentidad = 'CATALOGO' AND tbbitacoraregistroidentificacionnumero = :id ORDER BY tbbitacoraid");
    $acciones->execute(['id' => "VACUNA:{$vacunaId}"]);
    test_same(['CREAR', 'ACTUALIZAR', 'DESACTIVAR'], $acciones->fetchAll(PDO::FETCH_COLUMN), 'Cada cambio queda en la bitácora');
} finally {
    foreach ($creados as [$catalogo, $tabla, $columna, $id]) {
        $db->prepare("DELETE FROM {$tabla} WHERE {$columna} = :id")->execute(['id' => $id]);
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'CATALOGO' AND tbbitacoraregistroidentificacionnumero = :id")
            ->execute(['id' => "{$catalogo}:{$id}"]);
    }
}

echo "OK admin_catalogos_test: validación, alta de los cuatro catálogos, duplicados, renombrar, desactivar, reactivar y bitácora.\n";
