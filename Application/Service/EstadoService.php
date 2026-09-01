<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;
use Application\Model\Bitacora;
use RuntimeException;

/**
 * Orquesta desactivar/reactivar de entidades con el patrón legacy de "columna de
 * estado" (tbcompradorestado, tbtransportistaestado, tbvehiculoestado,
 * tbpagometodoactivo): bloquear -> verificar persona activa (opcional) ->
 * idempotencia -> cambiar columna -> registrar en bitácora, dentro de la
 * transacción del llamador. Las estrategias se inyectan por callable para poder
 * reutilizar el mismo esqueleto en entidades con firmas distintas.
 *
 * NOTA: Producto usa el patrón de periodos (ProductorEstadoService); este
 * servicio NO lo reemplaza. Evita duplicar el esqueleto legacy en los cuatro
 * controladores que todavía sobrescriben una sola columna de estado.
 */
final class EstadoService
{
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly string $solicitudId,
    ) {}

    /**
     * @param callable(mixed):?array  $bloquear    fila cruda con lock (o null)
     * @param callable(mixed):?array  $buscar      fila mapeada (o null)
     * @param callable(mixed,bool):void $cambiar   actualiza la columna de estado
     * @param array<string,mixed>     $config      etiquetas y datos de bitácora
     */
    public function transicionar(
        callable $bloquear,
        callable $buscar,
        callable $cambiar,
        string $campoEstado,
        int $nuevoEstado,
        string $mensajeNoEncontrado,
        string $registroId,
        string $entidad,
        string $origen,
        mixed $clave,
        ?string $campoPersonaEstado = 'tbpersonaestado',
        string $mensajePosterior = 'el cambio de estado',
    ): array {
        $bloqueado = $bloquear($clave);
        $anterior = $buscar($clave);
        if ($bloqueado === null || $anterior === null) {
            throw new HttpException($mensajeNoEncontrado, 404);
        }
        if ($campoPersonaEstado !== null && (int) $bloqueado[$campoPersonaEstado] !== 1) {
            throw new HttpException(
                $nuevoEstado === 1
                    ? 'La persona está inactiva y no puede reactivar capacidades.'
                    : 'La persona está inactiva y no puede operar capacidades.',
                409,
            );
        }
        if ((int) $bloqueado[$campoEstado] === $nuevoEstado) {
            return $anterior;
        }
        $cambiar($clave, $nuevoEstado === 1);
        $nuevo = $buscar($clave);
        $this->bitacora->registrar(
            $nuevoEstado === 1 ? 'REACTIVAR' : 'DESACTIVAR',
            $registroId,
            $anterior,
            $nuevo,
            $this->solicitudId,
            entidad: $entidad,
            origen: $origen,
        );

        return $nuevo ?? throw new RuntimeException('No fue posible leer el registro tras ' . $mensajePosterior . '.');
    }
}
