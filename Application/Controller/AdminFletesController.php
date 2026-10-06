<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Bitacora;
use Application\Model\CompraSolicitud;
use Application\Model\Direccion;
use Application\Model\TransportistaOferta;
use PDO;
use Throwable;

/**
 * Fletes para el administrador. Dos vistas de lectura (ofertas de flete y solicitudes de compra) y la
 * moderación de ofertas: retirar una ACTIVA o PAUSADA (motivo obligatorio) y reactivar una RETIRADA. Sin
 * esquema nuevo: la oferta guarda el estado RETIRADA en la misma columna VARCHAR. La autorización de
 * administrador la exige el endpoint antes de llegar aquí.
 */
final class AdminFletesController
{
    private const VISTAS = ['OFERTAS', 'SOLICITUDES'];
    private const ESTADOS_OFERTA = ['TODOS', 'ACTIVA', 'PAUSADA', 'RETIRADA'];
    private const ESTADOS_SOLICITUD = ['TODOS', ...CompraSolicitud::ESTADOS];
    private const ESTADOS_MODERABLES = ['RETIRADA', 'ACTIVA'];

    private readonly TransportistaOferta $ofertas;
    private readonly CompraSolicitud $solicitudes;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null, ?ActorContext $actor = null)
    {
        $this->ofertas = new TransportistaOferta($conexion, new Direccion($conexion));
        $this->solicitudes = new CompraSolicitud($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->listar($consulta),
                'PATCH' => $this->moderar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function listar(array $consulta): array
    {
        $vista = mb_strtoupper(trim((string) ($consulta['vista'] ?? 'OFERTAS')), 'UTF-8');
        if (!in_array($vista, self::VISTAS, true)) {
            throw new HttpException('La vista no es válida.', 422, null, ['vista' => 'Use ' . implode(' o ', self::VISTAS) . '.']);
        }
        $busqueda = $consulta['q'] ?? '';
        if (!is_string($busqueda) || mb_strlen($busqueda) > 150) {
            throw new HttpException('La búsqueda no es válida.', 422, null, ['q' => 'Use hasta 150 caracteres.']);
        }
        $estados = $vista === 'OFERTAS' ? self::ESTADOS_OFERTA : self::ESTADOS_SOLICITUD;
        $estado = mb_strtoupper(trim((string) ($consulta['estado'] ?? 'TODOS')), 'UTF-8');
        if (!in_array($estado, $estados, true)) {
            throw new HttpException('El filtro de estado no es válido.', 422, null, ['estado' => 'Use ' . implode(', ', $estados) . '.']);
        }
        $pagina = filter_var($consulta['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tamano = filter_var($consulta['tamanoPagina'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($pagina === false || $tamano === false) {
            throw new HttpException('La paginación no es válida.', 422, null, [
                'pagina' => 'Debe ser un entero positivo.',
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $vista === 'OFERTAS'
            ? $this->ofertas->listarAdmin(trim($busqueda), $estado, $pagina, $tamano)
            : $this->solicitudes->listarAdmin(trim($busqueda), $estado, $pagina, $tamano);
        $resultado['vista'] = $vista;
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Fletes consultados correctamente.', $resultado);
    }

    private function moderar(array $cuerpo): array
    {
        $ofertaId = filter_var($cuerpo['ofertaId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($ofertaId === false) {
            throw new HttpException('ofertaId debe ser un entero positivo.', 422);
        }
        $estado = mb_strtoupper(trim((string) ($cuerpo['estado'] ?? '')), 'UTF-8');
        if (!in_array($estado, self::ESTADOS_MODERABLES, true)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                'estado' => 'Use ' . implode(' o ', self::ESTADOS_MODERABLES) . '.',
            ]);
        }
        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));
        if (mb_strlen($motivo) > 250) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['motivo' => 'No puede superar 250 caracteres.']);
        }
        if ($estado === TransportistaOferta::ESTADO_RETIRADA && $motivo === '') {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                'motivo' => 'Indique el motivo para retirar la oferta.',
            ]);
        }

        $this->conexion->beginTransaction();
        try {
            $anterior = $this->ofertas->buscarAdmin((int) $ofertaId, true);
            if ($anterior === null) {
                throw new HttpException('La oferta no existe.', 404);
            }
            // Retirar solo desde ACTIVA o PAUSADA; reactivar solo desde RETIRADA. Pausar/reactivar es del transportista.
            $permitido = $estado === TransportistaOferta::ESTADO_RETIRADA
                ? in_array($anterior['estado'], TransportistaOferta::ESTADOS, true)
                : $anterior['estado'] === TransportistaOferta::ESTADO_RETIRADA;
            if (!$permitido && $anterior['estado'] !== $estado) {
                throw new HttpException(
                    $estado === TransportistaOferta::ESTADO_RETIRADA
                        ? 'La oferta ya no está abierta.'
                        : 'Solo se reactiva una oferta retirada; las pausadas las reactiva su transportista.',
                    409
                );
            }
            if ($anterior['estado'] !== $estado) {
                $this->ofertas->cambiarEstado((int) $ofertaId, $estado);
                $nueva = $this->ofertas->buscarAdmin((int) $ofertaId);
                $this->bitacora->registrar(
                    'MODERAR',
                    (string) $ofertaId,
                    $anterior,
                    $nueva + ['motivo' => $motivo === '' ? null : $motivo],
                    $this->solicitudId,
                    entidad: 'OFERTA_FLETE',
                    origen: 'API_ADMIN_FLETES',
                );
            } else {
                $nueva = $anterior;
            }
            $this->conexion->commit();
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }

        return $this->respuesta(true, 'Oferta actualizada correctamente.', ['oferta' => $nueva]);
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
