<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/AnimalValidacionService.php';
require_once dirname(__DIR__) . '/Model/AnimalCatalogo.php';
require_once dirname(__DIR__) . '/Service/CampoValidacion.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Service\CampoValidacion;
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
 * Inventario del vendedor (P2-4): ver sus animales, registrar uno sin publicarlo, editarlo, llevar su
 * historial de pesos y publicarlo después. Un animal ajeno responde 404. Cada cambio queda en la bitácora.
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
        private PDO $conexion,
        private ActorContext $actor,
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
                'GET' => array_key_exists('animalId', $cuerpo) ? $this->historial($cuerpo) : $this->respuesta(true, 'Inventario consultado correctamente.', [
                    'animales' => $this->animales->inventarioPropio($this->productorId(false)),
                ]),
                'POST' => $this->registrar($cuerpo),
                'PATCH' => match ($cuerpo['accion'] ?? null) {
                    'EDITAR' => $this->editar($cuerpo),
                    'PESAR' => $this->pesar($cuerpo),
                    default => $this->publicar($cuerpo),
                },
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
        $texto = \Closure::fromCallable([CampoValidacion::class, 'texto']);
        $numero = \Closure::fromCallable([CampoValidacion::class, 'numero']);
        $v = CampoValidacion::validarTodos([
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
        if (($cuerpo['accion'] ?? null) !== 'PUBLICAR') $errores['accion'] = 'Use PUBLICAR, EDITAR o PESAR.';
        $animalId = filter_var(is_bool($cuerpo['animalId'] ?? null) ? false : $cuerpo['animalId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($animalId === false) $errores['animalId'] = 'Debe ser un entero positivo.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $texto = \Closure::fromCallable([CampoValidacion::class, 'texto']);
        $datos = CampoValidacion::validarTodos([
            'fincaNombre' => fn () => $texto($cuerpo['fincaNombre'] ?? null, 'fincaNombre', 150, true),
            'titulo' => fn () => $texto($cuerpo['titulo'] ?? null, 'titulo', 150, true),
            'descripcion' => fn () => $texto($cuerpo['descripcion'] ?? null, 'descripcion', 500),
            'precio' => fn () => CampoValidacion::numero($cuerpo['precio'] ?? null, 'precio'),
            'imagenUrl' => fn () => CampoValidacion::imagenUrl($cuerpo['imagenUrl'] ?? null),
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

    /** GET ?animalId=: historial de pesos (observaciones) de un animal propio, de la más reciente a la más antigua. */
    private function historial(array $consulta): array
    {
        $animalId = $this->animalId($consulta);
        $productorId = $this->productorId(false);
        if ($this->vacunaciones->animalPropio($animalId, $productorId) === null) {
            throw new HttpException('El animal no existe en tu cuenta.', 404);
        }

        return $this->respuesta(true, 'Historial consultado correctamente.', [
            'observaciones' => $this->animales->observaciones($animalId),
        ]);
    }

    /**
     * PATCH { animalId, accion: 'EDITAR', ...los campos del alta }: reemplaza los datos del animal con los del formulario
     * (un campo que no llega queda vacío). Excepciones: sin la clave `arete` se conserva la identificación (los animales
     * viejos tienen una libre, no un arete SENASA) y sin `raza` ni `razaId` se conserva la raza escrita a mano.
     * Peso, edad o propósito distintos de los vigentes agregan una observación nueva: el historial no se reescribe.
     */
    private function editar(array $cuerpo): array
    {
        $campos = ['animalId', 'accion', 'especieId', 'tipoId', 'razaId', 'sexo', 'arete', 'fechaNacimiento',
            'fechaNacimientoEstimada', 'partos', 'peso', 'edadMeses', 'proposito', 'raza'];
        $desconocidos = array_diff(array_keys($cuerpo), $campos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
        $animalId = $this->animalId($cuerpo);
        $texto = \Closure::fromCallable([CampoValidacion::class, 'texto']);
        $numero = \Closure::fromCallable([CampoValidacion::class, 'numero']);
        $v = CampoValidacion::validarTodos([
            'modelo' => fn () => AnimalValidacionService::validar($this->catalogo, $cuerpo),
            'raza' => fn () => $texto($cuerpo['raza'] ?? null, 'raza', 120),
            'sexo' => fn () => $texto($cuerpo['sexo'] ?? null, 'sexo', 40),
            'proposito' => fn () => $texto($cuerpo['proposito'] ?? null, 'proposito', 80),
            'edadMeses' => fn () => $numero($cuerpo['edadMeses'] ?? null, 'edadMeses', true),
            'peso' => fn () => $numero($cuerpo['peso'] ?? null, 'peso'),
        ]);
        $modelo = $v['modelo'];
        $productorId = $this->productorId(true);

        $this->animales->ejecutarConBloqueoAlta('tbanimal', fn (): array => $this->animales->ejecutarConBloqueoAlta(
            'tbanimalproduccionsalud',
            fn (): array => $this->enTransaccion(function () use ($animalId, $productorId, $cuerpo, $modelo, $v): array {
                $this->exigirAnimalVigente($animalId, $productorId);
                $anterior = $this->animales->datosAnimal($animalId);
                $conArete = array_key_exists('arete', $cuerpo);
                $arete = $conArete ? $modelo['arete'] : $anterior['arete'];
                if ($conArete && $arete !== null && $this->animales->existeAreteVigente($arete, $animalId)) {
                    throw new HttpException('Revise los campos indicados.', 409, null, ['arete' => 'Ya hay un animal con ese arete.']);
                }
                $razaLibre = !array_key_exists('raza', $cuerpo) && !array_key_exists('razaId', $cuerpo) && $anterior['razaId'] === null;
                $nuevo = [
                    'arete' => $arete,
                    'sexo' => $modelo['sexo'] ?? $v['sexo'],
                    'raza' => $modelo['razaNombre'] ?? ($razaLibre ? $anterior['raza'] : $v['raza']),
                    'especieId' => $modelo['especieId'],
                    'tipoId' => $modelo['tipoId'],
                    'razaId' => $modelo['razaId'],
                    'fechaNacimiento' => $modelo['fechaNacimiento'],
                    'fechaNacimientoEstimada' => $modelo['fechaNacimientoEstimada'],
                    'partos' => $modelo['partos'],
                ];
                $this->animales->actualizarAnimal($animalId, $nuevo);

                // Peso, edad o propósito nuevos: una observación más, completando con los datos vigentes.
                $vigente = $this->animales->observaciones($animalId)[0] ?? ['peso' => null, 'edadMeses' => null, 'proposito' => null];
                $observacion = ['peso' => $vigente['peso'], 'edadMeses' => $vigente['edadMeses'], 'proposito' => $vigente['proposito']];
                $antes = $observacion;
                if ($v['peso'] !== null) $observacion['peso'] = $v['peso'];
                if ($v['edadMeses'] !== null) $observacion['edadMeses'] = (int) $v['edadMeses'];
                if ($v['proposito'] !== null) $observacion['proposito'] = $v['proposito'];
                if ($observacion !== $antes) {
                    $this->animales->registrarObservacion($animalId, ['origen' => self::ORIGEN] + $observacion);
                    $nuevo += $observacion;
                }
                $this->bitacora->registrar('ACTUALIZAR', (string) $animalId, $anterior, $nuevo, $this->solicitudId,
                    entidad: 'ANIMAL', origen: self::ORIGEN);
                return $nuevo;
            }),
        ));

        return $this->respuesta(true, 'Animal actualizado.', [
            'animales' => $this->animales->inventarioPropio($productorId),
        ]);
    }

    /**
     * PATCH { animalId, accion: 'PESAR', peso, fecha? }: agrega un peso al historial (fecha AAAA-MM-DD, hoy por omisión,
     * nunca futura). Conserva el propósito vigente y estima la edad sumando los meses desde la observación vigente.
     */
    private function pesar(array $cuerpo): array
    {
        $desconocidos = array_diff(array_keys($cuerpo), ['animalId', 'accion', 'peso', 'fecha']);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
        $animalId = $this->animalId($cuerpo);
        $hoy = AnimalValidacionService::hoy();
        $datos = CampoValidacion::validarTodos([
            'peso' => fn () => CampoValidacion::numero($cuerpo['peso'] ?? null, 'peso')
                ?? throw new HttpException('Revise los campos indicados.', 422, null, ['peso' => 'Este campo es obligatorio.']),
            'fecha' => function () use ($cuerpo, $hoy): string {
                $fecha = $cuerpo['fecha'] ?? null;
                if ($fecha === null || $fecha === '') return $hoy;
                $partes = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1 ? explode('-', $fecha) : null;
                if ($partes === null || !checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
                    throw new HttpException('Revise los campos indicados.', 422, null, ['fecha' => 'Usa una fecha válida (AAAA-MM-DD).']);
                }
                if ($fecha > $hoy) {
                    throw new HttpException('Revise los campos indicados.', 422, null, ['fecha' => 'La fecha no puede ser futura.']);
                }
                return $fecha;
            },
        ]);
        $productorId = $this->productorId(true);

        $this->animales->ejecutarConBloqueoAlta('tbanimalproduccionsalud', fn (): array => $this->enTransaccion(
            function () use ($animalId, $productorId, $datos, $hoy): array {
                $this->exigirAnimalVigente($animalId, $productorId);
                $vigente = $this->animales->observaciones($animalId)[0] ?? null;
                // Un peso de hoy queda como el vigente; uno de otro día se ordena en su fecha.
                $fecha = $datos['fecha'] === $hoy ? date('Y-m-d H:i:s') : $datos['fecha'] . ' 12:00:00';
                $edad = null;
                if ($vigente !== null && $vigente['edadMeses'] !== null) {
                    [$a1, $m1] = array_map('intval', explode('-', substr($vigente['fecha'], 0, 7)));
                    [$a2, $m2] = array_map('intval', explode('-', substr($fecha, 0, 7)));
                    $edad = $vigente['edadMeses'] + ($a2 - $a1) * 12 + ($m2 - $m1);
                    if ($edad < 0) $edad = null;
                }
                $nuevo = ['fecha' => $fecha, 'peso' => $datos['peso'], 'edadMeses' => $edad, 'proposito' => $vigente['proposito'] ?? null];
                $this->animales->registrarObservacion($animalId, ['origen' => self::ORIGEN] + $nuevo);
                $this->bitacora->registrar('PESAR', (string) $animalId, null, $nuevo, $this->solicitudId,
                    entidad: 'ANIMAL', origen: self::ORIGEN);
                return $nuevo;
            },
        ));

        return $this->respuesta(true, 'Peso registrado.', [
            'observaciones' => $this->animales->observaciones($animalId),
            'animales' => $this->animales->inventarioPropio($productorId),
        ], 201);
    }

    private function animalId(array $cuerpo): int
    {
        $animalId = filter_var(is_bool($cuerpo['animalId'] ?? null) ? false : $cuerpo['animalId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($animalId === false) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['animalId' => 'Debe ser un entero positivo.']);
        }
        return $animalId;
    }

    /** Solo se edita o pesa un animal propio que sigue en el inventario o publicado (no vendido ni inactivo). */
    private function exigirAnimalVigente(int $animalId, int $productorId): void
    {
        $animal = $this->vacunaciones->animalPropio($animalId, $productorId);
        if ($animal === null) throw new HttpException('El animal no existe en tu cuenta.', 404);
        if (in_array($animal['estado'], ['VENDIDO', 'INACTIVO'], true)) {
            throw new HttpException('El animal ya fue vendido o está inactivo: no se puede cambiar.', 409);
        }
        // Quien puja vio estos datos: no cambian mientras la subasta está abierta.
        if ($animal['estado'] === 'SUBASTA') {
            throw new HttpException('El animal está en una subasta abierta: se podrá cambiar cuando termine.', 409);
        }
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
