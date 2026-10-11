<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/AnimalValidacionService.php';
require_once dirname(__DIR__) . '/Model/AnimalCatalogo.php';
require_once __DIR__ . '/AnimalPublicacionController.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalCatalogo;
use Application\Model\AnimalComercial;
use Application\Model\AnimalVacunacion;
use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Service\AnimalValidacionService;
use PDO;
use Throwable;

/**
 * Inventario del vendedor (P2-4): ver sus animales, registrar uno sin publicarlo y publicarlo después.
 * Un animal ajeno responde 404. Cada alta queda en la bitácora.
 */
final class MiAnimalesController
{
    private const ORIGEN = 'API_MI_ANIMALES';

    private AnimalComercial $animales;
    private AnimalCatalogo $catalogo;
    private AnimalVacunacion $vacunaciones;
    private Productor $productor;
    private ProductorFinca $fincas;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->animales = new AnimalComercial($conexion);
        $this->catalogo = new AnimalCatalogo($conexion);
        $this->vacunaciones = new AnimalVacunacion($conexion);
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);
        $this->bitacora = new Bitacora($conexion, $actor);
        $solicitudId = trim((string) $solicitudId);
        $this->solicitudId = $solicitudId !== '' && strlen($solicitudId) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $solicitudId)
            ? $solicitudId : 'REQ-' . bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $cuerpo = []): array
    {
        try {
            if (!$this->actor->tienePersona()) {
                throw new HttpException('Debe iniciar sesión para ver sus animales.', 401);
            }
            return match ($metodo) {
                'GET' => $this->respuesta(true, 'Inventario consultado correctamente.', [
                    'animales' => $this->animales->inventarioPropio($this->productorId(false)),
                ]),
                'POST' => $this->registrar($cuerpo),
                'PATCH' => $this->publicar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    /** POST: registra un animal en el inventario (estado ACTIVO), sin publicación. */
    private function registrar(array $cuerpo): array
    {
        $campos = ['especieId', 'tipoId', 'razaId', 'sexo', 'arete', 'fechaNacimiento', 'fechaNacimientoEstimada', 'partos',
            'peso', 'edadMeses', 'proposito', 'raza', 'loteCantidad'];
        $desconocidos = array_diff(array_keys($cuerpo), $campos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
        // Todo es opcional, pero un animal sin ningún dato no sirve en el inventario.
        $conDatos = array_filter(array_diff_key($cuerpo, ['fechaNacimientoEstimada' => 0]),
            static fn (mixed $valor): bool => $valor !== null && $valor !== '');
        if ($conDatos === []) {
            throw new HttpException('No se puede registrar un animal sin datos. Completa al menos uno: especie, tipo, raza, arete, peso o edad.', 422, null, [
                'especieId' => 'Completa al menos un dato del animal.',
            ]);
        }
        $texto = AnimalPublicacionController::texto(...);
        $numero = AnimalPublicacionController::numero(...);
        $v = AnimalPublicacionController::validarTodos([
            'modelo' => function () use ($cuerpo): array {
                $modelo = AnimalValidacionService::validar($this->catalogo, $cuerpo);
                if ($modelo['loteCantidad'] > 1) {
                    throw new HttpException('Revise los campos indicados.', 422, null, ['loteCantidad' => 'Registra los animales uno por uno.']);
                }
                return $modelo;
            },
            'raza' => fn () => $texto($cuerpo['raza'] ?? null, 'raza', 120),
            'sexo' => fn () => $texto($cuerpo['sexo'] ?? null, 'sexo', 40),
            'proposito' => fn () => $texto($cuerpo['proposito'] ?? null, 'proposito', 80),
            'edadMeses' => fn () => $numero($cuerpo['edadMeses'] ?? null, 'edadMeses', true),
            'peso' => fn () => $numero($cuerpo['peso'] ?? null, 'peso'),
        ]);
        $modelo = $v['modelo'];
        $datos = [
            'raza' => $modelo['razaNombre'] ?? $v['raza'],
            'sexo' => $modelo['sexo'] ?? $v['sexo'],
            'proposito' => $v['proposito'],
            'edadMeses' => $v['edadMeses'],
            'peso' => $v['peso'],
        ];
        $productorId = $this->productorId(true);

        $animal = $this->animales->ejecutarConBloqueoAlta('tbanimal', fn (): array => $this->animales->ejecutarConBloqueoAlta(
            'tbanimalproduccionsalud',
            fn (): array => $this->enTransaccion(function () use ($modelo, $datos, $productorId): array {
                if ($modelo['arete'] !== null && $this->animales->existeAreteVigente($modelo['arete'])) {
                    throw new HttpException('Revise los campos indicados.', 409, null, ['arete' => 'Ya hay un animal con ese arete.']);
                }
                $animalId = $this->animales->crearAnimal($modelo['arete'], $datos['sexo'], $datos['raza'], self::ORIGEN, null, [
                    'especieId' => $modelo['especieId'],
                    'tipoId' => $modelo['tipoId'],
                    'razaId' => $modelo['razaId'],
                    'fechaNacimiento' => $modelo['fechaNacimiento'],
                    'fechaNacimientoEstimada' => $modelo['fechaNacimientoEstimada'],
                    'partos' => $modelo['partos'],
                    'estado' => 'ACTIVO',
                    'productorId' => $productorId,
                ]);
                $this->animales->registrarObservacion($animalId, [
                    'origen' => self::ORIGEN,
                    'edadMeses' => $datos['edadMeses'],
                    'peso' => $datos['peso'],
                    'proposito' => $datos['proposito'],
                ]);
                $nuevo = ['animalId' => $animalId, 'arete' => $modelo['arete'], 'estado' => 'ACTIVO'] + $datos;
                $this->bitacora->registrar('CREAR', (string) $animalId, null, $nuevo, $this->solicitudId,
                    entidad: 'ANIMAL', origen: self::ORIGEN);
                return $nuevo;
            }),
        ));

        return $this->respuesta(true, 'Animal registrado en tu inventario.', [
            'animal' => $animal,
            'animales' => $this->animales->inventarioPropio($productorId),
        ], 201);
    }

    /** PATCH { animalId, accion: 'PUBLICAR', fincaNombre, titulo, precio?, descripcion?, imagenUrl? } */
    private function publicar(array $cuerpo): array
    {
        $permitidos = ['animalId', 'accion', 'fincaNombre', 'titulo', 'precio', 'descripcion', 'imagenUrl'];
        $errores = [];
        foreach (array_diff(array_keys($cuerpo), $permitidos) as $campo) $errores[$campo] = 'Campo no permitido.';
        if (($cuerpo['accion'] ?? null) !== 'PUBLICAR') $errores['accion'] = 'La única acción disponible es PUBLICAR.';
        $animalId = filter_var(is_bool($cuerpo['animalId'] ?? null) ? false : $cuerpo['animalId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($animalId === false) $errores['animalId'] = 'Debe ser un entero positivo.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $texto = AnimalPublicacionController::texto(...);
        $datos = AnimalPublicacionController::validarTodos([
            'fincaNombre' => fn () => $texto($cuerpo['fincaNombre'] ?? null, 'fincaNombre', 150, true),
            'titulo' => fn () => $texto($cuerpo['titulo'] ?? null, 'titulo', 150, true),
            'descripcion' => fn () => $texto($cuerpo['descripcion'] ?? null, 'descripcion', 500),
            'precio' => fn () => AnimalPublicacionController::numero($cuerpo['precio'] ?? null, 'precio'),
            'imagenUrl' => fn () => AnimalPublicacionController::imagenUrl($cuerpo['imagenUrl'] ?? null),
        ]);
        $productorId = $this->productorId(true);
        $fincaId = $this->fincas->buscarIdActivo($productorId, $datos['fincaNombre']);
        if ($fincaId === null) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['fincaNombre' => 'Seleccione una finca activa de su perfil.']);
        }

        // Orden de locks: publicación -> periodo de estado -> bitácora, dentro de una transacción.
        $resultado = $this->animales->ejecutarConBloqueoAlta('tbanimalpublicacion', fn (): array => $this->animales->ejecutarConBloqueoAlta(
            'tbanimalpublicacionestadoperiodo',
            fn (): array => $this->enTransaccion(function () use ($animalId, $productorId, $fincaId, $datos): array {
                $animal = $this->vacunaciones->animalPropio($animalId, $productorId);
                if ($animal === null) throw new HttpException('El animal no existe en tu cuenta.', 404);
                // Solo un animal en inventario (ACTIVO) se publica: uno PUBLICADO ya tiene su publicación abierta.
                if ($animal['estado'] !== 'ACTIVO') {
                    throw new HttpException('El animal ya está publicado o no está en tu inventario.', 409);
                }
                $publicacionId = $this->animales->publicarAnimal($animalId, $productorId, $fincaId, [
                    'origen' => self::ORIGEN,
                    'estado' => 'ACTIVO',
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'],
                    'precio' => $datos['precio'],
                    'imagenUrl' => $datos['imagenUrl'],
                ]);
                $this->animales->marcarEstadoAnimales([$animalId], 'PUBLICADO');
                $resultado = ['animalId' => $animalId, 'publicacionId' => $publicacionId, 'loteCantidad' => 1];
                $this->bitacora->registrar('CREAR', 'PUBLICACION:' . $publicacionId, null, $resultado + $datos,
                    $this->solicitudId, entidad: 'PUBLICACION', origen: self::ORIGEN);
                return $resultado;
            }),
        ));

        return $this->respuesta(true, 'Publicación creada correctamente.', $resultado + [
            'animales' => $this->animales->inventarioPropio($productorId),
        ], 201);
    }

    private function productorId(bool $escritura): int
    {
        $productor = $this->productor->buscarPorPersonaId((int) $this->actor->personaId);
        if ($productor === null) {
            throw new HttpException('La cuenta no tiene la actividad Vendedor configurada.', 409);
        }
        if ($escritura && ((int) ($productor['tbproductorestado'] ?? 0) !== 1 || (int) ($productor['tbpersonaestado'] ?? 0) !== 1)) {
            throw new HttpException('La actividad Vendedor está inactiva.', 409);
        }

        return (int) $productor['tbproductorid'];
    }

    /** La bitácora se bloquea antes de abrir la transacción (cuidado #12 de MEMORIA.md). */
    private function enTransaccion(callable $operacion): array
    {
        return $this->bitacora->ejecutarConBloqueoAlta(function () use ($operacion): array {
            $this->conexion->beginTransaction();
            try {
                $resultado = $operacion();
                $this->conexion->commit();
                return $resultado;
            } catch (Throwable $excepcion) {
                if ($this->conexion->inTransaction()) $this->conexion->rollBack();
                throw $excepcion;
            }
        });
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
