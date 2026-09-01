<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Service\CompradorClasificacionService;
use Application\Service\EstadoService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

final class CompradorController
{
    private Comprador $comprador;
    private Bitacora $bitacora;
    private ValidacionService $validacion;
    private EstadoService $estadoService;
    private CompradorClasificacionService $clasificacion;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null, ?ActorContext $actor = null)
    {
        $this->comprador = new Comprador($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
        $this->validacion = new ValidacionService();
        $this->estadoService = new EstadoService($this->bitacora, $this->solicitudId);
        $this->clasificacion = new CompradorClasificacionService($conexion);
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
            $identificacion = $this->normalizarIdentificacion(
                $this->textoConsulta($consulta['identificacionNumero'], 250)
            );
            if ($identificacion === '') {
                throw new HttpException('La identificación no es válida.', 422);
            }
            $comprador = $this->comprador->buscar($identificacion);
            if ($comprador === null) {
                throw new HttpException('Comprador no encontrado.', 404);
            }
            return $this->respuesta(true, 'Comprador consultado correctamente.', $comprador);
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
        $resultado = $this->comprador->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['catalogos'] = ['tiposIdentificacion' => $this->validacion->tiposIdentificacion()];

        return $this->respuesta(true, 'Compradores consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarCuerpoPersona($cuerpo, false);
        $nuevo = $this->comprador->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos): array {
                $existente = $this->comprador->buscar($datos['identificacionNumero']);
                if ($existente !== null) {
                    $inactivo = $existente['estado'] === 'INACTIVO';
                    throw new HttpException(
                        $inactivo
                            ? 'La identificación pertenece a un comprador inactivo.'
                            : 'La identificación ya está registrada.',
                        409,
                        $inactivo
                            ? ['reactivacion' => ['identificacionNumero' => $datos['identificacionNumero']]]
                            : null,
                        ['identificacion.numero' => $inactivo
                            ? 'Debe reactivarse el comprador existente.'
                            : 'El número de identificación ya existe.'],
                    );
                }
                $this->comprador->crear($datos);
                // Paso (b): el alta abre la clasificación COMPRADOR del
                // productor. Si la persona no es productora, esto lanza 409 y
                // la transacción revierte también la fila legacy: no se crea un
                // comprador que la clasificación no pueda representar.
                $this->abrirClasificacion(
                    $datos['identificacionNumero'],
                    CompradorClasificacionService::MOTIVO_ALTA,
                );
                $nuevo = $this->comprador->buscar($datos['identificacionNumero']);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el comprador recién creado.');
                }
                $this->bitacora->registrar(
                    'CREAR',
                    $datos['identificacionNumero'],
                    null,
                    $nuevo,
                    $this->solicitudId,
                    entidad: 'COMPRADOR',
                    origen: 'API_COMPRADORES',
                );
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Comprador creado correctamente.', $nuevo, 201);
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
            $bloqueado = $this->comprador->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new HttpException('Comprador no encontrado.', 404);
            }
            $anterior = $this->comprador->buscar($identificacion);
            // El estado lo decide la clasificación (paso 5), no el bit legacy.
            if (($anterior['estado'] ?? 'INACTIVO') !== 'ACTIVO') {
                throw new HttpException(
                    'El comprador está inactivo. Debe reactivarlo antes de actualizarlo.',
                    409,
                );
            }
            $this->comprador->actualizar($identificacion, $datos);
            $nuevo = $this->comprador->buscar($identificacion);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el comprador actualizado.');
            }
            $this->bitacora->registrar(
                'ACTUALIZAR',
                $identificacion,
                $anterior,
                $nuevo,
                $this->solicitudId,
                entidad: 'COMPRADOR',
                origen: 'API_COMPRADORES',
            );
            return $nuevo;
        });

        return $this->respuesta(true, 'Comprador actualizado correctamente.', $nuevo);
    }

    private function desactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            // Cerrar primero la clasificación y después escribir el bit deja
            // ambos lados dentro de la misma transacción: si algo falla entre
            // medio, el ROLLBACK devuelve el periodo abierto y el bit intacto.
            $this->cerrarClasificacion($identificacion);

            return $this->estadoService->transicionar(
                fn ($clave) => $this->comprador->bloquear($clave),
                fn ($clave) => $this->comprador->buscar($clave),
                fn ($clave, $activo) => $this->comprador->cambiarEstado($clave, $activo),
                'tbcompradorestado',
                0,
                'Comprador no encontrado.',
                $identificacion,
                'COMPRADOR',
                'API_COMPRADORES',
                $identificacion,
            );
        });

        return $this->respuesta(true, 'Comprador desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            // Reactivar abre SIEMPRE un periodo nuevo: el anterior quedó
            // cerrado y no se reabre, porque dejó de ser comprador de verdad
            // en ese intervalo y esa historia no se toca.
            $this->abrirClasificacion($identificacion, CompradorClasificacionService::MOTIVO_REACTIVACION);

            return $this->estadoService->transicionar(
                fn ($clave) => $this->comprador->bloquear($clave),
                fn ($clave) => $this->comprador->buscar($clave),
                fn ($clave, $activo) => $this->comprador->cambiarEstado($clave, $activo),
                'tbcompradorestado',
                1,
                'Comprador no encontrado.',
                $identificacion,
                'COMPRADOR',
                'API_COMPRADORES',
                $identificacion,
            );
        });

        return $this->respuesta(true, 'Comprador reactivado correctamente.', $nuevo);
    }

    /**
     * Abre la clasificación COMPRADOR del productor de esa identificación.
     * Idempotente: si ya está abierta no crea otra. Exige productor existente
     * y nunca lo crea.
     */
    private function abrirClasificacion(string $identificacion, string $motivo): void
    {
        $bloqueado = $this->comprador->bloquear($identificacion);
        if ($bloqueado === null) {
            throw new HttpException('Comprador no encontrado.', 404);
        }
        $productorId = $this->clasificacion->exigirProductor(
            (int) $bloqueado['tbpersonaid'],
            $identificacion,
        );
        $this->clasificacion->activar($productorId, $motivo);
    }

    /**
     * Cierra la clasificación COMPRADOR si está abierta. Un comprador legacy
     * sin productor no tiene clasificación que cerrar: se deja pasar, y el
     * diagnóstico D-22 lo mantiene visible hasta que alguien lo resuelva.
     */
    private function cerrarClasificacion(string $identificacion): void
    {
        $bloqueado = $this->comprador->bloquear($identificacion);
        if ($bloqueado === null) {
            throw new HttpException('Comprador no encontrado.', 404);
        }
        $productorId = $this->clasificacion->productorDePersona((int) $bloqueado['tbpersonaid']);
        if ($productorId === null) {
            return;
        }
        $this->clasificacion->desactivar($productorId);
    }

    private function validarCuerpoPersona(array $cuerpo, bool $actualizacion): array
    {
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
