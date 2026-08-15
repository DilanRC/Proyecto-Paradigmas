<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Direccion
{
    public function __construct(private readonly PDO $conexion) {}

    /**
     * Crea una fila nueva en tbdireccion y devuelve su tbdireccionid.
     * Se autobloquea: adquiere y libera el lock en la misma llamada, por lo
     * que es seguro invocarlo desde cualquier flujo (productor, finca, etc.)
     * sin que el llamador deba coordinar el lock por fuera.
     */
    public function crear(array $direccion): int
    {
        $this->adquirirBloqueoAlta();
        try {
            $direccionId = $this->siguienteId();
            $sentencia = $this->conexion->prepare(
                'INSERT INTO tbdireccion
                 (tbdireccionid, tbdireccionprovincia, tbdireccioncanton,
                  tbdirecciondistrito, tbdireccionpueblo, tbdireccionsenas)
                 VALUES (:direccionId, :provincia, :canton, :distrito, :pueblo, :senas)'
            );
            $sentencia->execute(['direccionId' => $direccionId, ...$direccion]);

            return $direccionId;
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    public function actualizar(int $direccionId, array $direccion): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbdireccion
             SET tbdireccionprovincia = :provincia,
                 tbdireccioncanton = :canton,
                 tbdirecciondistrito = :distrito,
                 tbdireccionpueblo = :pueblo,
                 tbdireccionsenas = :senas
             WHERE tbdireccionid = :direccionId'
        );
        $sentencia->execute(['direccionId' => $direccionId, ...$direccion]);
    }

    public function buscar(int $direccionId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionprovincia AS provincia,
                    tbdireccioncanton AS canton,
                    tbdirecciondistrito AS distrito,
                    tbdireccionpueblo AS pueblo,
                    tbdireccionsenas AS senas
             FROM tbdireccion
             WHERE tbdireccionid = :direccionId'
        );
        $sentencia->execute(['direccionId' => $direccionId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbdireccionid), 0) + 1 FROM tbdireccion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_direccion_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_direccion_alta');
    }
}