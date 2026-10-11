<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Model\AnimalComercial;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Model\Notificacion;
use Application\Model\Subasta;
use PDO;
use Throwable;

/**
 * Cierra las subastas cuya hora de fin ya pasó. No hay tarea programada: cualquier consulta de subastas o de la
 * campana lo hace primero (y el navegador consulta al llegar el contador a cero). Con pujas: FINALIZADA, gana la
 * puja más alta, se registran la compra y la venta y el animal queda VENDIDO. Sin pujas: DESIERTA y el animal
 * vuelve al inventario. El cierre lo hace el sistema: la bitácora no lleva actor.
 */
final class SubastaCierreService
{
    private const ORIGEN = 'API_SUBASTAS';

    private Subasta $subastas;
    private AnimalComercial $animales;
    private Comprador $compradores;
    private Notificacion $notificaciones;
    private Bitacora $bitacora;

    public function __construct(private readonly PDO $conexion, private readonly TiempoReal $tiempoReal)
    {
        $this->subastas = new Subasta($conexion);
        $this->animales = new AnimalComercial($conexion);
        $this->compradores = new Comprador($conexion);
        $this->notificaciones = new Notificacion($conexion);
        $this->bitacora = new Bitacora($conexion);
    }

    /** Devuelve los ids cerrados. */
    public function cerrarVencidas(): array
    {
        $ahora = gmdate('Y-m-d H:i:s');
        $cerradas = [];
        $topicos = [];
        foreach ($this->subastas->vencidas($ahora) as $subastaId) {
            $avisos = $this->cerrar($subastaId, $ahora);
            if ($avisos === null) continue;
            $cerradas[] = $subastaId;
            $topicos = [...$topicos, 'subastas', "subasta-{$subastaId}", ...$avisos];
        }
        $this->tiempoReal->avisar($topicos);

        return $cerradas;
    }

    /** Orden de bloqueos: subastas → compra → venta → notificación → bitácora → transacción (Cuidados #12). */
    private function cerrar(int $subastaId, string $ahora): ?array
    {
        return $this->subastas->ejecutarConBloqueo(fn (): ?array => $this->animales->ejecutarConBloqueoAlta('tbcompra',
            fn (): ?array => $this->animales->ejecutarConBloqueoAlta('tbventa',
                fn (): ?array => $this->notificaciones->ejecutarConBloqueoAlta(
                    fn (): ?array => $this->bitacora->ejecutarConBloqueoAlta(function () use ($subastaId, $ahora): ?array {
                        $this->conexion->beginTransaction();
                        try {
                            $avisos = $this->cerrarEnTransaccion($subastaId, $ahora);
                            $this->conexion->commit();
                            return $avisos;
                        } catch (Throwable $error) {
                            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
                            throw $error;
                        }
                    }),
                ),
            ),
        ));
    }

    private function cerrarEnTransaccion(int $subastaId, string $ahora): ?array
    {
        $subasta = $this->subastas->buscar($subastaId);
        // Otra petición pudo cerrarla mientras esperábamos el bloqueo.
        if ($subasta === null || $subasta['estado'] !== 'ACTIVA' || str_replace(['T', 'Z'], [' ', ''], (string) $subasta['fechaFin']) > $ahora) {
            return null;
        }
        $titulo = $subasta['titulo'];
        $enlace = "subastas?id={$subastaId}";
        $mejor = $this->subastas->mejorPuja($subastaId);

        if ($mejor === null) {
            $this->subastas->cerrar($subastaId, 'DESIERTA', null, $ahora);
            $this->animales->marcarEstadoAnimales([$subasta['animalId']], 'ACTIVO');
            $this->notificaciones->crear($subasta['vendedorPersonaId'], 'SUBASTA_DESIERTA',
                "Tu subasta «{$titulo}» terminó sin pujas", 'El animal volvió a tu inventario en Mis animales.', $enlace);
            $this->bitacora->registrar('FINALIZAR', (string) $subastaId, ['estado' => 'ACTIVA'], ['estado' => 'DESIERTA'],
                'SUBASTA-' . $subastaId, entidad: 'SUBASTA', origen: self::ORIGEN);
            return ["notificaciones-{$subasta['vendedorPersonaId']}"];
        }

        $this->subastas->cerrar($subastaId, 'FINALIZADA', $mejor['pujaId'], $ahora);
        $this->animales->marcarEstadoAnimales([$subasta['animalId']], 'VENDIDO');
        // La venta queda registrada igual que una solicitud de compra aceptada (tbcompra y tbventa con el Comprador).
        $comprador = $this->compradores->buscarPorPersona($mejor['personaId']);
        $hecho = [
            'compradorId' => $comprador['compradorId'] ?? null,
            'fecha' => substr($ahora, 0, 10),
            'hora' => substr($ahora, 11, 8),
            'precio' => $mejor['monto'],
            'lugar' => 'Subasta en línea',
            'origen' => self::ORIGEN,
        ];
        $compraId = $this->animales->registrarCompra($subasta['animalId'], null, $subasta['fincaId'], $hecho);
        $this->animales->registrarVenta($subasta['animalId'], $subasta['productorId'], null, $subasta['fincaId'], $compraId, $hecho + [
            'proposito' => $subasta['animal']['proposito'],
            'edadMeses' => $subasta['animal']['edadMeses'],
            'peso' => $subasta['animal']['peso'],
            'razaSnapshot' => $subasta['animal']['raza'],
        ]);
        $precio = self::colones($mejor['monto']);
        $ganador = $this->subastas->contacto($mejor['personaId']);
        $this->notificaciones->crear($mejor['personaId'], 'SUBASTA_GANADA', "¡Ganaste la subasta «{$titulo}»!",
            "Precio final {$precio}. En la subasta tienes los datos del vendedor, la ubicación de la finca y los fletes que la cubren.", $enlace);
        $this->notificaciones->crear($subasta['vendedorPersonaId'], 'SUBASTA_FINALIZADA', "Tu subasta «{$titulo}» terminó",
            'Ganó ' . ($ganador['nombre'] ?? 'un comprador') . " con {$precio}. Sus datos de contacto están en la subasta.", $enlace);
        $this->bitacora->registrar('FINALIZAR', (string) $subastaId, ['estado' => 'ACTIVA'],
            ['estado' => 'FINALIZADA', 'pujaGanadoraId' => $mejor['pujaId'], 'monto' => $mejor['monto'], 'compraId' => $compraId],
            'SUBASTA-' . $subastaId, entidad: 'SUBASTA', origen: self::ORIGEN);

        return ["notificaciones-{$mejor['personaId']}", "notificaciones-{$subasta['vendedorPersonaId']}"];
    }

    /** ₡1.250.000, igual que formatPrice() del navegador. */
    public static function colones(float $monto): string
    {
        return '₡' . number_format($monto, 0, ',', '.');
    }
}
