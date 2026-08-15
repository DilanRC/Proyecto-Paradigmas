<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\PagoMetodo;
use PDO;
use Throwable;

final class PagoMetodoHttpException extends \RuntimeException
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

final class PagoMetodoController
{
    private PagoMetodo $pagoMetodo;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->pagoMetodo = new PagoMetodo($conexion);
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
        } catch (PagoMetodoHttpException $excepcion) {
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
        if (array_key_exists('id', $consulta)) {
            $id = $this->enteroConsulta($consulta['id'], 'id');
            $pagoMetodo = $this->pagoMetodo->buscarPorId($id);
            if ($pagoMetodo === null) {
                throw new PagoMetodoHttpException('Método de pago no encontrado.', 404);
            }
            return $this->respuesta(true, 'Método de pago consultado correctamente.', $pagoMetodo);
        }

        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'TODOS', 10), 'UTF-8');
        if (!in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO'], true)) {
            throw new PagoMetodoHttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use TODOS, ACTIVO o INACTIVO.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta) ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new PagoMetodoHttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->pagoMetodo->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Métodos de pago consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarPagoMetodo($cuerpo, false);
        $nuevo = $this->pagoMetodo->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos): array {
                $this->pagoMetodo->crear($datos);
                // Necesitamos el ID recién creado para buscarlo
                // Como no tenemos el ID directamente, usamos una consulta rápida
                $sentencia = $this->conexion->prepare('SELECT MAX(tbpagometodoid) FROM tbpagometodo');
                $sentencia->execute();
                $nuevoId = (int) $sentencia->fetchColumn();
                
                $nuevo = $this->pagoMetodo->buscarPorId($nuevoId);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el método de pago recién creado.');
                }
                $this->bitacora->registrar(
                    'CREAR',
                    (string) $nuevoId,
                    null,
                    $nuevo,
                    $this->solicitudId,
                    entidad: 'PAGOMETODO',
                    origen: 'API_PAGOMETODOS',
                );
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Método de pago creado correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarPagoMetodo($cuerpo, true);
        $id = $datos['id'];

        $nuevo = $this->transaccion(function () use ($datos, $id): array {
            $bloqueado = $this->pagoMetodo->bloquearPorId($id);
            if ($bloqueado === null) {
                throw new PagoMetodoHttpException('Método de pago no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpagometodoactivo'] !== 1) {
                throw new PagoMetodoHttpException(
                    'El método de pago está inactivo. Debe reactivarlo antes de actualizarlo.',
                    409,
                );
            }
            $anterior = $this->pagoMetodo->buscarPorId($id);
            $this->pagoMetodo->actualizar($id, $datos);
            $nuevo = $this->pagoMetodo->buscarPorId($id);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el método de pago actualizado.');
            }
            $this->bitacora->registrar(
                'ACTUALIZAR',
                (string) $id,
                $anterior,
                $nuevo,
                $this->solicitudId,
                entidad: 'PAGOMETODO',
                origen: 'API_PAGOMETODOS',
            );
            return $nuevo;
        });

        return $this->respuesta(true, 'Método de pago actualizado correctamente.', $nuevo);
    }

    private function desactivar(array $cuerpo): array
    {
        $id = $this->validarIdUnico($cuerpo);
        $nuevo = $this->transaccion(function () use ($id): array {
            $bloqueado = $this->pagoMetodo->bloquearPorId($id);
            $anterior = $this->pagoMetodo->buscarPorId($id);
            if ($bloqueado === null || $anterior === null) {
                throw new PagoMetodoHttpException('Método de pago no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpagometodoactivo'] === 0) {
                return $anterior;
            }
            $this->pagoMetodo->cambiarEstado($id, false);
            $nuevo = $this->pagoMetodo->buscarPorId($id);
            $this->bitacora->registrar(
                'DESACTIVAR',
                (string) $id,
                $anterior,
                $nuevo,
                $this->solicitudId,
                entidad: 'PAGOMETODO',
                origen: 'API_PAGOMETODOS',
            );
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el método de pago desactivado.');
        });

        return $this->respuesta(true, 'Método de pago desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $id = $this->validarIdUnico($cuerpo);
        $nuevo = $this->transaccion(function () use ($id): array {
            $bloqueado = $this->pagoMetodo->bloquearPorId($id);
            $anterior = $this->pagoMetodo->buscarPorId($id);
            if ($bloqueado === null || $anterior === null) {
                throw new PagoMetodoHttpException('Método de pago no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpagometodoactivo'] === 1) {
                return $anterior;
            }
            $this->pagoMetodo->cambiarEstado($id, true);
            $nuevo = $this->pagoMetodo->buscarPorId($id);
            $this->bitacora->registrar(
                'REACTIVAR',
                (string) $id,
                $anterior,
                $nuevo,
                $this->solicitudId,
                entidad: 'PAGOMETODO',
                origen: 'API_PAGOMETODOS',
            );
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el método de pago reactivado.');
        });

        return $this->respuesta(true, 'Método de pago reactivado correctamente.', $nuevo);
    }

    private function validarPagoMetodo(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['nombre', 'descripcion', 'activo'];
        if ($actualizacion) {
            $permitidos[] = 'id';
        }
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];
        
        $id = null;
        if ($actualizacion) {
            $id = $this->enteroCampo($cuerpo['id'] ?? null, 'id', $errores);
        }
        
        $nombre = $this->textoCampo($cuerpo['nombre'] ?? null, 'nombre', 100, $errores, 1);
        $descripcion = $this->textoCampo($cuerpo['descripcion'] ?? null, 'descripcion', 250, $errores, 1);
        $activo = $this->validarActivo($cuerpo['activo'] ?? null, $errores, !$actualizacion);

        if ($errores !== []) {
            throw new PagoMetodoHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => $activo,
        ];
        
        if ($actualizacion) {
            $resultado['id'] = $id;
        }

        return $resultado;
    }

    private function validarActivo(mixed $valor, array &$errores, bool $requerido): bool
    {
        if ($valor === null) {
            if ($requerido) {
                $errores['activo'] = 'El campo activo es obligatorio.';
            }
            return true; // Por defecto activo al crear
        }
        if (!is_bool($valor)) {
            $errores['activo'] = 'El campo activo debe ser verdadero o falso.';
            return true;
        }
        return $valor;
    }

    private function validarIdUnico(array $cuerpo): int
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['id']);
        $errores = [];
        $id = $this->enteroCampo($cuerpo['id'] ?? null, 'id', $errores);
        if ($errores !== []) {
            throw new PagoMetodoHttpException('Revise los campos indicados.', 422, null, $errores);
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
            throw new PagoMetodoHttpException('La consulta no es válida.', 422);
        }
        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new PagoMetodoHttpException("{$campo} debe ser un entero positivo.", 422);
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
        throw new PagoMetodoHttpException('Revise los campos indicados.', 422, null, $errores);
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