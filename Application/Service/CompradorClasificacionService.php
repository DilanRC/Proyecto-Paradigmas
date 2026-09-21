<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;
use Application\Model\Comprador;
use Application\Model\Persona;
use PDO;
use RuntimeException;

/**
 * Operaciones por persona del contexto Comprador (DEC-28/29).
 *
 * Ubica la inscripción del comprador en la Persona y no en el Productor: la
 * misma persona puede ser compradora sin ser productora. La fuente de verdad
 * del contexto es tbcomprador (tbpersonaid + tbcompradorestado). Las
 * escrituras corren dentro de la transacción y del bloqueo nombrado del
 * llamador, igual que los demás contextos de Persona.
 *
 * Las operaciones son idempotentes por definición del contexto: inscribir a un
 * comprador ya activo no duplica la fila, desactivar o reactivar a un
 * comprador ya en ese estado no toca la base. Ante una persona inactiva o con
 * datos personales incompatibles, la inscripción y la reactivación responden
 * 409 (PersonaConflictException / HttpException).
 *
 * tbproductorclasificacionperiodo (tipo=COMPRADOR) queda fuera del camino de
 * negocio de este servicio: es señal analítica de la clasificación histórica
 * del productor, alimentada por Tools/backfill-clasificacion-comprador.php.
 * Las constantes TIPO y MOTIVO_MIGRACION se conservan para ese backfill.
 */
final class CompradorClasificacionService
{
    public const TIPO = 'COMPRADOR';
    public const MOTIVO_MIGRACION = 'MIGRACION_TBCOMPRADOR_LEGACY';

    private Comprador $comprador;
    private Persona $persona;

    public function __construct(private readonly PDO $conexion)
    {
        $this->comprador = new Comprador($conexion);
        $this->persona = new Persona($conexion);
    }

    /** Contexto Comprador de la persona, o null si no está inscrita. */
    public function buscar(string $identificacionNumero): ?array
    {
        return $this->comprador->buscar($identificacionNumero);
    }

    /** La persona tiene contexto Comprador registrado (esté o no activo). */
    public function esComprador(string $identificacionNumero): bool
    {
        return $this->comprador->buscar($identificacionNumero) !== null;
    }

    /**
     * Inscribe a la persona como comprador.
     *
     * @return array{cambio: bool, nuevo: bool, comprador: array}
     *   - nuevo: false cuando el contexto ya existía (activo o inactivo)
     *   - cambio: true cuando se creó o se reactivó el contexto
     */
    public function inscribir(array $datos): array
    {
        $identificacion = $datos['identificacionNumero'];
        $existente = $this->comprador->bloquear($identificacion);
        // La validación crea la persona cuando no existe y lanza
        // PersonaConflictException cuando ya existe con otros datos o está
        // inactiva. Es la única fuente del "duplicado imposible" por
        // identificación.
        $persona = $this->persona->obtenerOCrear($datos);

        if ($existente === null) {
            $this->comprador->crear($datos, $persona);

            return [
                'cambio' => true,
                'nuevo' => true,
                'comprador' => $this->obligar($this->obtener($identificacion)),
            ];
        }
        if ((int) $existente['tbcompradorestado'] === 1 && (int) $existente['tbpersonaestado'] === 1) {
            return [
                'cambio' => false,
                'nuevo' => false,
                'comprador' => $this->obligar($this->obtener($identificacion)),
            ];
        }
        $this->comprador->cambiarEstado($identificacion, true);

        return [
            'cambio' => true,
            'nuevo' => false,
            'comprador' => $this->obligar($this->obtener($identificacion)),
        ];
    }

    /**
     * Desactiva el contexto Comprador (idempotente). Devuelve null si la
     * persona no tiene contexto inscrito. Ante una persona inactiva responde
     * 409: no se operan capacidades de una persona sin disponibilidad.
     *
     * @return array{cambio: bool, comprador: array}|null
     */
    public function desactivar(string $identificacionNumero): ?array
    {
        $bloqueado = $this->comprador->bloquear($identificacionNumero);
        if ($bloqueado === null) {
            return null;
        }
        if ((int) $bloqueado['tbpersonaestado'] !== 1) {
            throw new HttpException('La persona está inactiva y no puede operar capacidades.', 409);
        }
        $comprador = $this->obtener($identificacionNumero);
        if ($comprador['estado'] === 'INACTIVO') {
            return ['cambio' => false, 'comprador' => $comprador];
        }
        $this->comprador->cambiarEstado($identificacionNumero, false);

        return [
            'cambio' => true,
            'comprador' => $this->obligar($this->obtener($identificacionNumero)),
        ];
    }

    /**
     * Reactiva el contexto Comprador (idempotente). Devuelve null si la
     * persona no tiene contexto inscrito. Ante una persona inactiva responde
     * 409.
     *
     * @return array{cambio: bool, comprador: array}|null
     */
    public function reactivar(string $identificacionNumero): ?array
    {
        $bloqueado = $this->comprador->bloquear($identificacionNumero);
        if ($bloqueado === null) {
            return null;
        }
        if ((int) $bloqueado['tbpersonaestado'] !== 1) {
            throw new HttpException('La persona está inactiva y no puede reactivar capacidades.', 409);
        }
        $comprador = $this->obtener($identificacionNumero);
        if ($comprador['estado'] === 'ACTIVO') {
            return ['cambio' => false, 'comprador' => $comprador];
        }
        $this->comprador->cambiarEstado($identificacionNumero, true);

        return [
            'cambio' => true,
            'comprador' => $this->obligar($this->obtener($identificacionNumero)),
        ];
    }

    private function obtener(string $identificacionNumero): array
    {
        return $this->obligar($this->comprador->buscar($identificacionNumero));
    }

    private function obligar(?array $comprador): array
    {
        return $comprador ?? throw new RuntimeException('No fue posible leer el contexto Comprador.');
    }
}