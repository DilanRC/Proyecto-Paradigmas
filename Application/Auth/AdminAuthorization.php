<?php

declare(strict_types=1);

namespace Application\Auth;

use Application\HttpException;
use PDO;
use PDOException;

final class AdminAuthorization
{
    /**
     * La política vive en la base y no se acepta desde el navegador. El JWT ya
     * fue validado por SupabaseActorResolver antes de llegar aquí.
     */
    public static function require(ActorContext $actor, PDO $conexion): void
    {
        if (!$actor->estaAutenticado()) {
            throw new HttpException('Debe autenticarse para ejecutar esta operación.', 401);
        }

        try {
            $sentencia = $conexion->prepare(
                'SELECT tbadministradorid FROM tbadministrador
                 WHERE LOWER(tbadministradorcorreoelectronico) = LOWER(:correo)
                   AND tbadministradorestado = 1
                 ORDER BY tbadministradorid'
            );
            $sentencia->execute(['correo' => $actor->correoElectronico]);
            $autorizado = $sentencia->fetchColumn() !== false;
        } catch (PDOException) {
            throw new HttpException('No fue posible comprobar la autorización administrativa.', 503);
        }

        if (!$autorizado) {
            throw new HttpException('La cuenta no tiene autorización administrativa.', 403);
        }
    }
}
