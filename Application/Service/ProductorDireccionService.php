<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Model\Bitacora;
use Application\Model\ProductorDireccion;

/**
 * Orquesta el histórico de residencia (tramo 5/16): cierre + alta con
 * bitácora CAMBIO_DIRECCION / VACIAR_DIRECCION. El controlador decide el
 * límite transaccional; este servicio concentra la regla de histórico.
 */
final class ProductorDireccionService
{
    public function __construct(
        private readonly ProductorDireccion $direccion,
        private readonly Bitacora $bitacora,
        private readonly string $solicitudId,
    ) {}

    /**
     * Conveniencia para operaciones cuyo callback completo queda dentro del
     * lock. Si el llamador ya controla una transacción mayor, debe usar
     * cambiarConBloqueosExistentes() y envolver ESA transacción con
     * ProductorDireccion::ejecutarConBloqueoProducto().
     */
    public function cambiar(int $productorId, string $identificacion, ?array $anterior, array $nueva): array
    {
        return $this->direccion->ejecutarConBloqueoProducto(
            $productorId,
            fn (): array => $this->cambiarConBloqueosExistentes(
                $productorId,
                $identificacion,
                $anterior,
                $nueva,
            ),
        );
    }

    /** El llamador ya mantiene los locks de ProductorDireccion y Direccion. */
    public function cambiarConBloqueosExistentes(
        int $productorId,
        string $identificacion,
        ?array $anterior,
        array $nueva,
    ): array {
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
    }

    /** Véase cambiar(): esta variante adquiere los locks por sí misma. */
    public function vaciar(int $productorId, string $identificacion, ?array $anterior): array
    {
        return $this->direccion->ejecutarConBloqueoProducto(
            $productorId,
            fn (): array => $this->vaciarConBloqueosExistentes($productorId, $identificacion, $anterior),
        );
    }

    /** El llamador ya mantiene los locks de ProductorDireccion y Direccion. */
    public function vaciarConBloqueosExistentes(int $productorId, string $identificacion, ?array $anterior): array
    {
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
    }
}
