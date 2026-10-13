<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/TiempoReal.php';
require_once dirname(__DIR__) . '/Service/SubastaCierreService.php';
// Solo se usan sus validaciones estáticas (texto, numero, imagenUrl, validarTodos).
require_once __DIR__ . '/AnimalPublicacionController.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalComercial;
use Application\Model\AnimalVacunacion;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Model\Notificacion;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Model\Subasta;
use Application\Service\SubastaCierreService;
use Application\Service\TiempoReal;
use PDO;
use Throwable;

/**
 * Subastas en tiempo real (DEC-SUBASTA-001), con sesión de Persona.
 *  GET ?vista=ACTIVAS|HISTORIAL|MIAS|PARTICIPO&q&pagina  → lista
 *  GET ?id=N                                             → detalle (pujas, comentarios y, al ganador y al vendedor, el trato)
 *  POST { animalId, fincaNombre, titulo, descripcion?, imagenUrl?, precioBase, incrementoMinimo, duracionMinutos }
 *  PATCH { subastaId, accion: PUJAR, monto } | { subastaId, accion: COMENTAR, texto } | { subastaId, accion: CANCELAR }
 * Un administrador ve todo pero no participa (403). Cada cambio avisa por Supabase Realtime (TiempoReal).
 */
final class SubastasController
{
    private const ORIGEN = 'API_SUBASTAS';
    public const DURACION_MINIMA = 5;
    public const DURACION_MAXIMA = 43200; // 30 días

    private Subasta $subastas;
    private AnimalComercial $animales;
    private AnimalVacunacion $vacunaciones;
    private Comprador $compradores;
    private Productor $productor;
    private ProductorFinca $fincas;
    private Notificacion $notificaciones;
    private Bitacora $bitacora;
    private TiempoReal $tiempoReal;
    private SubastaCierreService $cierre;
    private string $solicitudId;

    public function __construct(
        private PDO $conexion,
        private ActorContext $actor,
        ?string $solicitudId = null,
        ?TiempoReal $tiempoReal = null,
    ) {
        $this->subastas = new Subasta($conexion);
        $this->animales = new AnimalComercial($conexion);
        $this->vacunaciones = new AnimalVacunacion($conexion);
        $this->compradores = new Comprador($conexion);
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);
        $this->notificaciones = new Notificacion($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->tiempoReal = $tiempoReal ?? new TiempoReal();
        $this->cierre = new SubastaCierreService($conexion, $this->tiempoReal);
        $solicitudId = trim((string) $solicitudId);
        $this->solicitudId = $solicitudId !== '' && strlen($solicitudId) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $solicitudId)
            ? $solicitudId : 'REQ-' . bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $consulta = [], array $cuerpo = []): array
    {
        try {
            if (!$this->actor->tienePersona()) {
                throw new HttpException('Debe iniciar sesión para ver las subastas.', 401);
            }
            // Antes de leer o escribir, se cierran las que ya vencieron (no hay tarea programada).
            $this->cierre->cerrarVencidas();
            return match ($metodo) {
                'GET' => array_key_exists('id', $consulta) ? $this->detalle($consulta['id']) : $this->listar($consulta),
                'POST' => $this->crear($cuerpo),
                'PATCH' => match ($cuerpo['accion'] ?? null) {
                    'PUJAR' => $this->pujar($cuerpo),
                    'COMENTAR' => $this->comentar($cuerpo),
                    'CANCELAR' => $this->cancelar($cuerpo),
                    default => throw new HttpException('Revise los campos indicados.', 422, null, ['accion' => 'Use PUJAR, COMENTAR o CANCELAR.']),
                },
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function listar(array $consulta): array
    {
        $vista = $consulta['vista'] ?? 'ACTIVAS';
        $vista = is_string($vista) ? mb_strtoupper(trim($vista), 'UTF-8') : '';
        $errores = [];
        if (!in_array($vista, Subasta::VISTAS, true)) $errores['vista'] = 'Use ' . implode(', ', Subasta::VISTAS) . '.';
        $q = $consulta['q'] ?? '';
        if (!is_string($q) || mb_strlen($q) > 100) $errores['q'] = 'La búsqueda es texto de hasta 100 caracteres.';
        $pagina = $this->entero($consulta['pagina'] ?? 1, 'pagina', 1, 2147483647, $errores);
        $tamano = $this->entero($consulta['tamanoPagina'] ?? 24, 'tamanoPagina', 1, 50, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $personaId = (int) $this->actor->personaId;
        $productor = $vista === 'MIAS' ? $this->productor->buscarPorPersonaId($personaId) : null;
        $resultado = $this->subastas->listar($vista, $personaId, $productor === null ? null : (int) $productor['tbproductorid'],
            trim((string) $q), $pagina, $tamano);
        $resultado['subastas'] = array_map(fn (array $s): array => $this->resumen($s), $resultado['subastas']);

        return $this->respuesta(true, 'Subastas consultadas correctamente.', $resultado + [
            'pagina' => $pagina, 'tamanoPagina' => $tamano, 'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ]);
    }

    private function detalle(mixed $id): array
    {
        $errores = [];
        $subastaId = $this->entero($id, 'id', 1, 2147483647, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $subasta = $this->subastas->buscar($subastaId) ?? throw new HttpException('La subasta no existe.', 404);

        return $this->respuesta(true, 'Subasta consultada correctamente.', [
            'subasta' => $this->vistaDetalle($subasta),
            'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ]);
    }

    /** POST: abre una subasta con un animal propio que esté en el inventario. */
    private function crear(array $cuerpo): array
    {
        $this->exigirNoAdministrador();
        $permitidos = ['animalId', 'fincaNombre', 'titulo', 'descripcion', 'imagenUrl', 'precioBase', 'incrementoMinimo', 'duracionMinutos'];
        $desconocidos = array_diff(array_keys($cuerpo), $permitidos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
        $texto = \Closure::fromCallable([AnimalPublicacionController::class, 'texto']);
        $datos = AnimalPublicacionController::validarTodos([
            'animalId' => fn () => $this->enteroCampo($cuerpo['animalId'] ?? null, 'animalId', 1, 2147483647),
            'fincaNombre' => fn () => $texto($cuerpo['fincaNombre'] ?? null, 'fincaNombre', 150, true),
            'titulo' => fn () => $texto($cuerpo['titulo'] ?? null, 'titulo', 150, true),
            'descripcion' => fn () => $texto($cuerpo['descripcion'] ?? null, 'descripcion', 500),
            'imagenUrl' => fn () => AnimalPublicacionController::imagenUrl($cuerpo['imagenUrl'] ?? null),
            'precioBase' => fn () => $this->dinero($cuerpo['precioBase'] ?? null, 'precioBase'),
            'incrementoMinimo' => fn () => $this->dinero($cuerpo['incrementoMinimo'] ?? null, 'incrementoMinimo'),
            'duracionMinutos' => fn () => $this->enteroCampo($cuerpo['duracionMinutos'] ?? null, 'duracionMinutos',
                self::DURACION_MINIMA, self::DURACION_MAXIMA, 'Elige una duración entre 5 minutos y 30 días.'),
        ]);
        $productorId = $this->productorActivo();
        $fincaId = $this->fincas->buscarIdActivo($productorId, $datos['fincaNombre'])
            ?? throw new HttpException('Revise los campos indicados.', 422, null, ['fincaNombre' => 'Seleccione una finca activa de su perfil.']);

        $subastaId = $this->enTransaccion(function () use ($datos, $productorId, $fincaId): int {
            $animal = $this->vacunaciones->animalPropio($datos['animalId'], $productorId)
                ?? throw new HttpException('El animal no existe en tu cuenta.', 404);
            if ($animal['estado'] !== 'ACTIVO') {
                throw new HttpException('Solo se subasta un animal que está en tu inventario (sin publicar ni en otra subasta).', 409);
            }
            $inicio = gmdate('Y-m-d H:i:s');
            $nueva = [
                'productorId' => $productorId,
                'animalId' => $datos['animalId'],
                'fincaId' => $fincaId,
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'imagenUrl' => $datos['imagenUrl'],
                'precioBase' => $datos['precioBase'],
                'incrementoMinimo' => $datos['incrementoMinimo'],
                'inicio' => $inicio,
                'fin' => gmdate('Y-m-d H:i:s', strtotime($inicio . ' UTC') + $datos['duracionMinutos'] * 60),
            ];
            $id = $this->subastas->crear($nueva);
            $this->animales->marcarEstadoAnimales([$datos['animalId']], 'SUBASTA');
            $this->bitacora->registrar('CREAR', (string) $id, null, $nueva, $this->solicitudId, entidad: 'SUBASTA', origen: self::ORIGEN);
            return $id;
        });
        $this->tiempoReal->avisar(['subastas']);

        return $this->respuesta(true, 'Subasta abierta.', [
            'subasta' => $this->vistaDetalle($this->subastas->buscar($subastaId)),
            'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ], 201);
    }

    /** PATCH PUJAR: la primera puja desde el precio base; luego, la más alta + el incremento mínimo. */
    private function pujar(array $cuerpo): array
    {
        $this->exigirNoAdministrador();
        $this->exigirCampos($cuerpo, ['subastaId', 'accion', 'monto']);
        $datos = AnimalPublicacionController::validarTodos([
            'subastaId' => fn () => $this->enteroCampo($cuerpo['subastaId'] ?? null, 'subastaId', 1, 2147483647),
            'monto' => fn () => $this->dinero($cuerpo['monto'] ?? null, 'monto'),
        ]);
        $personaId = (int) $this->actor->personaId;
        $comprador = $this->compradores->buscarPorPersona($personaId);
        if ($comprador === null || $comprador['estado'] !== 'ACTIVO') {
            throw new HttpException('Activa la actividad Comprador en Ajustes para pujar.', 409);
        }

        $superado = $this->enTransaccion(function () use ($datos, $personaId): ?int {
            $subasta = $this->subastas->buscar($datos['subastaId']) ?? throw new HttpException('La subasta no existe.', 404);
            if (!$this->vigente($subasta)) throw new HttpException('La subasta ya terminó.', 409);
            if ($subasta['vendedorPersonaId'] === $personaId) throw new HttpException('No puedes pujar en tu propia subasta.', 409);
            $mejor = $this->subastas->mejorPuja($datos['subastaId']);
            if ($mejor !== null && $mejor['personaId'] === $personaId) throw new HttpException('Ya tienes la puja más alta.', 409);
            $minimo = self::minimoSiguiente($subasta['precioBase'], $subasta['incrementoMinimo'], $mejor['monto'] ?? null);
            if ($datos['monto'] + 0.001 < $minimo) {
                throw new HttpException('Revise los campos indicados.', 422, null, [
                    'monto' => 'La puja mínima es ' . SubastaCierreService::colones($minimo) . '.',
                ]);
            }
            $pujaId = $this->subastas->registrarPuja($datos['subastaId'], $personaId, $datos['monto'], gmdate('Y-m-d H:i:s'));
            if ($mejor !== null) {
                $this->notificaciones->crear($mejor['personaId'], 'SUBASTA_SUPERADA', "Te superaron en «{$subasta['titulo']}»",
                    'La puja más alta ahora es ' . SubastaCierreService::colones($datos['monto']) . '. Todavía puedes volver a pujar.',
                    "subastas?id={$subasta['subastaId']}");
            }
            $this->bitacora->registrar('PUJAR', (string) $subasta['subastaId'], null,
                ['pujaId' => $pujaId, 'monto' => $datos['monto']], $this->solicitudId, entidad: 'SUBASTA', origen: self::ORIGEN);
            return $mejor['personaId'] ?? null;
        });
        $this->tiempoReal->avisar(['subastas', "subasta-{$datos['subastaId']}", $superado === null ? '' : "notificaciones-{$superado}"]);

        return $this->respuesta(true, 'Puja registrada.', [
            'subasta' => $this->vistaDetalle($this->subastas->buscar($datos['subastaId'])),
            'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ], 201);
    }

    private function comentar(array $cuerpo): array
    {
        $this->exigirNoAdministrador();
        $this->exigirCampos($cuerpo, ['subastaId', 'accion', 'texto']);
        $datos = AnimalPublicacionController::validarTodos([
            'subastaId' => fn () => $this->enteroCampo($cuerpo['subastaId'] ?? null, 'subastaId', 1, 2147483647),
            'texto' => fn () => AnimalPublicacionController::texto($cuerpo['texto'] ?? null, 'texto', 500, true),
        ]);
        $this->enTransaccion(function () use ($datos): int {
            $subasta = $this->subastas->buscar($datos['subastaId']) ?? throw new HttpException('La subasta no existe.', 404);
            $id = $this->subastas->registrarComentario($subasta['subastaId'], (int) $this->actor->personaId, $datos['texto'], gmdate('Y-m-d H:i:s'));
            $this->bitacora->registrar('COMENTAR', (string) $subasta['subastaId'], null, ['comentarioId' => $id],
                $this->solicitudId, entidad: 'SUBASTA', origen: self::ORIGEN);
            return $id;
        });
        $this->tiempoReal->avisar(["subasta-{$datos['subastaId']}"]);

        return $this->respuesta(true, 'Comentario publicado.', [
            'comentarios' => $this->vistaComentarios($this->subastas->comentarios($datos['subastaId'])),
        ], 201);
    }

    /** Solo su dueño, solo activa y solo sin pujas: con pujas ya hay compradores comprometidos. */
    private function cancelar(array $cuerpo): array
    {
        $this->exigirCampos($cuerpo, ['subastaId', 'accion']);
        $datos = AnimalPublicacionController::validarTodos([
            'subastaId' => fn () => $this->enteroCampo($cuerpo['subastaId'] ?? null, 'subastaId', 1, 2147483647),
        ]);
        $this->enTransaccion(function () use ($datos): bool {
            $subasta = $this->subastas->buscar($datos['subastaId']);
            if ($subasta === null || $subasta['vendedorPersonaId'] !== (int) $this->actor->personaId) {
                throw new HttpException('La subasta no existe en tu cuenta.', 404);
            }
            if (!$this->vigente($subasta)) throw new HttpException('La subasta ya terminó.', 409);
            if ($subasta['pujasTotal'] > 0) throw new HttpException('La subasta ya tiene pujas: no se puede cancelar.', 409);
            $this->subastas->cerrar($subasta['subastaId'], 'CANCELADA', null, gmdate('Y-m-d H:i:s'));
            $this->animales->marcarEstadoAnimales([$subasta['animalId']], 'ACTIVO');
            $this->bitacora->registrar('CANCELAR', (string) $subasta['subastaId'], ['estado' => 'ACTIVA'], ['estado' => 'CANCELADA'],
                $this->solicitudId, entidad: 'SUBASTA', origen: self::ORIGEN);
            return true;
        });
        $this->tiempoReal->avisar(['subastas', "subasta-{$datos['subastaId']}"]);

        return $this->respuesta(true, 'Subasta cancelada. El animal volvió a tu inventario.', [
            'subasta' => $this->vistaDetalle($this->subastas->buscar($datos['subastaId'])),
            'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ]);
    }

    public static function minimoSiguiente(float $precioBase, float $incremento, ?float $mejor): float
    {
        return $mejor === null ? $precioBase : round($mejor + $incremento, 2);
    }

    /** Nombre corto para mostrar a otros: el alias, o el primer nombre y la inicial del siguiente ("Ana P."). */
    public static function nombrePublico(?string $nombre, ?string $alias): string
    {
        if (is_string($alias) && trim($alias) !== '') return trim($alias);
        $partes = preg_split('/\s+/', trim((string) $nombre)) ?: [];
        if ($partes === [] || $partes[0] === '') return 'Participante';
        return $partes[0] . (isset($partes[1]) ? ' ' . mb_strtoupper(mb_substr($partes[1], 0, 1, 'UTF-8'), 'UTF-8') . '.' : '');
    }

    private function resumen(array $s): array
    {
        $personaId = (int) $this->actor->personaId;
        return [
            'subastaId' => $s['subastaId'],
            'titulo' => $s['titulo'],
            'imagenUrl' => $s['imagenUrl'],
            'estado' => $s['estado'],
            'precioBase' => $s['precioBase'],
            'precioActual' => $s['precioActual'],
            'pujasTotal' => $s['pujasTotal'],
            'fechaFin' => $s['fechaFin'],
            'fechaCierre' => $s['fechaCierre'],
            'vendedor' => $s['vendedor'],
            'finca' => ['provincia' => $s['finca']['provincia'], 'canton' => $s['finca']['canton']],
            'animal' => ['especie' => $s['animal']['especie'], 'tipo' => $s['animal']['tipo'], 'raza' => $s['animal']['raza']],
            'soyVendedor' => $s['vendedorPersonaId'] === $personaId,
            'soyGanador' => $s['ganadorPersonaId'] === $personaId,
        ];
    }

    private function vistaDetalle(array $s): array
    {
        $personaId = (int) $this->actor->personaId;
        $mejor = $this->subastas->mejorPuja($s['subastaId']);
        $soyVendedor = $s['vendedorPersonaId'] === $personaId;
        $soyGanador = $s['ganadorPersonaId'] === $personaId;
        $vigente = $this->vigente($s);
        $esAdmin = $this->esAdministrador();
        $comprador = $this->compradores->buscarPorPersona($personaId);
        $ganador = $s['ganadorPersonaId'] === null ? null : $this->subastas->contacto($s['ganadorPersonaId']);
        $motivo = match (true) {
            !$vigente => 'La subasta ya terminó.',
            $soyVendedor => 'Es tu subasta: aquí ves cómo va.',
            $esAdmin => 'Los administradores pueden ver las subastas, pero no participar.',
            $comprador === null || $comprador['estado'] !== 'ACTIVO' => 'Activa la actividad Comprador en Ajustes para pujar.',
            $mejor !== null && $mejor['personaId'] === $personaId => 'Vas ganando: espera a que alguien supere tu puja.',
            default => null,
        };
        $pujas = array_map(fn (array $p): array => [
            'pujaId' => $p['pujaId'],
            'monto' => $p['monto'],
            'fecha' => $p['fecha'],
            'participante' => $p['personaId'] === $personaId ? 'Tú' : self::nombrePublico($p['nombre'], $p['alias']),
            'esMia' => $p['personaId'] === $personaId,
        ], $this->subastas->pujas($s['subastaId']));
        $vista = [
            'subastaId' => $s['subastaId'],
            'titulo' => $s['titulo'],
            'descripcion' => $s['descripcion'],
            'imagenUrl' => $s['imagenUrl'],
            'estado' => $s['estado'],
            'precioBase' => $s['precioBase'],
            'incrementoMinimo' => $s['incrementoMinimo'],
            'precioActual' => $s['precioActual'],
            'pujasTotal' => $s['pujasTotal'],
            'minimoSiguiente' => self::minimoSiguiente($s['precioBase'], $s['incrementoMinimo'], $mejor['monto'] ?? null),
            'fechaInicio' => $s['fechaInicio'],
            'fechaFin' => $s['fechaFin'],
            'fechaCierre' => $s['fechaCierre'],
            'vendedor' => $s['vendedor'],
            'finca' => $s['finca'],
            'animal' => $s['animal'],
            'soyVendedor' => $soyVendedor,
            'soyGanador' => $soyGanador,
            'voyGanando' => $vigente && $mejor !== null && $mejor['personaId'] === $personaId,
            'puedoPujar' => $motivo === null,
            'puedoComentar' => !$esAdmin,
            'motivoNoPuja' => $motivo,
            'puedoCancelar' => $soyVendedor && $vigente && $s['pujasTotal'] === 0,
            'ganador' => $s['ganadorPersonaId'] === null ? null : [
                'participante' => $soyGanador ? 'Tú' : self::nombrePublico($ganador['nombre'] ?? null, $ganador['alias'] ?? null),
            ],
            'pujas' => $pujas,
            'comentarios' => $this->vistaComentarios($this->subastas->comentarios($s['subastaId'])),
            'trato' => null,
        ];
        // El trato (contactos y dirección completa) solo lo ven el ganador y el vendedor de una subasta finalizada.
        if ($s['estado'] === 'FINALIZADA' && ($soyGanador || $soyVendedor)) {
            $ubicacion = $this->subastas->ubicacionFinca($s['fincaId']);
            $vista['trato'] = [
                'precioFinal' => $mejor['monto'] ?? $s['precioActual'],
                'vendedor' => $this->subastas->contacto($s['vendedorPersonaId']),
                'ganador' => $ganador,
                'finca' => $ubicacion === null ? ['nombre' => $s['finca']['nombre']] : [
                    'nombre' => $s['finca']['nombre'], 'provincia' => $ubicacion['provincia'], 'canton' => $ubicacion['canton'],
                    'distrito' => $ubicacion['distrito'], 'pueblo' => $ubicacion['pueblo'], 'senas' => $ubicacion['senas'],
                    'tienePunto' => $ubicacion['latitud'] !== null && $ubicacion['longitud'] !== null,
                ],
            ];
        }
        return $vista;
    }

    private function vistaComentarios(array $comentarios): array
    {
        $personaId = (int) $this->actor->personaId;
        return array_map(static fn (array $c): array => [
            'comentarioId' => $c['comentarioId'],
            'texto' => $c['texto'],
            'fecha' => $c['fecha'],
            'autor' => $c['personaId'] === $personaId ? 'Tú' : self::nombrePublico($c['nombre'], $c['alias']),
            'esMio' => $c['personaId'] === $personaId,
        ], $comentarios);
    }

    private function vigente(array $subasta): bool
    {
        return $subasta['estado'] === 'ACTIVA' && strtotime((string) $subasta['fechaFin']) > time();
    }

    private function esAdministrador(): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT 1 FROM tbadministrador WHERE LOWER(tbadministradorcorreoelectronico) = LOWER(:correo) AND tbadministradorestado = 1 LIMIT 1'
        );
        $sentencia->execute(['correo' => (string) $this->actor->correoElectronico]);
        return $sentencia->fetchColumn() !== false;
    }

    private function exigirNoAdministrador(): void
    {
        if ($this->esAdministrador()) {
            throw new HttpException('Los administradores pueden ver las subastas, pero no participar.', 403);
        }
    }

    private function productorActivo(): int
    {
        $productor = $this->productor->buscarPorPersonaId((int) $this->actor->personaId);
        if ($productor === null || (int) ($productor['tbproductorestado'] ?? 0) !== 1 || (int) ($productor['tbpersonaestado'] ?? 0) !== 1) {
            throw new HttpException('Activa la actividad Vendedor en Ajustes para abrir una subasta.', 409);
        }
        return (int) $productor['tbproductorid'];
    }

    private function exigirCampos(array $cuerpo, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($cuerpo), $permitidos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
    }

    /** Un monto en colones: mayor que cero y con el tope de la columna (NUMERIC(12,2)); el error lleva el nombre del campo. */
    private function dinero(mixed $valor, string $campo): float
    {
        try {
            $monto = AnimalPublicacionController::numero($valor, 'precio');
        } catch (HttpException $error) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => (string) (array_values($error->errores)[0] ?? '')]);
        }
        return $this->positivo($monto, $campo);
    }

    private function positivo(?float $valor, string $campo): float
    {
        if ($valor === null) throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Este campo es obligatorio.']);
        if ($valor <= 0) throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser mayor que cero.']);
        return $valor;
    }

    private function enteroCampo(mixed $valor, string $campo, int $minimo, int $maximo, ?string $mensaje = null): int
    {
        $errores = [];
        $entero = $this->entero($valor, $campo, $minimo, $maximo, $errores);
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => $valor === null || $valor === ''
                ? 'Este campo es obligatorio.' : ($mensaje ?? $errores[$campo])]);
        }
        return $entero;
    }

    private function entero(mixed $valor, string $campo, int $minimo, int $maximo, array &$errores): int
    {
        $entero = filter_var(is_bool($valor) ? false : $valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);
        if ($entero === false) {
            $errores[$campo] = "Debe ser un número entero entre {$minimo} y " . number_format($maximo, 0, ',', '.') . '.';
            return 0;
        }
        return $entero;
    }

    /** Orden de bloqueos: subastas → notificación → bitácora → transacción (Cuidados #12). */
    private function enTransaccion(callable $operacion): mixed
    {
        return $this->subastas->ejecutarConBloqueo(fn (): mixed => $this->animales->ejecutarConBloqueoAlta('tbanimal',
            fn (): mixed => $this->notificaciones->ejecutarConBloqueoAlta(
                fn (): mixed => $this->bitacora->ejecutarConBloqueoAlta(function () use ($operacion): mixed {
                    $this->conexion->beginTransaction();
                    try {
                        $resultado = $operacion();
                        $this->conexion->commit();
                        return $resultado;
                    } catch (Throwable $error) {
                        if ($this->conexion->inTransaction()) $this->conexion->rollBack();
                        throw $error;
                    }
                }),
            ),
        ));
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
