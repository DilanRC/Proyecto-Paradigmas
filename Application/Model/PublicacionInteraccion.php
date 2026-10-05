<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/** Hechos de interacción de una Persona con una publicación. */
final class PublicacionInteraccion
{
    private const TIPOS = ['ME_INTERESA', 'PASAR', 'CONTACTAR'];
    private const ACCIONES = ['REGISTRAR', 'RETIRAR'];

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, 'tindercows_publicacion_interaccion_alta');
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, 'tindercows_publicacion_interaccion_alta');
        }
    }

    public function registrar(int $personaId, int $publicacionId, string $tipo, string $origen,
        string $accion = 'REGISTRAR'): int
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de interacción no aprobado.');
        }
        if (!in_array($accion, self::ACCIONES, true)) {
            throw new \InvalidArgumentException('Acción de interacción no aprobada.');
        }
        $this->exigirTransaccion();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbanimalpublicacioninteraccion
             (tbanimalpublicacioninteraccionid, tbpersonaid, tbanimalpublicacionid,
              tbanimalpublicacioninteracciontipo, tbanimalpublicacioninteraccionaccion,
              tbanimalpublicacioninteraccionfecha, tbanimalpublicacioninteraccionorigen)
             VALUES (:id, :personaId, :publicacionId, :tipo, :accion, :fecha, :origen)'
        );
        $id = $this->siguienteId();
        $sentencia->execute([
            'id' => $id,
            'personaId' => $personaId,
            'publicacionId' => $publicacionId,
            'tipo' => $tipo,
            'accion' => $accion,
            'fecha' => date('Y-m-d H:i:s'),
            'origen' => $origen,
        ]);

        return $id;
    }

    /**
     * El historial solo crece: la marca vigente es la última acción del par
     * (persona, publicación, tipo). REGISTRAR la deja marcada y RETIRAR la quita.
     */
    public function estaMarcada(int $personaId, int $publicacionId, string $tipo): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbanimalpublicacioninteraccionaccion
             FROM tbanimalpublicacioninteraccion
             WHERE tbpersonaid = :personaId AND tbanimalpublicacionid = :publicacionId
               AND tbanimalpublicacioninteracciontipo = :tipo
             ORDER BY tbanimalpublicacioninteraccionid DESC LIMIT 1'
        );
        $sentencia->execute(['personaId' => $personaId, 'publicacionId' => $publicacionId, 'tipo' => $tipo]);

        return $sentencia->fetchColumn() === 'REGISTRAR';
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbanimalpublicacioninteraccionid
             FROM tbanimalpublicacioninteraccion
             ORDER BY tbanimalpublicacioninteraccionid DESC
             LIMIT 1 FOR UPDATE'
        );
        $sentencia->execute();
        $ultimo = $sentencia->fetchColumn();

        return $ultimo === false ? 1 : (int) $ultimo + 1;
    }

    private function exigirTransaccion(): void
    {
        if (!$this->conexion->inTransaction()) {
            throw new \LogicException('La interacción debe registrarse dentro de una transacción.');
        }
    }
}
