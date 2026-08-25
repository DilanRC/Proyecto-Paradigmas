<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Transportista
{
    private Persona $persona;
    public function __construct(
        private readonly PDO $conexion,
        private readonly TransportistaVehiculo $vehiculos,
    ) {
        $this->persona = new Persona($conexion);
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid=t.tbpersonaid {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT t.*, pe.tbpersonaidentificacionnumero AS tbtransportistaidentificacionnumero,
                pe.tbpersonaidentificaciontipo AS tbtransportistaidentificaciontipo,
                pe.tbpersonanombre AS tbtransportistanombre, pe.tbpersonatelefono AS tbtransportistatelefono,
                pe.tbpersonacorreoelectronico AS tbtransportistacorreoelectronico, pe.tbpersonaestado
                FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid=t.tbpersonaid
                {$where}
                ORDER BY (t.tbtransportistaestado * pe.tbpersonaestado) DESC, pe.tbpersonanombre,
                         pe.tbpersonaidentificacionnumero
                LIMIT :limite OFFSET :desplazamiento";
        $sentencia = $this->conexion->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();

        return [
            'transportistas' => array_map(
                fn (array $fila): array => $this->mapear(
                    $fila,
                    $this->vehiculos->listarVehiculosPorTransportista((int) $fila['tbtransportistaid']),
                ),
                $filas,
            ),
            'total' => $total,
        ];
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT t.*, pe.tbpersonaidentificacionnumero AS tbtransportistaidentificacionnumero,
                    pe.tbpersonaidentificaciontipo AS tbtransportistaidentificaciontipo,
                    pe.tbpersonanombre AS tbtransportistanombre, pe.tbpersonatelefono AS tbtransportistatelefono,
                    pe.tbpersonacorreoelectronico AS tbtransportistacorreoelectronico, pe.tbpersonaestado
             FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid=t.tbpersonaid
             WHERE pe.tbpersonaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if ($filas === []) {
            return null;
        }
        if (count($filas) !== 1) {
            throw new \RuntimeException('La identificación no conserva un único transportista.');
        }
        $fila = $filas[0];

        return $this->mapear($fila, $this->vehiculos->listarVehiculosPorTransportista((int) $fila['tbtransportistaid']));
    }

    /**
     * Lectura mínima por id interno, para uso de otros controllers/modelos
     * (por ejemplo, TransportistaVehiculoController al resolver el dueño de
     * un vehículo). No sustituye a buscar(), que es la vía pública por
     * identificación.
     */
    public function buscarPorId(int $transportistaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT pe.tbpersonaidentificacionnumero AS identificacionNumero, pe.tbpersonanombre AS nombre,
                    t.tbtransportistaestado, pe.tbpersonaestado
             FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid=t.tbpersonaid
             WHERE t.tbtransportistaid = :id'
        );
        $sentencia->execute(['id' => $transportistaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT t.*, pe.tbpersonaestado FROM tbtransportista t INNER JOIN tbpersona pe ON pe.tbpersonaid=t.tbpersonaid
             WHERE pe.tbpersonaidentificacionnumero = :identificacionNumero FOR UPDATE'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('La identificación está duplicada en la base de datos.');
        }

        return $filas[0] ?? null;
    }

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        try {
            return $operacion();
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_persona_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_persona_alta');
    }

    public function crear(array $datos): int
    {
        $persona = $this->persona->obtenerOCrear($datos);
        $transportistaId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportista
             (tbtransportistaid, tbpersonaid, tbtransportistaestado)
             VALUES (:transportistaId, :personaId, :estado)'
        );
        $sentencia->execute([
            'transportistaId' => $transportistaId,
            'personaId' => $persona['tbpersonaid'],
            'estado' => 1,
        ]);

        return $transportistaId;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbtransportistaid), 0) + 1 FROM tbtransportista');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $this->persona->actualizar($identificacionNumero, $datos);
    }

    public function cambiarEstado(string $identificacionNumero, bool $activo): void
    {
        $persona = $this->persona->buscar($identificacionNumero); if ($persona === null) return;
        $sentencia = $this->conexion->prepare('UPDATE tbtransportista SET tbtransportistaestado = :estado WHERE tbpersonaid = :personaId');
        $sentencia->execute([
            'estado' => $activo ? 1 : 0,
            'personaId' => $persona['tbpersonaid'],
        ]);
    }

    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(pe.tbpersonanombre LIKE :busquedaNombre
                OR pe.tbpersonacorreoelectronico LIKE :busquedaCorreo
                OR pe.tbpersonaidentificacionnumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = '(t.tbtransportistaestado * pe.tbpersonaestado) = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila, array $vehiculos): array
    {
        return [
            'transportistaId' => (int) $fila['tbtransportistaid'],
            'identificacionNumero' => $fila['tbtransportistaidentificacionnumero'],
            'identificacion' => [
                'tipoCodigo' => $fila['tbtransportistaidentificaciontipo'],
                'numero' => $fila['tbtransportistaidentificacionnumero'],
            ],
            'nombre' => $fila['tbtransportistanombre'],
            'telefono' => $fila['tbtransportistatelefono'],
            'correoElectronico' => $fila['tbtransportistacorreoelectronico'],
            'estado' => (int) $fila['tbtransportistaestado'] === 1 && (int) $fila['tbpersonaestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            'vehiculos' => $vehiculos,
        ];
    }
}
