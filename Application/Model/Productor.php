<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Productor
{
    private Persona $persona;
    public function __construct(
        private readonly PDO $conexion,
        private readonly ProductorFinca $fincas,
    ) {
        $this->persona = new Persona($conexion);
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT p.*, pe.tbpersonaidentificacionnumero AS tbproductoridentificacionnumero,
                       pe.tbpersonaidentificaciontipo AS tbproductoridentificaciontipo,
                       pe.tbpersonanombre AS tbproductornombre, pe.tbpersonatelefono AS tbproductortelefono,
                       pe.tbpersonacorreoelectronico AS tbproductorcorreoelectronico, pe.tbpersonaestado,
                       d.tbdireccionprovincia AS tbproductordireccionprovincia,
                       d.tbdireccioncanton AS tbproductordireccioncanton,
                       d.tbdirecciondistrito AS tbproductordirecciondistrito,
                       d.tbdireccionpueblo AS tbproductordireccionpueblo,
                       d.tbdireccionsenas AS tbproductordireccionsenas
                FROM tbproductor p
                INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
                INNER JOIN tbproductordireccion pd
                    ON pd.tbproductorid = p.tbproductorid
                INNER JOIN tbdireccion d
                    ON d.tbdireccionid = pd.tbdireccionid
                {$where}
                ORDER BY (p.tbproductorestado * pe.tbpersonaestado) DESC, pe.tbpersonanombre,
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
            'SELECT p.*, pe.tbpersonaidentificacionnumero AS tbproductoridentificacionnumero,
                    pe.tbpersonaidentificaciontipo AS tbproductoridentificaciontipo,
                    pe.tbpersonanombre AS tbproductornombre, pe.tbpersonatelefono AS tbproductortelefono,
                    pe.tbpersonacorreoelectronico AS tbproductorcorreoelectronico, pe.tbpersonaestado,
                    d.tbdireccionprovincia AS tbproductordireccionprovincia,
                    d.tbdireccioncanton AS tbproductordireccioncanton,
                    d.tbdirecciondistrito AS tbproductordirecciondistrito,
                    d.tbdireccionpueblo AS tbproductordireccionpueblo,
                    d.tbdireccionsenas AS tbproductordireccionsenas
             FROM tbproductor p
             INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
             LEFT JOIN tbproductordireccion pd
                ON pd.tbproductorid = p.tbproductorid
             LEFT JOIN tbdireccion d
                ON d.tbdireccionid = pd.tbdireccionid
             WHERE pe.tbpersonaidentificacionnumero = :identificacionNumero'
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
            'SELECT p.*, pe.tbpersonaidentificacionnumero AS tbproductoridentificacionnumero,
                    pe.tbpersonanombre AS tbproductornombre, pe.tbpersonaestado
             FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid
             WHERE p.tbproductorid = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.*, pe.tbpersonaestado FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid=p.tbpersonaid
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
        $productorId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductor
             (tbproductorid, tbpersonaid, tbproductorestado)
             VALUES (:productorId, :personaId, :estado)'
        );
        $sentencia->execute([
            'productorId' => $productorId,
            'personaId' => $persona['tbpersonaid'],
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
        $this->persona->actualizar($identificacionNumero, $datos);
    }

    public function cambiarEstado(string $identificacionNumero, bool $activo): void
    {
        $persona = $this->persona->buscar($identificacionNumero);
        if ($persona === null) return;
        $sentencia = $this->conexion->prepare('UPDATE tbproductor SET tbproductorestado = :estado WHERE tbpersonaid = :personaId');
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
            $condiciones[] = '(p.tbproductorestado * pe.tbpersonaestado) = :estado';
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
            'estado' => (int) $fila['tbproductorestado'] === 1 && (int) $fila['tbpersonaestado'] === 1 ? 'ACTIVO' : 'INACTIVO',
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
