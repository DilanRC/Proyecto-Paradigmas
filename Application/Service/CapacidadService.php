<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Model\Persona;
use Application\Model\Productor;
use Application\Model\ProductorEstadoPeriodo;
use Application\Model\ProductorFinca;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Capa de casos de uso de contextos (Tramo 3): inscribirse / abandonar /
 * reactivar una capacidad de Persona con la MISMA firma por entidad, delegando
 * en los modelos existentes. La UI futura no conoce las diferencias entre
 * Comprador (clasificación), Productor (periodos) y Transportista (columna).
 *
 * Todas las operaciones son idempotentes, corren dentro de la transacción y del
 * bloqueo nombrado del llamador, y registran en tbbitacora con
 * `entidad`/`origen` consistentes. Los motivos se validan contra un catálogo
 * explícito (nada inventado) y se dejan parametrizados.
 *
 * Contrato de errores: 422 catálogo o identificación inválida; 404 contexto no
 * existe; 409 persona inactiva o persona con datos personales incompatibles
 * (PersonaConflictException del modelo). Repetir una operación produce el mismo
 * resultado sin duplicar filas ni periodos.
 */
final class CapacidadService
{
    public const ENTIDADES = ['COMPRADOR', 'PRODUCTOR', 'TRANSPORTISTA'];

    /** Catálogo de motivos aprobados; crecerá por decisión de Arquitectura. */
    public const MOTIVOS_INSCRIPCION = ['INSCRIPCION'];
    public const MOTIVOS_RETIRO = ['ABANDONO'];
    public const MOTIVOS_REACTIVACION = ['REACTIVACION'];

    public const ORIGEN = 'API_CAPACIDADES';

    private Bitacora $bitacora;
    private CompradorClasificacionService $clasificacionComprador;
    private Comprador $comprador;
    private ProductorEstadoService $estadoProductor;
    private ProductorEstadoPeriodo $estadoPeriodos;
    private EstadoService $estadoLegacy;
    private Persona $persona;
    private Productor $productor;
    private Transportista $transportista;
    private ValidacionService $validacion;

    public function __construct(
        private readonly PDO $conexion,
        private readonly string $solicitudId,
        private readonly ?ActorContext $actor = null,
    ) {
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->validacion = new ValidacionService();
        $this->persona = new Persona($conexion);
        $this->comprador = new Comprador($conexion);
        $this->clasificacionComprador = new CompradorClasificacionService($conexion);
        $this->productor = new Productor($conexion, new ProductorFinca($conexion));
        $this->estadoPeriodos = new ProductorEstadoPeriodo($conexion);
        $this->estadoProductor = new ProductorEstadoService($this->estadoPeriodos, $this->productor, $this->bitacora, $solicitudId);
        $this->transportista = new Transportista($conexion, new TransportistaVehiculo($conexion));
        $this->estadoLegacy = new EstadoService($this->bitacora, $solicitudId);
    }

    /**
     * @return array{
     *   estado: string, contexto: string, identificacionNumero: string,
     *   contextoRegistrado: ?array, pendientes: array
     * }
     *   - estado: 'COMPLETAR_PERSONA' cuando la persona no está completa y no
     *     se enviaron datos para completarla; 'INSCRITO'/'REACTIVADO'/'ACTIVO'
     *     cuando el contexto quedó (o ya estaba) activo; 'INACTIVO' tras
     *     abandonar.
     *   - pendientes: claves de persona que faltan para completar el registro
     *     (reutiliza la lista de campos de ValidacionService).
     */
    public function inscribir(
        string $entidad,
        string $identificacionNumero,
        string $motivo = 'INSCRIPCION',
        ?array $datosPersona = null,
    ): array {
        $entidad = $this->validarEntidad($entidad);
        if (!in_array($motivo, self::MOTIVOS_INSCRIPCION, true)) {
            throw new HttpException('El motivo de inscripción no está en el catálogo.', 422, null, [
                'motivo' => 'Use ' . implode(', ', self::MOTIVOS_INSCRIPCION) . '.',
            ]);
        }
        $identificacion = $this->validarIdentificacion($identificacionNumero);

        return match ($entidad) {
            'COMPRADOR' => $this->inscribirComprador($identificacion, $datosPersona),
            'PRODUCTOR' => $this->inscribirProductor($identificacion, $datosPersona),
            'TRANSPORTISTA' => $this->inscribirTransportista($identificacion, $datosPersona),
        };
    }

    /** Abandona un contexto: desactiva sin borrar (recuperable con reactivar). */
    public function abandonar(
        string $entidad,
        string $identificacionNumero,
        string $motivo = 'ABANDONO',
    ): array {
        $entidad = $this->validarEntidad($entidad);
        if (!in_array($motivo, self::MOTIVOS_RETIRO, true)) {
            throw new HttpException('El motivo de abandono no está en el catálogo.', 422, null, [
                'motivo' => 'Use ' . implode(', ', self::MOTIVOS_RETIRO) . '.',
            ]);
        }
        $identificacion = $this->validarIdentificacion($identificacionNumero);

        return match ($entidad) {
            'COMPRADOR' => $this->transicionarComprador($identificacion, false),
            'PRODUCTOR' => $this->transicionarProductor($identificacion, false),
            'TRANSPORTISTA' => $this->transicionarTransportista($identificacion, false),
        };
    }

    /** Reactiva un contexto abandonado (idempotente; 404 si nunca existió). */
    public function reactivar(
        string $entidad,
        string $identificacionNumero,
        string $motivo = 'REACTIVACION',
    ): array {
        $entidad = $this->validarEntidad($entidad);
        if (!in_array($motivo, self::MOTIVOS_REACTIVACION, true)) {
            throw new HttpException('El motivo de reactivación no está en el catálogo.', 422, null, [
                'motivo' => 'Use ' . implode(', ', self::MOTIVOS_REACTIVACION) . '.',
            ]);
        }
        $identificacion = $this->validarIdentificacion($identificacionNumero);

        return match ($entidad) {
            'COMPRADOR' => $this->transicionarComprador($identificacion, true),
            'PRODUCTOR' => $this->transicionarProductor($identificacion, true),
            'TRANSPORTISTA' => $this->transicionarTransportista($identificacion, true),
        };
    }

    // ------------------------------------------------------------------
    // Comprador (clasificación por persona, DEC-28)
    // ------------------------------------------------------------------

    private function inscribirComprador(string $identificacion, ?array $datosPersona): array
    {
        $pendientes = $this->pendientesPersona($identificacion);
        if ($pendientes !== [] && $datosPersona === null) {
            return $this->completarPersonaResultado('COMPRADOR', $identificacion, $pendientes);
        }
        $datos = $pendientes === []
            ? $this->datosDesdePersona($identificacion)
            : $this->datosPersonaCompletados($identificacion, $datosPersona);
        if ($datos['identificacionNumero'] !== $identificacion) {
            throw new HttpException('La persona registrada no coincide con la identificación.', 409, null, [
                'identificacion.numero' => 'La identificación enviada difiere de la registrada.',
            ]);
        }

        $estado = $this->comprador->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(
                fn (): array => $this->clasificacionComprador->inscribir($datos),
            ),
        );
        if ($estado['cambio']) {
            $this->bitacora->registrar(
                $estado['nuevo'] ? 'INSCRIBIR' : 'REACTIVAR',
                $identificacion,
                null,
                $estado['comprador'],
                $this->solicitudId,
                entidad: 'COMPRADOR',
                origen: self::ORIGEN,
            );
        }

        return $this->resultadoContexto(
            'COMPRADOR',
            $identificacion,
            $estado['cambio']
                ? ($estado['nuevo'] ? 'INSCRITO' : 'REACTIVADO')
                : 'ACTIVO',
            $estado['comprador'],
        );
    }

    private function transicionarComprador(string $identificacion, bool $activo): array
    {
        $nuevo = $this->comprador->ejecutarConBloqueoAlta(
            fn () => $this->transaccion(fn () => $activo
                ? $this->clasificacionComprador->reactivar($identificacion)
                : $this->clasificacionComprador->desactivar($identificacion)),
        );
        if ($nuevo === null) {
            if ($activo) {
                throw new HttpException('No existe un comprador para reactivar.', 404);
            }
            throw new HttpException('No existe un comprador para abandonar.', 404);
        }
        if (!$nuevo['cambio']) {
            return $this->resultadoContexto(
                'COMPRADOR',
                $identificacion,
                $nuevo['comprador']['estado'] === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO',
                $nuevo['comprador'],
            );
        }
        $this->bitacora->registrar(
            $activo ? 'REACTIVAR' : 'DESACTIVAR',
            $identificacion,
            null,
            $nuevo['comprador'],
            $this->solicitudId,
            entidad: 'COMPRADOR',
            origen: self::ORIGEN,
        );

        return $this->resultadoContexto(
            'COMPRADOR',
            $identificacion,
            $activo ? 'REACTIVADO' : 'INACTIVO',
            $nuevo['comprador'],
        );
    }

    // ------------------------------------------------------------------
    // Productor (periodos de estado, tramo 14)
    // ------------------------------------------------------------------

    private function inscribirProductor(string $identificacion, ?array $datosPersona): array
    {
        $productorActual = $this->productor->buscar($identificacion);
        if ($productorActual === null) {
            // El alta de productor (crear + fincas + dirección) sigue siendo del
            // CRUD admin (api/productores.php); aquí solo se adopta el contexto.
            return $this->completarPersonaResultado('PRODUCTOR', $identificacion, []);
        }

        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('El productor no está registrado.', 404);
            }
            if ((int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException('La persona está inactiva y no puede inscribir capacidades.', 409);
            }
            $cambio = $this->estadoProductor->transicionar(
                (int) $bloqueado['tbproductorid'],
                1,
                'Inscripción',
                $identificacion,
                'PRODUCTOR',
                self::ORIGEN,
            );

            $row = $this->productor->buscar($identificacion)
                ?? throw new RuntimeException('No fue posible leer el productor inscrito.');

            return ['cambio' => $cambio, 'row' => $row];
        });

        return $this->resultadoContexto(
            'PRODUCTOR',
            $identificacion,
            $nuevo['cambio'] ? 'INSCRITO' : 'ACTIVO',
            $nuevo['row'],
        );
    }

    private function transicionarProductor(string $identificacion, bool $activo): array
    {
        $nuevo = $this->transaccion(function () use ($identificacion, $activo): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException($activo ? 'No existe un productor para reactivar.' : 'No existe un productor para abandonar.', 404);
            }
            if ((int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException(
                    $activo
                        ? 'La persona está inactiva y no puede reactivar capacidades.'
                        : 'La persona está inactiva y no puede operar capacidades.',
                    409,
                );
            }
            $cambio = $this->estadoProductor->transicionar(
                (int) $bloqueado['tbproductorid'],
                $activo ? 1 : 0,
                $activo ? 'Reactivación' : 'Abandono',
                $identificacion,
                'PRODUCTOR',
                self::ORIGEN,
            );

            $row = $this->productor->buscar($identificacion)
                ?? throw new RuntimeException('No fue posible leer el productor tras la transición.');

            return ['cambio' => $cambio, 'row' => $row];
        });

        $estado = ($nuevo['row']['estado'] ?? '') === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';

        return $this->resultadoContexto(
            'PRODUCTOR',
            $identificacion,
            $nuevo['cambio'] ? ($activo ? 'REACTIVADO' : 'INACTIVO') : $estado,
            $nuevo['row'],
        );
    }

    // ------------------------------------------------------------------
    // Transportista (columna legacy de estado)
    // ------------------------------------------------------------------

    private function inscribirTransportista(string $identificacion, ?array $datosPersona): array
    {
        $pendientes = $this->pendientesPersona($identificacion);
        if ($pendientes !== [] && $datosPersona === null) {
            return $this->completarPersonaResultado('TRANSPORTISTA', $identificacion, $pendientes);
        }
        $anterior = $this->transportista->buscar($identificacion);
        if ($anterior === null) {
            $this->transportista->ejecutarConBloqueoAlta(
                fn (): int => $this->transaccion(fn (): int => $this->transportista->crear(
                    $this->datosPersonaCompletados($identificacion, $datosPersona),
                )),
            );
            $nuevo = $this->transportista->buscar($identificacion)
                ?? throw new RuntimeException('No fue posible leer el transportista inscrito.');
            $this->bitacora->registrar(
                'INSCRIBIR',
                $identificacion,
                null,
                $nuevo,
                $this->solicitudId,
                entidad: 'TRANSPORTISTA',
                origen: self::ORIGEN,
            );

            return $this->resultadoContexto('TRANSPORTISTA', $identificacion, 'INSCRITO', $nuevo);
        }

        $nuevo = $this->transaccion(fn (): array => $this->estadoLegacy->transicionar(
            fn ($clave) => $this->transportista->bloquear($clave),
            fn ($clave) => $this->transportista->buscar($clave),
            fn ($clave, $activo) => $this->transportista->cambiarEstado($clave, $activo),
            'tbtransportistaestado',
            1,
            'El transportista no está registrado.',
            $identificacion,
            'TRANSPORTISTA',
            self::ORIGEN,
            $identificacion,
        ));

        return $this->resultadoContexto(
            'TRANSPORTISTA',
            $identificacion,
            ($anterior['estado'] ?? 'ACTIVO') === 'ACTIVO' ? 'ACTIVO' : 'REACTIVADO',
            $nuevo,
        );
    }

    private function transicionarTransportista(string $identificacion, bool $activo): array
    {
        $anterior = $this->transportista->buscar($identificacion);
        $nuevo = $this->transaccion(fn (): array => $this->estadoLegacy->transicionar(
            fn ($clave) => $this->transportista->bloquear($clave),
            fn ($clave) => $this->transportista->buscar($clave),
            fn ($clave, $activo) => $this->transportista->cambiarEstado($clave, $activo),
            'tbtransportistaestado',
            $activo ? 1 : 0,
            $activo ? 'No existe un transportista para reactivar.' : 'No existe un transportista para abandonar.',
            $identificacion,
            'TRANSPORTISTA',
            self::ORIGEN,
            $identificacion,
        ));
        $estado = !$activo
            ? 'INACTIVO'
            : (($anterior['estado'] ?? 'INACTIVO') === 'ACTIVO' ? 'ACTIVO' : 'REACTIVADO');

        return $this->resultadoContexto('TRANSPORTISTA', $identificacion, $estado, $nuevo);
    }

    // ------------------------------------------------------------------
    // Introspección de la persona (Tramo 4: pendientes de registro)
    // ------------------------------------------------------------------

    private function pendientesPersona(string $identificacion): array
    {
        $persona = $this->persona->buscar($identificacion);
        if ($persona === null) {
            return ['nombre', 'telefono', 'correoElectronico'];
        }
        $pendientes = [];
        if ($persona['tbpersonanombre'] === null || trim((string) $persona['tbpersonanombre']) === '') {
            $pendientes[] = 'nombre';
        }
        if ($persona['tbpersonatelefono'] === null || trim((string) $persona['tbpersonatelefono']) === '') {
            $pendientes[] = 'telefono';
        }
        if ($persona['tbpersonacorreoelectronico'] === null || trim((string) $persona['tbpersonacorreoelectronico']) === '') {
            $pendientes[] = 'correoElectronico';
        }

        return $pendientes;
    }

    private function datosDesdePersona(string $identificacion): array
    {
        $persona = $this->persona->buscar($identificacion)
            ?? throw new RuntimeException('No fue posible leer la persona registrada.');

        return [
            'identificacionNumero' => $persona['tbpersonaidentificacionnumero'],
            'identificacionNumeroOriginal' => null,
            'identificacionTipo' => $persona['tbpersonaidentificaciontipo'],
            'nombre' => $persona['tbpersonanombre'],
            'alias' => $persona['tbpersonaalias'],
            'telefono' => $persona['tbpersonatelefono'],
            'correoElectronico' => $persona['tbpersonacorreoelectronico'],
        ];
    }

    /**
     * Combina la persona actual (si existe) con los datos enviados por quien
     * completa el registro y la valida contra ValidacionService. La persona
     * solo obtiene los campos que faltaban; la identificación siempre es la
     * del contexto solicitado.
     */
    private function datosPersonaCompletados(string $identificacion, array $datosPersona): array
    {
        $persona = $this->persona->buscar($identificacion);
        $datos = [
            'identificacion' => [
                'tipoCodigo' => $persona['tbpersonaidentificaciontipo'] ?? 'PASAPORTE',
                'numero' => $identificacion,
            ],
            'nombre' => $persona['tbpersonanombre'] ?? null,
            'alias' => $persona['tbpersonaalias'] ?? null,
            'telefono' => $persona['tbpersonatelefono'] ?? null,
            'correoElectronico' => $persona['tbpersonacorreoelectronico'] ?? null,
        ];
        foreach (['nombre', 'alias', 'telefono', 'correoElectronico'] as $campo) {
            if (array_key_exists($campo, $datosPersona)) {
                $datos[$campo] = $datosPersona[$campo];
            }
        }
        if (isset($datosPersona['identificacion']) && is_array($datosPersona['identificacion'])) {
            $datos['identificacion'] = $datosPersona['identificacion'];
        }

        $validados = $this->validacion->validarPersona($datos, false)['datos'];
        if ($validados['identificacionNumero'] !== $identificacion) {
            throw new HttpException('La persona registrada no coincide con la identificación.', 409, null, [
                'identificacion.numero' => 'La identificación enviada difiere de la registrada.',
            ]);
        }

        return $validados;
    }

    private function completarPersonaResultado(string $entidad, string $identificacion, array $pendientes): array
    {
        return [
            'estado' => 'COMPLETAR_PERSONA',
            'contexto' => $entidad,
            'identificacionNumero' => $identificacion,
            'contextoRegistrado' => null,
            'pendientes' => $pendientes,
        ];
    }

    private function resultadoContexto(
        string $entidad,
        string $identificacion,
        string $estado,
        array $contextoRegistrado,
    ): array {
        return [
            'estado' => $estado,
            'contexto' => $entidad,
            'identificacionNumero' => $identificacion,
            'contextoRegistrado' => $contextoRegistrado,
            'pendientes' => [],
        ];
    }

    private function validarEntidad(string $entidad): string
    {
        $entidad = mb_strtoupper(trim($entidad), 'UTF-8');
        if (!in_array($entidad, self::ENTIDADES, true)) {
            throw new HttpException('El contexto no está soportado.', 422, null, [
                'contexto' => 'Use ' . implode(', ', self::ENTIDADES) . '.',
            ]);
        }

        return $entidad;
    }

    private function validarIdentificacion(string $identificacionNumero): string
    {
        $identificacion = $this->validacion->normalizarIdentificacion($identificacionNumero);
        if ($identificacion === '') {
            throw new HttpException('La identificación es inválida.', 422, null, [
                'identificacionNumero' => 'La identificación es obligatoria.',
            ]);
        }

        return $identificacion;
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();

            return $resultado;
        } catch (Throwable $excepcion) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $excepcion;
        }
    }
}