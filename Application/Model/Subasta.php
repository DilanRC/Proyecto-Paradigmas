<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Subastas en tiempo real (DEC-SUBASTA-001): la subasta, sus pujas y sus comentarios.
 * Los ids salen de MAX()+1 bajo un solo NamedLock para las tres tablas; quien escribe debe
 * sostenerlo hasta el COMMIT (Cuidados #12). Fechas en UTC ('Y-m-d H:i:s').
 */
final class Subasta
{
    public const ESTADOS = ['ACTIVA', 'FINALIZADA', 'DESIERTA', 'CANCELADA'];
    public const VISTAS = ['ACTIVAS', 'HISTORIAL', 'MIAS', 'PARTICIPO'];
    private const LOCK = 'tindercows_subastas';

    public function __construct(private PDO $conexion) {}

    // ponytail: un solo lock para todas las subastas; pasar a uno por subasta si las pujas simultáneas lo piden.
    public function ejecutarConBloqueo(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK);
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, self::LOCK);
        }
    }

    /** @param array<string,mixed> $d productorId, animalId, fincaId, titulo, descripcion, imagenUrl, precioBase, incrementoMinimo, inicio, fin */
    public function crear(array $d): int
    {
        $id = $this->siguienteId('tbsubasta', 'tbsubastaid');
        $this->conexion->prepare(
            'INSERT INTO tbsubasta (tbsubastaid, tbproductorid, tbanimalid, tbfincaid, tbsubastatitulo, tbsubastadescripcion,
                tbsubastaimagenurl, tbsubastapreciobase, tbsubastaincrementominimo, tbsubastafechainicio, tbsubastafechafin,
                tbsubastaestado, tbsubastapujaganadoraid, tbsubastafechacierre)
             VALUES (:id, :productorId, :animalId, :fincaId, :titulo, :descripcion, :imagenUrl, :precioBase,
                :incremento, :inicio, :fin, :estado, NULL, NULL)'
        )->execute([
            'id' => $id,
            'productorId' => $d['productorId'],
            'animalId' => $d['animalId'],
            'fincaId' => $d['fincaId'],
            'titulo' => $d['titulo'],
            'descripcion' => $d['descripcion'],
            'imagenUrl' => $d['imagenUrl'],
            'precioBase' => $d['precioBase'],
            'incremento' => $d['incrementoMinimo'],
            'inicio' => $d['inicio'],
            'fin' => $d['fin'],
            'estado' => 'ACTIVA',
        ]);

        return $id;
    }

    /** Subasta con vendedor, finca, animal (con su observación vigente) y la puja más alta. */
    public function buscar(int $subastaId): ?array
    {
        $sentencia = $this->conexion->prepare($this->seleccion() . ' WHERE s.tbsubastaid = :id');
        $sentencia->execute(['id' => $subastaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    /**
     * ACTIVAS (de la que termina antes a la que termina después), HISTORIAL (las cerradas, la más reciente primero),
     * MIAS (las que creó el vendedor, en cualquier estado) o PARTICIPO (en las que la persona pujó).
     *
     * @return array{subastas:array<int,array<string,mixed>>,total:int}
     */
    public function listar(string $vista, ?int $personaId, ?int $productorId, string $busqueda, int $pagina, int $tamano): array
    {
        $condiciones = [];
        $parametros = [];
        $orden = 's.tbsubastafechafin ASC, s.tbsubastaid ASC';
        if ($vista === 'ACTIVAS') {
            $condiciones[] = "s.tbsubastaestado = 'ACTIVA'";
        } elseif ($vista === 'HISTORIAL') {
            $condiciones[] = "s.tbsubastaestado <> 'ACTIVA'";
            $orden = 'COALESCE(s.tbsubastafechacierre, s.tbsubastafechafin) DESC, s.tbsubastaid DESC';
        } elseif ($vista === 'MIAS') {
            $condiciones[] = 's.tbproductorid = :productorId';
            $parametros['productorId'] = $productorId ?? 0;
            $orden = "CASE WHEN s.tbsubastaestado = 'ACTIVA' THEN 0 ELSE 1 END, s.tbsubastafechafin DESC, s.tbsubastaid DESC";
        } else {
            $condiciones[] = 'EXISTS (SELECT 1 FROM tbsubastapuja pp WHERE pp.tbsubastaid = s.tbsubastaid AND pp.tbpersonaid = :personaId)';
            $parametros['personaId'] = $personaId ?? 0;
            $orden = "CASE WHEN s.tbsubastaestado = 'ACTIVA' THEN 0 ELSE 1 END, s.tbsubastafechafin DESC, s.tbsubastaid DESC";
        }
        if ($busqueda !== '') {
            $condiciones[] = '(LOWER(s.tbsubastatitulo) LIKE :q1 OR LOWER(a.tbanimalraza) LIKE :q2 OR LOWER(d.tbdireccioncanton) LIKE :q3'
                . ' OR LOWER(d.tbdireccionprovincia) LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $clave) $parametros[$clave] = '%' . mb_strtolower($busqueda, 'UTF-8') . '%';
        }
        $where = ' WHERE ' . implode(' AND ', $condiciones);

        $conteo = $this->conexion->prepare('SELECT COUNT(*) FROM (' . $this->seleccion() . $where . ') AS lista');
        $conteo->execute($parametros);
        $sentencia = $this->conexion->prepare($this->seleccion() . $where . " ORDER BY {$orden} LIMIT :limite OFFSET :desde");
        foreach ($parametros as $clave => $valor) $sentencia->bindValue($clave, $valor);
        $sentencia->bindValue('limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue('desde', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'subastas' => array_map(\Closure::fromCallable([$this, 'mapear']), $sentencia->fetchAll()),
            'total' => (int) $conteo->fetchColumn(),
        ];
    }

    /** Ids de las subastas ACTIVA cuya hora de fin ya pasó (se cierran al consultarlas, no hay tarea programada). */
    public function vencidas(string $ahora): array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT tbsubastaid FROM tbsubasta WHERE tbsubastaestado = 'ACTIVA' AND tbsubastafechafin <= :ahora ORDER BY tbsubastaid"
        );
        $sentencia->execute(['ahora' => $ahora]);

        return array_map('intval', $sentencia->fetchAll(PDO::FETCH_COLUMN));
    }

    public function cerrar(int $subastaId, string $estado, ?int $pujaGanadoraId, string $fecha): void
    {
        $this->conexion->prepare(
            'UPDATE tbsubasta SET tbsubastaestado = :estado, tbsubastapujaganadoraid = :puja, tbsubastafechacierre = :fecha
             WHERE tbsubastaid = :id'
        )->execute(['estado' => $estado, 'puja' => $pujaGanadoraId, 'fecha' => $fecha, 'id' => $subastaId]);
    }

    /** La puja más alta (la primera en llegar gana un empate, aunque las reglas no dejan pujar igual). */
    public function mejorPuja(int $subastaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbsubastapujaid AS pujaid, tbpersonaid AS personaid, tbsubastapujamonto AS monto
             FROM tbsubastapuja WHERE tbsubastaid = :id
             ORDER BY tbsubastapujamonto DESC, tbsubastapujaid ASC LIMIT 1'
        );
        $sentencia->execute(['id' => $subastaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null
            : ['pujaId' => (int) $fila['pujaid'], 'personaId' => (int) $fila['personaid'], 'monto' => (float) $fila['monto']];
    }

    public function registrarPuja(int $subastaId, int $personaId, float $monto, string $fecha): int
    {
        $id = $this->siguienteId('tbsubastapuja', 'tbsubastapujaid');
        $this->conexion->prepare(
            'INSERT INTO tbsubastapuja (tbsubastapujaid, tbsubastaid, tbpersonaid, tbsubastapujamonto, tbsubastapujafecha)
             VALUES (:id, :subastaId, :personaId, :monto, :fecha)'
        )->execute(['id' => $id, 'subastaId' => $subastaId, 'personaId' => $personaId, 'monto' => $monto, 'fecha' => $fecha]);

        return $id;
    }

    /** @return array<int,array<string,mixed>> de la más reciente a la más antigua */
    public function pujas(int $subastaId, int $limite = 50): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.tbsubastapujaid AS pujaid, p.tbpersonaid AS personaid, p.tbsubastapujamonto AS monto,
                    p.tbsubastapujafecha AS fecha, pe.tbpersonanombre AS nombre, pe.tbpersonaalias AS alias
             FROM tbsubastapuja p INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
             WHERE p.tbsubastaid = :id ORDER BY p.tbsubastapujamonto DESC, p.tbsubastapujaid DESC LIMIT :limite'
        );
        $sentencia->bindValue('id', $subastaId, PDO::PARAM_INT);
        $sentencia->bindValue('limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return array_map(static fn (array $f): array => [
            'pujaId' => (int) $f['pujaid'],
            'personaId' => (int) $f['personaid'],
            'monto' => (float) $f['monto'],
            'fecha' => self::iso($f['fecha']),
            'nombre' => $f['nombre'],
            'alias' => $f['alias'],
        ], $sentencia->fetchAll());
    }

    public function registrarComentario(int $subastaId, int $personaId, string $texto, string $fecha): int
    {
        $id = $this->siguienteId('tbsubastacomentario', 'tbsubastacomentarioid');
        $this->conexion->prepare(
            'INSERT INTO tbsubastacomentario (tbsubastacomentarioid, tbsubastaid, tbpersonaid, tbsubastacomentariotexto,
                tbsubastacomentariofecha) VALUES (:id, :subastaId, :personaId, :texto, :fecha)'
        )->execute(['id' => $id, 'subastaId' => $subastaId, 'personaId' => $personaId, 'texto' => $texto, 'fecha' => $fecha]);

        return $id;
    }

    /** Los últimos $limite comentarios, del más antiguo al más nuevo (como un chat). */
    public function comentarios(int $subastaId, int $limite = 100): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT c.tbsubastacomentarioid AS comentarioid, c.tbpersonaid AS personaid, c.tbsubastacomentariotexto AS texto,
                    c.tbsubastacomentariofecha AS fecha, pe.tbpersonanombre AS nombre, pe.tbpersonaalias AS alias
             FROM tbsubastacomentario c INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
             WHERE c.tbsubastaid = :id ORDER BY c.tbsubastacomentarioid DESC LIMIT :limite'
        );
        $sentencia->bindValue('id', $subastaId, PDO::PARAM_INT);
        $sentencia->bindValue('limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return array_reverse(array_map(static fn (array $f): array => [
            'comentarioId' => (int) $f['comentarioid'],
            'personaId' => (int) $f['personaid'],
            'texto' => $f['texto'],
            'fecha' => self::iso($f['fecha']),
            'nombre' => $f['nombre'],
            'alias' => $f['alias'],
        ], $sentencia->fetchAll()));
    }

    /** Nombre, teléfono y correo de una persona: solo para el trato entre ganador y vendedor. */
    public function contacto(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbpersonanombre AS nombre, tbpersonaalias AS alias, tbpersonatelefono AS telefono,
                    tbpersonacorreoelectronico AS correo
             FROM tbpersona WHERE tbpersonaid = :id'
        );
        $sentencia->execute(['id' => $personaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null
            : ['nombre' => $fila['nombre'], 'alias' => $fila['alias'], 'telefono' => $fila['telefono'], 'correo' => $fila['correo']];
    }

    /** Dirección completa de la finca (con señas): solo para el ganador y el vendedor. */
    public function ubicacionFinca(int $fincaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT d.tbdireccionprovincia AS provincia, d.tbdireccioncanton AS canton, d.tbdirecciondistrito AS distrito,
                    d.tbdireccionpueblo AS pueblo, d.tbdireccionsenas AS senas, d.tbdireccionlatitud AS latitud,
                    d.tbdireccionlongitud AS longitud
             FROM tbfincadireccion fd INNER JOIN tbdireccion d ON d.tbdireccionid = fd.tbdireccionid
             WHERE fd.tbfincaid = :id ORDER BY fd.tbdireccionid DESC LIMIT 1'
        );
        $sentencia->execute(['id' => $fincaId]);
        $fila = $sentencia->fetch();
        if ($fila === false) return null;

        return [
            'provincia' => $fila['provincia'], 'canton' => $fila['canton'], 'distrito' => $fila['distrito'],
            'pueblo' => $fila['pueblo'], 'senas' => $fila['senas'],
            'latitud' => $fila['latitud'] === null ? null : (float) $fila['latitud'],
            'longitud' => $fila['longitud'] === null ? null : (float) $fila['longitud'],
        ];
    }

    /** 'Y-m-d H:i:s' (UTC) → ISO 8601 con Z, para que el navegador lo lea sin adivinar la zona. */
    public static function iso(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') return null;
        return str_replace(' ', 'T', substr((string) $fecha, 0, 19)) . 'Z';
    }

    private function seleccion(): string
    {
        // Alias en minúscula: Postgres los pliega; las claves camelCase se arman en mapear().
        return 'SELECT s.tbsubastaid AS subastaid, s.tbproductorid AS productorid, s.tbanimalid AS animalid,
                       s.tbfincaid AS fincaid, s.tbsubastatitulo AS titulo, s.tbsubastadescripcion AS descripcion,
                       s.tbsubastaimagenurl AS imagenurl, s.tbsubastapreciobase AS preciobase,
                       s.tbsubastaincrementominimo AS incremento, s.tbsubastafechainicio AS inicio,
                       s.tbsubastafechafin AS fin, s.tbsubastaestado AS estado, s.tbsubastapujaganadoraid AS pujaganadoraid,
                       s.tbsubastafechacierre AS cierre, pr.tbpersonaid AS vendedorpersonaid, pe.tbpersonanombre AS vendedornombre,
                       f.tbfincanombre AS fincanombre, d.tbdireccionprovincia AS provincia, d.tbdireccioncanton AS canton,
                       d.tbdirecciondistrito AS distrito,
                       a.tbanimalsexo AS sexo, a.tbanimalraza AS raza, a.tbanimalfechanacimiento AS fechanacimiento,
                       a.tbanimalfechanacimientoestimada AS fechaestimada, a.tbanimalpartos AS partos,
                       es.tbespecienombre AS especie, ti.tbanimaltiponombre AS tipo,
                       (SELECT o.tbanimalproduccionsaludpeso FROM tbanimalproduccionsalud o WHERE o.tbanimalid = s.tbanimalid
                         ORDER BY o.tbanimalproduccionsaludfecha DESC, o.tbanimalproduccionsaludid DESC LIMIT 1) AS peso,
                       (SELECT o.tbanimalproduccionsaludedadmeses FROM tbanimalproduccionsalud o WHERE o.tbanimalid = s.tbanimalid
                         ORDER BY o.tbanimalproduccionsaludfecha DESC, o.tbanimalproduccionsaludid DESC LIMIT 1) AS edadmeses,
                       (SELECT o.tbanimalproduccionsaludproposito FROM tbanimalproduccionsalud o WHERE o.tbanimalid = s.tbanimalid
                         ORDER BY o.tbanimalproduccionsaludfecha DESC, o.tbanimalproduccionsaludid DESC LIMIT 1) AS proposito,
                       (SELECT MAX(p.tbsubastapujamonto) FROM tbsubastapuja p WHERE p.tbsubastaid = s.tbsubastaid) AS maximo,
                       (SELECT COUNT(*) FROM tbsubastapuja p WHERE p.tbsubastaid = s.tbsubastaid) AS pujas,
                       (SELECT p.tbpersonaid FROM tbsubastapuja p WHERE p.tbsubastapujaid = s.tbsubastapujaganadoraid) AS ganadorpersonaid
                FROM tbsubasta s
                INNER JOIN tbproductor pr ON pr.tbproductorid = s.tbproductorid
                INNER JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid
                INNER JOIN tbanimal a ON a.tbanimalid = s.tbanimalid
                LEFT JOIN tbespecie es ON es.tbespecieid = a.tbespecieid
                LEFT JOIN tbanimaltipo ti ON ti.tbanimaltipoid = a.tbanimaltipoid
                LEFT JOIN tbfinca f ON f.tbfincaid = s.tbfincaid
                LEFT JOIN tbdireccion d ON d.tbdireccionid = (SELECT MAX(fd.tbdireccionid) FROM tbfincadireccion fd
                                                              WHERE fd.tbfincaid = s.tbfincaid)';
    }

    private function mapear(array $f): array
    {
        $numero = static fn (mixed $v): ?float => $v === null ? null : (float) $v;
        $entero = static fn (mixed $v): ?int => $v === null ? null : (int) $v;

        return [
            'subastaId' => (int) $f['subastaid'],
            'productorId' => (int) $f['productorid'],
            'animalId' => (int) $f['animalid'],
            'fincaId' => (int) $f['fincaid'],
            'titulo' => $f['titulo'],
            'descripcion' => $f['descripcion'],
            'imagenUrl' => $f['imagenurl'],
            'precioBase' => (float) $f['preciobase'],
            'incrementoMinimo' => (float) $f['incremento'],
            'fechaInicio' => self::iso($f['inicio']),
            'fechaFin' => self::iso($f['fin']),
            'fechaCierre' => self::iso($f['cierre']),
            'estado' => $f['estado'],
            'precioActual' => $numero($f['maximo']),
            'pujasTotal' => (int) $f['pujas'],
            'pujaGanadoraId' => $entero($f['pujaganadoraid']),
            'ganadorPersonaId' => $entero($f['ganadorpersonaid']),
            'vendedorPersonaId' => (int) $f['vendedorpersonaid'],
            'vendedor' => ['nombre' => $f['vendedornombre']],
            'finca' => [
                'nombre' => $f['fincanombre'], 'provincia' => $f['provincia'],
                'canton' => $f['canton'], 'distrito' => $f['distrito'],
            ],
            'animal' => [
                'especie' => $f['especie'], 'tipo' => $f['tipo'], 'raza' => $f['raza'], 'sexo' => $f['sexo'],
                'fechaNacimiento' => $f['fechanacimiento'] === null ? null : substr((string) $f['fechanacimiento'], 0, 10),
                'fechaNacimientoEstimada' => $f['fechaestimada'] === null ? null : (int) $f['fechaestimada'] === 1,
                'partos' => $entero($f['partos']),
                'peso' => $numero($f['peso']),
                'edadMeses' => $entero($f['edadmeses']),
                'proposito' => $f['proposito'],
            ],
        ];
    }

    private function siguienteId(string $tabla, string $columna): int
    {
        $sentencia = $this->conexion->prepare("SELECT COALESCE(MAX({$columna}), 0) + 1 FROM {$tabla}");
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
