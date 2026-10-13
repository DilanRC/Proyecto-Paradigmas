<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Direccion;
use Application\Model\Productor;
use Application\Model\ProductorDireccion;
use Application\Model\ProductorFinca;
use Application\Service\ProductorDireccionService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/**
 * Dirección principal de un productor (api/v1/productores/direccion): consultar, crear la
 * que falta, cambiarla y vaciarla. El histórico de periodos lo lleva ProductorDireccionService;
 * aquí se valida la petición, se abre la transacción y se arma la respuesta.
 */
final class ProductorDireccionController
{
    private Productor $productor;
    private ProductorDireccion $direccion;
    private Bitacora $bitacora;
    private ProductorDireccionService $direccionService;
    private ValidacionService $validacion;
    private string $solicitudId;

    public function __construct(private PDO $conexion, ?string $solicitudId = null, ?ActorContext $actor = null)
    {
        $this->productor = new Productor($conexion, new ProductorFinca($conexion));
        $this->direccion = new ProductorDireccion($conexion, new Direccion($conexion));
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
        $this->direccionService = new ProductorDireccionService($this->direccion, $this->bitacora, $this->solicitudId);
        $this->validacion = new ValidacionService();
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST' => $this->crear($cuerpo),
                'PUT' => $this->actualizar($cuerpo),
                'DELETE' => $this->vaciar($cuerpo),
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

    private function consultar(array $consulta): array
    {
        if (!array_key_exists('identificacionNumero', $consulta)) {
            throw new HttpException('Debe indicar identificacionNumero.', 422, null, [
                'identificacionNumero' => 'El parámetro es obligatorio.',
            ]);
        }
        $identificacion = $this->validacion->normalizarIdentificacion(
            $this->textoConsulta($consulta['identificacionNumero'], 250)
        );
        if ($identificacion === '') {
            throw new HttpException('La identificación no es válida.', 422);
        }
        $productor = $this->productor->buscar($identificacion);
        if ($productor === null) {
            throw new HttpException('Productor no encontrado.', 404);
        }
        $productorId = (int) $productor['productorId'];
        $direccion = $this->direccion->buscar($productorId);
        if ($direccion === null) {
            throw new HttpException('El productor no tiene una dirección registrada.', 404);
        }

        return $this->respuesta(true, 'Dirección consultada correctamente.', [
            'identificacionNumero' => $identificacion,
            'direccionPrincipal' => $direccion,
        ]);
    }

    /**
     * Ruta de reparación: crea la dirección de un productor que quedó sin fila
     * por datos heredados o corrupción previa. El alta normal siempre crea el
     * enlace de dirección: con los datos recibidos en POST o vacío para clientes
     * heredados que todavía no envían direccionPrincipal.
     */
    private function crear(array $cuerpo): array
    {
        $validada = $this->validar(fn (): array => $this->validacion->validarIdentificacionYDireccion($cuerpo, 'direccionPrincipal'));
        $identificacion = $validada['identificacionNumero'];
        $direccion = $validada['direccion'];

        $nuevo = $this->direccion->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($identificacion, $direccion): array {
                $bloqueado = $this->productor->bloquear($identificacion);
                if ($bloqueado === null) {
                    throw new HttpException('Productor no encontrado.', 404);
                }
                $productorId = (int) $bloqueado['tbproductorid'];
                try {
                    $this->direccion->crear($productorId, $direccion);
                } catch (\RuntimeException $excepcion) {
                    throw new HttpException($excepcion->getMessage(), 409);
                }
                $nuevo = $this->productor->buscar($identificacion);
                $this->bitacora->registrar('CREAR_DIRECCION', $identificacion, null, $nuevo, $this->solicitudId);
                return $nuevo ?? throw new \RuntimeException('No fue posible leer el productor tras crear la dirección.');
            }),
        );

        return $this->respuesta(true, 'Dirección creada correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $validada = $this->validar(fn (): array => $this->validacion->validarIdentificacionYDireccion($cuerpo, 'direccionPrincipal'));
        $identificacion = $validada['identificacionNumero'];
        $direccion = $validada['direccion'];

        $resultado = $this->transaccion(function () use ($identificacion, $direccion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException(
                    'El productor está inactivo. Debe reactivarlo antes de actualizar su dirección.',
                    409,
                );
            }
            $productorId = (int) $bloqueado['tbproductorid'];
            $anterior = $this->direccion->buscar($productorId);
            if ($anterior === null) {
                throw new HttpException(
                    'El productor no tiene una dirección registrada; use POST para crearla.',
                    404,
                );
            }

            try {
                $nueva = $this->direccionService->cambiar($productorId, $identificacion, $anterior, $direccion);
            } catch (\RuntimeException $excepcion) {
                throw new HttpException($excepcion->getMessage(), 409);
            }

            return ['identificacionNumero' => $identificacion, 'direccionPrincipal' => $nueva];
        });

        return $this->respuesta(true, 'Dirección actualizada correctamente.', $resultado);
    }

    private function vaciar(array $cuerpo): array
    {
        $identificacion = $this->validar(fn (): string => $this->validacion->validarIdentificacionUnica($cuerpo));

        $resultado = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException(
                    'El productor está inactivo. Debe reactivarlo antes de modificar su dirección.',
                    409,
                );
            }
            $productorId = (int) $bloqueado['tbproductorid'];
            $anterior = $this->direccion->buscar($productorId);
            if ($anterior === null) {
                throw new HttpException(
                    'El productor no tiene una dirección registrada; no hay nada que eliminar.',
                    404,
                );
            }
            try {
                $nueva = $this->direccionService->vaciar($productorId, $identificacion, $anterior);
            } catch (\RuntimeException $excepcion) {
                throw new HttpException($excepcion->getMessage(), 409);
            }

            return ['identificacionNumero' => $identificacion, 'direccionPrincipal' => $nueva];
        });

        return $this->respuesta(true, 'Dirección vaciada correctamente.', $resultado);
    }

    /** @param callable(): mixed $regla */
    private function validar(callable $regla): mixed
    {
        try {
            return $regla();
        } catch (ValidacionException $excepcion) {
            throw new HttpException($excepcion->getMessage(), 422, null, $excepcion->errores);
        }
    }

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new HttpException("La búsqueda no es válida: escriba texto de hasta {$maximo} caracteres.", 422);
        }
        return trim($valor);
    }

    /** La bitácora queda bloqueada hasta el COMMIT: su id sale de MAX()+1 (MEMORIA.md, cuidado #12). */
    private function transaccion(callable $operacion): mixed
    {
        return $this->bitacora->ejecutarConBloqueoAlta(function () use ($operacion): mixed {
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
        });
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
