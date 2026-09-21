<?php

declare(strict_types=1);

namespace Application\Controller;

// El repositorio usa loaders manuales en API y pruebas; este servicio nuevo
// se carga aqui tambien para mantener el controlador autocontenido.
require_once dirname(__DIR__) . '/Service/PublicacionCercaniaService.php';

use Application\HttpException;
use Application\Auth\ActorContext;
use Application\Model\AnimalComercial;
use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Service\PublicacionCercaniaService;
use PDO;
use Throwable;

/** Lectura de publicaciones para la vista Explorar. */
final class AnimalPublicacionController
{
    private const ESTADOS = ['TODOS', 'ACTIVO', 'VENDIDO', 'RETIRADO'];

    private readonly AnimalComercial $animales;
    private readonly PublicacionCercaniaService $cercania;
    private readonly ?ActorContext $actor;
    private readonly Productor $productor;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null,
        ?ActorContext $actor = null)
    {
        $this->animales = new AnimalComercial($conexion);
        $this->cercania = new PublicacionCercaniaService($conexion);
        $this->actor = $actor;
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
        $this->productor = new Productor($conexion, new ProductorFinca($conexion));
        $this->bitacora = new Bitacora($conexion, $actor);
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST' => $this->crear($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    private function crear(array $cuerpo): array
    {
        $actor = $this->actor;
        if (!$actor?->tienePersona()) {
            throw new HttpException('Debe iniciar sesión para publicar ganado.', 401);
        }

        $productor = $this->productor->buscarPorPersonaId((int) $actor->personaId);
        if ($productor === null) {
            throw new HttpException('La cuenta no tiene una actividad Productor configurada.', 409);
        }
        if ((int) ($productor['tbproductorestado'] ?? 0) !== 1
            || (int) ($productor['tbpersonaestado'] ?? 0) !== 1) {
            throw new HttpException('La actividad Productor está inactiva.', 409);
        }

        $datos = $this->validarPublicacion($cuerpo);
        $finca = $this->fincaActiva((int) $productor['tbproductorid'], $datos['fincaNombre']);
        if ($finca === null) {
            throw new HttpException('La finca seleccionada no está activa para este Productor.', 422, null, [
                'fincaNombre' => 'Seleccione una finca activa de su perfil.',
            ]);
        }

        $this->conexion->beginTransaction();
        try {
            $resultado = $this->animales->ejecutarConBloqueoAlta(
                'tbanimal',
                fn (): array => $this->crearPublicacionCompleta(
                    (int) $productor['tbproductorid'],
                    (int) $finca['tbfincaid'],
                    $datos,
                ),
            );
            $this->bitacora->registrar(
                'CREAR',
                'PUBLICACION:' . $resultado['publicacionId'],
                null,
                $resultado,
                $this->solicitudId,
                entidad: 'PUBLICACION',
                origen: 'API_PUBLICACIONES',
            );
            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }

        return $this->respuesta(true, 'Publicación creada correctamente.', $resultado, 201);
    }

    private function crearPublicacionCompleta(int $productorId, int $fincaId, array $datos): array
    {
        $animalId = $this->animales->crearAnimal(
            $datos['animalIdentificacion'],
            $datos['sexo'],
            $datos['raza'],
            'PUBLIC_API',
        );
        $this->animales->ejecutarConBloqueoAlta(
            'tbanimalproduccionsalud',
            fn (): int => $this->animales->registrarObservacion($animalId, [
                'origen' => 'PUBLIC_API',
                'edadMeses' => $datos['edadMeses'],
                'peso' => $datos['peso'],
                'proposito' => $datos['proposito'],
            ]),
        );
        $publicacionId = $this->animales->ejecutarConBloqueoAlta(
            'tbanimalpublicacion',
            fn (): int => $this->animales->publicarAnimal($animalId, $productorId, $fincaId, [
                'origen' => 'PUBLIC_API',
                'estado' => 'ACTIVO',
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'precio' => $datos['precio'],
            ]),
        );

        return ['animalId' => $animalId, 'publicacionId' => $publicacionId];
    }

    private function fincaActiva(int $productorId, string $nombre): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbfincaid, tbfincanombre FROM tbfinca
             WHERE tbproductorid = :productorId AND tbfincanombre = :nombre AND tbfincaestado = 1'
        );
        $sentencia->execute(['productorId' => $productorId, 'nombre' => $nombre]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new HttpException('La finca está duplicada para este Productor.', 409);
        }

        return $filas[0] ?? null;
    }

    private function validarPublicacion(array $cuerpo): array
    {
        $texto = static function (mixed $valor, string $campo, int $maximo, bool $obligatorio = false): ?string {
            if ($valor === null || trim((string) $valor) === '') {
                if ($obligatorio) {
                    throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Este campo es obligatorio.']);
                }
                return null;
            }
            $valor = trim((string) $valor);
            if (mb_strlen($valor) > $maximo) {
                throw new HttpException('Revise los campos indicados.', 422, null, [$campo => "No puede superar {$maximo} caracteres."]);
            }
            return $valor;
        };
        $numero = static function (mixed $valor, string $campo, bool $entero = false): ?float {
            if ($valor === null || $valor === '') return null;
            if (!is_numeric($valor) || (float) $valor < 0 || !is_finite((float) $valor)) {
                throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un número no negativo.']);
            }
            if ($entero && floor((float) $valor) !== (float) $valor) {
                throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un entero no negativo.']);
            }
            return (float) $valor;
        };

        return [
            'fincaNombre' => $texto($cuerpo['fincaNombre'] ?? null, 'fincaNombre', 150, true),
            'animalIdentificacion' => $texto($cuerpo['animalIdentificacion'] ?? null, 'animalIdentificacion', 100),
            'raza' => $texto($cuerpo['raza'] ?? null, 'raza', 120),
            'sexo' => $texto($cuerpo['sexo'] ?? null, 'sexo', 40),
            'proposito' => $texto($cuerpo['proposito'] ?? null, 'proposito', 80),
            'edadMeses' => $numero($cuerpo['edadMeses'] ?? null, 'edadMeses', true),
            'peso' => $numero($cuerpo['peso'] ?? null, 'peso'),
            'titulo' => $texto($cuerpo['titulo'] ?? null, 'titulo', 150, true),
            'descripcion' => $texto($cuerpo['descripcion'] ?? null, 'descripcion', 500),
            'precio' => $numero($cuerpo['precio'] ?? null, 'precio'),
        ];
    }

    private function consultar(array $consulta): array
    {
        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'ACTIVO', 10), 'UTF-8');
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new HttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use ' . implode(', ', self::ESTADOS) . '.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta)
            ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new HttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $ubicacion = $this->ubicacionConsulta($consulta);

        if ($ubicacion === null) {
            $resultado = $this->animales->listarPublicaciones($busqueda, $estado, $pagina, $tamano);
            $resultado['ranking'] = 'RECIENTE';
        } else {
            // La capa de datos conserva su paginación tradicional. Para ordenar
            // correctamente por distancia antes de paginar se obtiene el total
            // filtrado y el servicio aplica Haversine en PHP. Si el catálogo
            // crece de forma sustancial deberá evolucionar a candidatos por zona.
            $conteo = $this->animales->listarPublicaciones($busqueda, $estado, 1, 1);
            $total = (int) ($conteo['total'] ?? 0);
            $todas = $total === 0
                ? []
                : $this->animales->listarPublicaciones($busqueda, $estado, 1, $total)['publicaciones'];
            $resultado = $this->cercania->ordenarYPaginar(
                $todas,
                $ubicacion['latitud'],
                $ubicacion['longitud'],
                $pagina,
                $tamano
            );
        }
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Publicaciones consultadas correctamente.', $resultado);
    }

    private function ubicacionConsulta(array $consulta): ?array
    {
        $tieneLatitud = array_key_exists('latitud', $consulta) && trim((string) $consulta['latitud']) !== '';
        $tieneLongitud = array_key_exists('longitud', $consulta) && trim((string) $consulta['longitud']) !== '';
        if (!$tieneLatitud && !$tieneLongitud) return null;
        if ($tieneLatitud !== $tieneLongitud) {
            throw new HttpException('Latitud y longitud deben enviarse juntas.', 422, null, [
                'latitud' => 'Envíe ambas coordenadas.',
                'longitud' => 'Envíe ambas coordenadas.',
            ]);
        }
        if (!is_numeric($consulta['latitud']) || !is_numeric($consulta['longitud'])) {
            throw new HttpException('Las coordenadas no son válidas.', 422);
        }
        $latitud = (float) $consulta['latitud'];
        $longitud = (float) $consulta['longitud'];
        if (!is_finite($latitud) || $latitud < -90 || $latitud > 90
            || !is_finite($longitud) || $longitud < -180 || $longitud > 180) {
            throw new HttpException('Las coordenadas están fuera de rango.', 422, null, [
                'latitud' => 'Debe estar entre -90 y 90.',
                'longitud' => 'Debe estar entre -180 y 180.',
            ]);
        }
        return ['latitud' => $latitud, 'longitud' => $longitud];
    }

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new HttpException('La consulta no es válida.', 422);
        }

        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new HttpException("{$campo} debe ser un entero positivo.", 422);
        }

        return $entero;
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200,
        array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
