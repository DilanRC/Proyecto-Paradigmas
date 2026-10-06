<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Política técnica de acceso administrativo (tbadministrador): un correo con
 * estado 1 entra al panel. Sin UNIQUE en la base: la unicidad del correo se
 * garantiza aquí, bajo NamedLock (ver Decisiones, sección 0 del plan).
 */
final class Administrador
{
    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, 'tindercows_administrador_alta');
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, 'tindercows_administrador_alta');
        }
    }

    public function listar(): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado
             FROM tbadministrador
             ORDER BY tbadministradorestado DESC, tbadministradorcorreoelectronico'
        );
        $sentencia->execute();

        return array_map(fn (array $fila): array => $this->mapear($fila), $sentencia->fetchAll());
    }

    /** Sin distinguir mayúsculas, igual que AdminAuthorization. */
    public function buscarPorCorreo(string $correo): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado
             FROM tbadministrador
             WHERE LOWER(tbadministradorcorreoelectronico) = LOWER(:correo)
             ORDER BY tbadministradorid'
        );
        $sentencia->execute(['correo' => $correo]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    public function bloquearPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado
             FROM tbadministrador WHERE tbadministradorid = :id FOR UPDATE'
        );
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    public function contarActivos(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COUNT(*) FROM tbadministrador WHERE tbadministradorestado = 1');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function crear(string $correo): int
    {
        $siguiente = $this->conexion->prepare('SELECT COALESCE(MAX(tbadministradorid), 0) + 1 FROM tbadministrador');
        $siguiente->execute();
        $id = (int) $siguiente->fetchColumn();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbadministrador (tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado)
             VALUES (:id, :correo, 1)'
        );
        $sentencia->execute(['id' => $id, 'correo' => $correo]);

        return $id;
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbadministrador SET tbadministradorestado = :estado WHERE tbadministradorid = :id'
        );
        $sentencia->execute(['estado' => $activo ? 1 : 0, 'id' => $id]);
    }

    private function mapear(array $fila): array
    {
        return [
            'administradorId' => (int) $fila['tbadministradorid'],
            'correoElectronico' => $fila['tbadministradorcorreoelectronico'],
            'estado' => (int) $fila['tbadministradorestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
        ];
    }
}
