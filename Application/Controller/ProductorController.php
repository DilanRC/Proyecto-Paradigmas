<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorDireccion;
use Application\Model\ProductorEstadoPeriodo;
use Application\Model\ProductorFinca;
use Application\Model\Direccion;
use Application\Service\ProductorDireccionService;
use Application\Service\ProductorEstadoService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

final class ProductorController
{
    private Productor $productor;
    private ProductorDireccion $direccion;
    private ProductorEstadoPeriodo $estadoPeriodos;
    private ProductorFinca $fincas;
    private Bitacora $bitacora;
    private ProductorEstadoService $estadoService;
    private ProductorDireccionService $direccionService;
    private ValidacionService $validacion;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);
        $this->direccion = new ProductorDireccion($conexion, new Direccion($conexion));
        $this->estadoPeriodos = new ProductorEstadoPeriodo($conexion);
        $this->bitacora = new Bitacora($conexion);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
        $this->estadoService = new ProductorEstadoService($this->estadoPeriodos, $this->productor, $this->bitacora, $this->solicitudId);
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
                'DELETE' => $this->desactivar($cuerpo),
                'PATCH' => $this->reactivar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (\Application\Model\PersonaConflictException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), null, 409, [
                'identificacion.numero' => $excepcion->getMessage(),
            ]);
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
            return $this->respuesta(true, 'Productor consultado correctamente.', $productor);
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
        $resultado = $this->productor->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['catalogos'] = ['tiposIdentificacion' => $this->validacion->tiposIdentificacion()];

        return $this->respuesta(true, 'Productores consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarCuerpoProductor($cuerpo, false);
        $nuevo = $this->productor->ejecutarConBloqueoAlta(
            fn (): array => $this->direccion->ejecutarConBloqueoAlta(
                fn (): array => $this->fincas->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($datos): array {
                        $existente = $this->productor->buscar($datos['identificacionNumero']);
                        if ($existente !== null) {
                            $inactivo = $existente['estado'] === 'INACTIVO';
                            throw new HttpException(
                                $inactivo
                                    ? 'La identificación pertenece a un productor inactivo.'
                                    : 'La identificación ya está registrada.',
                                409,
                                $inactivo
                                    ? ['reactivacion' => ['identificacionNumero' => $datos['identificacionNumero']]]
                                    : null,
                                ['identificacion.numero' => $inactivo
                                    ? 'Debe reactivarse el productor existente.'
                                    : 'El número de identificación ya existe.'],
                            );
                        }
                        $productorId = $this->productor->crear($datos);
                        if ($datos['direccion'] === null) {
                            $this->direccion->crearVacia($productorId);
                        } else {
                            $this->direccion->crear($productorId, $datos['direccion']);
                        }
                        $this->fincas->sincronizar($productorId, $datos['fincas']);
                        $this->estadoPeriodos->ejecutarConBloqueo(
                            $productorId,
                            fn (): int => $this->estadoPeriodos->abrir($productorId, 1, 'Alta del productor'),
                        );
                        $nuevo = $this->productor->buscar($datos['identificacionNumero']);
                        if ($nuevo === null) {
                            throw new \RuntimeException('No fue posible leer el productor recién creado.');
                        }
                        $this->bitacora->registrar(
                            'CREAR',
                            $datos['identificacionNumero'],
                            null,
                            $nuevo,
                            $this->solicitudId,
                        );
                        return $nuevo;
                    }),
                ),
            ),
        );

        return $this->respuesta(true, 'Productor creado correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarCuerpoProductor($cuerpo, true);
        $identificacion = $datos['identificacionNumeroOriginal'];
        if ($datos['identificacionNumero'] !== $identificacion) {
            throw new HttpException('La identificación es inmutable y no puede modificarse.', 422, null, [
                'identificacion.numero' => 'Cree otro registro si la identificación fue digitada incorrectamente.',
            ]);
        }

        $nuevo = $this->fincas->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos, $identificacion): array {
                $bloqueado = $this->productor->bloquear($identificacion);
                if ($bloqueado === null) {
                    throw new HttpException('Productor no encontrado.', 404);
                }
                if ((int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                    throw new HttpException(
                        'El productor está inactivo. Debe reactivarlo antes de actualizarlo.',
                        409,
                    );
                }
                $anterior = $this->productor->buscar($identificacion);
                if ($anterior === null) {
                    throw new HttpException('El productor no conserva su dirección obligatoria.', 409);
                }
                $this->productor->actualizar($identificacion, $datos);
                $productorId = (int) $bloqueado['tbproductorid'];
                $direccionAnterior = $this->direccion->buscar($productorId);
                $this->direccionService->cambiar(
                    $productorId,
                    $identificacion,
                    $direccionAnterior,
                    $datos['direccion'],
                );
                $this->fincas->sincronizar($productorId, $datos['fincas']);
                $nuevo = $this->productor->buscar($identificacion);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el productor actualizado.');
                }
                $this->bitacora->registrar('ACTUALIZAR', $identificacion, $anterior, $nuevo, $this->solicitudId);
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Productor actualizado correctamente.', $nuevo);
    }

    /**
     * Ruta de reparación: crea la dirección de un productor que quedó sin fila
     * por datos heredados o corrupción previa. El alta normal siempre crea el
     * enlace de dirección: con los datos recibidos en POST o vacío para clientes
     * heredados que todavía no envían direccionPrincipal.
     */
    public function crearDireccion(array $cuerpo): array
    {
        try {
            $validada = $this->validarIdentificacionYDireccion($cuerpo, 'direccionPrincipal');
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

    public function procesarDireccion(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultarDireccion($consulta),
                'POST' => $this->crearDireccion($cuerpo),
                'PUT' => $this->actualizarDireccion($cuerpo),
                'DELETE' => $this->eliminarDireccion($cuerpo),
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

    private function desactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException('La persona está inactiva y no puede operar capacidades.', 409);
            }
            $this->estadoService->transicionar((int) $bloqueado['tbproductorid'], 0, 'Desactivación', $identificacion);

            return $this->productor->buscar($identificacion)
                ?? throw new \RuntimeException('No fue posible leer el productor desactivado.');
        });

        return $this->respuesta(true, 'Productor desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new HttpException('La persona está inactiva y no puede reactivar capacidades.', 409);
            }
            $this->estadoService->transicionar((int) $bloqueado['tbproductorid'], 1, 'Reactivación', $identificacion);

            return $this->productor->buscar($identificacion)
                ?? throw new \RuntimeException('No fue posible leer el productor reactivado.');
        });

        return $this->respuesta(true, 'Productor reactivado correctamente.', $nuevo);
    }

    private function validarCuerpoProductor(array $cuerpo, bool $actualizacion): array
    {
        try {
            return $this->validacion->validarProductor($cuerpo, $actualizacion)['datos'];
        } catch (ValidacionException $excepcion) {
            throw new HttpException(
                $excepcion->getMessage(),
                422,
                null,
                $excepcion->errores
            );
        }
    }

    private function consultarDireccion(array $consulta): array
    {
        if (!array_key_exists('identificacionNumero', $consulta)) {
            throw new HttpException('Debe indicar identificacionNumero.', 422, null, [
                'identificacionNumero' => 'El parámetro es obligatorio.',
            ]);
        }
        $identificacion = $this->normalizarIdentificacion(
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

    private function actualizarDireccion(array $cuerpo): array
    {
        $validada = $this->validarIdentificacionYDireccion($cuerpo, 'direccionPrincipal');
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

    private function eliminarDireccion(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);

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

    private function validarIdentificacionUnica(array $cuerpo): string
    {
        try {
            return $this->validacion->validarIdentificacionUnica($cuerpo);
        } catch (ValidacionException $excepcion) {
            throw new HttpException(
                $excepcion->getMessage(),
                422,
                null,
                $excepcion->errores
            );
        }
    }

    private function validarIdentificacionYDireccion(array $cuerpo, string $campo): array
    {
        try {
            return $this->validacion->validarIdentificacionYDireccion($cuerpo, $campo);
        } catch (ValidacionException $excepcion) {
            throw new HttpException(
                $excepcion->getMessage(),
                422,
                null,
                $excepcion->errores
            );
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
