<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/** Hechos de interacción de una Persona con una publicación. */
final class PublicacionInteraccion
{
    private const TIPOS = ['ME_INTERESA', 'PASAR', 'CONTACTAR'];
    private const ACCIONES = ['REGISTRAR'];

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

    public function registrar(int $personaId, int $publicacionId, string $tipo, string $origen): int
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de interacción no aprobado.');
        }
        $accion = self::ACCIONES[0];
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
