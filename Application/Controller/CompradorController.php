<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Service\CompradorClasificacionService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/**
 * Contexto Comprador sobre una Persona.
 *
 * Comprador no es un rol que se administre a mano: es un contexto de negocio
 * que la persona adopta al inscribirse. POST declara el contexto (idempotente),
 * DELETE lo desactiva, PATCH lo reactiva y GET lo consulta desde
 * tbcomprador + tbpersona. No existe PUT: los datos personales se editan desde
 * la capacidad que corresponda sobre la misma tbpersona.
 */
final class CompradorController
{
    private Comprador $comprador;
    private CompradorClasificacionService $clasificacion;
    private Bitacora $bitacora;
    private ValidacionService $validacion;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        ?string $solicitudId = null,
        ?ActorContext $actor = null,
    ) {
        $this->comprador = new Comprador($conexion);
        $this->clasificacion = new CompradorClasificacionService($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
        $this->validacion = new ValidacionService();
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST' => $this->inscribir($cuerpo),
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
                $excepcion->errores,
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
            $comprador['fuente'] = 'tbcomprador + tbpersona';

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
        $resultado['fuente'] = 'tbcomprador + tbpersona';
        $resultado['catalogos'] = ['tiposIdentificacion' => $this->validacion->tiposIdentificacion()];

        return $this->respuesta(true, 'Compradores consultados correctamente.', $resultado);
    }

    private function inscribir(array $cuerpo): array
    {
        $datos = $this->validarCuerpoPersona($cuerpo);
        $estadoInscripcion = $this->comprador->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos): array {
                $estado = $this->clasificacion->inscribir($datos);
                if ($estado['cambio']) {
                    $this->bitacora->registrar(
                        $estado['nuevo'] ? 'INSCRIBIR' : 'REACTIVAR',
                        $datos['identificacionNumero'],
                        null,
                        $estado['comprador'],
                        $this->solicitudId,
                        entidad: 'COMPRADOR',
                        origen: 'API_COMPRADORES',
                    );
                }

                return $estado;
            }),
        );
        if (!$estadoInscripcion['cambio']) {
            return $this->respuesta(true, 'La persona ya es comprador.', $estadoInscripcion['comprador'], 200);
        }

        return $this->respuesta(
            true,
            $estadoInscripcion['nuevo'] ? 'Comprador inscrito correctamente.' : 'Comprador reactivado correctamente.',
            $estadoInscripcion['comprador'],
            201,
        );
    }

    private function desactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $comprador = $this->comprador->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($identificacion): array {
                $estado = $this->clasificacion->desactivar($identificacion);
                if ($estado === null) {
                    throw new HttpException('Comprador no encontrado.', 404);
                }
                if ($estado['cambio']) {
                    $this->bitacora->registrar(
                        'DESACTIVAR',
                        $identificacion,
                        null,
                        $estado['comprador'],
                        $this->solicitudId,
                        entidad: 'COMPRADOR',
                        origen: 'API_COMPRADORES',
                    );
                }

                return $estado['comprador'];
            }),
        );

        return $this->respuesta(true, 'Comprador desactivado correctamente.', $comprador);
    }

    private function reactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $comprador = $this->comprador->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($identificacion): array {
                $estado = $this->clasificacion->reactivar($identificacion);
                if ($estado === null) {
                    throw new HttpException('Comprador no encontrado.', 404);
                }
                if ($estado['cambio']) {
                    $this->bitacora->registrar(
                        'REACTIVAR',
                        $identificacion,
                        null,
                        $estado['comprador'],
                        $this->solicitudId,
                        entidad: 'COMPRADOR',
                        origen: 'API_COMPRADORES',
                    );
                }

                return $estado['comprador'];
            }),
        );

        return $this->respuesta(true, 'Comprador reactivado correctamente.', $comprador);
    }

    private function validarCuerpoPersona(array $cuerpo): array
    {
        try {
            return $this->validacion->validarPersona($cuerpo, false)['datos'];
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

    private function respuesta(
        bool $exito,
        string $mensaje,
        ?array $datos,
        int $estado = 200,
        array $errores = [],
    ): array {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}