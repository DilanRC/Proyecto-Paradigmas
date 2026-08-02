<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorDireccion
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function crear(string $identificacionNumero, array $direccion): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductoresdireccion
             (tbproductoresIdentificacionNumero, tbproductoresdireccionProvincia,
              tbproductoresdireccionCanton, tbproductoresdireccionDistrito,
              tbproductoresdireccionPueblo, tbproductoresdireccionSenas)
             VALUES (:identificacionNumero, :provincia, :canton, :distrito, :pueblo, :senas)'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero, ...$direccion]);
    }

    public function actualizar(string $identificacionNumero, array $direccion): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductoresdireccion
             SET tbproductoresdireccionProvincia = :provincia,
                 tbproductoresdireccionCanton = :canton,
                 tbproductoresdireccionDistrito = :distrito,
                 tbproductoresdireccionPueblo = :pueblo,
                 tbproductoresdireccionSenas = :senas
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero, ...$direccion]);
        if ($sentencia->rowCount() === 0) {
            $comprobar = $this->conexion->prepare(
                'SELECT 1 FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
            );
            $comprobar->execute(['identificacionNumero' => $identificacionNumero]);
            if ($comprobar->fetchColumn() === false) {
                throw new \RuntimeException('El productor no conserva su dirección obligatoria.');
            }
        }
    }
}
