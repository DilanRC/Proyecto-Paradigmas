<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorDireccion
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

    public function crearVacia(int $productorId): void
    {
        $this->insertarEnlace($productorId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    public function crear(int $productorId, array $direccion): void
    {
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $comprobar->execute(['productorId' => $productorId]);
        if ((int) $comprobar->fetchColumn() !== 0) {
            throw new \RuntimeException('El productor ya tiene una dirección registrada; use actualizar.');
        }
        $this->insertarEnlace($productorId, $direccion);
    }

    public function actualizar(int $productorId, array $direccion): void
    {
        $direccionId = $this->obtenerDireccionId($productorId);
        $this->direccion->actualizar($direccionId, $direccion);
    }

    public function buscar(int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) > 1) {
            throw new \RuntimeException(
                'El productor tiene más de una dirección registrada; revise la integridad de los datos.'
            );
        }
        if ($filas === []) {
            return null;
        }

        return $this->direccion->buscar((int) $filas[0]);
    }

    public function vaciar(int $productorId): void
    {
        $this->actualizar($productorId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    private function obtenerDireccionId(int $productorId): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) !== 1) {
            throw new \RuntimeException('El productor no conserva exactamente una dirección.');
        }

        return (int) $filas[0];
    }

    private function insertarEnlace(int $productorId, array $direccion): void
    {
        $direccionId = $this->direccion->crearConBloqueoExistente($direccion);

        $enlaceId = $this->siguienteEnlaceId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductordireccion
             (tbproductordireccionid, tbproductorid, tbdireccionid)
             VALUES (:enlaceId, :productorId, :direccionId)'
        );
        $sentencia->execute([
            'enlaceId' => $enlaceId,
            'productorId' => $productorId,
            'direccionId' => $direccionId,
        ]);
    }

    private function siguienteEnlaceId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbproductordireccionid), 0) + 1 FROM tbproductordireccion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_productor_direccion_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_productor_direccion_alta');
    }
}
