<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;
use Application\Model\Bitacora;
use RuntimeException;

/**
 * Orquesta desactivar/reactivar de entidades con el patrón legacy de "columna de
 * estado" (transportista, vehículo y método de pago): bloquear -> verificar
 * persona activa (opcional) -> idempotencia -> cambiar columna -> registrar en
 * bitácora, dentro de la transacción del llamador. Las estrategias se inyectan
 * por callable para poder reutilizar el mismo esqueleto en entidades con firmas
 * distintas.
 *
 * NOTA: Productor usa el patrón de periodos (ProductorEstadoService). Comprador
 * ya no usa una columna legacy como fuente de negocio: su estado se deriva de
 * la clasificación y el CRUD administrativo fue retirado en DEC-DBREADY-008.
 * Este servicio se conserva para los patrones legacy que todavía siguen vivos.
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
     * @param ?callable(array):int    $estadoDeNegocio  estado vigente (0/1) leído
     *        de la fila mapeada. Sirve para entidades cuyo estado de negocio ya
     *        no vive en la columna legacy. Si es null se usa la columna
     *        $campoEstado.
     * @param ?callable(mixed,bool):void $sincronizar  escritura adicional que
     *        acompaña el cambio de estado, dentro de la misma transacción.
     *        Se ejecuta DESPUÉS de capturar $anterior: si corriera antes, la
     *        lectura derivada ya reflejaría el estado nuevo y la bitácora
     *        registraría INACTIVO -> INACTIVO en vez de ACTIVO -> INACTIVO.
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
        ?callable $estadoDeNegocio = null,
        ?callable $sincronizar = null,
    ): array {
        $bloqueado = $bloquear($clave);
        // $anterior se captura antes de cualquier escritura: es lo que la
        // bitácora debe mostrar como estado previo.
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
        $estadoVigente = $estadoDeNegocio === null
            ? (int) $bloqueado[$campoEstado]
            : (int) $estadoDeNegocio($anterior);
        if ($estadoVigente === $nuevoEstado) {
            return $anterior;
        }
        if ($sincronizar !== null) {
            $sincronizar($clave, $nuevoEstado === 1);
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
