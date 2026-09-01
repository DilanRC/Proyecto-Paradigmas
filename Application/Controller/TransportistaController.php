<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use Application\Service\EstadoService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

final class TransportistaController
{
    private Transportista $transportista;
    private Bitacora $bitacora;
    private ValidacionService $validacion;
    private EstadoService $estadoService;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null, ?ActorContext $actor = null)
    {
        $vehiculos = new TransportistaVehiculo($conexion);
        $this->transportista = new Transportista($conexion, $vehiculos);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
        $this->validacion = new ValidacionService();
        $this->estadoService = new EstadoService($this->bitacora, $this->solicitudId);
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST' => $this->crear($cuerpo),
                'PUT' => $this->actualizar($cuerpo),
                'DELETE' => $this->desactivar($cuerpo),
                'PATCH' => $this->reactivar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (\Application\Model\PersonaConflictException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), null, 409, ['identificacion.numero' => $excepcion->getMessage()]);
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

    private function consultar(array $consulta): array
    {
        if (array_key_exists('identificacionNumero', $consulta)) {
            $identificacion = $this->normalizarIdentificacion(
                $this->textoConsulta($consulta['identificacionNumero'], 250)
            );
            if ($identificacion === '') {
                throw new HttpException('La identificación no es válida.', 422);
            }
            $transportista = $this->transportista->buscar($identificacion);
            if ($transportista === null) {
                throw new HttpException('Transportista no encontrado.', 404);
            }
            return $this->respuesta(true, 'Transportista consultado correctamente.', $transportista);
        }

        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'TODOS', 10), 'UTF-8');
        if (!in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO'], true)) {
            throw new HttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use TODOS, ACTIVO o INACTIVO.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta) ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new HttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->transportista->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['catalogos'] = ['tiposIdentificacion' => $this->validacion->tiposIdentificacion()];

        return $this->respuesta(true, 'Transportistas consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarCuerpoPersona($cuerpo, false);
        $nuevo = $this->transportista->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos): array {
                $existente = $this->transportista->buscar($datos['identificacionNumero']);
                if ($existente !== null) {
                    $inactivo = $existente['estado'] === 'INACTIVO';
                    throw new HttpException(
                        $inactivo
                            ? 'La identificación pertenece a un transportista inactivo.'
                            : 'La identificación ya está registrada.',
                        409,
                        $inactivo
                            ? ['reactivacion' => ['identificacionNumero' => $datos['identificacionNumero']]]
                            : null,
                        ['identificacion.numero' => $inactivo
                            ? 'Debe reactivarse el transportista existente.'
                            : 'El número de identificación ya existe.'],
                    );
                }
                $this->transportista->crear($datos);
                $nuevo = $this->transportista->buscar($datos['identificacionNumero']);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el transportista recién creado.');
                }
                $this->bitacora->registrar(
                    'CREAR',
                    $datos['identificacionNumero'],
                    null,
                    $nuevo,
                    $this->solicitudId,
                    entidad: 'TRANSPORTISTA',
                    origen: 'API_TRANSPORTISTAS',
                );
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Transportista creado correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarCuerpoPersona($cuerpo, true);
        $identificacion = $datos['identificacionNumeroOriginal'];
        if ($datos['identificacionNumero'] !== $identificacion) {
            throw new HttpException('La identificación es inmutable y no puede modificarse.', 422, null, [
                'identificacion.numero' => 'Cree otro registro si la identificación fue digitada incorrectamente.',
            ]);
        }

        $nuevo = $this->transaccion(function () use ($datos, $identificacion): array {
            $bloqueado = $this->transportista->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('Transportista no encontrado.', 404);
            }
            if ((int) $bloqueado['tbtransportistaestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException(
                    'El transportista está inactivo. Debe reactivarlo antes de actualizarlo.',
                    409,
                );
            }
            $anterior = $this->transportista->buscar($identificacion);
            $this->transportista->actualizar($identificacion, $datos);
            $nuevo = $this->transportista->buscar($identificacion);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el transportista actualizado.');
            }
            $this->bitacora->registrar(
                'ACTUALIZAR', $identificacion, $anterior, $nuevo, $this->solicitudId,
                entidad: 'TRANSPORTISTA', origen: 'API_TRANSPORTISTAS',
            );
            return $nuevo;
        });

        return $this->respuesta(true, 'Transportista actualizado correctamente.', $nuevo);
    }

    private function desactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(fn (): array => $this->estadoService->transicionar(
            fn ($clave) => $this->transportista->bloquear($clave),
            fn ($clave) => $this->transportista->buscar($clave),
            fn ($clave, $activo) => $this->transportista->cambiarEstado($clave, $activo),
            'tbtransportistaestado',
            0,
            'Transportista no encontrado.',
            $identificacion,
            'TRANSPORTISTA',
            'API_TRANSPORTISTAS',
            $identificacion,
        ));

        return $this->respuesta(true, 'Transportista desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(fn (): array => $this->estadoService->transicionar(
            fn ($clave) => $this->transportista->bloquear($clave),
            fn ($clave) => $this->transportista->buscar($clave),
            fn ($clave, $activo) => $this->transportista->cambiarEstado($clave, $activo),
            'tbtransportistaestado',
            1,
            'Transportista no encontrado.',
            $identificacion,
            'TRANSPORTISTA',
            'API_TRANSPORTISTAS',
            $identificacion,
        ));

        return $this->respuesta(true, 'Transportista reactivado correctamente.', $nuevo);
    }

    private function validarCuerpoPersona(array $cuerpo, bool $actualizacion): array
    {
        // Contrato de campos del transportista (alineado con la UI): el POST y
        // el PUT aceptan el mismo perfil que el resto de personas.
        $permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico'];
        try {
            return $this->validacion->validarPersona($cuerpo, $actualizacion)['datos'];
        } catch (ValidacionException $excepcion) {
            throw new HttpException($excepcion->getMessage(), 422, null, $excepcion->errores);
        }
    }

    private function validarIdentificacionUnica(array $cuerpo): string
    {
        try {
            return $this->validacion->validarIdentificacionUnica($cuerpo);
        } catch (ValidacionException $excepcion) {
            throw new HttpException($excepcion->getMessage(), 422, null, $excepcion->errores);
        }
    }

    private function normalizarIdentificacion(string $valor): string
    {
        return $this->validacion->normalizarIdentificacion($valor);
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

    private function normalizarSolicitudId(?string $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor !== '' && strlen($valor) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $valor)) {
            return $valor;
        }
        return 'REQ-' . bin2hex(random_bytes(16));
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
