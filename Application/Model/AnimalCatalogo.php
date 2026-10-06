<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Catálogos del animal (P2-2, DEC-ANIMAL-001): especies, tipos y razas. Solo lectura aquí;
 * el panel de administración (P2-6) los gestionará con la columna "activo".
 * Alias SQL en minúscula: Postgres los pliega.
 */
final class AnimalCatalogo
{
    public function __construct(private readonly PDO $conexion) {}

    /** @return array<int,array{especieId:int,nombre:string}> solo activas */
    public function especies(): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbespecieid AS id, tbespecienombre AS nombre FROM tbespecie
             WHERE tbespecieactivo = 1 ORDER BY tbespecienombre'
        );
        $sentencia->execute();

        return array_map(static fn (array $f): array => ['especieId' => (int) $f['id'], 'nombre' => $f['nombre']], $sentencia->fetchAll());
    }

    /** @return array<int,array{tipoId:int,especieId:int,nombre:string,sexo:?string}> solo activos */
    public function tipos(?int $especieId = null): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbanimaltipoid AS id, tbespecieid AS especieid, tbanimaltiponombre AS nombre, tbanimaltiposexo AS sexo
             FROM tbanimaltipo
             WHERE tbanimaltipoactivo = 1' . ($especieId === null ? '' : ' AND tbespecieid = :especieId') . '
             ORDER BY tbespecieid, tbanimaltipoid'
        );
        $sentencia->execute($especieId === null ? [] : ['especieId' => $especieId]);

        return array_map(static fn (array $f): array => [
            'tipoId' => (int) $f['id'], 'especieId' => (int) $f['especieid'], 'nombre' => $f['nombre'], 'sexo' => $f['sexo'],
        ], $sentencia->fetchAll());
    }

    /** @return array<int,array{razaId:int,especieId:int,nombre:string}> solo activas */
    public function razas(?int $especieId = null): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbrazaid AS id, tbespecieid AS especieid, tbrazanombre AS nombre
             FROM tbraza
             WHERE tbrazaactivo = 1' . ($especieId === null ? '' : ' AND tbespecieid = :especieId') . '
             ORDER BY tbespecieid, tbrazanombre'
        );
        $sentencia->execute($especieId === null ? [] : ['especieId' => $especieId]);

        return array_map(static fn (array $f): array => [
            'razaId' => (int) $f['id'], 'especieId' => (int) $f['especieid'], 'nombre' => $f['nombre'],
        ], $sentencia->fetchAll());
    }

    /** @return array{especieId:int,nombre:string,activo:bool}|null */
    public function especie(int $id): ?array
    {
        $fila = $this->uno('SELECT tbespecieid AS id, tbespecienombre AS nombre, tbespecieactivo AS activo
                            FROM tbespecie WHERE tbespecieid = :id', $id);

        return $fila === null ? null
            : ['especieId' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'activo' => (int) $fila['activo'] === 1];
    }

    /** @return array{tipoId:int,especieId:int,nombre:string,sexo:?string,activo:bool}|null */
    public function tipo(int $id): ?array
    {
        $fila = $this->uno('SELECT tbanimaltipoid AS id, tbespecieid AS especieid, tbanimaltiponombre AS nombre,
                                   tbanimaltiposexo AS sexo, tbanimaltipoactivo AS activo
                            FROM tbanimaltipo WHERE tbanimaltipoid = :id', $id);

        return $fila === null ? null : [
            'tipoId' => (int) $fila['id'], 'especieId' => (int) $fila['especieid'], 'nombre' => $fila['nombre'],
            'sexo' => $fila['sexo'], 'activo' => (int) $fila['activo'] === 1,
        ];
    }

    /** @return array{razaId:int,especieId:int,nombre:string,activo:bool}|null */
    public function raza(int $id): ?array
    {
        $fila = $this->uno('SELECT tbrazaid AS id, tbespecieid AS especieid, tbrazanombre AS nombre, tbrazaactivo AS activo
                            FROM tbraza WHERE tbrazaid = :id', $id);

        return $fila === null ? null : [
            'razaId' => (int) $fila['id'], 'especieId' => (int) $fila['especieid'], 'nombre' => $fila['nombre'],
            'activo' => (int) $fila['activo'] === 1,
        ];
    }

    private function uno(string $sql, int $id): ?array
    {
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }
}
