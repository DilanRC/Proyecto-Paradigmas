<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Histórico de los cambios de correo de una Persona. Cada fila es un hecho: se pidió
 * cambiar del correo anterior al nuevo para la cuenta de Supabase indicada (sujeto) y
 * quedó PENDIENTE, APLICADO o CANCELADO. La fila PENDIENTE se guarda antes de llamar a
 * Supabase; con ella se concilian los dos sistemas si la operación se interrumpe.
 * Los ids salen de MAX()+1 bajo su NamedLock.
 */
final class PersonaCorreoCambio
{
    public const PENDIENTE = 'PENDIENTE';
    public const APLICADO = 'APLICADO';
    public const CANCELADO = 'CANCELADO';

    private const LOCK = 'tindercows_persona_correo_cambio';

    public function __construct(private PDO $conexion)
    {
    }

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK);
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, self::LOCK);
        }
    }

    public function crear(int $personaId, string $sujeto, string $anterior, string $nuevo): int
    {
        return $this->ejecutarConBloqueoAlta(function () use ($personaId, $sujeto, $anterior, $nuevo): int {
            $siguiente = $this->conexion->prepare(
                'SELECT COALESCE(MAX(tbpersonacorreocambioid), 0) + 1 FROM tbpersonacorreocambio'
            );
            $siguiente->execute();
            $id = (int) $siguiente->fetchColumn();
            $this->conexion->prepare(
                'INSERT INTO tbpersonacorreocambio (tbpersonacorreocambioid, tbpersonaid, tbpersonacorreocambiosujeto,
                    tbpersonacorreocambioanterior, tbpersonacorreocambionuevo, tbpersonacorreocambioestado,
                    tbpersonacorreocambiofecha, tbpersonacorreocambiofecharesolucion)
                 VALUES (:id, :personaId, :sujeto, :anterior, :nuevo, :estado, :fecha, NULL)'
            )->execute([
                'id' => $id,
                'personaId' => $personaId,
                'sujeto' => $sujeto,
                'anterior' => $anterior,
                'nuevo' => $nuevo,
                'estado' => self::PENDIENTE,
                'fecha' => gmdate('Y-m-d H:i:s'),
            ]);

            return $id;
        });
    }

    /** El cambio pendiente más reciente de una cuenta de Supabase. */
    public function pendientePorSujeto(string $sujeto): ?array
    {
        return $this->pendiente('tbpersonacorreocambiosujeto', $sujeto);
    }

    public function pendientePorPersona(int $personaId): ?array
    {
        return $this->pendiente('tbpersonaid', $personaId);
    }

    /** Solo cierra un cambio PENDIENTE: uno ya resuelto conserva su resultado y su fecha. */
    public function cerrar(int $id, string $estado): void
    {
        $this->conexion->prepare(
            'UPDATE tbpersonacorreocambio
             SET tbpersonacorreocambioestado = :estado, tbpersonacorreocambiofecharesolucion = :fecha
             WHERE tbpersonacorreocambioid = :id AND tbpersonacorreocambioestado = :pendiente'
        )->execute([
            'estado' => $estado,
            'fecha' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'pendiente' => self::PENDIENTE,
        ]);
    }

    private function pendiente(string $columna, string|int $valor): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT * FROM tbpersonacorreocambio
             WHERE {$columna} = :valor AND tbpersonacorreocambioestado = :estado
             ORDER BY tbpersonacorreocambioid DESC LIMIT 1"
        );
        $sentencia->execute(['valor' => $valor, 'estado' => self::PENDIENTE]);
        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);

        return $fila === false ? null : $fila;
    }
}
