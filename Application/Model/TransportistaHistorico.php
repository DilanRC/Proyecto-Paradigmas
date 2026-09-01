<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Históricos confirmados para transportistas: periodos de estado, fletes y
 * reseñas. No almacena agregados derivados como cantidad semanal, método
 * frecuente ni promedio de calificación.
 */
final class TransportistaHistorico
{
    private const PREFIJO_LOCK_ESTADO = 'tindercows_transportista_estado_';
    private const LOCK_ESTADO_ALTA = 'tindercows_transportista_estado_alta';
    private const LOCK_FLETE_ALTA = 'tindercows_transportista_flete_alta';
    private const LOCK_RESENA_ALTA = 'tindercows_transportista_resena_alta';
    private int $profundidadEstado = 0;
    private int $transportistaBloqueado = 0;
    private int $profundidadFlete = 0;
    private int $profundidadResena = 0;

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoEstado(int $transportistaId, callable $operacion): mixed
    {
        $lockEntidad = self::PREFIJO_LOCK_ESTADO . $transportistaId;
        NamedLock::acquire($this->conexion, $lockEntidad);
        try {
            NamedLock::acquire($this->conexion, self::LOCK_ESTADO_ALTA);
            $this->profundidadEstado++;
            $this->transportistaBloqueado = $transportistaId;
            try {
                return $operacion();
            } finally {
                $this->profundidadEstado--;
                $this->transportistaBloqueado = 0;
                NamedLock::release($this->conexion, self::LOCK_ESTADO_ALTA);
            }
        } finally {
            NamedLock::release($this->conexion, $lockEntidad);
        }
    }

    public function ejecutarConBloqueoFlete(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK_FLETE_ALTA);
        $this->profundidadFlete++;
        try {
            return $operacion();
        } finally {
            $this->profundidadFlete--;
            NamedLock::release($this->conexion, self::LOCK_FLETE_ALTA);
        }
    }

    public function ejecutarConBloqueoResena(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK_RESENA_ALTA);
        $this->profundidadResena++;
        try {
            return $operacion();
        } finally {
            $this->profundidadResena--;
            NamedLock::release($this->conexion, self::LOCK_RESENA_ALTA);
        }
    }

    public function abrirEstado(int $transportistaId, int $estado, ?string $fechaInicioReal, ?string $motivo): int
    {
        $this->exigirLockEstado($transportistaId);
        if (!in_array($estado, [0, 1], true)) {
            throw new \InvalidArgumentException('Estado de transportista no aprobado.');
        }
        if ($this->consultarEstadoAbierto($transportistaId) !== null) {
            throw new \RuntimeException('El transportista ya tiene un periodo de estado abierto.');
        }

        $periodoId = $this->siguienteId('tbtransportistaestadoperiodo', 'tbtransportistaestadoperiodoid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportistaestadoperiodo
             (tbtransportistaestadoperiodoid, tbtransportistaid, tbtransportistaestadoperiodoestado,
              tbtransportistaestadoperiodofechainicio, tbtransportistaestadoperiodofechafin,
              tbtransportistaestadoperiodomotivo, tbtransportistaestadoperiodofecharegistroensistema)
             VALUES (:id, :transportistaId, :estado, :fechaInicio, NULL, :motivo, :fechaRegistro)'
        );
        $sentencia->execute([
            'id' => $periodoId,
            'transportistaId' => $transportistaId,
            'estado' => $estado,
            'fechaInicio' => $fechaInicioReal,
            'motivo' => $motivo,
            'fechaRegistro' => date('Y-m-d H:i:s'),
        ]);

        return $periodoId;
    }

    public function cerrarEstado(int $transportistaId): void
    {
        $this->exigirLockEstado($transportistaId);
        $sentencia = $this->conexion->prepare(
            'UPDATE tbtransportistaestadoperiodo
             SET tbtransportistaestadoperiodofechafin = :fechaFin
             WHERE tbtransportistaid = :transportistaId
               AND tbtransportistaestadoperiodofechafin IS NULL'
        );
        $sentencia->execute(['fechaFin' => date('Y-m-d H:i:s'), 'transportistaId' => $transportistaId]);
        if ($sentencia->rowCount() !== 1) {
            throw new \RuntimeException('Cerrar estado de transportista debía afectar exactamente una fila.');
        }
    }

    public function consultarEstadoAbierto(int $transportistaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbtransportistaestadoperiodo
             WHERE tbtransportistaid = :transportistaId
               AND tbtransportistaestadoperiodofechafin IS NULL'
        );
        $sentencia->execute(['transportistaId' => $transportistaId]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('El transportista conserva periodos de estado abiertos duplicados.');
        }

        return $filas === [] ? null : $filas[0];
    }

    public function registrarFlete(int $transportistaId, array $datos): int
    {
        if ($this->profundidadFlete <= 0) {
            throw new \LogicException('La conexión debe poseer el lock de alta de flete.');
        }
        $this->exigirTransaccionAlta('flete');

        $fleteId = $this->siguienteId('tbtransportistaflete', 'tbtransportistafleteid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportistaflete
             (tbtransportistafleteid, tbtransportistaid, tbproductororigenid,
              tbfincaorigenid, tbdireccionorigenid, tbdirecciondestinoid,
              tbtransportistafletefecha, tbtransportistafletehora,
              tbtransportistafletedescripcion, tbtransportistafleteprecio,
              tbpagometodoid, tbtransportistafleteorigen)
             VALUES (:id, :transportistaId, :productorOrigenId, :fincaOrigenId,
              :direccionOrigenId, :direccionDestinoId, :fecha, :hora,
              :descripcion, :precio, :pagoMetodoId, :origen)'
        );
        $sentencia->execute([
            'id' => $fleteId,
            'transportistaId' => $transportistaId,
            'productorOrigenId' => $datos['productorOrigenId'] ?? null,
            'fincaOrigenId' => $datos['fincaOrigenId'] ?? null,
            'direccionOrigenId' => $datos['direccionOrigenId'] ?? null,
            'direccionDestinoId' => $datos['direccionDestinoId'] ?? null,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'] ?? null,
            'pagoMetodoId' => $datos['pagoMetodoId'],
            'origen' => $datos['origen'],
        ]);

        return $fleteId;
    }

    public function registrarResena(int $transportistaId, int $productorId, ?int $fleteId, array $datos): int
    {
        if ($this->profundidadResena <= 0) {
            throw new \LogicException('La conexión debe poseer el lock de alta de reseña.');
        }
        $this->exigirTransaccionAlta('reseña');

        $resenaId = $this->siguienteId('tbtransportistaresena', 'tbtransportistaresenaid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportistaresena
             (tbtransportistaresenaid, tbtransportistaid, tbproductorid,
              tbtransportistafleteid, tbtransportistaresenafecha,
              tbtransportistaresenacalificacion, tbtransportistaresenacomentario,
              tbtransportistaresenaorigen)
             VALUES (:id, :transportistaId, :productorId, :fleteId, :fecha,
              :calificacion, :comentario, :origen)'
        );
        $sentencia->execute([
            'id' => $resenaId,
            'transportistaId' => $transportistaId,
            'productorId' => $productorId,
            'fleteId' => $fleteId,
            'fecha' => $datos['fecha'] ?? date('Y-m-d H:i:s'),
            'calificacion' => $datos['calificacion'],
            'comentario' => $datos['comentario'] ?? null,
            'origen' => $datos['origen'],
        ]);

        return $resenaId;
    }

    private function exigirLockEstado(int $transportistaId): void
    {
        if ($this->profundidadEstado <= 0 || $this->transportistaBloqueado !== $transportistaId) {
            throw new \LogicException('La conexión debe poseer el lock de estado del transportista.');
        }
        if (!$this->conexion->inTransaction()) {
            throw new \LogicException('La modificación de estado del transportista debe ejecutarse dentro de una transacción.');
        }
    }

    private function exigirTransaccionAlta(string $concepto): void
    {
        if (!$this->conexion->inTransaction()) {
            throw new \LogicException("La escritura de {$concepto} debe ejecutarse dentro de una transacción.");
        }
    }

    private function siguienteId(string $tabla, string $columna): int
    {
        $sentencia = $this->conexion->prepare(
            "SELECT {$columna} FROM {$tabla} ORDER BY {$columna} DESC LIMIT 1 FOR UPDATE"
        );
        $sentencia->execute();
        $maximo = $sentencia->fetchColumn();

        return $maximo === false ? 1 : ((int) $maximo) + 1;
    }
}
