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

    /**
     * Estado vigente derivado del periodo abierto de tbproductorestadoperiodo.
     * Un productor sin periodos (dato heredado o corrupto) se considera
     * INACTIVO: no hay evidencia de que esté activo.
     */
    private function sqlEstadoVigente(string $alias): string
    {
        return "COALESCE((SELECT ep.tbproductorestadoperiodoestado
                 FROM tbproductorestadoperiodo ep
                 WHERE ep.tbproductorid = {$alias}.tbproductorid
                   AND ep.tbproductorestadoperiodofechafin IS NULL), 0)";
    }

    private function sqlExistePeriodoActivo(string $alias): string
    {
        return "EXISTS (SELECT 1 FROM tbproductorestadoperiodo ep
                 WHERE ep.tbproductorid = {$alias}.tbproductorid
                   AND ep.tbproductorestadoperiodofechafin IS NULL
                   AND ep.tbproductorestadoperiodoestado = 1)";
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbproductor p {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT p.*, {$this->sqlEstadoVigente('p')} AS tbproductorestado,
                       d.tbdireccionprovincia AS tbproductordireccionprovincia,
                       d.tbdireccioncanton AS tbproductordireccioncanton,
                       d.tbdirecciondistrito AS tbproductordirecciondistrito,
                       d.tbdireccionpueblo AS tbproductordireccionpueblo,
                       d.tbdireccionsenas AS tbproductordireccionsenas
                FROM tbproductor p
                INNER JOIN tbproductordireccion pd
                    ON pd.tbproductorid = p.tbproductorid
                INNER JOIN tbdireccion d
                    ON d.tbdireccionid = pd.tbdireccionid
                {$where}
                ORDER BY {$this->sqlEstadoVigente('p')} DESC, p.tbproductornombre,
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
            "SELECT p.*, {$this->sqlEstadoVigente('p')} AS tbproductorestado,
                    d.tbdireccionprovincia AS tbproductordireccionprovincia,
                    d.tbdireccioncanton AS tbproductordireccioncanton,
                    d.tbdirecciondistrito AS tbproductordirecciondistrito,
                    d.tbdireccionpueblo AS tbproductordireccionpueblo,
                    d.tbdireccionsenas AS tbproductordireccionsenas
             FROM tbproductor p
             LEFT JOIN tbproductordireccion pd
                ON pd.tbproductorid = p.tbproductorid
             LEFT JOIN tbdireccion d
                ON d.tbdireccionid = pd.tbdireccionid
             WHERE p.tbproductoridentificacionnumero = :identificacionNumero"
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

    /** Fila cruda por ID numérico; usada para validar existencia y estado. */
    public function buscarPorId(int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT p.*, {$this->sqlEstadoVigente('p')} AS tbproductorestado
             FROM tbproductor p WHERE p.tbproductorid = :productorId"
        );
        $sentencia->execute(['productorId' => $productorId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT p.*, {$this->sqlEstadoVigente('p')} AS tbproductorestado
             FROM tbproductor p
             WHERE p.tbproductoridentificacionnumero = :identificacionNumero FOR UPDATE"
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
        NamedLock::acquire($this->conexion, 'tindercows_productor_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_productor_alta');
    }

    public function crear(array $datos): int
    {
        $productorId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductor
             (tbproductorid, tbproductoridentificacionnumero, tbproductoridentificaciontipo,
              tbproductornombre, tbproductortelefono,
              tbproductorcorreoelectronico)
             VALUES (:productorId, :identificacionNumero, :identificacionTipo, :nombre, :telefono,
                     :correoElectronico)'
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
            $condiciones[] = $estado === 'ACTIVO'
                ? $this->sqlExistePeriodoActivo('p')
                : 'NOT ' . $this->sqlExistePeriodoActivo('p');
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
