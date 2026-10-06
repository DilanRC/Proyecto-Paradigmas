<?php

declare(strict_types=1);

namespace Application\Model;

use Application\Service\PublicacionCercaniaService;
use PDO;

/**
 * Oferta de flete (DEC-FLETE-001): el servicio que publica un transportista con
 * su vehículo, su zona base y el radio que cubre. No es un viaje realizado
 * (eso es tbtransportistaflete). Las reglas viven en PHP, sin UNIQUE ni FK.
 */
final class TransportistaOferta
{
    public const ESTADOS = ['ACTIVA', 'PAUSADA'];
    private const LOCK_ALTA = 'tindercows_transportista_oferta_alta';

    private int $profundidadBloqueoAlta = 0;

    public function __construct(private readonly PDO $conexion, private readonly Direccion $direccion) {}

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK_ALTA);
        $this->profundidadBloqueoAlta++;
        try {
            return $operacion();
        } finally {
            $this->profundidadBloqueoAlta--;
            NamedLock::release($this->conexion, self::LOCK_ALTA);
        }
    }

    /** Crea la oferta y su zona base. Exige los locks de oferta y de dirección. */
    public function crear(int $transportistaId, array $datos): int
    {
        if ($this->profundidadBloqueoAlta <= 0) {
            throw new \LogicException('La conexión debe poseer el lock de alta de oferta antes de crearla.');
        }
        $direccionId = $this->direccion->crearConBloqueoExistente($datos['direccion']);
        $ofertaId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportistaoferta
             (tbtransportistaofertaid, tbtransportistaid, tbvehiculoid, tbdireccionid,
              tbtransportistaofertaradiokm, tbtransportistaofertacapacidad, tbtransportistaofertaprecio,
              tbtransportistaofertadescripcion, tbtransportistaofertaestado, tbtransportistaofertafecha)
             VALUES (:ofertaId, :transportistaId, :vehiculoId, :direccionId, :radio, :capacidad, :precio,
              :descripcion, :estado, :fecha)'
        );
        $sentencia->execute([
            'ofertaId' => $ofertaId,
            'transportistaId' => $transportistaId,
            'vehiculoId' => $datos['vehiculoId'],
            'direccionId' => $direccionId,
            'radio' => $datos['radioKm'],
            'capacidad' => $datos['capacidad'],
            'precio' => $datos['precio'],
            'descripcion' => $datos['descripcion'],
            'estado' => 'ACTIVA',
            'fecha' => gmdate('Y-m-d H:i:s'),
        ]);

        return $ofertaId;
    }

    public function actualizar(int $ofertaId, array $datos): void
    {
        $actual = $this->filaPropia($ofertaId, null, true);
        if ($actual === null) throw new \RuntimeException('La oferta a actualizar no existe.');
        $this->direccion->actualizar((int) $actual['direccionid'], $datos['direccion']);
        $sentencia = $this->conexion->prepare(
            'UPDATE tbtransportistaoferta
             SET tbvehiculoid = :vehiculoId, tbtransportistaofertaradiokm = :radio,
                 tbtransportistaofertacapacidad = :capacidad, tbtransportistaofertaprecio = :precio,
                 tbtransportistaofertadescripcion = :descripcion
             WHERE tbtransportistaofertaid = :ofertaId'
        );
        $sentencia->execute([
            'ofertaId' => $ofertaId,
            'vehiculoId' => $datos['vehiculoId'],
            'radio' => $datos['radioKm'],
            'capacidad' => $datos['capacidad'],
            'precio' => $datos['precio'],
            'descripcion' => $datos['descripcion'],
        ]);
    }

    public function cambiarEstado(int $ofertaId, string $estado): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbtransportistaoferta SET tbtransportistaofertaestado = :estado WHERE tbtransportistaofertaid = :ofertaId'
        );
        $sentencia->execute(['estado' => $estado, 'ofertaId' => $ofertaId]);
    }

    /** Oferta del transportista, con su zona exacta. Con $bloquear toma la fila (FOR UPDATE). */
    public function buscarPropia(int $ofertaId, int $transportistaId, bool $bloquear = false): ?array
    {
        $fila = $this->filaPropia($ofertaId, $transportistaId, $bloquear);
        return $fila === null ? null : $this->mapearPropia($fila);
    }

    /**
     * Oferta que hoy se puede pedir: ACTIVA, de un transportista y persona activos y con vehículo activo.
     * @return array{ofertaId:int,transportistaId:int,personaId:int,radioKm:int,latitud:?float,longitud:?float}|null
     */
    public function buscarDisponible(int $ofertaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT o.tbtransportistaofertaid AS ofertaid, o.tbtransportistaid AS transportistaid,
                    t.tbpersonaid AS personaid, o.tbtransportistaofertaradiokm AS radiokm,
                    d.tbdireccionlatitud AS latitud, d.tbdireccionlongitud AS longitud
             FROM tbtransportistaoferta o
             INNER JOIN tbtransportista t ON t.tbtransportistaid = o.tbtransportistaid
             INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
             INNER JOIN tbvehiculo v ON v.tbvehiculoid = o.tbvehiculoid
             INNER JOIN tbdireccion d ON d.tbdireccionid = o.tbdireccionid
             WHERE o.tbtransportistaofertaid = :ofertaId AND o.tbtransportistaofertaestado = :estado
               AND t.tbtransportistaestado = 1 AND pe.tbpersonaestado = 1 AND v.tbvehiculoestado = 1'
        );
        $sentencia->execute(['ofertaId' => $ofertaId, 'estado' => 'ACTIVA']);
        $fila = $sentencia->fetch();
        if ($fila === false) return null;

        return [
            'ofertaId' => (int) $fila['ofertaid'],
            'transportistaId' => (int) $fila['transportistaid'],
            'personaId' => (int) $fila['personaid'],
            'radioKm' => (int) $fila['radiokm'],
            'latitud' => $fila['latitud'] === null ? null : (float) $fila['latitud'],
            'longitud' => $fila['longitud'] === null ? null : (float) $fila['longitud'],
        ];
    }

    public function listarPropias(int $transportistaId): array
    {
        $sentencia = $this->conexion->prepare(
            $this->seleccion() . ' WHERE o.tbtransportistaid = :transportistaId
             ORDER BY o.tbtransportistaofertafecha DESC, o.tbtransportistaofertaid DESC'
        );
        $sentencia->execute(['transportistaId' => $transportistaId]);

        return array_map(fn (array $fila): array => $this->mapearPropia($fila), $sentencia->fetchAll());
    }

    /**
     * Ofertas ACTIVAS que cubren el punto (distancia <= radio), de la más cercana a la más lejana.
     * Solo de transportistas, personas y vehículos activos. La zona exacta no se expone.
     * Con $personaId deja fuera las ofertas de esa persona: tus propias ofertas viven en "Mis ofertas".
     * ponytail: se calcula en PHP sobre todas las activas; con miles de ofertas, filtrar por caja de coordenadas en SQL.
     */
    public function listarCercanas(float $latitud, float $longitud, int $capacidadMinima, int $pagina, int $tamano,
        ?int $personaId = null): array
    {
        $sentencia = $this->conexion->prepare(
            $this->seleccion() . ' INNER JOIN tbtransportista t ON t.tbtransportistaid = o.tbtransportistaid
             INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
             WHERE o.tbtransportistaofertaestado = :estado AND t.tbtransportistaestado = 1
               AND pe.tbpersonaestado = 1 AND v.tbvehiculoestado = 1
               AND o.tbtransportistaofertacapacidad >= :capacidadMinima
               AND d.tbdireccionlatitud IS NOT NULL AND d.tbdireccionlongitud IS NOT NULL'
            . ($personaId === null ? '' : ' AND t.tbpersonaid <> :personaId')
        );
        $parametros = ['estado' => 'ACTIVA', 'capacidadMinima' => $capacidadMinima];
        if ($personaId !== null) $parametros['personaId'] = $personaId;
        $sentencia->execute($parametros);

        $ofertas = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $distancia = PublicacionCercaniaService::calcularDistanciaKm(
                $latitud, $longitud, (float) $fila['latitud'], (float) $fila['longitud']
            );
            if ($distancia > (int) $fila['radiokm']) continue;
            $ofertas[] = $this->mapearPublica($fila, round($distancia, 2));
        }
        usort($ofertas, static fn (array $a, array $b): int
            => [$a['distanciaKm'], -$a['ofertaId']] <=> [$b['distanciaKm'], -$b['ofertaId']]);

        return [
            'ofertas' => array_values(array_slice($ofertas, ($pagina - 1) * $tamano, $tamano)),
            'total' => count($ofertas),
        ];
    }

    private function seleccion(): string
    {
        // Alias en minúscula: Postgres los pliega; las claves públicas camelCase se arman al mapear.
        return 'SELECT o.tbtransportistaofertaid AS ofertaid, o.tbtransportistaid AS transportistaid,
                       o.tbvehiculoid AS vehiculoid, o.tbdireccionid AS direccionid,
                       o.tbtransportistaofertaradiokm AS radiokm, o.tbtransportistaofertacapacidad AS capacidad,
                       o.tbtransportistaofertaprecio AS precio, o.tbtransportistaofertadescripcion AS descripcion,
                       o.tbtransportistaofertaestado AS estado, o.tbtransportistaofertafecha AS fecha,
                       v.tbvehiculoplaca AS placa, v.tbvehiculomodelo AS modelo, v.tbvehiculofotourl AS fotourl,
                       d.tbdireccionprovincia AS provincia, d.tbdireccioncanton AS canton,
                       d.tbdirecciondistrito AS distrito, d.tbdireccionpueblo AS pueblo,
                       d.tbdireccionsenas AS senas, d.tbdireccionlatitud AS latitud, d.tbdireccionlongitud AS longitud,
                       (SELECT p2.tbpersonanombre FROM tbtransportista t2
                         INNER JOIN tbpersona p2 ON p2.tbpersonaid = t2.tbpersonaid
                         WHERE t2.tbtransportistaid = o.tbtransportistaid) AS transportistanombre
                FROM tbtransportistaoferta o
                INNER JOIN tbvehiculo v ON v.tbvehiculoid = o.tbvehiculoid
                INNER JOIN tbdireccion d ON d.tbdireccionid = o.tbdireccionid';
    }

    private function filaPropia(int $ofertaId, ?int $transportistaId, bool $bloquear): ?array
    {
        $sql = $this->seleccion() . ' WHERE o.tbtransportistaofertaid = :ofertaId';
        $parametros = ['ofertaId' => $ofertaId];
        if ($transportistaId !== null) {
            $sql .= ' AND o.tbtransportistaid = :transportistaId';
            $parametros['transportistaId'] = $transportistaId;
        }
        // FOR UPDATE sobre un JOIN bloquea también vehículo y dirección: es lo que se quiere al editar.
        $sentencia = $this->conexion->prepare($bloquear ? $sql . ' FOR UPDATE' : $sql);
        $sentencia->execute($parametros);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function mapearPropia(array $fila): array
    {
        return [
            'ofertaId' => (int) $fila['ofertaid'],
            'estado' => $fila['estado'],
            'radioKm' => (int) $fila['radiokm'],
            'capacidad' => (int) $fila['capacidad'],
            'precio' => $fila['precio'] === null ? null : (float) $fila['precio'],
            'descripcion' => $fila['descripcion'],
            'fecha' => $fila['fecha'],
            'vehiculo' => [
                'vehiculoId' => (int) $fila['vehiculoid'],
                'placa' => $fila['placa'],
                'modelo' => $fila['modelo'],
                'fotoUrl' => $fila['fotourl'],
            ],
            'zona' => [
                'provincia' => $fila['provincia'], 'canton' => $fila['canton'], 'distrito' => $fila['distrito'],
                'pueblo' => $fila['pueblo'], 'senas' => $fila['senas'],
                'latitud' => $fila['latitud'] === null ? null : (float) $fila['latitud'],
                'longitud' => $fila['longitud'] === null ? null : (float) $fila['longitud'],
            ],
        ];
    }

    /** Vista para clientes: sin placa, señas ni coordenadas exactas. */
    private function mapearPublica(array $fila, float $distanciaKm): array
    {
        return [
            'ofertaId' => (int) $fila['ofertaid'],
            'radioKm' => (int) $fila['radiokm'],
            'capacidad' => (int) $fila['capacidad'],
            'precio' => $fila['precio'] === null ? null : (float) $fila['precio'],
            'descripcion' => $fila['descripcion'],
            'distanciaKm' => $distanciaKm,
            'transportista' => $fila['transportistanombre'],
            'vehiculo' => ['modelo' => $fila['modelo'], 'fotoUrl' => $fila['fotourl']],
            'zona' => [
                'provincia' => $fila['provincia'], 'canton' => $fila['canton'],
                'distrito' => $fila['distrito'], 'pueblo' => $fila['pueblo'],
            ],
        ];
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbtransportistaofertaid), 0) + 1 FROM tbtransportistaoferta');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
