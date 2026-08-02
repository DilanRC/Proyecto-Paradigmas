<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorDireccion
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function crear(int $productorId, array $direccion): void
    {
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorId = :productorId'
        );
        $comprobar->execute(['productorId' => $productorId]);
        if ((int) $comprobar->fetchColumn() !== 0) {
            throw new \RuntimeException('El productor ya tiene una dirección registrada.');
        }
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductordireccion
             (tbproductorId, tbproductordireccionProvincia,
              tbproductordireccionCanton, tbproductordireccionDistrito,
              tbproductordireccionPueblo, tbproductordireccionSenas)
             VALUES (:productorId, :provincia, :canton, :distrito, :pueblo, :senas)'
        );
        $sentencia->execute(['productorId' => $productorId, ...$direccion]);
    }

    public function actualizar(int $productorId, array $direccion): void
    {
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorId = :productorId'
        );
        $comprobar->execute(['productorId' => $productorId]);
        if ((int) $comprobar->fetchColumn() !== 1) {
            throw new \RuntimeException('El productor no conserva exactamente una dirección.');
        }
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductordireccion
             SET tbproductordireccionProvincia = :provincia,
                 tbproductordireccionCanton = :canton,
                 tbproductordireccionDistrito = :distrito,
                 tbproductordireccionPueblo = :pueblo,
                 tbproductordireccionSenas = :senas
             WHERE tbproductorId = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId, ...$direccion]);
    }
}
