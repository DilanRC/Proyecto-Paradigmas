<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Catálogos del animal (P2-2, DEC-ANIMAL-001): especies, tipos y razas; y vacunas (P2-3). El panel de
 * administración (P2-6) los crea, renombra y activa o desactiva; nada se borra porque los animales los referencian.
 * Alias SQL en minúscula: Postgres los pliega.
 */
final class AnimalCatalogo
{
    /** Tablas por catálogo. `especie`: el registro pertenece a una especie; `sexo`: columna del sexo (solo tipos). */
    public const TABLAS = [
        'ESPECIE' => ['tabla' => 'tbespecie', 'id' => 'tbespecieid', 'nombre' => 'tbespecienombre', 'activo' => 'tbespecieactivo', 'especie' => false, 'sexo' => null],
        'TIPO' => ['tabla' => 'tbanimaltipo', 'id' => 'tbanimaltipoid', 'nombre' => 'tbanimaltiponombre', 'activo' => 'tbanimaltipoactivo', 'especie' => true, 'sexo' => 'tbanimaltiposexo'],
        'RAZA' => ['tabla' => 'tbraza', 'id' => 'tbrazaid', 'nombre' => 'tbrazanombre', 'activo' => 'tbrazaactivo', 'especie' => true, 'sexo' => null],
        'VACUNA' => ['tabla' => 'tbvacuna', 'id' => 'tbvacunaid', 'nombre' => 'tbvacunanombre', 'activo' => 'tbvacunaactivo', 'especie' => false, 'sexo' => null],
    ];
    // ponytail: un lock para los cuatro catálogos; las altas son raras y solo las hace un administrador.
    private const LOCK_ALTA = 'tindercows_catalogo_alta';

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK_ALTA);
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, self::LOCK_ALTA);
        }
    }

    /**
     * Todos los registros de un catálogo, también los inactivos (panel admin).
     *
     * @return array<int,array{id:int,nombre:string,especieId:?int,sexo:?string,activo:bool}>
     */
    public function listarAdmin(string $catalogo): array
    {
        $t = self::TABLAS[$catalogo];
        $sentencia = $this->conexion->prepare($this->seleccionAdmin($t) . " ORDER BY {$t['nombre']}");
        $sentencia->execute();

        return array_map(self::mapearAdmin(...), $sentencia->fetchAll());
    }

    /** @return array{id:int,nombre:string,especieId:?int,sexo:?string,activo:bool}|null */
    public function buscarAdmin(string $catalogo, int $id): ?array
    {
        $t = self::TABLAS[$catalogo];
        $fila = $this->uno($this->seleccionAdmin($t) . " WHERE {$t['id']} = :id", $id);

        return $fila === null ? null : self::mapearAdmin($fila);
    }

    /** ¿Ya hay otro registro con ese nombre (sin distinguir mayúsculas) en la misma especie? */
    public function existeNombre(string $catalogo, string $nombre, ?int $especieId, ?int $exceptoId = null): bool
    {
        $t = self::TABLAS[$catalogo];
        $sql = "SELECT COUNT(*) FROM {$t['tabla']} WHERE LOWER({$t['nombre']}) = LOWER(:nombre)";
        $parametros = ['nombre' => $nombre];
        if ($t['especie']) {
            $sql .= ' AND tbespecieid = :especieId';
            $parametros['especieId'] = $especieId;
        }
        if ($exceptoId !== null) {
            $sql .= " AND {$t['id']} <> :exceptoId";
            $parametros['exceptoId'] = $exceptoId;
        }
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute($parametros);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /** Requiere el lock de alta. Nace activo. */
    public function crear(string $catalogo, string $nombre, ?int $especieId, ?string $sexo): int
    {
        $t = self::TABLAS[$catalogo];
        $id = (int) $this->conexion->query("SELECT COALESCE(MAX({$t['id']}), 0) + 1 FROM {$t['tabla']}")->fetchColumn();
        $columnas = [$t['id'] => $id, $t['nombre'] => $nombre, $t['activo'] => 1];
        if ($t['especie']) $columnas['tbespecieid'] = $especieId;
        if ($t['sexo'] !== null) $columnas[$t['sexo']] = $sexo;
        $marcas = implode(', ', array_map(static fn (string $c): string => ":{$c}", array_keys($columnas)));
        $this->conexion->prepare("INSERT INTO {$t['tabla']} (" . implode(', ', array_keys($columnas)) . ") VALUES ({$marcas})")
            ->execute($columnas);

        return $id;
    }

    /** @param array{nombre?:string,sexo?:?string,activo?:bool} $cambios */
    public function actualizar(string $catalogo, int $id, array $cambios): void
    {
        $t = self::TABLAS[$catalogo];
        $columnas = [];
        if (array_key_exists('nombre', $cambios)) $columnas[$t['nombre']] = $cambios['nombre'];
        if (array_key_exists('sexo', $cambios) && $t['sexo'] !== null) $columnas[$t['sexo']] = $cambios['sexo'];
        if (array_key_exists('activo', $cambios)) $columnas[$t['activo']] = $cambios['activo'] ? 1 : 0;
        if ($columnas === []) return;
        $asignaciones = implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", array_keys($columnas)));
        $this->conexion->prepare("UPDATE {$t['tabla']} SET {$asignaciones} WHERE {$t['id']} = :id")
            ->execute($columnas + ['id' => $id]);
    }

    private function seleccionAdmin(array $t): string
    {
        return "SELECT {$t['id']} AS id, {$t['nombre']} AS nombre, "
            . ($t['especie'] ? 'tbespecieid' : 'NULL') . ' AS especieid, '
            . ($t['sexo'] ?? 'NULL') . " AS sexo, {$t['activo']} AS activo FROM {$t['tabla']}";
    }

    private static function mapearAdmin(array $f): array
    {
        return [
            'id' => (int) $f['id'], 'nombre' => $f['nombre'],
            'especieId' => $f['especieid'] === null ? null : (int) $f['especieid'],
            'sexo' => $f['sexo'], 'activo' => (int) $f['activo'] === 1,
        ];
    }

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

    /** @return array<int,array{vacunaId:int,nombre:string}> solo activas (P2-3) */
    public function vacunas(): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbvacunaid AS id, tbvacunanombre AS nombre FROM tbvacuna WHERE tbvacunaactivo = 1 ORDER BY tbvacunaid'
        );
        $sentencia->execute();

        return array_map(static fn (array $f): array => ['vacunaId' => (int) $f['id'], 'nombre' => $f['nombre']], $sentencia->fetchAll());
    }

    /** @return array{vacunaId:int,nombre:string,activo:bool}|null */
    public function vacuna(int $id): ?array
    {
        $fila = $this->uno('SELECT tbvacunaid AS id, tbvacunanombre AS nombre, tbvacunaactivo AS activo
                            FROM tbvacuna WHERE tbvacunaid = :id', $id);

        return $fila === null ? null
            : ['vacunaId' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'activo' => (int) $fila['activo'] === 1];
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
