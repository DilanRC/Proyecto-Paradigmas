<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ParticipanteIdentificacion
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function buscarPorTipoYNumero(int $tipoId, string $numeroNormalizado): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT i.tbparticipanteidentificacionId, i.tbparticipanteId, p.tbparticipanteEstado
             FROM tbparticipanteidentificacion i
             INNER JOIN tbparticipante p ON p.tbparticipanteId = i.tbparticipanteId
             WHERE i.tbidentificaciontipoId = :tipoId
               AND i.tbparticipanteidentificacionNumeroNormalizado = :numeroNormalizado
             LIMIT 1'
        );
        $sentencia->execute(['tipoId' => $tipoId, 'numeroNormalizado' => $numeroNormalizado]);
        $identificacion = $sentencia->fetch();

        return $identificacion === false ? null : $identificacion;
    }

    public function crearPrincipal(int $participanteId, int $tipoId, string $numero, string $normalizado): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbparticipanteidentificacion
             (tbparticipanteId, tbidentificaciontipoId, tbparticipanteidentificacionNumero,
              tbparticipanteidentificacionNumeroNormalizado, tbparticipanteidentificacionEsPrincipal,
              tbparticipanteidentificacionEstado)
             VALUES (:participanteId, :tipoId, :numero, :normalizado, 1, 1)'
        );
        $sentencia->execute(compact('participanteId', 'tipoId', 'numero', 'normalizado'));
    }

    public function actualizarPrincipal(int $participanteId, int $tipoId, string $numero, string $normalizado): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbparticipanteidentificacion
             SET tbidentificaciontipoId = :tipoId,
                 tbparticipanteidentificacionNumero = :numero,
                 tbparticipanteidentificacionNumeroNormalizado = :normalizado
             WHERE tbparticipanteId = :participanteId
               AND tbparticipanteidentificacionEsPrincipal = 1
               AND tbparticipanteidentificacionEstado = 1'
        );
        $sentencia->execute(compact('participanteId', 'tipoId', 'numero', 'normalizado'));
        if ($sentencia->rowCount() === 0 && $this->contarPrincipalesActivas($participanteId) !== 1) {
            throw new \RuntimeException('El participante no tiene una identificación principal activa válida.');
        }
    }

    public function contarPrincipalesActivas(int $participanteId): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbparticipanteidentificacion
             WHERE tbparticipanteId = :participanteId
               AND tbparticipanteidentificacionEsPrincipal = 1
               AND tbparticipanteidentificacionEstado = 1'
        );
        $sentencia->execute(['participanteId' => $participanteId]);

        return (int) $sentencia->fetchColumn();
    }

    public function obtenerTipoPrincipalId(int $participanteId): ?int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbidentificaciontipoId FROM tbparticipanteidentificacion
             WHERE tbparticipanteId = :participanteId
               AND tbparticipanteidentificacionEsPrincipal = 1
               AND tbparticipanteidentificacionEstado = 1 LIMIT 1'
        );
        $sentencia->execute(['participanteId' => $participanteId]);
        $tipoId = $sentencia->fetchColumn();

        return $tipoId === false ? null : (int) $tipoId;
    }
}
