<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Historial de vacunación de un animal (P2-3). Sin llaves ni UNIQUE: el dueño, la vacuna y los datos los valida PHP.
 * Alias SQL en minúscula (Postgres los pliega); las claves camelCase se arman al mapear.
 */
final class AnimalVacunacion
{
    private const LOCK_ALTA = 'tindercows_animal_vacunacion_alta';
    private const COLUMNAS_EDITABLES = [
        'vacunaId' => 'tbvacunaid',
        'fecha' => 'tbanimalvacunacionfecha',
        'dosis' => 'tbanimalvacunaciondosis',
        'lote' => 'tbanimalvacunacionlote',
        'aplicadaPor' => 'tbanimalvacunacionaplicadapor',
        'proximaDosis' => 'tbanimalvacunacionproximadosis',
        'observaciones' => 'tbanimalvacunacionobservaciones',
    ];

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
     * Un animal es del vendedor si es su dueño explícito, o si es el animal (o uno del lote) de una publicación suya.
     * Devuelve null si no existe o es ajeno: el controlador responde 404 sin distinguir.
     *
     * @return array{animalId:int,estado:?string}|null
     */
    public function animalPropio(int $animalId, int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT a.tbanimalid AS animalid, a.tbanimalestado AS estado
             FROM tbanimal a
             WHERE a.tbanimalid = :animalId
               AND (a.tbproductorid = :duenoId
                    OR EXISTS (SELECT 1 FROM tbanimalpublicacion p
                               WHERE p.tbproductorvendedorid = :vendedorId
                                 AND (p.tbanimalid = a.tbanimalid
                                      OR EXISTS (SELECT 1 FROM tbanimalpublicacionanimal l
                                                 WHERE l.tbanimalpublicacionid = p.tbanimalpublicacionid
                                                   AND l.tbanimalid = a.tbanimalid))))'
        );
        $sentencia->execute(['animalId' => $animalId, 'duenoId' => $productorId, 'vendedorId' => $productorId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : ['animalId' => (int) $fila['animalid'], 'estado' => $fila['estado']];
    }

    /** @return array<int,array<string,mixed>> del registro más reciente al más antiguo */
    public function listar(int $animalId): array
    {
        $sentencia = $this->conexion->prepare(
            $this->seleccion() . ' WHERE v.tbanimalid = :animalId
             ORDER BY v.tbanimalvacunacionfecha DESC, v.tbanimalvacunacionid DESC'
        );
        $sentencia->execute(['animalId' => $animalId]);

        return array_map(self::mapear(...), $sentencia->fetchAll());
    }

    /** El registro, sin comprobar el dueño: el controlador verifica el animal con animalPropio(). */
    public function buscar(int $vacunacionId): ?array
    {
        $sentencia = $this->conexion->prepare($this->seleccion() . ' WHERE v.tbanimalvacunacionid = :id');
        $sentencia->execute(['id' => $vacunacionId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : self::mapear($fila);
    }

    /** @param array<string,mixed> $datos animalId, vacunaId, fecha y los opcionales */
    public function crear(array $datos): int
    {
        $id = $this->siguienteId();
        $this->conexion->prepare(
            'INSERT INTO tbanimalvacunacion
             (tbanimalvacunacionid, tbanimalid, tbvacunaid, tbanimalvacunacionfecha, tbanimalvacunaciondosis,
              tbanimalvacunacionlote, tbanimalvacunacionaplicadapor, tbanimalvacunacionproximadosis,
              tbanimalvacunacionobservaciones, tbanimalvacunacionfecharegistro)
             VALUES (:id, :animalId, :vacunaId, :fecha, :dosis, :lote, :aplicadaPor, :proximaDosis, :observaciones, :registro)'
        )->execute([
            'id' => $id,
            'animalId' => $datos['animalId'],
            'vacunaId' => $datos['vacunaId'],
            'fecha' => $datos['fecha'],
            'dosis' => $datos['dosis'] ?? null,
            'lote' => $datos['lote'] ?? null,
            'aplicadaPor' => $datos['aplicadaPor'] ?? null,
            'proximaDosis' => $datos['proximaDosis'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'registro' => gmdate('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /** Solo cambia las claves presentes de COLUMNAS_EDITABLES. */
    public function actualizar(int $vacunacionId, array $campos): void
    {
        $asignaciones = [];
        $parametros = ['id' => $vacunacionId];
        foreach (self::COLUMNAS_EDITABLES as $clave => $columna) {
            if (array_key_exists($clave, $campos)) {
                $asignaciones[] = "{$columna} = :{$clave}";
                $parametros[$clave] = $campos[$clave];
            }
        }
        if ($asignaciones === []) {
            return;
        }
        $this->conexion->prepare(
            'UPDATE tbanimalvacunacion SET ' . implode(', ', $asignaciones) . ' WHERE tbanimalvacunacionid = :id'
        )->execute($parametros);
    }

    private function seleccion(): string
    {
        return 'SELECT v.tbanimalvacunacionid AS vacunacionid, v.tbanimalid AS animalid, v.tbvacunaid AS vacunaid,
                       c.tbvacunanombre AS vacuna, v.tbanimalvacunacionfecha AS fecha,
                       v.tbanimalvacunaciondosis AS dosis, v.tbanimalvacunacionlote AS lote,
                       v.tbanimalvacunacionaplicadapor AS aplicadapor, v.tbanimalvacunacionproximadosis AS proximadosis,
                       v.tbanimalvacunacionobservaciones AS observaciones, v.tbanimalvacunacionfecharegistro AS fecharegistro
                FROM tbanimalvacunacion v
                INNER JOIN tbvacuna c ON c.tbvacunaid = v.tbvacunaid';
    }

    private static function mapear(array $f): array
    {
        return [
            'vacunacionId' => (int) $f['vacunacionid'], 'animalId' => (int) $f['animalid'],
            'vacunaId' => (int) $f['vacunaid'], 'vacuna' => $f['vacuna'], 'fecha' => $f['fecha'],
            'dosis' => $f['dosis'], 'lote' => $f['lote'], 'aplicadaPor' => $f['aplicadapor'],
            'proximaDosis' => $f['proximadosis'], 'observaciones' => $f['observaciones'],
            'fechaRegistro' => $f['fecharegistro'],
        ];
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbanimalvacunacionid), 0) + 1 FROM tbanimalvacunacion');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
