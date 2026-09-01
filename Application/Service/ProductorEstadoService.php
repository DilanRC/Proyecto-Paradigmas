<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorEstadoPeriodo;

/**
 * Transiciones de estado atómicas e idempotentes (tramo 14). Una transición
 * cierra el periodo abierto y abre el nuevo dentro de la misma transacción que
 * el llamador (controlador) abre bajo el lock por productor; si una de las dos
 * escrituras falla, el COMMIT/ROLLBACK exterior las deja a ambas o a ninguna.
 *
 * Idempotencia: transicionar al mismo estado es un no-op que NO abre un periodo
 * duplicado. La bitácora registra la auditoría técnica; no sustituye al
 * histórico de negocio.
 */
final class ProductorEstadoService
{
    public function __construct(
        private readonly ProductorEstadoPeriodo $estadoPeriodos,
        private readonly Productor $productor,
        private readonly Bitacora $bitacora,
        private readonly string $solicitudId,
    ) {}

    /**
     * Transiciona el estado operativo del productor. Requiere que el llamador
     * posea la transacción abierta.
     *
     * @return bool true si hubo transición real; false si ya estaba en ese estado.
     */
    public function transicionar(int $productorId, int $nuevoEstado, string $motivo, string $identificacion): bool
    {
        $anterior = $this->productor->buscarPorId($productorId);

        $transicionOcurrida = $this->estadoPeriodos->ejecutarConBloqueo(
            $productorId,
            function () use ($productorId, $nuevoEstado, $motivo): bool {
                $abierto = $this->estadoPeriodos->consultarAbierto($productorId);
                if ($abierto !== null && (int) $abierto['tbproductorestadoperiodoestado'] === $nuevoEstado) {
                    return false;
                }
                if ($abierto !== null) {
                    $this->estadoPeriodos->cerrar($productorId);
                }
                $this->estadoPeriodos->abrir($productorId, $nuevoEstado, $motivo);

                return true;
            },
        );

        if ($transicionOcurrida && $anterior !== null) {
            $nuevo = $this->productor->buscarPorId($productorId);
            $this->bitacora->registrar(
                $nuevoEstado === 1 ? 'REACTIVAR' : 'DESACTIVAR',
                $identificacion,
                $anterior,
                $nuevo,
                $this->solicitudId,
            );
        }

        return $transicionOcurrida;
    }
}
