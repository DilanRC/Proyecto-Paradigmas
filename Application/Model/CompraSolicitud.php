<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Solicitud de compra (DEC-COMPRA-001): un Comprador pide un animal publicado,
 * con flete opcional, y el vendedor la acepta o la rechaza. El flete lo
 * responde aparte el transportista. Las reglas viven en PHP, sin UNIQUE ni FK.
 */
final class CompraSolicitud
{
    public const ESTADOS = ['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'];
    private const LOCK_ALTA = 'tindercows_compra_solicitud_alta';

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

    public function crear(array $datos): int
    {
        $solicitudId = $this->siguienteId();
        $this->conexion->prepare(
            'INSERT INTO tbcomprasolicitud
             (tbcomprasolicitudid, tbanimalpublicacionid, tbcompradorid, tbtransportistaofertaid, tbpagometodoid,
              tbcomprasolicitudprecio, tbcomprasolicitudmensaje, tbcomprasolicitudestado, tbcomprasolicitudfleteestado,
              tbcomprasolicitudfecha)
             VALUES (:id, :publicacionId, :compradorId, :ofertaId, :pagoMetodoId, :precio, :mensaje, :estado,
              :fleteEstado, :fecha)'
        )->execute([
            'id' => $solicitudId,
            'publicacionId' => $datos['publicacionId'],
            'compradorId' => $datos['compradorId'],
            'ofertaId' => $datos['ofertaId'],
            'pagoMetodoId' => $datos['pagoMetodoId'],
            'precio' => $datos['precio'],
            'mensaje' => $datos['mensaje'],
            'estado' => 'PENDIENTE',
            'fleteEstado' => $datos['ofertaId'] === null ? null : 'PENDIENTE',
            'fecha' => gmdate('Y-m-d H:i:s'),
        ]);

        return $solicitudId;
    }

    /** Fila cruda con todo lo necesario para decidir y mostrar. Con $bloquear toma solo la fila de la solicitud (FOR UPDATE OF s). */
    public function buscar(int $solicitudId, bool $bloquear = false): ?array
    {
        $sentencia = $this->conexion->prepare(
            $this->seleccion() . ' WHERE s.tbcomprasolicitudid = :id' . ($bloquear ? ' FOR UPDATE OF s' : '')
        );
        $sentencia->execute(['id' => $solicitudId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return array<int,array<string,mixed>> filas crudas, de la más reciente a la más antigua */
    public function listar(string $rol, int $id): array
    {
        $filtro = match ($rol) {
            'COMPRADOR' => 's.tbcompradorid = :id',
            'VENDEDOR' => 'p.tbproductorvendedorid = :id',
            // El transportista solo ve el flete una vez que el vendedor aceptó la venta.
            'TRANSPORTISTA' => "o.tbtransportistaid = :id AND s.tbcomprasolicitudestado = 'ACEPTADA'
                                AND s.tbcomprasolicitudfleteestado IS NOT NULL",
        };
        $sentencia = $this->conexion->prepare(
            $this->seleccion() . " WHERE {$filtro} ORDER BY s.tbcomprasolicitudfecha DESC, s.tbcomprasolicitudid DESC"
        );
        $sentencia->execute(['id' => $id]);

        return $sentencia->fetchAll();
    }

    public function hayPendiente(int $publicacionId, int $compradorId): bool
    {
        $sentencia = $this->conexion->prepare(
            "SELECT COUNT(*) FROM tbcomprasolicitud
             WHERE tbanimalpublicacionid = :publicacionId AND tbcompradorid = :compradorId
               AND tbcomprasolicitudestado = 'PENDIENTE'"
        );
        $sentencia->execute(['publicacionId' => $publicacionId, 'compradorId' => $compradorId]);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Cierra la solicitud con su estado y, si pidió flete, lo marca $fleteEstado.
     * Con $precio también fija el precio acordado (al aceptar una publicación "a convenir").
     */
    public function responder(int $solicitudId, string $estado, ?string $motivo, ?string $fleteEstado, ?float $precio = null): void
    {
        $this->conexion->prepare(
            'UPDATE tbcomprasolicitud
             SET tbcomprasolicitudestado = :estado, tbcomprasolicitudrespuestafecha = :fecha,
                 tbcomprasolicitudrespuestamotivo = :motivo,
                 tbcomprasolicitudfleteestado = CASE WHEN tbtransportistaofertaid IS NULL THEN NULL ELSE :fleteEstado END,
                 tbcomprasolicitudprecio = COALESCE(:precio, tbcomprasolicitudprecio)
             WHERE tbcomprasolicitudid = :id'
        )->execute([
            'estado' => $estado, 'fecha' => gmdate('Y-m-d H:i:s'), 'motivo' => $motivo,
            'fleteEstado' => $fleteEstado, 'precio' => $precio, 'id' => $solicitudId,
        ]);
    }

    /** Cancelación del comprador: no es una respuesta del vendedor, así que no toca esos campos. */
    public function cancelar(int $solicitudId): void
    {
        $this->conexion->prepare(
            "UPDATE tbcomprasolicitud
             SET tbcomprasolicitudestado = 'CANCELADA',
                 tbcomprasolicitudfleteestado = CASE WHEN tbtransportistaofertaid IS NULL THEN NULL ELSE 'CANCELADA' END
             WHERE tbcomprasolicitudid = :id"
        )->execute(['id' => $solicitudId]);
    }

    public function responderFlete(int $solicitudId, string $fleteEstado): void
    {
        $this->conexion->prepare(
            'UPDATE tbcomprasolicitud SET tbcomprasolicitudfleteestado = :estado, tbcomprasolicitudfleterespuestafecha = :fecha
             WHERE tbcomprasolicitudid = :id'
        )->execute(['estado' => $fleteEstado, 'fecha' => gmdate('Y-m-d H:i:s'), 'id' => $solicitudId]);
    }

    /** Rechaza las demás solicitudes pendientes de una publicación que ya se vendió; devuelve sus ids. */
    public function rechazarPendientesDe(int $publicacionId, int $exceptoId, string $motivo): array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT tbcomprasolicitudid FROM tbcomprasolicitud
             WHERE tbanimalpublicacionid = :publicacionId AND tbcomprasolicitudestado = 'PENDIENTE'
               AND tbcomprasolicitudid <> :exceptoId"
        );
        $sentencia->execute(['publicacionId' => $publicacionId, 'exceptoId' => $exceptoId]);
        $ids = array_map('intval', $sentencia->fetchAll(PDO::FETCH_COLUMN));
        foreach ($ids as $id) {
            $this->responder($id, 'RECHAZADA', $motivo, 'CANCELADA');
        }

        return $ids;
    }

    /** Datos del animal y la finca para registrar la compra y la venta (con los snapshots del hecho). */
    public function datosParaVenta(int $publicacionId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.tbanimalid AS animalid, p.tbproductorvendedorid AS vendedorid, p.tbfincaid AS fincaid,
                    p.tbanimalpublicacionprecio AS precio, a.tbanimalraza AS raza,
                    (SELECT s.tbanimalproduccionsaludedadmeses FROM tbanimalproduccionsalud s
                      WHERE s.tbanimalid = p.tbanimalid
                      ORDER BY s.tbanimalproduccionsaludfecha DESC, s.tbanimalproduccionsaludid DESC LIMIT 1) AS edadmeses,
                    (SELECT s.tbanimalproduccionsaludpeso FROM tbanimalproduccionsalud s
                      WHERE s.tbanimalid = p.tbanimalid
                      ORDER BY s.tbanimalproduccionsaludfecha DESC, s.tbanimalproduccionsaludid DESC LIMIT 1) AS peso,
                    (SELECT s.tbanimalproduccionsaludproposito FROM tbanimalproduccionsalud s
                      WHERE s.tbanimalid = p.tbanimalid
                      ORDER BY s.tbanimalproduccionsaludfecha DESC, s.tbanimalproduccionsaludid DESC LIMIT 1) AS proposito
             FROM tbanimalpublicacion p
             INNER JOIN tbanimal a ON a.tbanimalid = p.tbanimalid
             WHERE p.tbanimalpublicacionid = :id'
        );
        $sentencia->execute(['id' => $publicacionId]);
        $fila = $sentencia->fetch();
        if ($fila === false) return null;

        return [
            'animalId' => (int) $fila['animalid'],
            'vendedorId' => (int) $fila['vendedorid'],
            'fincaId' => (int) $fila['fincaid'],
            'precio' => $fila['precio'] === null ? null : (float) $fila['precio'],
            'raza' => $fila['raza'],
            'edadMeses' => $fila['edadmeses'] === null ? null : (int) $fila['edadmeses'],
            'peso' => $fila['peso'] === null ? null : (float) $fila['peso'],
            'proposito' => $fila['proposito'],
        ];
    }

    /** Punto de la finca de la publicación, o null si no se marcó en el mapa. */
    public function puntoDeFinca(int $publicacionId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT d.tbdireccionlatitud AS latitud, d.tbdireccionlongitud AS longitud
             FROM tbanimalpublicacion p
             INNER JOIN tbfincadireccion fd ON fd.tbfincaid = p.tbfincaid
             INNER JOIN tbdireccion d ON d.tbdireccionid = fd.tbdireccionid
             WHERE p.tbanimalpublicacionid = :id AND d.tbdireccionlatitud IS NOT NULL AND d.tbdireccionlongitud IS NOT NULL'
        );
        $sentencia->execute(['id' => $publicacionId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : ['latitud' => (float) $fila['latitud'], 'longitud' => (float) $fila['longitud']];
    }

    private function seleccion(): string
    {
        // Alias en minúscula: Postgres los pliega; las claves públicas camelCase se arman en el controlador.
        return 'SELECT s.tbcomprasolicitudid AS solicitudid, s.tbanimalpublicacionid AS publicacionid,
                       s.tbcompradorid AS compradorid, s.tbtransportistaofertaid AS ofertaid,
                       s.tbpagometodoid AS pagometodoid, s.tbcomprasolicitudprecio AS precio,
                       s.tbcomprasolicitudmensaje AS mensaje, s.tbcomprasolicitudestado AS estado,
                       s.tbcomprasolicitudfleteestado AS fleteestado, s.tbcomprasolicitudfecha AS fecha,
                       s.tbcomprasolicitudrespuestafecha AS respuestafecha,
                       s.tbcomprasolicitudrespuestamotivo AS respuestamotivo,
                       s.tbcomprasolicitudfleterespuestafecha AS fleterespuestafecha,
                       p.tbanimalpublicaciontitulo AS titulo, p.tbanimalpublicacionimagenurl AS imagenurl,
                       p.tbanimalpublicacionprecio AS publicacionprecio, p.tbproductorvendedorid AS vendedorid,
                       (SELECT ep.tbanimalpublicacionestadoperiodoestado FROM tbanimalpublicacionestadoperiodo ep
                         WHERE ep.tbanimalpublicacionid = p.tbanimalpublicacionid
                           AND ep.tbanimalpublicacionestadoperiodofechafin IS NULL) AS publicacionestado,
                       pv.tbpersonaid AS vendedorpersonaid, pv.tbpersonanombre AS vendedornombre,
                       pv.tbpersonatelefono AS vendedortelefono,
                       c.tbpersonaid AS compradorpersonaid, pc.tbpersonanombre AS compradornombre,
                       pc.tbpersonatelefono AS compradortelefono,
                       o.tbtransportistaid AS transportistaid, ov.tbvehiculomodelo AS fletemodelo,
                       ptr.tbpersonaid AS transportistapersonaid, ptr.tbpersonanombre AS transportistanombre,
                       ptr.tbpersonatelefono AS transportistatelefono
                FROM tbcomprasolicitud s
                INNER JOIN tbanimalpublicacion p ON p.tbanimalpublicacionid = s.tbanimalpublicacionid
                INNER JOIN tbproductor pr ON pr.tbproductorid = p.tbproductorvendedorid
                INNER JOIN tbpersona pv ON pv.tbpersonaid = pr.tbpersonaid
                INNER JOIN tbcomprador c ON c.tbcompradorid = s.tbcompradorid
                INNER JOIN tbpersona pc ON pc.tbpersonaid = c.tbpersonaid
                LEFT JOIN tbtransportistaoferta o ON o.tbtransportistaofertaid = s.tbtransportistaofertaid
                LEFT JOIN tbvehiculo ov ON ov.tbvehiculoid = o.tbvehiculoid
                LEFT JOIN tbtransportista t ON t.tbtransportistaid = o.tbtransportistaid
                LEFT JOIN tbpersona ptr ON ptr.tbpersonaid = t.tbpersonaid';
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbcomprasolicitudid), 0) + 1 FROM tbcomprasolicitud');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
