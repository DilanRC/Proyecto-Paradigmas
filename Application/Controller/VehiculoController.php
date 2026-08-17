<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\Vehiculo;
use PDO;
use Throwable;

final class VehiculoHttpException extends \RuntimeException
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

final class VehiculoController
{
    private Vehiculo $vehiculo;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->vehiculo = new Vehiculo($conexion);
        $this->bitacora = new Bitacora($conexion);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
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
        } catch (VehiculoHttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function consultar(array $consulta): array
    {
        if (array_key_exists('vehiculoId', $consulta)) {
            $id = $this->enteroConsulta($consulta['vehiculoId'], 'vehiculoId');
            $vehiculo = $this->vehiculo->buscarPorId($id);
            if ($vehiculo === null) {
                throw new VehiculoHttpException('Vehículo no encontrado.', 404);
            }
            return $this->respuesta(true, 'Vehículo consultado correctamente.', $vehiculo);
        }

        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'TODOS', 10), 'UTF-8');
        if (!in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO'], true)) {
            throw new VehiculoHttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use TODOS, ACTIVO o INACTIVO.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta) ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new VehiculoHttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->vehiculo->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Vehículos consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarVehiculo($cuerpo, false);
        $nuevo = $this->vehiculo->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos): array {
                $vehiculoId = $this->vehiculo->crear($datos);
                $nuevo = $this->vehiculo->buscarPorId($vehiculoId);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el vehículo recién creado.');
                }
                $this->bitacora->registrar(
                    'CREAR', (string) $vehiculoId, null, $nuevo, $this->solicitudId,
                    entidad: 'VEHICULO', origen: 'API_VEHICULOS',
                );
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Vehículo creado correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarVehiculo($cuerpo, true);
        $id = $datos['vehiculoId'];

        $nuevo = $this->transaccion(function () use ($datos, $id): array {
            $bloqueado = $this->vehiculo->bloquearPorId($id);
            if ($bloqueado === null) {
                throw new VehiculoHttpException('Vehículo no encontrado.', 404);
            }
            if ((int) $bloqueado['tbvehiculoestado'] !== 1) {
                throw new VehiculoHttpException(
                    'El vehículo está inactivo. Debe reactivarlo antes de actualizarlo.', 409,
                );
            }
            $anterior = $this->vehiculo->buscarPorId($id);
            $this->vehiculo->actualizar($id, $datos);
            $nuevo = $this->vehiculo->buscarPorId($id);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el vehículo actualizado.');
            }
            $this->bitacora->registrar(
                'ACTUALIZAR', (string) $id, $anterior, $nuevo, $this->solicitudId,
                entidad: 'VEHICULO', origen: 'API_VEHICULOS',
            );
            return $nuevo;
        });

        return $this->respuesta(true, 'Vehículo actualizado correctamente.', $nuevo);
    }

    private function desactivar(array $cuerpo): array
    {
        $id = $this->validarIdUnico($cuerpo);
        $nuevo = $this->transaccion(function () use ($id): array {
            $bloqueado = $this->vehiculo->bloquearPorId($id);
            $anterior = $this->vehiculo->buscarPorId($id);
            if ($bloqueado === null || $anterior === null) {
                throw new VehiculoHttpException('Vehículo no encontrado.', 404);
            }
            if ((int) $bloqueado['tbvehiculoestado'] === 0) {
                return $anterior;
            }
            $this->vehiculo->cambiarEstado($id, false);
            $nuevo = $this->vehiculo->buscarPorId($id);
            $this->bitacora->registrar(
                'DESACTIVAR', (string) $id, $anterior, $nuevo, $this->solicitudId,
                entidad: 'VEHICULO', origen: 'API_VEHICULOS',
            );
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el vehículo desactivado.');
        });

        return $this->respuesta(true, 'Vehículo desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $id = $this->validarIdUnico($cuerpo);
        $nuevo = $this->transaccion(function () use ($id): array {
            $bloqueado = $this->vehiculo->bloquearPorId($id);
            $anterior = $this->vehiculo->buscarPorId($id);
            if ($bloqueado === null || $anterior === null) {
                throw new VehiculoHttpException('Vehículo no encontrado.', 404);
            }
            if ((int) $bloqueado['tbvehiculoestado'] === 1) {
                return $anterior;
            }
            $this->vehiculo->cambiarEstado($id, true);
            $nuevo = $this->vehiculo->buscarPorId($id);
            $this->bitacora->registrar(
                'REACTIVAR', (string) $id, $anterior, $nuevo, $this->solicitudId,
                entidad: 'VEHICULO', origen: 'API_VEHICULOS',
            );
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el vehículo reactivado.');
        });

        return $this->respuesta(true, 'Vehículo reactivado correctamente.', $nuevo);
    }

    private function validarVehiculo(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['placa', 'vin', 'modelo'];
        if ($actualizacion) {
            $permitidos[] = 'vehiculoId';
        }
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];

        $vehiculoId = null;
        if ($actualizacion) {
            $vehiculoId = $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', $errores);
        }

        $placa = $this->textoCampo($cuerpo['placa'] ?? null, 'placa', 20, $errores, 1);
        $vin = $this->textoCampo($cuerpo['vin'] ?? null, 'vin', 50, $errores, 1);
        $modelo = $this->textoCampo($cuerpo['modelo'] ?? null, 'modelo', 100, $errores, 1);

        if ($errores !== []) {
            throw new VehiculoHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = ['placa' => $placa, 'vin' => $vin, 'modelo' => $modelo];
        if ($actualizacion) {
            $resultado['vehiculoId'] = $vehiculoId;
        }

        return $resultado;
    }

    private function validarIdUnico(array $cuerpo): int
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['vehiculoId']);
        $errores = [];
        $id = $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', $errores);
        if ($errores !== []) {
            throw new VehiculoHttpException('Revise los campos indicados.', 422, null, $errores);
        }
        return $id;
    }

    private function textoCampo(mixed $valor, string $campo, int $maximo, array &$errores, int $minimo = 0): string
    {
        if (!is_string($valor)) {
            $errores[$campo] = 'El campo es obligatorio.';
            return '';
        }
        $texto = trim($valor);
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
        $longitud = mb_strlen($texto);
        if ($longitud < $minimo || $longitud > $maximo) {
            $errores[$campo] = "Debe contener entre {$minimo} y {$maximo} caracteres.";
        }
        return $texto;
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

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new VehiculoHttpException('La consulta no es válida.', 422);
        }
        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new VehiculoHttpException("{$campo} debe ser un entero positivo.", 422);
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
        throw new VehiculoHttpException('Revise los campos indicados.', 422, null, $errores);
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