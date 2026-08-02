<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ParticipanteDireccion
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function crearPrincipal(int $participanteId, array $direccion): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbparticipantedireccion
             (tbparticipanteId, tbparticipantedireccionProvincia, tbparticipantedireccionCanton,
              tbparticipantedireccionDistrito, tbparticipantedireccionPueblo, tbparticipantedireccionSenas,
              tbparticipantedireccionEsPrincipal, tbparticipantedireccionEstado)
             VALUES (:participanteId, :provincia, :canton, :distrito, :pueblo, :senas, 1, 1)'
        );
        $sentencia->execute(['participanteId' => $participanteId, ...$direccion]);
    }

    public function actualizarPrincipal(int $participanteId, array $direccion): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbparticipantedireccion
             SET tbparticipantedireccionProvincia = :provincia,
                 tbparticipantedireccionCanton = :canton,
                 tbparticipantedireccionDistrito = :distrito,
                 tbparticipantedireccionPueblo = :pueblo,
                 tbparticipantedireccionSenas = :senas
             WHERE tbparticipanteId = :participanteId
               AND tbparticipantedireccionEsPrincipal = 1
               AND tbparticipantedireccionEstado = 1'
        );
        $sentencia->execute(['participanteId' => $participanteId, ...$direccion]);
        if ($sentencia->rowCount() === 0 && $this->contarPrincipalesActivas($participanteId) !== 1) {
            throw new \RuntimeException('El participante no tiene una dirección principal activa válida.');
        }
    }

    public function contarPrincipalesActivas(int $participanteId): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbparticipantedireccion
             WHERE tbparticipanteId = :participanteId
               AND tbparticipantedireccionEsPrincipal = 1
               AND tbparticipantedireccionEstado = 1'
        );
        $sentencia->execute(['participanteId' => $participanteId]);

        return (int) $sentencia->fetchColumn();
    }
}
