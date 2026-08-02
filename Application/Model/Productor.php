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
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM tbproductores p {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT p.*, d.tbproductoresdireccionProvincia, d.tbproductoresdireccionCanton,
                       d.tbproductoresdireccionDistrito, d.tbproductoresdireccionPueblo,
                       d.tbproductoresdireccionSenas
                FROM tbproductores p
                INNER JOIN tbproductoresdireccion d
                    ON d.tbproductoresIdentificacionNumero = p.tbproductoresIdentificacionNumero
                {$where}
                ORDER BY p.tbproductoresEstado DESC, p.tbproductoresNombre,
                         p.tbproductoresIdentificacionNumero
                LIMIT :limite OFFSET :desplazamiento";
        $sentencia = $this->conexion->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $filas = $sentencia->fetchAll();
        $porProductor = $this->fincas->listarPorProductores(array_column($filas, 'tbproductoresIdentificacionNumero'));

        return [
            'productores' => array_map(
                fn (array $fila): array => $this->mapear($fila, $porProductor[$fila['tbproductoresIdentificacionNumero']] ?? []),
                $filas,
            ),
            'total' => $total,
        ];
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.*, d.tbproductoresdireccionProvincia, d.tbproductoresdireccionCanton,
                    d.tbproductoresdireccionDistrito, d.tbproductoresdireccionPueblo,
                    d.tbproductoresdireccionSenas
             FROM tbproductores p
             INNER JOIN tbproductoresdireccion d
                ON d.tbproductoresIdentificacionNumero = p.tbproductoresIdentificacionNumero
             WHERE p.tbproductoresIdentificacionNumero = :identificacionNumero LIMIT 1'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $fila = $sentencia->fetch();
        if ($fila === false) {
            return null;
        }

        return $this->mapear($fila, $this->fincas->listarActivas($identificacionNumero));
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductores
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero FOR UPDATE'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function crear(array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductores
             (tbproductoresIdentificacionNumero, tbproductoresIdentificacionTipo,
              tbproductoresNombre, tbproductoresTelefono,
              tbproductoresCorreoElectronico, tbproductoresEstado)
             VALUES (:identificacionNumero, :identificacionTipo, :nombre, :telefono,
                     :correoElectronico, 1)'
        );
        $sentencia->execute([
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductores
             SET tbproductoresIdentificacionTipo = :identificacionTipo,
                 tbproductoresNombre = :nombre,
                 tbproductoresTelefono = :telefono,
                 tbproductoresCorreoElectronico = :correoElectronico
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
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
            'UPDATE tbproductores SET tbproductoresEstado = :estado
             WHERE tbproductoresIdentificacionNumero = :identificacionNumero'
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
            $condiciones[] = '(p.tbproductoresNombre LIKE :busquedaNombre
                OR p.tbproductoresCorreoElectronico LIKE :busquedaCorreo
                OR p.tbproductoresIdentificacionNumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'p.tbproductoresEstado = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila, array $fincas): array
    {
        return [
            'identificacionNumero' => $fila['tbproductoresIdentificacionNumero'],
            'identificacion' => [
                'tipoCodigo' => $fila['tbproductoresIdentificacionTipo'],
                'numero' => $fila['tbproductoresIdentificacionNumero'],
            ],
            'nombre' => $fila['tbproductoresNombre'],
            'telefono' => $fila['tbproductoresTelefono'],
            'correoElectronico' => $fila['tbproductoresCorreoElectronico'],
            'estado' => (int) $fila['tbproductoresEstado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            'direccionPrincipal' => [
                'provincia' => $fila['tbproductoresdireccionProvincia'],
                'canton' => $fila['tbproductoresdireccionCanton'],
                'distrito' => $fila['tbproductoresdireccionDistrito'],
                'pueblo' => $fila['tbproductoresdireccionPueblo'],
                'senas' => $fila['tbproductoresdireccionSenas'],
            ],
            'fincas' => $fincas,
        ];
    }
}
