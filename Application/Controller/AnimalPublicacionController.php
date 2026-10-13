<?php

declare(strict_types=1);

namespace Application\Controller;

// El repositorio usa loaders manuales en API y pruebas; este servicio nuevo
// se carga aqui tambien para mantener el controlador autocontenido.
require_once dirname(__DIR__) . '/Service/PublicacionCercaniaService.php';
require_once dirname(__DIR__) . '/Service/AnimalValidacionService.php';
require_once dirname(__DIR__) . '/Model/AnimalCatalogo.php';

use Application\HttpException;
use Application\Auth\ActorContext;
use Application\Model\AnimalCatalogo;
use Application\Model\AnimalComercial;
use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Service\AnimalValidacionService;
use Application\Service\PublicacionCercaniaService;
use PDO;
use Throwable;

/** Lectura de publicaciones para la vista Explorar. */
final class AnimalPublicacionController
{
    private const ESTADOS = ['TODOS', 'ACTIVO', 'PAUSADO', 'VENDIDO', 'RETIRADO'];
    /** Solo una publicación ACTIVA o PAUSADA se puede editar o cambiar de estado. */
    private const ESTADOS_EDITABLES = ['ACTIVO', 'PAUSADO'];
    private const LOCKS_CREAR = ['tbanimal', 'tbanimalproduccionsalud', 'tbanimalpublicacion',
        'tbanimalpublicacionestadoperiodo', 'tbanimalpublicacionanimal'];

    private AnimalComercial $animales;
    private AnimalCatalogo $catalogo;
    private PublicacionCercaniaService $cercania;
    private ?ActorContext $actor;
    private Productor $productor;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private PDO $conexion, ?string $solicitudId = null,
        ?ActorContext $actor = null)
    {
        $this->animales = new AnimalComercial($conexion);
        $this->catalogo = new AnimalCatalogo($conexion);
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
                'PATCH' => $this->actualizar($cuerpo),
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

        $resultado = $this->conBloqueos(self::LOCKS_CREAR, function () use ($productor, $finca, $datos): array {
            $resultado = $this->crearPublicacionCompleta((int) $productor['tbproductorid'], (int) $finca['tbfincaid'], $datos);
            $this->bitacora->registrar(
                'CREAR',
                'PUBLICACION:' . $resultado['publicacionId'],
                null,
                $resultado,
                $this->solicitudId,
                entidad: 'PUBLICACION',
                origen: 'API_PUBLICACIONES',
            );
            return $resultado;
        });

        return $this->respuesta(true, 'Publicación creada correctamente.', $resultado, 201);
    }

    /**
     * Edita precio, título, descripción y foto, y/o cambia el estado de una
     * publicación propia. Solo se tocan las claves presentes en el cuerpo.
     */
    private function actualizar(array $cuerpo): array
    {
        $actor = $this->actor;
        if (!$actor?->tienePersona()) {
            throw new HttpException('Debe iniciar sesión para editar publicaciones.', 401);
        }
        $productor = $this->productor->buscarPorPersonaId((int) $actor->personaId);
        if ($productor === null) {
            throw new HttpException('La cuenta no tiene una actividad Productor configurada.', 409);
        }

        $publicacionId = $this->enteroConsulta($cuerpo['publicacionId'] ?? null, 'publicacionId');
        $campos = [];
        if (array_key_exists('titulo', $cuerpo)) $campos['titulo'] = self::texto($cuerpo['titulo'], 'titulo', 150, true);
        if (array_key_exists('descripcion', $cuerpo)) $campos['descripcion'] = self::texto($cuerpo['descripcion'], 'descripcion', 500);
        if (array_key_exists('precio', $cuerpo)) $campos['precio'] = self::numero($cuerpo['precio'], 'precio');
        if (array_key_exists('imagenUrl', $cuerpo)) $campos['imagenUrl'] = self::imagenUrl($cuerpo['imagenUrl']);
        $estado = null;
        if (array_key_exists('estado', $cuerpo)) {
            $estado = is_string($cuerpo['estado']) ? mb_strtoupper(trim($cuerpo['estado']), 'UTF-8') : '';
            $validos = array_values(array_diff(self::ESTADOS, ['TODOS']));
            if (!in_array($estado, $validos, true)) {
                throw new HttpException('Revise los campos indicados.', 422, null, [
                    'estado' => 'Use ' . implode(', ', $validos) . '.',
                ]);
            }
        }
        $motivo = self::texto($cuerpo['motivo'] ?? null, 'motivo', 250);
        if ($campos === [] && $estado === null) {
            throw new HttpException('Indique qué desea cambiar.', 422);
        }

        $vendedorId = (int) $productor['tbproductorid'];
        $nueva = $this->conBloqueos(['tbanimalpublicacionestadoperiodo'], function () use ($publicacionId, $vendedorId, $campos, $estado, $motivo): array {
            $anterior = $this->animales->buscarPublicacionPropia($publicacionId, $vendedorId);
            if ($anterior === null) {
                throw new HttpException('La publicación no existe.', 404);
            }
            if (!in_array($anterior['estado'], self::ESTADOS_EDITABLES, true)) {
                throw new HttpException('La publicación ya está cerrada y no admite cambios.', 409);
            }
            if ($campos !== []) {
                $this->animales->actualizarPublicacion($publicacionId, $campos);
            }
            if ($estado !== null && $estado !== $anterior['estado']) {
                $this->animales->cambiarEstadoPublicacion($publicacionId, $estado, $motivo, 'API_PUBLICACIONES');
            }
            $nueva = $this->animales->buscarPublicacionPropia($publicacionId, $vendedorId);
            $this->bitacora->registrar(
                'ACTUALIZAR',
                'PUBLICACION:' . $publicacionId,
                $anterior,
                $nueva,
                $this->solicitudId,
                entidad: 'PUBLICACION',
                origen: 'API_PUBLICACIONES',
            );
            return $nueva;
        });

        return $this->respuesta(true, 'Publicación actualizada correctamente.', ['publicacion' => $nueva]);
    }

    /**
     * Crea el animal (o los N animales de un lote, DEC-ANIMAL-001) y su publicación. Corre dentro del lock de tbanimal.
     * La publicación apunta al primer animal; en un lote, tbanimalpublicacionanimal enlaza a los N.
     */
    private function crearPublicacionCompleta(int $productorId, int $fincaId, array $datos): array
    {
        if ($datos['arete'] !== null && $this->animales->existeAreteVigente($datos['arete'])) {
            throw new HttpException('Revise los campos indicados.', 409, null, [
                'arete' => 'Ya hay un animal con ese arete. Si es tuyo, revisa tus publicaciones.',
            ]);
        }
        $lote = $datos['loteCantidad'];
        $animalIds = [];
        for ($i = 0; $i < $lote; $i++) {
            $animalId = $this->animales->crearAnimal(
                $i === 0 ? $datos['animalIdentificacion'] : null,
                $datos['sexo'],
                $datos['raza'],
                'PUBLIC_API',
                null,
                [
                    'especieId' => $datos['especieId'],
                    'tipoId' => $datos['tipoId'],
                    'razaId' => $datos['razaId'],
                    'fechaNacimiento' => $datos['fechaNacimiento'],
                    'fechaNacimientoEstimada' => $datos['fechaNacimientoEstimada'],
                    'partos' => $datos['partos'],
                    'estado' => 'PUBLICADO',
                    'productorId' => $productorId,
                ],
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
            $animalIds[] = $animalId;
        }
        $publicacionId = $this->animales->ejecutarConBloqueoAlta(
            'tbanimalpublicacion',
            fn (): int => $this->animales->publicarAnimal($animalIds[0], $productorId, $fincaId, [
                'origen' => 'PUBLIC_API',
                'estado' => 'ACTIVO',
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'precio' => $datos['precio'],
                'imagenUrl' => $datos['imagenUrl'],
            ]),
        );
        $resultado = ['animalId' => $animalIds[0], 'publicacionId' => $publicacionId, 'loteCantidad' => $lote];
        if ($lote > 1) {
            $this->animales->ejecutarConBloqueoAlta(
                'tbanimalpublicacionanimal',
                fn () => $this->animales->enlazarAnimalesPublicacion($publicacionId, $animalIds),
            );
            $resultado['animalIds'] = $animalIds;
        }

        return $resultado;
    }

    /**
     * Locks de las tablas en orden fijo, luego el de la bitácora y recién entonces la transacción: así el id de
     * la bitácora (MAX()+1) sigue protegido hasta el COMMIT (MEMORIA.md, cuidado #12). Es el mismo orden que
     * usan MiAnimalesController y AdminPublicacionController, para que dos peticiones no se esperen en cruz.
     */
    private function conBloqueos(array $tablas, callable $operacion): mixed
    {
        if ($tablas !== []) {
            $tabla = array_shift($tablas);
            return $this->animales->ejecutarConBloqueoAlta($tabla, fn (): mixed => $this->conBloqueos($tablas, $operacion));
        }

        return $this->bitacora->ejecutarConBloqueoAlta(function () use ($operacion): mixed {
            $this->conexion->beginTransaction();
            try {
                $resultado = $operacion();
                $this->conexion->commit();
                return $resultado;
            } catch (Throwable $error) {
                if ($this->conexion->inTransaction()) {
                    $this->conexion->rollBack();
                }
                throw $error;
            }
        });
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

    public static function texto(mixed $valor, string $campo, int $maximo, bool $obligatorio = false): ?string
    {
        // Una lista u objeto se convertiría en el texto "Array": se rechaza con un mensaje claro.
        if ($valor !== null && !is_string($valor) && !is_int($valor) && !is_float($valor)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser texto.']);
        }
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
    }

    /** Topes por campo: los de la columna (precio NUMERIC(12,2)) o uno razonable para el dato. */
    private const MAXIMOS = ['precio' => 9999999999.99, 'peso' => 5000, 'edadMeses' => 600];

    public static function numero(mixed $valor, string $campo, bool $entero = false): ?float
    {
        if ($valor === null || $valor === '') return null;
        if (is_bool($valor) || !is_numeric($valor) || (float) $valor < 0 || !is_finite((float) $valor)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un número mayor o igual a cero.']);
        }
        $maximo = self::MAXIMOS[$campo] ?? 2147483647;
        if ((float) $valor > $maximo) {
            $texto = number_format($maximo, $maximo == floor($maximo) ? 0 : 2, ',', '.');
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => "No puede ser mayor que {$texto}."]);
        }
        if ($entero && floor((float) $valor) !== (float) $valor) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un entero no negativo.']);
        }
        return (float) $valor;
    }

    /**
     * Corre todas las reglas y junta sus errores en un solo 422: la persona ve de una vez todo lo que debe
     * corregir (antes solo el primero, p. ej. el peso, y el precio aparecía recién al reenviar).
     *
     * @param array<string,callable():mixed> $reglas
     * @return array<string,mixed>
     */
    public static function validarTodos(array $reglas): array
    {
        $datos = [];
        $errores = [];
        foreach ($reglas as $clave => $regla) {
            try {
                $datos[$clave] = $regla();
            } catch (HttpException $error) {
                if ($error->errores === []) throw $error;
                $errores += $error->errores;
                $datos[$clave] = null;
            }
        }
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return $datos;
    }

    private function validarPublicacion(array $cuerpo): array
    {
        $texto = \Closure::fromCallable([self::class, 'texto']);
        $numero = \Closure::fromCallable([self::class, 'numero']);
        $v = self::validarTodos([
            'animalIdentificacion' => fn () => $texto($cuerpo['animalIdentificacion'] ?? null, 'animalIdentificacion', 100),
            'raza' => fn () => $texto($cuerpo['raza'] ?? null, 'raza', 120),
            'sexo' => fn () => $texto($cuerpo['sexo'] ?? null, 'sexo', 40),
            // Modelo de animal (P2-2): catálogos, sexo por tipo, partos, nacimiento, arete y lote. Todo opcional.
            'modelo' => fn () => AnimalValidacionService::validar($this->catalogo, $cuerpo),
            'fincaNombre' => fn () => $texto($cuerpo['fincaNombre'] ?? null, 'fincaNombre', 150, true),
            'proposito' => fn () => $texto($cuerpo['proposito'] ?? null, 'proposito', 80),
            'edadMeses' => fn () => $numero($cuerpo['edadMeses'] ?? null, 'edadMeses', true),
            'peso' => fn () => $numero($cuerpo['peso'] ?? null, 'peso'),
            'titulo' => fn () => $texto($cuerpo['titulo'] ?? null, 'titulo', 150, true),
            'descripcion' => fn () => $texto($cuerpo['descripcion'] ?? null, 'descripcion', 500),
            'precio' => fn () => $numero($cuerpo['precio'] ?? null, 'precio'),
            'imagenUrl' => fn () => self::imagenUrl($cuerpo['imagenUrl'] ?? null),
        ]);
        $modelo = $v['modelo'];
        $identificacion = $v['animalIdentificacion'];
        $raza = $v['raza'];
        $sexo = $v['sexo'];

        return [
            'fincaNombre' => $v['fincaNombre'],
            // El arete (13 dígitos SENASA) reemplaza a la identificación libre; la raza de catálogo, al texto libre.
            'animalIdentificacion' => $modelo['arete'] ?? $identificacion,
            'arete' => $modelo['arete'],
            'raza' => $modelo['razaNombre'] ?? $raza,
            'sexo' => $modelo['sexo'] ?? $sexo,
            'especieId' => $modelo['especieId'],
            'tipoId' => $modelo['tipoId'],
            'razaId' => $modelo['razaId'],
            'fechaNacimiento' => $modelo['fechaNacimiento'],
            'fechaNacimientoEstimada' => $modelo['fechaNacimientoEstimada'],
            'partos' => $modelo['partos'],
            'loteCantidad' => $modelo['loteCantidad'],
            'proposito' => $v['proposito'],
            'edadMeses' => $v['edadMeses'],
            'peso' => $v['peso'],
            'titulo' => $v['titulo'],
            'descripcion' => $v['descripcion'],
            'precio' => $v['precio'],
            'imagenUrl' => $v['imagenUrl'],
        ];
    }

    /**
     * Imagen opcional: solo URLs https absolutas, sin credenciales embebidas.
     * Vale para Supabase Storage y para direcciones externas; un esquema como
     * javascript: o data: nunca llega a la base ni a un <img>.
     */
    public static function imagenUrl(mixed $valor, string $campo = 'imagenUrl'): ?string
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return null;
        }
        if (!is_string($valor)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser la dirección (URL) de la imagen.']);
        }
        $url = trim($valor);
        $partes = parse_url($url);
        $valida = mb_strlen($url) <= 500
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($partes)
            && strtolower((string) ($partes['scheme'] ?? '')) === 'https'
            && ($partes['host'] ?? '') !== ''
            && !isset($partes['user'])
            && !isset($partes['pass']);
        if (!$valida) {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                $campo => 'Usa una dirección https válida de hasta 500 caracteres.',
            ]);
        }
        return $url;
    }

    private function consultar(array $consulta): array
    {
        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 'q', 150);
        // mias=true: solo las publicaciones del vendedor autenticado, en todos sus estados.
        $mias = filter_var($consulta['mias'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $vendedorId = null;
        if ($mias) {
            if (!$this->actor?->tienePersona()) {
                throw new HttpException('Debe iniciar sesión para ver sus publicaciones.', 401);
            }
            $productor = $this->productor->buscarPorPersonaId((int) $this->actor->personaId);
            $vendedorId = $productor === null ? 0 : (int) $productor['tbproductorid'];
        }
        // excluirPropias=true (Explorar): con sesión, deja fuera las publicaciones del propio vendedor.
        $excluirId = null;
        if (filter_var($consulta['excluirPropias'] ?? false, FILTER_VALIDATE_BOOLEAN) && $this->actor?->tienePersona()) {
            $propio = $this->productor->buscarPorPersonaId((int) $this->actor->personaId);
            $excluirId = $propio === null ? null : (int) $propio['tbproductorid'];
        }
        // Con sesión, cada publicación trae meInteresa (ver api/v1/publicaciones/interacciones).
        $personaId = $this->actor?->tienePersona() ? (int) $this->actor->personaId : null;
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? ($mias ? 'TODOS' : 'ACTIVO'), 'estado', 10), 'UTF-8');
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
        $filtros = $this->filtrosConsulta($consulta, $personaId);

        if ($ubicacion === null) {
            $resultado = $this->animales->listarPublicaciones($busqueda, $estado, $pagina, $tamano, $vendedorId, $personaId, false, $excluirId, $filtros);
            $resultado['ranking'] = 'RECIENTE';
        } else {
            // La capa de datos conserva su paginación tradicional. Para ordenar
            // correctamente por distancia antes de paginar se obtiene el total
            // filtrado y el servicio aplica Haversine en PHP. Si el catálogo
            // crece de forma sustancial deberá evolucionar a candidatos por zona.
            $conteo = $this->animales->listarPublicaciones($busqueda, $estado, 1, 1, $vendedorId, $personaId, false, $excluirId, $filtros);
            $total = (int) ($conteo['total'] ?? 0);
            $todas = $total === 0
                ? []
                : $this->animales->listarPublicaciones($busqueda, $estado, 1, $total, $vendedorId, $personaId, false, $excluirId, $filtros)['publicaciones'];
            $resultado = $this->cercania->ordenarYPaginar(
                $todas,
                $ubicacion['latitud'],
                $ubicacion['longitud'],
                $pagina,
                $tamano
            );
        }
        // Vacunas (P2-3): públicas y acotadas, solo de la página que se devuelve.
        $resultado['publicaciones'] = $this->animales->adjuntarVacunas($resultado['publicaciones']);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Publicaciones consultadas correctamente.', $resultado);
    }

    /**
     * Filtros de Explorar: zona (texto en provincia, cantón, distrito o pueblo, sin tildes),
     * rango de precio (deja fuera las "a convenir") y excluirMarcadas (lo que ya está en Me interesa).
     */
    private function filtrosConsulta(array $consulta, ?int $personaId): array
    {
        $v = self::validarTodos([
            'ubicacion' => fn () => self::texto($consulta['ubicacion'] ?? null, 'ubicacion', 100),
            'precioMin' => fn () => self::numero($consulta['precioMin'] ?? null, 'precioMin'),
            'precioMax' => fn () => self::numero($consulta['precioMax'] ?? null, 'precioMax'),
        ]);
        if ($v['precioMin'] !== null && $v['precioMax'] !== null && $v['precioMin'] > $v['precioMax']) {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                'precioMax' => 'Debe ser mayor o igual que el precio mínimo.',
            ]);
        }

        return [
            'ubicacion' => $v['ubicacion'] ?? '',
            'precioMin' => $v['precioMin'],
            'precioMax' => $v['precioMax'],
            'excluirMarcadas' => $personaId !== null
                && filter_var($consulta['excluirMarcadas'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function ubicacionConsulta(array $consulta): ?array
    {
        $presente = static fn (string $campo): bool => array_key_exists($campo, $consulta)
            && !(is_string($consulta[$campo]) && trim($consulta[$campo]) === '') && $consulta[$campo] !== null;
        $tieneLatitud = $presente('latitud');
        $tieneLongitud = $presente('longitud');
        if (!$tieneLatitud && !$tieneLongitud) return null;
        if ($tieneLatitud !== $tieneLongitud) {
            throw new HttpException('Latitud y longitud deben enviarse juntas.', 422, null, [
                'latitud' => 'Envíe ambas coordenadas.',
                'longitud' => 'Envíe ambas coordenadas.',
            ]);
        }
        if (!is_numeric($consulta['latitud']) || !is_numeric($consulta['longitud'])) {
            throw new HttpException('Las coordenadas no son válidas.', 422, null, array_filter([
                'latitud' => is_numeric($consulta['latitud']) ? null : 'Debe ser un número entre -90 y 90.',
                'longitud' => is_numeric($consulta['longitud']) ? null : 'Debe ser un número entre -180 y 180.',
            ]));
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

    private function textoConsulta(mixed $valor, string $campo, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new HttpException('La consulta no es válida.', 422, null, [
                $campo => is_string($valor) ? "No puede superar {$maximo} caracteres." : 'Debe ser texto.',
            ]);
        }

        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var(is_bool($valor) ? false : $valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($entero === false) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un número entero positivo.']);
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
