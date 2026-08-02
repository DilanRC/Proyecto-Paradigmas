<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Rol
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function buscarActivoPorCodigo(string $codigo, bool $bloquear = false): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbrolId, tbrolCodigo, tbrolNombre FROM tbrol
             WHERE tbrolCodigo = :codigo AND tbrolEstado = 1 LIMIT 1' . ($bloquear ? ' FOR SHARE' : '')
        );
        $sentencia->execute(['codigo' => $codigo]);
        $rol = $sentencia->fetch();

        return $rol === false ? null : $rol;
    }
}
