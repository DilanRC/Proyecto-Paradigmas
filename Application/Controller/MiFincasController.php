<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Direccion;
use Application\Model\FincaDireccion;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/** Autogestión de las fincas del Productor vinculado a la sesión. */
final class MiFincasController
{
    private ProductorFinca $fincas;
    private Productor $productor;
    private FincaDireccion $direccionFinca;
    private Bitacora $bitacora;
    private ValidacionService $validacion;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);
        $this->direccionFinca = new FincaDireccion($conexion, new Direccion($conexion));
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->validacion = new ValidacionService();
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
        $productor = $this->productorAutenticado();
        return $this->respuesta(true, 'Fincas propias consultadas correctamente.', [
            'productorId' => (int) $productor['productorId'],
            'estadoProductor' => $productor['estado'],
            'escrituraDisponible' => $productor['estado'] === 'ACTIVO',
            'fincas' => $this->listar((int) $productor['productorId']),
        ]);
    }

    private function crear(array $cuerpo): array
    {
        [$nombre, $direccion, $direccionEnviada] = $this->validarDatos($cuerpo, false);
        $productor = $this->productorAutenticado();
        $this->exigirEscritura($productor);
        $resultado = $this->fincas->ejecutarConBloqueoAlta(
            fn (): array => $this->direccionFinca->ejecutarConBloqueoAlta(
                fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($productor, $nombre, $direccion, $direccionEnviada): array {
                        $bloqueado = $this->bloquearProductorActivo($productor);
                        $productorId = (int) $bloqueado['tbproductorid'];
                        $fincaId = $this->fincas->buscarIdPorNombre($productorId, $nombre);
                        if ($fincaId !== null) {
                            $existente = $this->fincas->bloquearPropia($fincaId, $productorId);
                            if ($existente !== null && $existente['estado'] === 1) {
                                throw new HttpException('Ya tienes una finca activa con ese nombre.', 409, null, ['nombreFinca' => 'Use otro nombre.']);
                            }
                            $this->fincas->cambiarEstado($fincaId, $productorId, true);
                        } else {
                            $fincaId = $this->fincas->crear($productorId, $nombre);
                        }
                        $this->guardarDireccion($fincaId, $direccion, $direccionEnviada);
                        $nueva = $this->finca($productorId, $fincaId);
                        $this->bitacora->registrar('CREAR', (string) $fincaId, null, $nueva, $this->solicitudId, 'FINCA', 'API_MI_FINCAS');
                        return ['finca' => $nueva, 'fincas' => $this->listar($productorId)];
                    }),
                ),
            ),
        );

        return $this->respuesta(true, 'Finca agregada correctamente.', $resultado, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['fincaId', 'nombreFinca', 'direccionFinca']);
        $errores = [];
        $fincaId = $this->enteroCampo($cuerpo['fincaId'] ?? null, 'fincaId', $errores);
        $nombre = $this->textoCampo($cuerpo['nombreFinca'] ?? null, 'nombreFinca', 150, $errores);
        $direccionEnviada = array_key_exists('direccionFinca', $cuerpo);
        $direccion = $direccionEnviada
            ? $this->validacion->validarDireccionFincaEnCampo($cuerpo['direccionFinca'], 'direccionFinca', $errores)
            : null;
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $productor = $this->productorAutenticado();
        $this->exigirEscritura($productor);
        $resultado = $this->fincas->ejecutarConBloqueoAlta(
            fn (): array => $this->direccionFinca->ejecutarConBloqueoAlta(
                fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($productor, $fincaId, $nombre, $direccion, $direccionEnviada): array {
                        $bloqueado = $this->bloquearProductorActivo($productor);
                        $productorId = (int) $bloqueado['tbproductorid'];
                        $anterior = $this->fincas->bloquearPropia($fincaId, $productorId);
                        if ($anterior === null || $anterior['estado'] !== 1) throw new HttpException('La finca no existe o está inactiva.', 404);
                        $otroId = $this->fincas->buscarIdPorNombre($productorId, $nombre);
                        if ($otroId !== null && $otroId !== $fincaId) throw new HttpException('Ya tienes otra finca activa con ese nombre.', 409, null, ['nombreFinca' => 'Use otro nombre.']);
                        $anteriorMapeada = $this->finca($productorId, $fincaId);
                        $this->fincas->actualizarNombre($fincaId, $productorId, $nombre);
                        $this->guardarDireccion($fincaId, $direccion, $direccionEnviada);
                        $nueva = $this->finca($productorId, $fincaId);
                        $this->bitacora->registrar('ACTUALIZAR', (string) $fincaId, $anteriorMapeada, $nueva, $this->solicitudId, 'FINCA', 'API_MI_FINCAS');
                        return ['finca' => $nueva, 'fincas' => $this->listar($productorId)];
                    }),
                ),
            ),
        );
        return $this->respuesta(true, 'Finca actualizada correctamente.', $resultado);
    }

    private function cambiarEstado(array $cuerpo, bool $activo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['fincaId']);
        $errores = [];
        $fincaId = $this->enteroCampo($cuerpo['fincaId'] ?? null, 'fincaId', $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $productor = $this->productorAutenticado();
        $this->exigirEscritura($productor);
        $resultado = $this->fincas->ejecutarConBloqueoAlta(
            fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                fn (): array => $this->transaccion(function () use ($productor, $fincaId, $activo): array {
                    $bloqueado = $this->bloquearProductorActivo($productor);
                    $productorId = (int) $bloqueado['tbproductorid'];
                    $anterior = $this->fincas->bloquearPropia($fincaId, $productorId);
                    if ($anterior === null) throw new HttpException('La finca no existe en tu cuenta.', 404);
                    if ($anterior['estado'] === ($activo ? 1 : 0)) throw new HttpException($activo ? 'La finca ya está activa.' : 'La finca ya está inactiva.', 409);
                    $anteriorMapeada = $this->finca($productorId, $fincaId);
                    $this->fincas->cambiarEstado($fincaId, $productorId, $activo);
                    $nueva = $this->finca($productorId, $fincaId);
                    $this->bitacora->registrar($activo ? 'REACTIVAR' : 'DESACTIVAR', (string) $fincaId, $anteriorMapeada, $nueva, $this->solicitudId, 'FINCA', 'API_MI_FINCAS');
                    return ['finca' => $nueva, 'fincas' => $this->listar($productorId)];
                }),
            ),
        );
        return $this->respuesta(true, $activo ? 'Finca reactivada correctamente.' : 'Finca desactivada correctamente.', $resultado);
    }

    private function productorAutenticado(): array
    {
        if (!$this->actor->tienePersona()) throw new HttpException('Debe iniciar sesión para administrar sus fincas.', 401);
        $productor = $this->productor->buscarPorPersonaId((int) $this->actor->personaId);
        if ($productor === null) throw new HttpException('Configure la actividad Productor antes de administrar fincas.', 409);
        return [
            'productorId' => (int) $productor['tbproductorid'],
            'identificacionNumero' => $productor['tbpersonaidentificacionnumero'],
            'estado' => (int) $productor['tbproductorestado'] === 1 && (int) $productor['tbpersonaestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
        ];
    }

    private function bloquearProductorActivo(array $productor): array
    {
        $bloqueado = $this->productor->bloquear($productor['identificacionNumero']);
        if ($bloqueado === null || (int) $bloqueado['tbproductorid'] !== $productor['productorId'] || (int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
            throw new HttpException('Reactive la actividad Productor antes de modificar fincas.', 409);
        }
        return $bloqueado;
    }

    private function exigirEscritura(array $productor): void
    {
        if ($productor['estado'] !== 'ACTIVO') throw new HttpException('Reactive la actividad Productor antes de modificar fincas.', 409);
    }

    private function listar(int $productorId): array
    {
        return array_map(fn (array $finca): array => $this->finca($productorId, $finca['fincaId']), $this->fincas->listarActivasConIds($productorId));
    }

    private function finca(int $productorId, int $fincaId): array
    {
        $fila = $this->fincas->bloquearPropia($fincaId, $productorId);
        if ($fila === null) throw new \RuntimeException('No fue posible leer la finca.');
        return [
            'fincaId' => $fincaId,
            'nombre' => $fila['nombre'],
            'direccion' => $this->direccionFinca->buscar($fincaId),
            'estado' => $fila['estado'] === 1 ? 'ACTIVO' : 'INACTIVO',
        ];
    }

    private function guardarDireccion(int $fincaId, ?array $direccion, bool $enviada): void
    {
        if (!$enviada || $direccion === null) return;
        if ($this->direccionFinca->buscar($fincaId) === null) $this->direccionFinca->crear($fincaId, $direccion);
        else $this->direccionFinca->actualizar($fincaId, $direccion);
    }

    private function validarDatos(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = $actualizacion ? ['fincaId', 'nombreFinca', 'direccionFinca'] : ['nombreFinca', 'direccionFinca'];
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];
        $nombre = $this->textoCampo($cuerpo['nombreFinca'] ?? null, 'nombreFinca', 150, $errores);
        $enviada = array_key_exists('direccionFinca', $cuerpo);
        $direccion = $enviada ? $this->validacion->validarDireccionFincaEnCampo($cuerpo['direccionFinca'], 'direccionFinca', $errores) : null;
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        return [$nombre, $direccion, $enviada];
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos): void
    {
        $errores = [];
        foreach (array_diff(array_keys($datos), $permitidos) as $campo) $errores[$campo] = 'Campo no permitido.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
    }

    private function textoCampo(mixed $valor, string $campo, int $maximo, array &$errores): string
    {
        if (!is_string($valor)) { $errores[$campo] = 'El nombre es obligatorio.'; return ''; }
        $texto = preg_replace('/\s+/u', ' ', trim($valor)) ?? trim($valor);
        if ($texto === '' || mb_strlen($texto) > $maximo) $errores[$campo] = "Debe contener entre 1 y {$maximo} caracteres.";
        return $texto;
    }

    private function enteroCampo(mixed $valor, string $campo, array &$errores): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) { $errores[$campo] = 'Debe ser un entero positivo.'; return 0; }
        return $entero;
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try { $resultado = $operacion(); $this->conexion->commit(); return $resultado; }
        catch (Throwable $excepcion) { if ($this->conexion->inTransaction()) $this->conexion->rollBack(); throw $excepcion; }
    }

    private function normalizarSolicitudId(?string $valor): string
    {
        $valor = trim((string) $valor);
        return $valor !== '' && strlen($valor) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $valor) ? $valor : 'REQ-' . bin2hex(random_bytes(16));
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
