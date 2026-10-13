<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Controller\FincaController;
use Application\Controller\ProductorController;
use Application\Model\AnimalComercial;
use PDO;
use RuntimeException;
use Throwable;

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/Configuration/Configuration.php';
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

/**
 * Publicaciones ficticias para ver la portada y Explorar con contenido. Todo queda marcado
 * con el origen DEMO_EXPLORAR. La usan Tools/seed-publicaciones-demo.php y el asistente local.
 */
final class PublicacionesDemo
{
    public const ORIGEN = 'DEMO_EXPLORAR';

    public static function existentes(PDO $conexion): int
    {
        $consulta = $conexion->prepare(
            'SELECT COUNT(*) FROM tbanimalpublicacion WHERE tbanimalpublicacionorigen = :origen'
        );
        $consulta->execute(['origen' => self::ORIGEN]);

        return (int) $consulta->fetchColumn();
    }

    /**
     * El asistente solo siembra en una base sin fincas: así el vendedor y la finca son siempre
     * los de demostración y nada queda a nombre de una persona real.
     */
    public static function baseSinFincas(PDO $conexion): bool
    {
        return (int) $conexion->query('SELECT COUNT(*) FROM tbfinca')->fetchColumn() === 0;
    }

    /**
     * Devuelve cuántas publicaciones creó. Publica desde la primera finca ubicada que exista
     * (uso de desarrollo); reutiliza los controladores para no duplicar el alta de vendedor y finca.
     */
    public static function sembrar(PDO $conexion): int
    {
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
            // El pasaporte admite hasta 9 caracteres: DEMO + 5 hexadecimales.
            $identificacion = 'DEMO' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $productores = new ProductorController($conexion, 'publicaciones-demo');
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
                throw new RuntimeException('No fue posible crear el vendedor de demostración: '
                        . json_encode($creado['body'] ?? [], JSON_UNESCAPED_UNICODE));
            }

            $fincas = new FincaController($conexion, 'publicaciones-demo');
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
                throw new RuntimeException('No fue posible ubicar la finca de demostración: '
                        . json_encode($direccion['body'] ?? [], JSON_UNESCAPED_UNICODE));
            }

            $finca = $conexion->query(
                'SELECT f.tbfincaid, f.tbproductorid FROM tbfinca f
                 INNER JOIN tbfincadireccion fd ON fd.tbfincaid = f.tbfincaid
                 WHERE f.tbfincaestado = 1 LIMIT 1'
            )->fetch();
        }

        // Imágenes de Wikimedia Commons (licencias libres; se enlazan, no se copian):
        //   Brahman_cattle_in_Costa_Rica.jpg — CC0
        //   Jersey_Cow_01.jpg — CC BY-SA 4.0
        //   Gyr_cattle_-_01.jpg — CC BY-SA 4.0
        //   Nelore_cattle_Brasil.jpg — CC BY 2.0
        //   Milchkuh_auf_Weide_in_NRW.jpg — CC BY 2.0
        //   Simmental_calf_cow.jpg — dominio público
        // Autoría y licencia completas en https://commons.wikimedia.org/wiki/File:<nombre>.
        $catalogo = [
            ['titulo' => 'Novillas de engorde', 'imagen' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4f/Brahman_cattle_in_Costa_Rica.jpg/960px-Brahman_cattle_in_Costa_Rica.jpg', 'raza' => 'Brahman', 'sexo' => 'HEMBRA', 'precio' => 950000,
                'edad' => 18, 'peso' => 320.5, 'proposito' => 'ENGORDE',
                'descripcion' => 'Lote de novillas listas para engorde, con desparasitación al día.'],
            ['titulo' => 'Vaca lechera Jersey', 'imagen' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/Jersey_Cow_01.jpg/960px-Jersey_Cow_01.jpg', 'raza' => 'Jersey', 'sexo' => 'HEMBRA', 'precio' => 1200000,
                'edad' => 36, 'peso' => 410.0, 'proposito' => 'DOBLE PROPOSITO',
                'descripcion' => 'Segundo parto, producción estable durante la última lactancia.'],
            ['titulo' => 'Toro reproductor Gyr', 'imagen' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/Gyr_cattle_-_01.jpg/960px-Gyr_cattle_-_01.jpg', 'raza' => 'Gyr', 'sexo' => 'MACHO', 'precio' => 2450000,
                'edad' => 48, 'peso' => 780.0, 'proposito' => 'CRIA',
                'descripcion' => 'Reproductor con registro, apto para monta directa.'],
            ['titulo' => 'Terneros destetados', 'imagen' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1c/Nelore_cattle_Brasil.jpg/960px-Nelore_cattle_Brasil.jpg', 'raza' => 'Nelore', 'sexo' => 'MACHO', 'precio' => 680000,
                'edad' => 8, 'peso' => 210.0, 'proposito' => 'ENGORDE',
                'descripcion' => 'Lote recién destetado, vacunado y acostumbrado a potrero.'],
            ['titulo' => 'Vaquillas Holstein', 'imagen' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/98/Milchkuh_auf_Weide_in_NRW.jpg/960px-Milchkuh_auf_Weide_in_NRW.jpg', 'raza' => 'Holstein', 'sexo' => 'HEMBRA', 'precio' => 1050000,
                'edad' => 20, 'peso' => 380.0, 'proposito' => 'LECHE',
                'descripcion' => 'Vaquillas de reemplazo para lechería, manejo tranquilo.'],
            ['titulo' => 'Vaca de cría Simmental', 'imagen' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/03/Simmental_calf_cow.jpg/960px-Simmental_calf_cow.jpg', 'raza' => 'Simmental', 'sexo' => 'HEMBRA', 'precio' => 1380000,
                'edad' => 54, 'peso' => 560.0, 'proposito' => 'CRIA',
                'descripcion' => 'Buena madre, con cría al pie y partos sin asistencia.'],
        ];

        $animales = new AnimalComercial($conexion);
        $conexion->beginTransaction();
        try {
            foreach ($catalogo as $indice => $datos) {
                $animalId = $animales->ejecutarConBloqueoAlta('tbanimal',
                    static fn (): int => $animales->crearAnimal(
                        sprintf('DEMO-%03d', $indice + 1), $datos['sexo'], $datos['raza'], self::ORIGEN));
                $animales->ejecutarConBloqueoAlta('tbanimalproduccionsalud',
                    static fn (): int => $animales->registrarObservacion($animalId, [
                        'origen' => self::ORIGEN,
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
                            'imagenUrl' => $datos['imagen'] ?? null,
                            'origen' => self::ORIGEN,
                            'estado' => 'ACTIVO',
                        ]));
            }
            $conexion->commit();
        } catch (Throwable $error) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            throw $error;
        }

        return count($catalogo);
    }
}
