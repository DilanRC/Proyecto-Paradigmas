<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class TipoIdentificacion
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function listarActivos(): array
    {
        $sentencia = $this->conexion->query(
            'SELECT tbidentificaciontipoId AS tipoId, tbidentificaciontipoCodigo AS codigo, tbidentificaciontipoNombre AS nombre
             FROM tbidentificaciontipo WHERE tbidentificaciontipoEstado = 1 ORDER BY tbidentificaciontipoId'
        );

        return $sentencia->fetchAll();
    }

    public function buscarActivoPorId(int $id, bool $bloquear = false): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbidentificaciontipoId, tbidentificaciontipoCodigo, tbidentificaciontipoNombre
             FROM tbidentificaciontipo WHERE tbidentificaciontipoId = :id AND tbidentificaciontipoEstado = 1 LIMIT 1'
             . ($bloquear ? ' FOR SHARE' : '')
        );
        $sentencia->execute(['id' => $id]);
        $tipo = $sentencia->fetch();

        return $tipo === false ? null : $tipo;
    }
}
