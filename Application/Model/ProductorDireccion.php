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
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
        );
        $comprobar->execute(['identificacionNumero' => $identificacionNumero]);
        if ((int) $comprobar->fetchColumn() !== 0) {
            throw new \RuntimeException('El productor ya tiene una dirección registrada.');
        }
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
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
        );
        $comprobar->execute(['identificacionNumero' => $identificacionNumero]);
        if ((int) $comprobar->fetchColumn() !== 1) {
            throw new \RuntimeException('El productor no conserva exactamente una dirección.');
        }
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
    }
}
