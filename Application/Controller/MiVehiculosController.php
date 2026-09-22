<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use Application\Model\Vehiculo;
use PDO;
use Throwable;

final class MiVehiculosController
{
    private Transportista $transportista;
    private TransportistaVehiculo $enlace;
    private Vehiculo $vehiculo;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->enlace = new TransportistaVehiculo($conexion);
        $this->transportista = new Transportista($conexion, $this->enlace);
        $this->vehiculo = new Vehiculo($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesar(string $metodo, array $cuerpo = []): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar(),
                'POST' => $this->crear($cuerpo),
                'PUT' => $this->actualizar($cuerpo),
                'DELETE' => $this->cambiarEstado($cuerpo, false),
                'PATCH' => $this->cambiarEstado($cuerpo, true),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function consultar(): array
    {
        $transportista = $this->transportistaAutenticado();
        return $this->respuesta(true, 'Vehículos propios consultados correctamente.', [
            'transportistaId' => $transportista['transportistaId'],
            'estadoTransportista' => $transportista['estado'],
            'escrituraDisponible' => $transportista['estado'] === 'ACTIVO',
            'vehiculos' => $transportista['vehiculos'],
        ]);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarVehiculo($cuerpo);
        $transportista = $this->transportistaAutenticado();
        $this->exigirEscritura($transportista);
        $resultado = $this->enlace->ejecutarConBloqueoAlta(
            fn (): array => $this->vehiculo->ejecutarConBloqueoAlta(
                fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($transportista, $datos): array {
                        $bloqueado = $this->transportista->bloquear($transportista['identificacionNumero']);
                        $this->validarTransportistaBloqueado($bloqueado, (int) $transportista['transportistaId']);
                        $vehiculoId = $this->vehiculo->crear($datos);
                        $this->enlace->asignar((int) $transportista['transportistaId'], $vehiculoId);
                        $nuevo = $this->vehiculo->buscarPorId($vehiculoId);
                        if ($nuevo === null) throw new \RuntimeException('No fue posible leer el vehículo recién creado.');
                        $this->bitacora->registrar(
                            'CREAR', (string) $vehiculoId, null, $nuevo, $this->solicitudId,
                            entidad: 'VEHICULO', origen: 'API_MI_VEHICULOS',
                        );
                        return ['vehiculo' => $nuevo, 'vehiculos' => $this->enlace->listarVehiculosPorTransportista((int) $transportista['transportistaId'])];
                    }),
                ),
            ),
        );

        return $this->respuesta(true, 'Vehículo agregado correctamente.', $resultado, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['vehiculoId', 'placa', 'vin', 'modelo']);
        $datos = $this->validarVehiculo($cuerpo, true);
        $transportista = $this->transportistaAutenticado();
        $this->exigirEscritura($transportista);
        $id = $datos['vehiculoId'];

        $nuevo = $this->enlace->ejecutarConBloqueoAlta(
            fn (): array => $this->vehiculo->ejecutarConBloqueoAlta(
                fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($transportista, $datos, $id): array {
                        $bloqueado = $this->transportista->bloquear($transportista['identificacionNumero']);
                        $this->validarTransportistaBloqueado($bloqueado, (int) $transportista['transportistaId']);
                        $anterior = $this->vehiculoPropioBloqueado($id, (int) $transportista['transportistaId']);
                        if ((int) $anterior['tbvehiculoestado'] !== 1) {
                            throw new HttpException('El vehículo está inactivo. Debe reactivarlo antes de actualizarlo.', 409);
                        }
                        $anteriorMapeado = $this->vehiculo->buscarPorId($id);
                        $this->vehiculo->actualizar($id, $datos);
                        $actualizado = $this->vehiculo->buscarPorId($id);
                        if ($actualizado === null) throw new \RuntimeException('No fue posible leer el vehículo actualizado.');
                        $this->bitacora->registrar(
                            'ACTUALIZAR', (string) $id, $anteriorMapeado, $actualizado, $this->solicitudId,
                            entidad: 'VEHICULO', origen: 'API_MI_VEHICULOS',
                        );
                        return ['vehiculo' => $actualizado, 'vehiculos' => $this->enlace->listarVehiculosPorTransportista((int) $transportista['transportistaId'])];
                    }),
                ),
            ),
        );

        return $this->respuesta(true, 'Vehículo actualizado correctamente.', $nuevo);
    }

    private function cambiarEstado(array $cuerpo, bool $activo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['vehiculoId']);
        $errores = [];
        $id = $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $transportista = $this->transportistaAutenticado();
        $this->exigirEscritura($transportista);

        $resultado = $this->enlace->ejecutarConBloqueoAlta(
            fn (): array => $this->vehiculo->ejecutarConBloqueoAlta(
                fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($transportista, $id, $activo): array {
                        $bloqueado = $this->transportista->bloquear($transportista['identificacionNumero']);
                        $this->validarTransportistaBloqueado($bloqueado, (int) $transportista['transportistaId']);
                        $anterior = $this->vehiculoPropioBloqueado($id, (int) $transportista['transportistaId']);
                        $anteriorMapeado = $this->vehiculo->buscarPorId($id);
                        $this->vehiculo->cambiarEstado($id, $activo);
                        $actualizado = $this->vehiculo->buscarPorId($id);
                        $this->bitacora->registrar(
                            $activo ? 'REACTIVAR' : 'DESACTIVAR', (string) $id,
                            $anteriorMapeado, $actualizado, $this->solicitudId,
                            entidad: 'VEHICULO', origen: 'API_MI_VEHICULOS',
                        );
                        return ['vehiculo' => $actualizado, 'vehiculos' => $this->enlace->listarVehiculosPorTransportista((int) $transportista['transportistaId'])];
                    }),
                ),
            ),
        );

        return $this->respuesta(true, $activo ? 'Vehículo reactivado correctamente.' : 'Vehículo desactivado correctamente.', $resultado);
    }

    private function transportistaAutenticado(): array
    {
        if (!$this->actor->tienePersona()) throw new HttpException('Debe iniciar sesión para administrar sus vehículos.', 401);
        $transportista = $this->transportista->buscarPorPersonaId((int) $this->actor->personaId);
        if ($transportista === null) throw new HttpException('Configure la actividad Transportista antes de administrar vehículos.', 409);
        return $transportista;
    }

    private function exigirEscritura(array $transportista): void
    {
        if ($transportista['estado'] !== 'ACTIVO') {
            throw new HttpException('Reactive la actividad Transportista antes de modificar vehículos.', 409);
        }
    }

    private function validarTransportistaBloqueado(?array $bloqueado, int $transportistaId): void
    {
        if ($bloqueado === null || (int) $bloqueado['tbtransportistaid'] !== $transportistaId) {
            throw new HttpException('La actividad Transportista ya no está disponible.', 409);
        }
        if ((int) $bloqueado['tbtransportistaestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
            throw new HttpException('Reactive la actividad Transportista antes de modificar vehículos.', 409);
        }
    }

    private function vehiculoPropioBloqueado(int $id, int $transportistaId): array
    {
        $vehiculo = $this->vehiculo->bloquearPorId($id);
        $dueño = $this->enlace->buscarTransportistaDeVehiculo($id);
        if ($vehiculo === null || $dueño !== $transportistaId) {
            throw new HttpException('El vehículo no existe en tu cuenta.', 404);
        }
        return $vehiculo;
    }

    private function validarVehiculo(array $cuerpo, bool $actualizacion = false): array
    {
        $permitidos = $actualizacion ? ['vehiculoId', 'placa', 'vin', 'modelo'] : ['placa', 'vin', 'modelo'];
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];
        $resultado = [
            'placa' => $this->textoCampo($cuerpo['placa'] ?? null, 'placa', 20, $errores, 1),
            'vin' => $this->textoCampo($cuerpo['vin'] ?? null, 'vin', 50, $errores, 1),
            'modelo' => $this->textoCampo($cuerpo['modelo'] ?? null, 'modelo', 100, $errores, 1),
        ];
        if ($actualizacion) $resultado['vehiculoId'] = $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        return $resultado;
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) return;
        $errores = [];
        foreach ($desconocidos as $campo) $errores[$campo] = 'Campo no permitido.';
        throw new HttpException('Revise los campos indicados.', 422, null, $errores);
    }

    private function textoCampo(mixed $valor, string $campo, int $maximo, array &$errores, int $minimo): string
    {
        if (!is_string($valor)) {
            $errores[$campo] = 'El campo es obligatorio.';
            return '';
        }
        $texto = preg_replace('/\s+/u', ' ', trim($valor)) ?? trim($valor);
        $longitud = mb_strlen($texto);
        if ($longitud < $minimo || $longitud > $maximo) $errores[$campo] = "Debe contener entre {$minimo} y {$maximo} caracteres.";
        return $texto;
    }

    private function enteroCampo(mixed $valor, string $campo, array &$errores): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $errores[$campo] = 'Debe ser un entero positivo.';
            return 0;
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
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $excepcion;
        }
    }

    private function normalizarSolicitudId(?string $valor): string
    {
        $valor = trim((string) $valor);
        return $valor !== '' && strlen($valor) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $valor)
            ? $valor : 'REQ-' . bin2hex(random_bytes(16));
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
