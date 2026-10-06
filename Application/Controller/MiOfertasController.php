<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\Direccion;
use Application\Model\Transportista;
use Application\Model\TransportistaOferta;
use Application\Model\TransportistaVehiculo;
use Application\Model\Vehiculo;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/** Ofertas de flete del Transportista vinculado a la sesión (DEC-FLETE-001). */
final class MiOfertasController
{
    private TransportistaOferta $ofertas;
    private Direccion $direccion;
    private Transportista $transportista;
    private TransportistaVehiculo $enlace;
    private Vehiculo $vehiculo;
    private Bitacora $bitacora;
    private ValidacionService $validacion;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->direccion = new Direccion($conexion);
        $this->ofertas = new TransportistaOferta($conexion, $this->direccion);
        $this->enlace = new TransportistaVehiculo($conexion);
        $this->transportista = new Transportista($conexion, $this->enlace);
        $this->vehiculo = new Vehiculo($conexion);
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
                'PATCH' => $this->cambiarEstado($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function consultar(): array
    {
        $transportista = $this->transportistaAutenticado();
        return $this->respuesta(true, 'Ofertas de flete propias consultadas correctamente.', [
            'estadoTransportista' => $transportista['estado'],
            'escrituraDisponible' => $transportista['estado'] === 'ACTIVO',
            'ofertas' => $this->ofertas->listarPropias((int) $transportista['transportistaId']),
        ]);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarOferta($cuerpo, false);
        $transportista = $this->transportistaAutenticado();
        $this->exigirEscritura($transportista);
        $transportistaId = (int) $transportista['transportistaId'];

        $oferta = $this->ejecutarConBloqueos(function () use ($transportista, $transportistaId, $datos): array {
            $this->bloquearTransportista($transportista, $transportistaId);
            $this->exigirVehiculoPropio($datos['vehiculoId'], $transportistaId);
            $ofertaId = $this->ofertas->crear($transportistaId, $datos);
            $nueva = $this->ofertas->buscarPropia($ofertaId, $transportistaId);
            if ($nueva === null) throw new \RuntimeException('No fue posible leer la oferta recién creada.');
            $this->bitacora->registrar('CREAR', (string) $ofertaId, null, $nueva, $this->solicitudId,
                entidad: 'OFERTA_FLETE', origen: 'API_MI_OFERTAS');
            return $nueva;
        });

        return $this->respuesta(true, 'Oferta de flete publicada correctamente.', [
            'oferta' => $oferta,
            'ofertas' => $this->ofertas->listarPropias($transportistaId),
        ], 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarOferta($cuerpo, true);
        $transportista = $this->transportistaAutenticado();
        $this->exigirEscritura($transportista);
        $transportistaId = (int) $transportista['transportistaId'];
        $id = $datos['ofertaId'];

        $oferta = $this->ejecutarConBloqueos(function () use ($transportista, $transportistaId, $datos, $id): array {
            $this->bloquearTransportista($transportista, $transportistaId);
            $anterior = $this->ofertas->buscarPropia($id, $transportistaId, true);
            if ($anterior === null) throw new HttpException('La oferta no existe en tu cuenta.', 404);
            $this->exigirNoRetirada($anterior);
            $this->exigirVehiculoPropio($datos['vehiculoId'], $transportistaId);
            $this->ofertas->actualizar($id, $datos);
            $actualizada = $this->ofertas->buscarPropia($id, $transportistaId);
            if ($actualizada === null) throw new \RuntimeException('No fue posible leer la oferta actualizada.');
            $this->bitacora->registrar('ACTUALIZAR', (string) $id, $anterior, $actualizada, $this->solicitudId,
                entidad: 'OFERTA_FLETE', origen: 'API_MI_OFERTAS');
            return $actualizada;
        });

        return $this->respuesta(true, 'Oferta de flete actualizada correctamente.', [
            'oferta' => $oferta,
            'ofertas' => $this->ofertas->listarPropias($transportistaId),
        ]);
    }

    /** PATCH { ofertaId, estado: ACTIVA|PAUSADA }: pausar y reactivar son idempotentes. */
    private function cambiarEstado(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['ofertaId', 'estado']);
        $errores = [];
        $id = $this->enteroCampo($cuerpo['ofertaId'] ?? null, 'ofertaId', 1, PHP_INT_MAX, $errores);
        $estado = is_string($cuerpo['estado'] ?? null) ? mb_strtoupper(trim($cuerpo['estado']), 'UTF-8') : '';
        if (!in_array($estado, TransportistaOferta::ESTADOS, true)) {
            $errores['estado'] = 'Use ' . implode(' o ', TransportistaOferta::ESTADOS) . '.';
        }
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $transportista = $this->transportistaAutenticado();
        $this->exigirEscritura($transportista);
        $transportistaId = (int) $transportista['transportistaId'];

        $oferta = $this->ejecutarConBloqueos(function () use ($transportista, $transportistaId, $id, $estado): array {
            $this->bloquearTransportista($transportista, $transportistaId);
            $anterior = $this->ofertas->buscarPropia($id, $transportistaId, true);
            if ($anterior === null) throw new HttpException('La oferta no existe en tu cuenta.', 404);
            $this->exigirNoRetirada($anterior);
            if ($anterior['estado'] !== $estado) {
                $this->ofertas->cambiarEstado($id, $estado);
                $actualizada = $this->ofertas->buscarPropia($id, $transportistaId);
                $this->bitacora->registrar($estado === 'PAUSADA' ? 'PAUSAR' : 'REACTIVAR', (string) $id, $anterior, $actualizada,
                    $this->solicitudId, entidad: 'OFERTA_FLETE', origen: 'API_MI_OFERTAS');
                return $actualizada;
            }
            return $anterior;
        });

        return $this->respuesta(true, $estado === 'PAUSADA' ? 'Oferta pausada correctamente.' : 'Oferta reactivada correctamente.', [
            'oferta' => $oferta,
            'ofertas' => $this->ofertas->listarPropias($transportistaId),
        ]);
    }

    /** Una oferta retirada por un administrador no se edita, pausa ni reactiva desde la cuenta del transportista. */
    private function exigirNoRetirada(array $oferta): void
    {
        if ($oferta['estado'] === TransportistaOferta::ESTADO_RETIRADA) {
            throw new HttpException('Un administrador retiró esta oferta; no puede modificarse.', 409);
        }
    }

    /** Orden de locks: oferta -> dirección -> bitácora, dentro de una transacción. */
    private function ejecutarConBloqueos(callable $operacion): array
    {
        return $this->ofertas->ejecutarConBloqueoAlta(
            fn (): array => $this->direccion->ejecutarConBloqueoAlta(
                fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion($operacion),
                ),
            ),
        );
    }

    private function transportistaAutenticado(): array
    {
        if (!$this->actor->tienePersona()) throw new HttpException('Debe iniciar sesión para administrar sus ofertas de flete.', 401);
        $transportista = $this->transportista->buscarPorPersonaId((int) $this->actor->personaId);
        if ($transportista === null) throw new HttpException('Configure la actividad Transportista antes de publicar fletes.', 409);
        return $transportista;
    }

    private function exigirEscritura(array $transportista): void
    {
        if ($transportista['estado'] !== 'ACTIVO') {
            throw new HttpException('Reactive la actividad Transportista antes de modificar sus ofertas.', 409);
        }
    }

    private function bloquearTransportista(array $transportista, int $transportistaId): void
    {
        $bloqueado = $this->transportista->bloquear($transportista['identificacionNumero']);
        if ($bloqueado === null || (int) $bloqueado['tbtransportistaid'] !== $transportistaId) {
            throw new HttpException('La actividad Transportista ya no está disponible.', 409);
        }
        if ((int) $bloqueado['tbtransportistaestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
            throw new HttpException('Reactive la actividad Transportista antes de modificar sus ofertas.', 409);
        }
    }

    /** El vehículo debe ser del transportista y estar activo (404 si es ajeno, sin revelar que existe). */
    private function exigirVehiculoPropio(int $vehiculoId, int $transportistaId): void
    {
        $vehiculo = $this->vehiculo->bloquearPorId($vehiculoId);
        if ($vehiculo === null || $this->enlace->buscarTransportistaDeVehiculo($vehiculoId) !== $transportistaId) {
            throw new HttpException('El vehículo no existe en tu cuenta.', 404, null, ['vehiculoId' => 'Elige uno de tus vehículos.']);
        }
        if ((int) $vehiculo['tbvehiculoestado'] !== 1) {
            throw new HttpException('El vehículo está inactivo.', 409, null, ['vehiculoId' => 'Reactiva el vehículo o elige otro.']);
        }
    }

    private function validarOferta(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['vehiculoId', 'direccion', 'radioKm', 'capacidad', 'precio', 'descripcion'];
        $this->rechazarCamposDesconocidos($cuerpo, $actualizacion ? ['ofertaId', ...$permitidos] : $permitidos);
        $errores = [];
        $resultado = [
            'vehiculoId' => $this->enteroCampo($cuerpo['vehiculoId'] ?? null, 'vehiculoId', 1, PHP_INT_MAX, $errores),
            'radioKm' => $this->enteroCampo($cuerpo['radioKm'] ?? null, 'radioKm', 1, 500, $errores),
            'capacidad' => $this->enteroCampo($cuerpo['capacidad'] ?? null, 'capacidad', 1, 200, $errores),
            'precio' => $this->precioCampo($cuerpo['precio'] ?? null, $errores),
            'descripcion' => $this->descripcionCampo($cuerpo['descripcion'] ?? null, $errores),
        ];
        if ($actualizacion) $resultado['ofertaId'] = $this->enteroCampo($cuerpo['ofertaId'] ?? null, 'ofertaId', 1, PHP_INT_MAX, $errores);

        $direccion = $this->validacion->validarDireccionFincaEnCampo($cuerpo['direccion'] ?? null, 'direccion', $errores);
        // Sin punto en el mapa la oferta no se podría ordenar por cercanía.
        if (!isset($errores['direccion']) && ($direccion['latitud'] === null || $direccion['longitud'] === null)) {
            $errores['direccion.latitud'] = $errores['direccion.longitud'] = 'Marca tu zona base en el mapa.';
        }
        $resultado['direccion'] = $direccion;
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        return $resultado;
    }

    private function precioCampo(mixed $valor, array &$errores): ?float
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) return null;
        if (!is_int($valor) && !is_float($valor) && !(is_string($valor) && is_numeric($valor))) {
            $errores['precio'] = 'El precio debe ser un número.';
            return null;
        }
        $precio = (float) $valor;
        if (!is_finite($precio) || $precio < 0 || $precio > 9999999999.99) {
            $errores['precio'] = 'El precio debe estar entre 0 y 9.999.999.999.';
            return null;
        }
        return round($precio, 2);
    }

    private function descripcionCampo(mixed $valor, array &$errores): ?string
    {
        if ($valor === null) return null;
        if (!is_string($valor)) {
            $errores['descripcion'] = 'La descripción debe ser texto.';
            return null;
        }
        $texto = trim($valor);
        if (mb_strlen($texto) > 500) {
            $errores['descripcion'] = 'Debe tener hasta 500 caracteres.';
            return null;
        }
        return $texto === '' ? null : $texto;
    }

    private function enteroCampo(mixed $valor, string $campo, int $minimo, int $maximo, array &$errores): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);
        if ($entero === false) {
            $errores[$campo] = $maximo === PHP_INT_MAX
                ? 'Debe ser un entero positivo.'
                : "Debe ser un entero entre {$minimo} y {$maximo}.";
            return 0;
        }
        return $entero;
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) return;
        $errores = [];
        foreach ($desconocidos as $campo) $errores[$campo] = 'Campo no permitido.';
        throw new HttpException('Revise los campos indicados.', 422, null, $errores);
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
