<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Model\Bitacora;
use Application\Model\ProductorDireccion;

/**
 * Orquesta el histórico de residencia (tramo 5/16) bajo el lock por productor:
 * cierre + alta transaccional con bitácora CAMBIO_DIRECCION / VACIAR_DIRECCION
 * en la misma transacción del llamador. El controlador sigue resolviendo el
 * sobre JSON; este servicio concentra la lógica de cambio de residencia.
 */
final class ProductorDireccionService
{
    public function __construct(
        private readonly ProductorDireccion $direccion,
        private readonly Bitacora $bitacora,
        private readonly string $solicitudId,
    ) {}

    /** Cierra el periodo abierto, abre uno nuevo y registra CAMBIO_DIRECCION. */
    public function cambiar(int $productorId, string $identificacion, ?array $anterior, array $nueva): array
    {
        return $this->direccion->ejecutarConBloqueoProducto(
            $productorId,
            function () use ($productorId, $identificacion, $anterior, $nueva): array {
                $this->direccion->actualizar($productorId, $nueva);
                $direccionNueva = $this->direccion->buscar($productorId);
                $this->bitacora->registrar(
                    'CAMBIO_DIRECCION',
                    $identificacion,
                    ['direccionPrincipal' => $anterior],
                    ['direccionPrincipal' => $direccionNueva],
                    $this->solicitudId,
                );

                return $direccionNueva;
            },
        );
    }

    /** Vacía la residencia vigente (cierre + alta vacía) con VACIAR_DIRECCION. */
    public function vaciar(int $productorId, string $identificacion, ?array $anterior): array
    {
        return $this->direccion->ejecutarConBloqueoProducto(
            $productorId,
            function () use ($productorId, $identificacion, $anterior): array {
                $this->direccion->vaciar($productorId);
                $direccionNueva = $this->direccion->buscar($productorId);
                $this->bitacora->registrar(
                    'VACIAR_DIRECCION',
                    $identificacion,
                    ['direccionPrincipal' => $anterior],
                    ['direccionPrincipal' => $direccionNueva],
                    $this->solicitudId,
                );

                return $direccionNueva;
            },
        );
    }
}
