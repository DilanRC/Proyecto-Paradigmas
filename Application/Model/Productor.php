<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Productor
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly ProductorFinca $fincas,
    ) {
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbproductor p {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT p.*, d.tbproductordireccionProvincia, d.tbproductordireccionCanton,
                       d.tbproductordireccionDistrito, d.tbproductordireccionPueblo,
                       d.tbproductordireccionSenas
                FROM tbproductor p
                INNER JOIN tbproductordireccion d
                    ON d.tbproductorId = p.tbproductorId
                {$where}
                ORDER BY p.tbproductorEstado DESC, p.tbproductorNombre,
                         p.tbproductorIdentificacionNumero
                LIMIT :limite OFFSET :desplazamiento";
        $sentencia = $this->conexion->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();
        $porProductor = $this->fincas->listarPorProductores(array_map('intval', array_column($filas, 'tbproductorId')));

        return [
            'productores' => array_map(
                fn (array $fila): array => $this->mapear($fila, $porProductor[(int) $fila['tbproductorId']] ?? []),
                $filas,
            ),
            'total' => $total,
        ];
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.*, d.tbproductordireccionProvincia, d.tbproductordireccionCanton,
                    d.tbproductordireccionDistrito, d.tbproductordireccionPueblo,
                    d.tbproductordireccionSenas
             FROM tbproductor p
             INNER JOIN tbproductordireccion d
                ON d.tbproductorId = p.tbproductorId
             WHERE p.tbproductorIdentificacionNumero = :identificacionNumero'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if ($filas === []) {
            return null;
        }
        if (count($filas) !== 1) {
            throw new \RuntimeException('La identificación no conserva un único productor y una única dirección.');
        }
        $fila = $filas[0];

        return $this->mapear($fila, $this->fincas->listarActivas((int) $fila['tbproductorId']));
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductor
             WHERE tbproductorIdentificacionNumero = :identificacionNumero FOR UPDATE'
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
        $sentencia = $this->conexion->prepare("SELECT GET_LOCK('tindercows_productor_alta', 10)");
        $sentencia->execute();
        if ((int) $sentencia->fetchColumn() !== 1) {
            throw new \RuntimeException('No fue posible reservar la secuencia de productores.');
        }
    }

    private function liberarBloqueoAlta(): void
    {
        $sentencia = $this->conexion->prepare("SELECT RELEASE_LOCK('tindercows_productor_alta')");
        $sentencia->execute();
    }

    public function crear(array $datos): int
    {
        $productorId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductor
             (tbproductorId, tbproductorIdentificacionNumero, tbproductorIdentificacionTipo,
              tbproductorNombre, tbproductorTelefono,
              tbproductorCorreoElectronico, tbproductorEstado)
             VALUES (:productorId, :identificacionNumero, :identificacionTipo, :nombre, :telefono,
                     :correoElectronico, 1)'
        );
        $sentencia->execute([
            'productorId' => $productorId,
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);

        return $productorId;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbproductorId), 0) + 1 FROM tbproductor');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductor
             SET tbproductorIdentificacionTipo = :identificacionTipo,
                 tbproductorNombre = :nombre,
                 tbproductorTelefono = :telefono,
                 tbproductorCorreoElectronico = :correoElectronico
             WHERE tbproductorIdentificacionNumero = :identificacionNumero'
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
            'UPDATE tbproductor SET tbproductorEstado = :estado
             WHERE tbproductorIdentificacionNumero = :identificacionNumero'
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
            $condiciones[] = '(p.tbproductorNombre LIKE :busquedaNombre
                OR p.tbproductorCorreoElectronico LIKE :busquedaCorreo
                OR p.tbproductorIdentificacionNumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'p.tbproductorEstado = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila, array $fincas): array
    {
        return [
            'productorId' => (int) $fila['tbproductorId'],
            'identificacionNumero' => $fila['tbproductorIdentificacionNumero'],
            'identificacion' => [
                'tipoCodigo' => $fila['tbproductorIdentificacionTipo'],
                'numero' => $fila['tbproductorIdentificacionNumero'],
            ],
            'nombre' => $fila['tbproductorNombre'],
            'telefono' => $fila['tbproductorTelefono'],
            'correoElectronico' => $fila['tbproductorCorreoElectronico'],
            'estado' => (int) $fila['tbproductorEstado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            'direccionPrincipal' => [
                'provincia' => $fila['tbproductordireccionProvincia'],
                'canton' => $fila['tbproductordireccionCanton'],
                'distrito' => $fila['tbproductordireccionDistrito'],
                'pueblo' => $fila['tbproductordireccionPueblo'],
                'senas' => $fila['tbproductordireccionSenas'],
            ],
            'fincas' => $fincas,
        ];
    }
}
