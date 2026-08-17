<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Transportista
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly TransportistaVehiculo $vehiculos,
    ) {
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbtransportista t {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT t.* FROM tbtransportista t
                {$where}
                ORDER BY t.tbtransportistaestado DESC, t.tbtransportistanombre,
                         t.tbtransportistaidentificacionnumero
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
            'SELECT * FROM tbtransportista WHERE tbtransportistaidentificacionnumero = :identificacionNumero'
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
            'SELECT tbtransportistaidentificacionnumero AS identificacionNumero,
                    tbtransportistanombre AS nombre
             FROM tbtransportista WHERE tbtransportistaid = :id'
        );
        $sentencia->execute(['id' => $transportistaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbtransportista
             WHERE tbtransportistaidentificacionnumero = :identificacionNumero FOR UPDATE'
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
        NamedLock::acquire($this->conexion, 'tindercows_transportista_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_transportista_alta');
    }

    public function crear(array $datos): int
    {
        $transportistaId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbtransportista
             (tbtransportistaid, tbtransportistaidentificacionnumero, tbtransportistaidentificaciontipo,
              tbtransportistanombre, tbtransportistatelefono, tbtransportistacorreoelectronico, tbtransportistaestado)
             VALUES (:transportistaId, :identificacionNumero, :identificacionTipo, :nombre, :telefono, :correoElectronico, :estado)'
        );
        $sentencia->execute([
            'transportistaId' => $transportistaId,
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
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
        $sentencia = $this->conexion->prepare(
            'UPDATE tbtransportista
             SET tbtransportistaidentificaciontipo = :identificacionTipo,
                 tbtransportistanombre = :nombre,
                 tbtransportistatelefono = :telefono,
                 tbtransportistacorreoelectronico = :correoElectronico
             WHERE tbtransportistaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute([
            'identificacionNumero' => $identificacionNumero,
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);
    }

    public function cambiarEstado(string $identificacionNumero, bool $activo): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbtransportista SET tbtransportistaestado = :estado
             WHERE tbtransportistaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute([
            'estado' => $activo ? 1 : 0,
            'identificacionNumero' => $identificacionNumero,
        ]);
    }

    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(t.tbtransportistanombre LIKE :busquedaNombre
                OR t.tbtransportistacorreoelectronico LIKE :busquedaCorreo
                OR t.tbtransportistaidentificacionnumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 't.tbtransportistaestado = :estado';
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
            'estado' => (int) $fila['tbtransportistaestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            'vehiculos' => $vehiculos,
        ];
    }
}