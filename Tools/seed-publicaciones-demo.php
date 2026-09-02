<?php

declare(strict_types=1);

/**
 * Siembra publicaciones de demostración para ver Explorar con contenido.
 *
 * Todo lo que crea queda marcado con origen DEMO_EXPLORAR, que es lo que usa
 * --limpiar para borrarlo sin tocar datos reales. No usar contra producción.
 *
 *   docker compose exec -T app php Tools/seed-publicaciones-demo.php
 *   docker compose exec -T app php Tools/seed-publicaciones-demo.php --limpiar
 */

use Application\Controller\FincaController;
use Application\Controller\ProductorController;
use Application\Model\AnimalComercial;
use Configuration\Database;

$raiz = dirname(__DIR__);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
foreach (['NamedLock', 'Persona', 'ProductorFinca', 'Direccion', 'ProductorDireccion', 'FincaDireccion',
    'Bitacora', 'Productor', 'ProductorEstadoPeriodo', 'ProductorClasificacionPeriodo',
    'AnimalComercial'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach (['ProductorDireccionService', 'ProductorEstadoService', 'ValidacionService',
    'EstadoService'] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
require_once $raiz . '/Application/Controller/ProductorController.php';
require_once $raiz . '/Application/Controller/FincaController.php';

const ORIGEN_DEMO = 'DEMO_EXPLORAR';

$conexion = Database::getConnection();
$limpiar = in_array('--limpiar', $argv, true);

if ($limpiar) {
    $conexion->beginTransaction();
    try {
        $publicaciones = $conexion->query(
            "SELECT tbanimalpublicacionid, tbanimalid FROM tbanimalpublicacion
             WHERE tbanimalpublicacionorigen = '" . ORIGEN_DEMO . "'"
        )->fetchAll();
        foreach ($publicaciones as $fila) {
            $conexion->prepare('DELETE FROM tbanimalpublicacionestadoperiodo
                WHERE tbanimalpublicacionid = :id')->execute(['id' => $fila['tbanimalpublicacionid']]);
            $conexion->prepare('DELETE FROM tbanimalpublicacion
                WHERE tbanimalpublicacionid = :id')->execute(['id' => $fila['tbanimalpublicacionid']]);
            $conexion->prepare('DELETE FROM tbanimalproduccionsalud
                WHERE tbanimalid = :id')->execute(['id' => $fila['tbanimalid']]);
            $conexion->prepare('DELETE FROM tbanimal
                WHERE tbanimalid = :id')->execute(['id' => $fila['tbanimalid']]);
        }
        $conexion->commit();
        printf("Limpiadas %d publicaciones de demostración.\n", count($publicaciones));
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
    exit(0);
}

// Se publica desde una finca que ya exista y tenga dirección: la ubicación del
// animal sale de ahí, no del productor.
$finca = $conexion->query(
    'SELECT f.tbfincaid, f.tbproductorid FROM tbfinca f
     INNER JOIN tbfincadireccion fd ON fd.tbfincaid = f.tbfincaid
     WHERE f.tbfincaestado = 1 LIMIT 1'
)->fetch();
if ($finca === false) {
    // Sin finca ubicada no hay dónde publicar: se crea el vendedor de
    // demostración con su finca y la dirección de esa finca.
    $identificacion = 'DEMO' . strtoupper(bin2hex(random_bytes(4)));
    $productores = new ProductorController($conexion, 'seed-publicaciones-demo');
    $creado = $productores->procesar('POST', [], [
        'identificacion' => ['tipoCodigo' => 'PASAPORTE', 'numero' => $identificacion],
        'nombre' => 'Ganadera Demostración',
        'telefono' => '+506 8888-7777',
        'correoElectronico' => 'demo.explorar@example.test',
        'fincas' => [['nombre' => 'Finca Demostración']],
        'direccionPrincipal' => [
            'provincia' => 'Alajuela', 'canton' => 'San Carlos',
            'distrito' => 'Aguas Zarcas', 'pueblo' => null,
            'senas' => 'Registro de demostración para Explorar.',
        ],
    ]);
    if (($creado['status'] ?? 0) !== 201) {
        fwrite(STDERR, 'No fue posible crear el vendedor de demostración: '
            . json_encode($creado['body'] ?? [], JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }

    $fincas = new FincaController($conexion, 'seed-publicaciones-demo');
    $direccion = $fincas->procesarDireccion('POST', [], [
        'identificacionNumero' => $creado['body']['data']['identificacionNumero'],
        'nombreFinca' => 'Finca Demostración',
        'direccionFinca' => [
            'provincia' => 'Alajuela', 'canton' => 'San Carlos',
            'distrito' => 'Aguas Zarcas', 'pueblo' => 'La Palmera',
            'senas' => 'Registro de demostración para Explorar.',
        ],
    ]);
    if (($direccion['status'] ?? 0) !== 201) {
        fwrite(STDERR, 'No fue posible ubicar la finca de demostración: '
            . json_encode($direccion['body'] ?? [], JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }

    $finca = $conexion->query(
        'SELECT f.tbfincaid, f.tbproductorid FROM tbfinca f
         INNER JOIN tbfincadireccion fd ON fd.tbfincaid = f.tbfincaid
         WHERE f.tbfincaestado = 1 LIMIT 1'
    )->fetch();
    printf("Vendedor de demostración creado (%s).\n", $identificacion);
}

$catalogo = [
    ['titulo' => 'Novillas de engorde', 'raza' => 'Brahman', 'sexo' => 'HEMBRA', 'precio' => 950000,
        'edad' => 18, 'peso' => 320.5, 'proposito' => 'ENGORDE',
        'descripcion' => 'Lote de novillas listas para engorde, con desparasitación al día.'],
    ['titulo' => 'Vaca lechera Jersey', 'raza' => 'Jersey', 'sexo' => 'HEMBRA', 'precio' => 1200000,
        'edad' => 36, 'peso' => 410.0, 'proposito' => 'DOBLE PROPOSITO',
        'descripcion' => 'Segundo parto, producción estable durante la última lactancia.'],
    ['titulo' => 'Toro reproductor Gyr', 'raza' => 'Gyr', 'sexo' => 'MACHO', 'precio' => 2450000,
        'edad' => 48, 'peso' => 780.0, 'proposito' => 'CRIA',
        'descripcion' => 'Reproductor con registro, apto para monta directa.'],
];

$animales = new AnimalComercial($conexion);
$conexion->beginTransaction();
try {
    foreach ($catalogo as $indice => $datos) {
        $animalId = $animales->ejecutarConBloqueoAlta('tbanimal',
            static fn (): int => $animales->crearAnimal(
                sprintf('DEMO-%03d', $indice + 1), $datos['sexo'], $datos['raza'], ORIGEN_DEMO));
        $animales->ejecutarConBloqueoAlta('tbanimalproduccionsalud',
            static fn (): int => $animales->registrarObservacion($animalId, [
                'origen' => ORIGEN_DEMO,
                'edadMeses' => $datos['edad'],
                'peso' => $datos['peso'],
                'proposito' => $datos['proposito'],
            ]));
        $animales->ejecutarConBloqueoAlta('tbanimalpublicacion',
            static fn (): int => $animales->publicarAnimal($animalId,
                (int) $finca['tbproductorid'], (int) $finca['tbfincaid'], [
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'],
                    'precio' => $datos['precio'],
                    'origen' => ORIGEN_DEMO,
                    'estado' => 'ACTIVO',
                ]));
    }
    $conexion->commit();
    printf("Sembradas %d publicaciones de demostración.\n", count($catalogo));
} catch (Throwable $error) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    throw $error;
}
