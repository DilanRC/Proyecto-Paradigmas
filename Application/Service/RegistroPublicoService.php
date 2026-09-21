<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Model\Direccion;
use Application\Model\FincaDireccion;
use Application\Model\Persona;
use Application\Model\Productor;
use Application\Model\ProductorDireccion;
use Application\Model\ProductorEstadoPeriodo;
use Application\Model\ProductorFinca;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use PDO;
use Throwable;

/**
 * Caso de uso público de primera alta / ampliación de actividades.
 *
 * Una sola transacción confirma o revierte Persona + contextos + fincas +
 * direcciones + periodo Productor + bitácora. La base permanece deliberadamente
 * mínima; las relaciones, duplicados y políticas se comprueban en PHP.
 */
final class RegistroPublicoService
{
    private Persona $persona;
    private ProductorFinca $fincas;
    private Productor $productor;
    private ProductorDireccion $direccionProductor;
    private FincaDireccion $direccionFinca;
    private ProductorEstadoPeriodo $estadoProductor;
    private Comprador $comprador;
    private Transportista $transportista;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        private readonly string $solicitudId,
    ) {
        $this->persona = new Persona($conexion);
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);

        $direccion = new Direccion($conexion);
        $this->direccionProductor = new ProductorDireccion($conexion, $direccion);
        $this->direccionFinca = new FincaDireccion($conexion, $direccion);
        $this->estadoProductor = new ProductorEstadoPeriodo($conexion);

        $this->comprador = new Comprador($conexion);
        $this->transportista = new Transportista($conexion, new TransportistaVehiculo($conexion));
    }

    /**
     * @param array<string,mixed> $personaDatos Datos ya validados/canónicos.
     * @param list<string> $capacidades PRODUCTOR|COMPRADOR|TRANSPORTISTA.
     * @param list<array{nombre:string,direccion?:array<string,mixed>}> $fincasDetalle
     */
    public function registrar(array $personaDatos, array $capacidades, array $fincasDetalle): array
    {
        if (!$this->actor->estaAutenticado()) {
            throw new HttpException('Debe verificar su cuenta antes de completar el registro.', 401);
        }

        return $this->persona->ejecutarConBloqueoAlta(
            fn (): array => $this->conBloqueosProductor(
                in_array('PRODUCTOR', $capacidades, true),
                fn (): array => $this->transaccion($personaDatos, $capacidades, $fincasDetalle),
            ),
        );
    }

    private function conBloqueosProductor(bool $necesarios, callable $operacion): array
    {
        if (!$necesarios) {
            return $operacion();
        }

        return $this->direccionProductor->ejecutarConBloqueoAlta(
            fn (): array => $this->fincas->ejecutarConBloqueoAlta(
                fn (): array => $this->direccionFinca->ejecutarConBloqueoEnlaceAlta($operacion),
            ),
        );
    }

    private function transaccion(array $personaDatos, array $capacidades, array $fincasDetalle): array
    {
        $this->conexion->beginTransaction();
        try {
            $persona = $this->resolverPersona($personaDatos);
            $personaId = (int) $persona['tbpersonaid'];
            $identificacion = (string) $persona['tbpersonaidentificacionnumero'];

            $actorVinculado = ActorContext::personaAutenticada(
                $personaId,
                (string) $this->actor->proveedorSujeto,
                $this->actor->correoElectronico,
                $this->actor->rolTecnico,
            );
            $bitacora = new Bitacora($this->conexion, $actorVinculado);

            return $bitacora->ejecutarConBloqueoAlta(function () use (
                $persona,
                $personaDatos,
                $personaId,
                $identificacion,
                $capacidades,
                $fincasDetalle,
                $bitacora,
            ): array {
                $creadas = [];

                foreach ($capacidades as $capacidad) {
                    switch ($capacidad) {
                        case 'PRODUCTOR':
                            $this->asegurarProductor(
                                $personaDatos,
                                $identificacion,
                                $fincasDetalle,
                                $creadas,
                            );
                            break;
                        case 'COMPRADOR':
                            $this->asegurarComprador($personaId, $identificacion, $creadas);
                            break;
                        case 'TRANSPORTISTA':
                            $this->asegurarTransportista($personaDatos, $identificacion, $creadas);
                            break;
                        default:
                            throw new HttpException('La actividad solicitada no es válida.', 422);
                    }
                }

                if ($creadas !== []) {
                    $bitacora->registrar(
                        'REGISTRO_PUBLICO',
                        $identificacion,
                        null,
                        [
                            'personaId' => (int) $persona['tbpersonaid'],
                            'capacidadesSolicitadas' => $capacidades,
                            'capacidadesCreadas' => $creadas,
                            'fincasProductor' => in_array('PRODUCTOR', $creadas, true)
                                ? array_values(array_map(
                                    static fn (array $finca): string => $finca['nombre'],
                                    $fincasDetalle,
                                ))
                                : [],
                        ],
                        $this->solicitudId,
                        'PERSONA',
                        'API_REGISTRO_PUBLICO',
                    );
                }

                $this->conexion->commit();

                return [
                    'personaId' => (int) $persona['tbpersonaid'],
                    'identificacionNumero' => $identificacion,
                    'capacidadesSolicitadas' => $capacidades,
                    'capacidadesCreadas' => $creadas,
                ];
            });
        } catch (Throwable $excepcion) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $excepcion;
        }
    }

    private function resolverPersona(array $datos): array
    {
        if ($this->actor->personaId !== null) {
            $persona = $this->persona->buscarPorId($this->actor->personaId);
            if ($persona === null || (int) $persona['tbpersonaestado'] !== 1) {
                throw new HttpException('La Persona vinculada a la sesión no está disponible.', 409);
            }
            if (
                $persona['tbpersonaidentificacionnumero'] !== $datos['identificacionNumero']
                || mb_strtolower((string) $persona['tbpersonacorreoelectronico'], 'UTF-8')
                    !== mb_strtolower((string) $datos['correoElectronico'], 'UTF-8')
            ) {
                throw new HttpException(
                    'Los datos enviados no corresponden a la Persona autenticada.',
                    409,
                );
            }
            return $persona;
        }

        $correoActor = mb_strtolower((string) $this->actor->correoElectronico, 'UTF-8');
        if ($correoActor === '' || $correoActor !== mb_strtolower((string) $datos['correoElectronico'], 'UTF-8')) {
            throw new HttpException(
                'El correo del registro debe coincidir con la cuenta autenticada.',
                422,
                null,
                ['persona.correoElectronico' => 'Use el mismo correo que verificó en Supabase.'],
            );
        }

        $porCorreo = $this->conexion->prepare(
            'SELECT tbpersonaid, tbpersonaidentificacionnumero FROM tbpersona
             WHERE LOWER(tbpersonacorreoelectronico) = LOWER(:correo)
             ORDER BY tbpersonaid'
        );
        $porCorreo->execute(['correo' => $correoActor]);
        $filasCorreo = $porCorreo->fetchAll();
        if (count($filasCorreo) > 1) {
            throw new HttpException('El correo autenticado está vinculado a más de una Persona.', 409);
        }
        if ($filasCorreo !== []
            && $filasCorreo[0]['tbpersonaidentificacionnumero'] !== $datos['identificacionNumero']) {
            throw new HttpException(
                'El correo autenticado ya pertenece a otra identificación.',
                409,
                null,
                ['persona.correoElectronico' => 'Este correo ya está vinculado a otra Persona.'],
            );
        }

        try {
            return $this->persona->obtenerOCrear($datos);
        } catch (\Application\Model\PersonaConflictException $excepcion) {
            throw new HttpException($excepcion->getMessage(), 409);
        }
    }

    /** @param list<string> $creadas */
    private function asegurarProductor(
        array $personaDatos,
        string $identificacion,
        array $fincasDetalle,
        array &$creadas,
    ): void {
        $existente = $this->productor->buscar($identificacion);
        if ($existente !== null) {
            $this->exigirActivo('PRODUCTOR', $existente['estado']);
            return;
        }
        if ($fincasDetalle === []) {
            throw new HttpException(
                'Para registrarse como Productor debe indicar al menos una finca.',
                422,
                null,
                ['fincas' => 'Agregue al menos una finca.'],
            );
        }

        $productorId = $this->productor->crear($personaDatos);
        $this->direccionProductor->crearVacia($productorId);
        $this->fincas->sincronizar(
            $productorId,
            array_values(array_map(static fn (array $finca): string => $finca['nombre'], $fincasDetalle)),
        );

        foreach ($fincasDetalle as $finca) {
            if (!array_key_exists('direccion', $finca)) {
                continue;
            }
            $fincaId = $this->fincas->buscarIdActivo($productorId, $finca['nombre']);
            if ($fincaId === null) {
                throw new HttpException(
                    "La finca {$finca['nombre']} no quedó activa; se canceló todo el registro.",
                    409,
                );
            }
            $this->direccionFinca->crear($fincaId, $finca['direccion']);
        }

        $this->estadoProductor->ejecutarConBloqueo(
            $productorId,
            fn (): int => $this->estadoProductor->abrir(
                $productorId,
                1,
                'Registro público de Productor',
            ),
        );
        $creadas[] = 'PRODUCTOR';
    }

    /** @param list<string> $creadas */
    private function asegurarComprador(int $personaId, string $identificacion, array &$creadas): void
    {
        $existente = $this->comprador->buscar($identificacion);
        if ($existente !== null) {
            $this->exigirActivo('COMPRADOR', $existente['estado']);
            return;
        }

        $persona = $this->persona->buscarPorId($personaId);
        if ($persona === null) {
            throw new HttpException('La Persona no existe para declarar la capacidad Comprador.', 409);
        }
        $this->comprador->crear([], $persona);
        $creadas[] = 'COMPRADOR';
    }

    /** @param list<string> $creadas */
    private function asegurarTransportista(array $personaDatos, string $identificacion, array &$creadas): void
    {
        $existente = $this->transportista->buscar($identificacion);
        if ($existente !== null) {
            $this->exigirActivo('TRANSPORTISTA', $existente['estado']);
            return;
        }

        $this->transportista->crear($personaDatos);
        $creadas[] = 'TRANSPORTISTA';
    }

    private function exigirActivo(string $capacidad, string $estado): void
    {
        if ($estado === 'ACTIVO') {
            return;
        }

        throw new HttpException(
            "La actividad {$capacidad} ya existe pero está inactiva; reactívela desde Mi actividad.",
            409,
            [
                'contexto' => $capacidad,
                'estado' => $estado,
                'siguientePaso' => 'mi-actividad.php',
            ],
        );
    }
}
