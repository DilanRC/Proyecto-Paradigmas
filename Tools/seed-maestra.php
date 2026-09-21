<?php

declare(strict_types=1);

/**
 * Semilla maestra (DEC-32): puebla TODAS las tablas madre que alimentan los
 * SELECT/páneles usando los MISMOS servicios PHP de la API (MAX(id)+1, fechas
 * por PHP, NamedLock, bitácora). Nada de IDs a mano ni SQL suelto: cada alta
 * pasa por su controlador, por lo que la puerta de nombres (
 * `Tests/naming_gate.php`), la de esquema (`Tests/schema_manifest_test.php`) y
 * la de base lista (`Tests/backend_db_ready_test.php`) siguen verdes.
 *
 * La semilla es idempotente: si una identificación o placa ya existe, la omite.
 * Los nombres propios (fincas, personas) son ficticios y no chocan con las
 * semillas SQL existentes (101/103) ni con las pruebas (TST...).
 *
 *   docker compose exec -T app php Tools/seed-maestra.php        # sembrar
 *   docker compose exec -T app php Tools/seed-maestra.php --check  # auditar
 *   docker compose exec -T app php Tools/seed-maestra.php --limpiar # retirar
 */

use Application\Controller\CompradorController;
use Application\Controller\FincaController;
use Application\Controller\ProductorController;
use Application\Controller\TransportistaController;
use Application\Controller\TransportistaVehiculoController;
use Application\Controller\VehiculoController;

$raiz = dirname(__DIR__);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/HttpException.php';
require_once $raiz . '/Application/Auth/ActorContext.php';
require_once $raiz . '/Application/Auth/SupabaseActorResolver.php';
foreach (['NamedLock', 'PersonaTelefonoHistorico', 'Persona', 'Comprador', 'ProductorFinca',
    'Direccion', 'ProductorDireccion', 'FincaDireccion', 'Bitacora', 'Productor',
    'ProductorUbicacion', 'ProductorEstadoPeriodo', 'ProductorClasificacionPeriodo',
    'Transportista', 'TransportistaVehiculo', 'Vehiculo'] as $modelo) {
    require_once $raiz . "/Application/Model/{$modelo}.php";
}
foreach (['ProductorDireccionService', 'ProductorEstadoService', 'ValidacionService',
    'EstadoService', 'CompradorClasificacionService'] as $servicio) {
    require_once $raiz . "/Application/Service/{$servicio}.php";
}
foreach (['ProductorController', 'CompradorController', 'FincaController',
    'TransportistaController', 'VehiculoController', 'TransportistaVehiculoController'] as $controlador) {
    require_once $raiz . "/Application/Controller/{$controlador}.php";
}

const SEED_ORIGEN = 'SEED_MAESTRA';

const SEED_PRODUCTORES = [
    [
        'identificacion' => ['tipoCodigo' => 'CEDULA_FISICA', 'numero' => '104550123'],
        'nombre' => 'María José Chaves Rojas',
        'telefono' => '+506 8655-1020',
        'correoElectronico' => 'santa.rosa@example.test',
        'direccionPrincipal' => [
            'provincia' => 'Heredia', 'canton' => 'San Rafael', 'distrito' => 'San Josecito',
            'pueblo' => 'Los Ángeles', 'senas' => 'Vereda Los Retoños, camino al río.',
        ],
        'fincas' => [['nombre' => 'Finca La Primavera'], ['nombre' => 'Finca El Bosque']],
    ],
    [
        'identificacion' => ['tipoCodigo' => 'CEDULA_JURIDICA', 'numero' => '3101556677'],
        'nombre' => 'Ganadería Los Cerros S.A.',
        'telefono' => '+506 2255-8877',
        'correoElectronico' => 'los.cerros@example.test',
        'direccionPrincipal' => [
            'provincia' => 'Alajuela', 'canton' => 'San Carlos', 'distrito' => 'Aguas Zarcas',
            'pueblo' => null, 'senas' => 'Frente a la escuela, portón verde.',
        ],
        'fincas' => [['nombre' => 'Finca Los Cerros']],
    ],
];

const SEED_COMPRADOR = [
    'identificacion' => ['tipoCodigo' => 'CEDULA_JURIDICA', 'numero' => '3101333344'],
    'nombre' => 'Comercializadora El Mercado S.A.',
    'telefono' => '+506 2444-5566',
    'correoElectronico' => 'el.mercado@example.test',
];

const SEED_TRANSPORTISTA = [
    'identificacion' => ['tipoCodigo' => 'CEDULA_FISICA', 'numero' => '108550999'],
    'nombre' => 'Julio César Mora Alfaro',
    'telefono' => '+506 8777-3344',
    'correoElectronico' => 'julio.mora@example.test',
];

const SEED_VEHICULOS = [
    ['placa' => 'ABC-148', 'vin' => '1HGCM82633A004352', 'modelo' => 'Toyota Hilux 4x4 Doble Cabina'],
    ['placa' => 'ABC-901', 'vin' => '2FTJF35L6FCA13579', 'modelo' => 'Isuzu NQR Cabina Simple'],
];

const SEED_VEHICULO_TRANSPORTISTA = 'ABC-148';

function seed_maestra(PDO $conexion, ?callable $notificar = null): array
{
    $notificar ??= static function (string $mensaje): void {
        echo $mensaje . PHP_EOL;
    };
    $resumen = [
        'productores' => 0, 'productoresYaListos' => 0,
        'compradores' => 0, 'compradoresYaListos' => 0,
        'transportistas' => 0, 'transportistasYaListos' => 0,
        'vehiculos' => 0, 'vehiculosYaListos' => 0,
        'asignaciones' => 0, 'asignacionesYaListas' => 0,
        'direccionesFinca' => 0, 'direccionesFincaYaListas' => 0,
    ];

    // Solicitud corta a propósito: los tests limpian su propia bitácora con
    // LENGTH(tbbitacorasolicitudid) > 5, y la semilla es dato de instalación,
    // no de prueba: su rastro en tbbitacora debe sobrevivir a la suite.
    $controladorProductores = new ProductorController($conexion, 'SEED');
    $controladorCompradores = new CompradorController($conexion, 'SEED');
    $controladorTransportistas = new TransportistaController($conexion, 'SEED');
    $controladorVehiculos = new VehiculoController($conexion, 'SEED');
    $controladorFincas = new FincaController($conexion, 'SEED');
    $controladorAsignaciones = new TransportistaVehiculoController($conexion, 'SEED');

    // -----------------------------------------------
    // Productores (persona + contexto + fincas + dirección).
    // -----------------------------------------------
    foreach (SEED_PRODUCTORES as $productor) {
        $identificacion = $productor['identificacion']['numero'];
        $existe = $controladorProductores->procesar('GET', ['identificacionNumero' => $identificacion], [])['status'] === 200;
        if (!$existe) {
            $creado = $controladorProductores->procesar('POST', [], $productor);
            seed_maestra_exigir($creado, 201, "alta del productor {$identificacion}");
            $resumen['productores']++;
            $notificar("Creado productor {$identificacion} ({$productor['nombre']}).");
        } else {
            $resumen['productoresYaListos']++;
            $notificar("Productor {$identificacion} ya sembrado; se omite.");
        }

        // Dirección por finca (madre de tbfincadireccion).
        foreach ($productor['fincas'] as $finca) {
            $nombreFinca = $finca['nombre'];
            $tieneDireccion = $controladorFincas->procesarDireccion('GET', [
                'identificacionNumero' => $identificacion, 'nombreFinca' => $nombreFinca,
            ], [])['status'] === 200;
            if (!$tieneDireccion) {
                $creada = $controladorFincas->procesarDireccion('POST', [], [
                    'identificacionNumero' => $identificacion,
                    'nombreFinca' => $nombreFinca,
                    'direccionFinca' => [
                        'provincia' => $productor['direccionPrincipal']['provincia'],
                        'canton' => $productor['direccionPrincipal']['canton'],
                        'distrito' => $productor['direccionPrincipal']['distrito'],
                        'pueblo' => $productor['direccionPrincipal']['pueblo'] ?? null,
                        'senas' => 'Ubicación de ' . $nombreFinca . ' (semilla maestra).',
                    ],
                ]);
                seed_maestra_exigir($creada, 201, "dirección de finca {$nombreFinca}");
                $resumen['direccionesFinca']++;
                $notificar("Ubicada finca {$nombreFinca} de {$identificacion}.");
            } else {
                $resumen['direccionesFincaYaListas']++;
            }
        }
    }

    // -----------------------------------------------
    // Comprador (contexto de Persona, madre de tbcomprador).
    // -----------------------------------------------
    $compradorId = SEED_COMPRADOR['identificacion']['numero'];
    $compradorExiste = $controladorCompradores->procesar('GET', ['identificacionNumero' => $compradorId], [])['status'] === 200;
    if (!$compradorExiste) {
        $creado = $controladorCompradores->procesar('POST', [], SEED_COMPRADOR);
        seed_maestra_exigir($creado, 201, "inscripción del comprador {$compradorId}");
        $resumen['compradores']++;
        $notificar('Inscrito comprador ' . $compradorId . ' (' . SEED_COMPRADOR['nombre'] . ').');
    } else {
        $resumen['compradoresYaListos']++;
        $notificar("Comprador {$compradorId} ya sembrado; se omite.");
    }

    // -----------------------------------------------
    // Transportista (contexto de Persona, madre de tbtransportista).
    // -----------------------------------------------
    $transportistaId = SEED_TRANSPORTISTA['identificacion']['numero'];
    $transportistaExiste = $controladorTransportistas->procesar('GET', ['identificacionNumero' => $transportistaId], [])['status'] === 200;
    if (!$transportistaExiste) {
        $creado = $controladorTransportistas->procesar('POST', [], SEED_TRANSPORTISTA);
        seed_maestra_exigir($creado, 201, "alta del transportista {$transportistaId}");
        $resumen['transportistas']++;
        $notificar('Creado transportista ' . $transportistaId . ' (' . SEED_TRANSPORTISTA['nombre'] . ').');
    } else {
        $resumen['transportistasYaListos']++;
        $notificar("Transportista {$transportistaId} ya sembrado; se omite.");
    }

    // -----------------------------------------------
    // Vehículos (madre de tbvehiculo).
    // -----------------------------------------------
    $vehiculoIds = [];
    foreach (SEED_VEHICULOS as $vehiculo) {
        $fila = seed_maestra_buscarVehiculoPorPlaca($conexion, $vehiculo['placa']);
        if ($fila === null) {
            $creado = $controladorVehiculos->procesar('POST', [], $vehiculo);
            seed_maestra_exigir($creado, 201, "alta del vehículo {$vehiculo['placa']}");
            $vehiculoIds[$vehiculo['placa']] = (int) $creado['body']['data']['vehiculoId'];
            $resumen['vehiculos']++;
            $notificar("Creado vehículo {$vehiculo['placa']} ({$vehiculo['modelo']}).");
        } else {
            $vehiculoIds[$vehiculo['placa']] = (int) $fila['tbvehiculoid'];
            $resumen['vehiculosYaListos']++;
            $notificar("Vehículo {$vehiculo['placa']} ya sembrado; se omite.");
        }
    }

    // -----------------------------------------------
    // Asignación transportista → vehículo (madre de tbtransportistavehiculo).
    // -----------------------------------------------
    $vehiculoAsignado = $vehiculoIds[SEED_VEHICULO_TRANSPORTISTA] ?? null;
    if ($vehiculoAsignado !== null) {
        $asignado = seed_maestra_buscarAsignacion($conexion, $vehiculoAsignado);
        if (!$asignado) {
            $creada = $controladorAsignaciones->procesar('POST', [], [
                'identificacionNumero' => $transportistaId,
                'vehiculoId' => $vehiculoAsignado,
            ]);
            seed_maestra_exigir($creada, 201, "asignación del vehículo a {$transportistaId}");
            $resumen['asignaciones']++;
            $notificar("Asignado {$vehiculoIds[SEED_VEHICULO_TRANSPORTISTA]} al transportista {$transportistaId}.");
        } else {
            $resumen['asignacionesYaListas']++;
        }
    }

    return $resumen;
}

function seed_maestra_check(PDO $conexion, ?callable $notificar = null): array
{
    $notificar ??= static function (string $mensaje): void {
        echo $mensaje . PHP_EOL;
    };
    $faltantes = [];

    // Tabla madre tbpagometodo (la semilla SQL 101 aporta Efectivo).
    if ((int) $conexion->query('SELECT COUNT(*) FROM tbpagometodo')->fetchColumn() < 1) {
        $faltantes[] = 'tbpagometodo sin fila (falta 101initialpagometodo.sql)';
    }

    foreach (SEED_PRODUCTORES as $productor) {
        $identificacion = $productor['identificacion']['numero'];
        $existe = $conexion->prepare('SELECT 1 FROM tbpersona pe
            INNER JOIN tbproductor p ON p.tbpersonaid = pe.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
        $existe->execute(['id' => $identificacion]);
        if ($existe->fetch() === false) {
            $faltantes[] = "productor {$identificacion}";
        }
        foreach ($productor['fincas'] as $finca) {
            $fincaOk = $conexion->prepare('SELECT 1 FROM tbfinca f
                INNER JOIN tbproductor p ON p.tbproductorid = f.tbproductorid
                INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
                WHERE pe.tbpersonaidentificacionnumero = :id AND f.tbfincanombre = :nombre LIMIT 1');
            $fincaOk->execute(['id' => $identificacion, 'nombre' => $finca['nombre']]);
            if ($fincaOk->fetch() === false) {
                $faltantes[] = "finca {$finca['nombre']} del productor {$identificacion}";
            }
        }
    }

    $compradorId = SEED_COMPRADOR['identificacion']['numero'];
    $compradorOk = $conexion->prepare('SELECT 1 FROM tbpersona pe
        INNER JOIN tbcomprador c ON c.tbpersonaid = pe.tbpersonaid
        WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
    $compradorOk->execute(['id' => $compradorId]);
    if ($compradorOk->fetch() === false) {
        $faltantes[] = "comprador {$compradorId}";
    }

    $transportistaId = SEED_TRANSPORTISTA['identificacion']['numero'];
    $transportistaOk = $conexion->prepare('SELECT 1 FROM tbpersona pe
        INNER JOIN tbtransportista t ON t.tbpersonaid = pe.tbpersonaid
        WHERE pe.tbpersonaidentificacionnumero = :id LIMIT 1');
    $transportistaOk->execute(['id' => $transportistaId]);
    if ($transportistaOk->fetch() === false) {
        $faltantes[] = "transportista {$transportistaId}";
    }

    foreach (SEED_VEHICULOS as $vehiculo) {
        if (seed_maestra_buscarVehiculoPorPlaca($conexion, $vehiculo['placa']) === null) {
            $faltantes[] = "vehículo {$vehiculo['placa']}";
        }
    }
    $asignado = seed_maestra_buscarVehiculoPorPlaca($conexion, SEED_VEHICULO_TRANSPORTISTA);
    if ($asignado !== null && !seed_maestra_buscarAsignacion($conexion, (int) $asignado['tbvehiculoid'])) {
        $faltantes[] = "asignación del vehículo " . SEED_VEHICULO_TRANSPORTISTA . " al transportista";
    }

    return ['completa' => $faltantes === [], 'faltantes' => $faltantes];
}

function seed_maestra_limpiar(PDO $conexion, ?callable $notificar = null): array
{
    $notificar ??= static function (string $mensaje): void {
        echo $mensaje . PHP_EOL;
    };
    $identificaciones = array_merge(
        array_column(SEED_PRODUCTORES, 'identificacion'),
        [SEED_COMPRADOR['identificacion'], SEED_TRANSPORTISTA['identificacion']],
    );
    $numeros = array_values(array_unique(array_map(
        static fn (array $id): string => (string) $id['numero'], $identificaciones,
    )));
    $placas = array_column(SEED_VEHICULOS, 'placa');
    $marcadores = implode(',', array_fill(0, count($numeros), '?'));

    $conexion->beginTransaction();
    try {
        $productorIds = $conexion->prepare("SELECT p.tbproductorid FROM tbproductor p
            INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero IN ({$marcadores})");
        $productorIds->execute($numeros);
        $productores = array_map('intval', $productorIds->fetchAll(PDO::FETCH_COLUMN));

        if ($productores !== []) {
            $mp = implode(',', array_fill(0, count($productores), '?'));

            $fincaIds = $conexion->prepare("SELECT tbfincaid FROM tbfinca WHERE tbproductorid IN ({$mp})");
            $fincaIds->execute($productores);
            $fincas = array_map('intval', $fincaIds->fetchAll(PDO::FETCH_COLUMN));

            $direccionIds = [];
            $dirProductor = $conexion->prepare("SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid IN ({$mp})");
            $dirProductor->execute($productores);
            $direccionIds = array_merge($direccionIds, array_map('intval', $dirProductor->fetchAll(PDO::FETCH_COLUMN)));

            if ($fincas !== []) {
                $mf = implode(',', array_fill(0, count($fincas), '?'));
                $dirFinca = $conexion->prepare("SELECT tbdireccionid FROM tbfincadireccion WHERE tbfincaid IN ({$mf})");
                $dirFinca->execute($fincas);
                $direccionIds = array_merge($direccionIds, array_map('intval', $dirFinca->fetchAll(PDO::FETCH_COLUMN)));

                $conexion->prepare("DELETE FROM tbfincadireccion WHERE tbfincaid IN ({$mf})")->execute($fincas);
                $conexion->prepare("DELETE FROM tbfinca WHERE tbfincaid IN ({$mf})")->execute($fincas);
            }

            $conexion->prepare("DELETE FROM tbproductorubicacion WHERE tbproductorid IN ({$mp})")->execute($productores);
            $conexion->prepare("DELETE FROM tbproductordireccion WHERE tbproductorid IN ({$mp})")->execute($productores);
            $conexion->prepare("DELETE FROM tbproductorestadoperiodo WHERE tbproductorid IN ({$mp})")->execute($productores);
            $conexion->prepare("DELETE FROM tbproductorclasificacionperiodo WHERE tbproductorid IN ({$mp})")->execute($productores);
            $conexion->prepare("DELETE FROM tbproductor WHERE tbproductorid IN ({$mp})")->execute($productores);

            $direccionIds = array_values(array_unique(array_filter($direccionIds)));
            if ($direccionIds !== []) {
                $md = implode(',', array_fill(0, count($direccionIds), '?'));
                $conexion->prepare("DELETE FROM tbdireccion WHERE tbdireccionid IN ({$md})")->execute($direccionIds);
            }
        }

        $vehiculoIds = [];
        foreach ($placas as $placa) {
            $fila = seed_maestra_buscarVehiculoPorPlaca($conexion, $placa);
            if ($fila !== null) {
                $vehiculoIds[] = (int) $fila['tbvehiculoid'];
            }
        }
        $vehiculoIds = array_values(array_unique($vehiculoIds));
        if ($vehiculoIds !== []) {
            $mv = implode(',', array_fill(0, count($vehiculoIds), '?'));
            $conexion->prepare("DELETE FROM tbtransportistavehiculo WHERE tbvehiculoid IN ({$mv})")->execute($vehiculoIds);
            $conexion->prepare("DELETE FROM tbvehiculo WHERE tbvehiculoid IN ({$mv})")->execute($vehiculoIds);
        }

        $conexion->prepare("DELETE t FROM tbcomprador t INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero IN ({$marcadores})")->execute($numeros);
        $conexion->prepare("DELETE t FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
            WHERE pe.tbpersonaidentificacionnumero IN ({$marcadores})")->execute($numeros);

        $registrosBitacora = array_merge($numeros, array_map('strval', $vehiculoIds));
        $registrosBitacora = array_values(array_unique($registrosBitacora));
        if ($registrosBitacora !== []) {
            $mb = implode(',', array_fill(0, count($registrosBitacora), '?'));
            $conexion->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero IN ({$mb})")->execute($registrosBitacora);
        }

        $conexion->prepare("DELETE FROM tbpersona WHERE tbpersonaidentificacionnumero IN ({$marcadores})")->execute($numeros);

        $conexion->commit();
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $error;
    }

    $notificar('Semilla maestra retirada (filas de ' . SEED_ORIGEN . ').');

    return ['retiradas' => true, 'identificaciones' => $numeros, 'vehiculos' => $vehiculoIds];
}

function seed_maestra_buscarVehiculoPorPlaca(PDO $conexion, string $placa): ?array
{
    $sentencia = $conexion->prepare('SELECT tbvehiculoid FROM tbvehiculo WHERE tbvehiculoplaca = :placa LIMIT 1');
    $sentencia->execute(['placa' => $placa]);
    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function seed_maestra_buscarAsignacion(PDO $conexion, int $vehiculoId): bool
{
    $sentencia = $conexion->prepare(
        'SELECT 1 FROM tbtransportistavehiculo WHERE tbvehiculoid = :vehiculoId LIMIT 1'
    );
    $sentencia->execute(['vehiculoId' => $vehiculoId]);

    return $sentencia->fetch() !== false;
}

function seed_maestra_exigir(array $respuesta, int $esperado, string $operacion): void
{
    if (($respuesta['status'] ?? 0) !== $esperado) {
        throw new RuntimeException(
            "La semilla maestra falló en {$operacion}: "
            . json_encode($respuesta['body'] ?? $respuesta, JSON_UNESCAPED_UNICODE)
        );
    }
}

function seed_maestra_main(array $argv): int
{
    $modo = 'sembrar';
    foreach (array_slice($argv, 1) as $argumento) {
        if (in_array($argumento, ['--check', '--limpiar'], true)) {
            $modo = substr($argumento, 2);
        } else {
            throw new RuntimeException("Argumento no reconocido: {$argumento}");
        }
    }

    $conexion = \Configuration\Database::getConnection();

    if ($modo === 'limpiar') {
        seed_maestra_limpiar($conexion);
        return 0;
    }

    if ($modo === 'check') {
        $estado = seed_maestra_check($conexion);
        foreach ($estado['faltantes'] as $faltante) {
            fwrite(STDERR, "Falta: {$faltante}" . PHP_EOL);
        }
        echo 'Semilla maestra ' . ($estado['completa'] ? 'COMPLETA.' : 'INCOMPLETA.') . PHP_EOL;
        return $estado['completa'] ? 0 : 1;
    }

    $resumen = seed_maestra($conexion);
    printf(
        "Semilla maestra: %d productores (+%d ya), %d compradores (+%d ya), %d transportistas (+%d ya), "
        . "%d vehículos (+%d ya), %d asignaciones (+%d ya), %d direcciones de finca (+%d ya).\n",
        $resumen['productores'], $resumen['productoresYaListos'],
        $resumen['compradores'], $resumen['compradoresYaListos'],
        $resumen['transportistas'], $resumen['transportistasYaListos'],
        $resumen['vehiculos'], $resumen['vehiculosYaListos'],
        $resumen['asignaciones'], $resumen['asignacionesYaListas'],
        $resumen['direccionesFinca'], $resumen['direccionesFincaYaListas'],
    );

    return 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(seed_maestra_main($argv));
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
}