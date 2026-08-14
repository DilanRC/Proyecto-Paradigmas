<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Comprador
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbcomprador c {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT c.* FROM tbcomprador c
                {$where}
                ORDER BY c.tbcompradorestado DESC, c.tbcompradornombre,
                         c.tbcompradoridentificacionnumero
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
            'compradores' => array_map(fn (array $fila): array => $this->mapear($fila), $filas),
            'total' => $total,
        ];
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbcomprador WHERE tbcompradoridentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if ($filas === []) {
            return null;
        }
        if (count($filas) !== 1) {
            throw new \RuntimeException('La identificación no conserva un único comprador.');
        }

        return $this->mapear($filas[0]);
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbcomprador
             WHERE tbcompradoridentificacionnumero = :identificacionNumero FOR UPDATE'
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
        NamedLock::acquire($this->conexion, 'tindercows_comprador_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_comprador_alta');
    }

    public function crear(array $datos): int
    {
        $compradorId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbcomprador
             (tbcompradorid, tbcompradoridentificacionnumero, tbcompradoridentificaciontipo,
              tbcompradornombre, tbcompradortelefono,
              tbcompradorcorreoelectronico, tbcompradorestado)
             VALUES (:compradorId, :identificacionNumero, :identificacionTipo, :nombre, :telefono,
                     :correoElectronico, :estado)'
        );
        $sentencia->execute([
            'compradorId' => $compradorId,
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
            'estado' => 1,
        ]);

        return $compradorId;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbcompradorid), 0) + 1 FROM tbcomprador');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbcomprador
             SET tbcompradoridentificaciontipo = :identificacionTipo,
                 tbcompradornombre = :nombre,
                 tbcompradortelefono = :telefono,
                 tbcompradorcorreoelectronico = :correoElectronico
             WHERE tbcompradoridentificacionnumero = :identificacionNumero'
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
            'UPDATE tbcomprador SET tbcompradorestado = :estado
             WHERE tbcompradoridentificacionnumero = :identificacionNumero'
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
            $condiciones[] = '(c.tbcompradornombre LIKE :busquedaNombre
                OR c.tbcompradorcorreoelectronico LIKE :busquedaCorreo
                OR c.tbcompradoridentificacionnumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'c.tbcompradorestado = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila): array
    {
        return [
            'compradorId' => (int) $fila['tbcompradorid'],
            'identificacionNumero' => $fila['tbcompradoridentificacionnumero'],
            'identificacion' => [
                'tipoCodigo' => $fila['tbcompradoridentificaciontipo'],
                'numero' => $fila['tbcompradoridentificacionnumero'],
            ],
            'nombre' => $fila['tbcompradornombre'],
            'telefono' => $fila['tbcompradortelefono'],
            'correoElectronico' => $fila['tbcompradorcorreoelectronico'],
            'estado' => (int) $fila['tbcompradorestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
        ];
    }
}