<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ParticipanteRol
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function asignar(int $participanteId, int $rolId): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado)
             VALUES (:participanteId, :rolId, 1)
             ON DUPLICATE KEY UPDATE tbparticipanterolEstado = VALUES(tbparticipanterolEstado)'
        );
        $sentencia->execute(['participanteId' => $participanteId, 'rolId' => $rolId]);
    }

    public function estaActivo(int $participanteId, int $rolId): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT 1 FROM tbparticipanterol
             WHERE tbparticipanteId = :participanteId AND tbrolId = :rolId AND tbparticipanterolEstado = 1'
        );
        $sentencia->execute(['participanteId' => $participanteId, 'rolId' => $rolId]);

        return $sentencia->fetchColumn() !== false;
    }
}
