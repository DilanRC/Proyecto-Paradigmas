<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class FincaDireccion
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly Direccion $direccion,
    ) {}

  public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        try {
            return $this->direccion->ejecutarConBloqueoAlta($operacion);
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    /**
     * Creación explícita: solo permitida cuando la finca todavía no tiene
     * ninguna fila de enlace.
     */
    public function crear(int $fincaId, array $direccion): void
    {
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbfincadireccion WHERE tbfincaid = :fincaId'
        );
        $comprobar->execute(['fincaId' => $fincaId]);
        if ((int) $comprobar->fetchColumn() !== 0) {
            throw new \RuntimeException('La finca ya tiene una dirección registrada; use actualizar.');
        }
        $this->insertarEnlace($fincaId, $direccion);
    }

    public function actualizar(int $fincaId, array $direccion): void
    {
        $direccionId = $this->obtenerDireccionId($fincaId);
        $this->direccion->actualizar($direccionId, $direccion);
    }

    public function buscar(int $fincaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionid FROM tbfincadireccion WHERE tbfincaid = :fincaId'
        );
        $sentencia->execute(['fincaId' => $fincaId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) > 1) {
            throw new \RuntimeException(
                'La finca tiene más de una dirección registrada; revise la integridad de los datos.'
            );
        }
        if ($filas === []) {
            return null;
        }

        return $this->direccion->buscar((int) $filas[0]);
    }

    public function vaciar(int $fincaId): void
    {
        $this->actualizar($fincaId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    private function obtenerDireccionId(int $fincaId): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionid FROM tbfincadireccion WHERE tbfincaid = :fincaId'
        );
        $sentencia->execute(['fincaId' => $fincaId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) !== 1) {
            throw new \RuntimeException('La finca no conserva exactamente una dirección.');
        }

        return (int) $filas[0];
    }

    private function insertarEnlace(int $fincaId, array $direccion): void
    {
        $direccionId = $this->direccion->crear($direccion);

        $enlaceId = $this->siguienteEnlaceId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbfincadireccion
             (tbfincadireccionid, tbfincaid, tbdireccionid)
             VALUES (:enlaceId, :fincaId, :direccionId)'
        );
        $sentencia->execute([
            'enlaceId' => $enlaceId,
            'fincaId' => $fincaId,
            'direccionId' => $direccionId,
        ]);
    }

    private function siguienteEnlaceId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbfincadireccionid), 0) + 1 FROM tbfincadireccion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_finca_direccion_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_finca_direccion_alta');
    }
}