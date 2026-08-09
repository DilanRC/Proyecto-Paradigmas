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

        $sql = "SELECT p.*, d.tbproductordireccionprovincia, d.tbproductordireccioncanton,
                       d.tbproductordirecciondistrito, d.tbproductordireccionpueblo,
                       d.tbproductordireccionsenas
                FROM tbproductor p
                INNER JOIN tbproductordireccion d
                    ON d.tbproductorid = p.tbproductorid
                {$where}
                ORDER BY p.tbproductorestado DESC, p.tbproductornombre,
                         p.tbproductoridentificacionnumero
                LIMIT :limite OFFSET :desplazamiento";
        $sentencia = $this->conexion->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();
        $porProductor = $this->fincas->listarPorProductores(array_map('intval', array_column($filas, 'tbproductorid')));

        return [
            'productores' => array_map(
                fn (array $fila): array => $this->mapear($fila, $porProductor[(int) $fila['tbproductorid']] ?? []),
                $filas,
            ),
            'total' => $total,
        ];
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.*, d.tbproductordireccionprovincia, d.tbproductordireccioncanton,
                    d.tbproductordirecciondistrito, d.tbproductordireccionpueblo,
                    d.tbproductordireccionsenas
             FROM tbproductor p
             INNER JOIN tbproductordireccion d
                ON d.tbproductorid = p.tbproductorid
             WHERE p.tbproductoridentificacionnumero = :identificacionNumero'
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

        return $this->mapear($fila, $this->fincas->listarActivas((int) $fila['tbproductorid']));
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductor
             WHERE tbproductoridentificacionnumero = :identificacionNumero FOR UPDATE'
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
             (tbproductorid, tbproductoridentificacionnumero, tbproductoridentificaciontipo,
              tbproductornombre, tbproductortelefono,
              tbproductorcorreoelectronico, tbproductorestado)
             VALUES (:productorId, :identificacionNumero, :identificacionTipo, :nombre, :telefono,
                     :correoElectronico, :estado)'
        );
        $sentencia->execute([
            'productorId' => $productorId,
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
            'estado' => 1,
        ]);

        return $productorId;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbproductorid), 0) + 1 FROM tbproductor');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductor
             SET tbproductoridentificaciontipo = :identificacionTipo,
                 tbproductornombre = :nombre,
                 tbproductortelefono = :telefono,
                 tbproductorcorreoelectronico = :correoElectronico
             WHERE tbproductoridentificacionnumero = :identificacionNumero'
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
            'UPDATE tbproductor SET tbproductorestado = :estado
             WHERE tbproductoridentificacionnumero = :identificacionNumero'
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
            $condiciones[] = '(p.tbproductornombre LIKE :busquedaNombre
                OR p.tbproductorcorreoelectronico LIKE :busquedaCorreo
                OR p.tbproductoridentificacionnumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'p.tbproductorestado = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila, array $fincas): array
    {
        return [
            'productorId' => (int) $fila['tbproductorid'],
            'identificacionNumero' => $fila['tbproductoridentificacionnumero'],
            'identificacion' => [
                'tipoCodigo' => $fila['tbproductoridentificaciontipo'],
                'numero' => $fila['tbproductoridentificacionnumero'],
            ],
            'nombre' => $fila['tbproductornombre'],
            'telefono' => $fila['tbproductortelefono'],
            'correoElectronico' => $fila['tbproductorcorreoelectronico'],
            'estado' => (int) $fila['tbproductorestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            'direccionPrincipal' => [
                'provincia' => $fila['tbproductordireccionprovincia'],
                'canton' => $fila['tbproductordireccioncanton'],
                'distrito' => $fila['tbproductordirecciondistrito'],
                'pueblo' => $fila['tbproductordireccionpueblo'],
                'senas' => $fila['tbproductordireccionsenas'],
            ],
            'fincas' => $fincas,
        ];
    }
}
