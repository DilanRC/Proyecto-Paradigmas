<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\Model\Bitacora;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use Application\Model\Vehiculo;
use PDO;
use Throwable;

final class TransportistaVehiculoHttpException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $estadoHttp,
        public readonly ?array $datos = null,
        public readonly array $errores = [],
    ) {
        parent::__construct($message);
    }
}

final class TransportistaVehiculoController
{
    private Transportista $transportista;
    private Vehiculo $vehiculo;
    private TransportistaVehiculo $enlace;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null, ?ActorContext $actor = null)
    {
        $this->enlace = new TransportistaVehiculo($conexion);
        $this->transportista = new Transportista($conexion, $this->enlace);
        $this->vehiculo = new Vehiculo($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST' => $this->asignar($cuerpo),
                'PUT' => $this->reasignar($cuerpo),
                'DELETE' => $this->desasignar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (TransportistaVehiculoHttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function consultar(array $consulta): array
    {
        if (array_key_exists('identificacionNumero', $consulta)) {
            $identificacion = $this->normalizarIdentificacion($this->textoConsulta($consulta['identificacionNumero'], 250));
            if ($identificacion === '') {
                throw new TransportistaVehiculoHttpException('La identificación no es válida.', 422);
            }
            $transportista = $this->transportista->buscar($identificacion);
            if ($transportista === null) {
                throw new TransportistaVehiculoHttpException('Transportista no encontrado.', 404);
            }
            return $this->respuesta(true, 'Vehículos del transportista consultados correctamente.', [
                'identificacionNumero' => $identificacion,
                'vehiculos' => $transportista['vehiculos'],
            ]);
        }

        if (array_key_exists('vehiculoId', $consulta)) {
            $vehiculoId = $this->enteroConsulta($consulta['vehiculoId'], 'vehiculoId');
            $vehiculo = $this->vehiculo->buscarPorId($vehiculoId);
            if ($vehiculo === null) {
                throw new TransportistaVehiculoHttpException('Vehículo no encontrado.', 404);
            }
            $transportistaId = $this->enlace->buscarTransportistaDeVehiculo($vehiculoId);
            $transportista = $transportistaId === null ? null : $this->transportista->buscarPorId($transportistaId);

            return $this->respuesta(true, 'Asignación del vehículo consultada correctamente.', [
                'vehiculoId' => $vehiculoId,
                'transportista' => $transportista,
            ]);
        }

        throw new TransportistaVehiculoHttpException(
            'Debe indicar identificacionNumero o vehiculoId.', 422, null, [
                'identificacionNumero' => 'Indique uno de los dos parámetros.',
                'vehiculoId' => 'Indique uno de los dos parámetros.',
            ]
        );
    }

    private function asignar(array $cuerpo): array
    {
        [$identificacion, $vehiculoId] = $this->validarCuerpo($cuerpo);

        $resultado = $this->enlace->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($identificacion, $vehiculoId): array {
                [$transportistaId] = $this->resolverActivos($identificacion, $vehiculoId);
                try {
                    $this->enlace->asignar($transportistaId, $vehiculoId);
                } catch (\RuntimeException $excepcion) {
                    throw new TransportistaVehiculoHttpException($excepcion->getMessage(), 409);
                }
                $vehiculos = $this->enlace->listarVehiculosPorTransportista($transportistaId);
                $this->bitacora->registrar(
                    'ASIGNAR_VEHICULO', "{$identificacion}:{$vehiculoId}", null,
                    ['identificacionNumero' => $identificacion, 'vehiculoId' => $vehiculoId],
                    $this->solicitudId, entidad: 'TRANSPORTISTA_VEHICULO', origen: 'API_TRANSPORTISTAS_VEHICULOS',
                );
                return ['identificacionNumero' => $identificacion, 'vehiculos' => $vehiculos];
            }),
        );

        return $this->respuesta(true, 'Vehículo asignado correctamente.', $resultado, 201);
    }

    private function reasignar(array $cuerpo): array
    {
        [$identificacion, $vehiculoId] = $this->validarCuerpo($cuerpo);

        $resultado = $this->enlace->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($identificacion, $vehiculoId): array {
                [$transportistaId] = $this->resolverActivos($identificacion, $vehiculoId);
                $anteriorTransportistaId = $this->enlace->buscarTransportistaDeVehiculo($vehiculoId);
                $this->enlace->reasignar($transportistaId, $vehiculoId);
                $vehiculos = $this->enlace->listarVehiculosPorTransportista($transportistaId);
                $this->bitacora->registrar(
                    'REASIGNAR_VEHICULO', "{$identificacion}:{$vehiculoId}",
                    ['transportistaIdAnterior' => $anteriorTransportistaId],
                    ['identificacionNumero' => $identificacion, 'vehiculoId' => $vehiculoId],
                    $this->solicitudId, entidad: 'TRANSPORTISTA_VEHICULO', origen: 'API_TRANSPORTISTAS_VEHICULOS',
                );
                return ['identificacionNumero' => $identificacion, 'vehiculos' => $vehiculos];
            }),
        );

        return $this->respuesta(true, 'Vehículo reasignado correctamente.', $resultado);
    }

    private function desasignar(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['vehiculoId']);
        $errores = [];
        $vehiculoId = $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', $errores);
        if ($errores !== []) {
            throw new TransportistaVehiculoHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = $this->transaccion(function () use ($vehiculoId): array {
            $vehiculo = $this->vehiculo->buscarPorId($vehiculoId);
            if ($vehiculo === null) {
                throw new TransportistaVehiculoHttpException('Vehículo no encontrado.', 404);
            }
            $transportistaId = $this->enlace->buscarTransportistaDeVehiculo($vehiculoId);
            try {
                $this->enlace->desasignar($vehiculoId);
            } catch (\RuntimeException $excepcion) {
                throw new TransportistaVehiculoHttpException($excepcion->getMessage(), 404);
            }
            $this->bitacora->registrar(
                'DESASIGNAR_VEHICULO', (string) $vehiculoId,
                ['transportistaId' => $transportistaId], null,
                $this->solicitudId, entidad: 'TRANSPORTISTA_VEHICULO', origen: 'API_TRANSPORTISTAS_VEHICULOS',
            );
            return ['vehiculoId' => $vehiculoId, 'transportistaId' => null];
        });

        return $this->respuesta(true, 'Vehículo desasignado correctamente.', $resultado);
    }

    /**
     * Bloquea al transportista (misma disciplina que otros controllers antes
     * de tocar datos hijos), valida que esté activo, valida que el vehículo
     * exista y esté activo, y devuelve [transportistaId, vehiculoId].
     */
    private function resolverActivos(string $identificacion, int $vehiculoId): array
    {
        $bloqueado = $this->transportista->bloquear($identificacion);
        if ($bloqueado === null) {
            throw new TransportistaVehiculoHttpException('Transportista no encontrado.', 404);
        }
        if ((int) $bloqueado['tbtransportistaestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
            throw new TransportistaVehiculoHttpException(
                'El transportista está inactivo. Debe reactivarlo antes de asignarle vehículos.', 409,
            );
        }
        $vehiculo = $this->vehiculo->bloquearPorId($vehiculoId);
        if ($vehiculo === null) {
            throw new TransportistaVehiculoHttpException('Vehículo no encontrado.', 404);
        }
        if ((int) $vehiculo['tbvehiculoestado'] !== 1) {
            throw new TransportistaVehiculoHttpException(
                'El vehículo está inactivo. Debe reactivarlo antes de asignarlo.', 409,
            );
        }

        return [(int) $bloqueado['tbtransportistaid'], $vehiculoId];
    }

    private function validarCuerpo(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', 'vehiculoId']);
        $errores = [];
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
            ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
        if ($identificacion === '') {
            $errores['identificacionNumero'] = 'La identificación es obligatoria.';
        }
        $vehiculoId = $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', $errores);
        if ($errores !== []) {
            throw new TransportistaVehiculoHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return [$identificacion, $vehiculoId];
    }

    private function enteroCampo(mixed $valor, string $campo, array &$errores): int
    {
        if ($valor === null) {
            $errores[$campo] = 'El campo es obligatorio.';
            return 0;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $errores[$campo] = 'Debe ser un entero positivo.';
            return 0;
        }
        return $entero;
    }

    private function normalizarIdentificacion(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[ -]+/u', '', trim($valor)) ?? '', 'UTF-8');
    }

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new TransportistaVehiculoHttpException('La consulta no es válida.', 422);
        }
        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new TransportistaVehiculoHttpException("{$campo} debe ser un entero positivo.", 422);
        }
        return $entero;
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos, string $prefijo = ''): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) {
            return;
        }
        $errores = [];
        foreach ($desconocidos as $campo) {
            $errores[$prefijo . $campo] = 'Campo no permitido.';
        }
        throw new TransportistaVehiculoHttpException('Revise los campos indicados.', 422, null, $errores);
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
