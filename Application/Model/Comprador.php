<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Comprador
{
    private Persona $persona;

    public function __construct(private readonly PDO $conexion)
    {
        $this->persona = new Persona($conexion);
    }

    private function seleccion(): string
    {
        return 'c.*, pe.tbpersonaidentificacionnumero AS tbcompradoridentificacionnumero,
                pe.tbpersonaidentificaciontipo AS tbcompradoridentificaciontipo,
                pe.tbpersonanombre AS tbcompradornombre,
                pe.tbpersonatelefono AS tbcompradortelefono,
                pe.tbpersonacorreoelectronico AS tbcompradorcorreoelectronico,
                pe.tbpersonaestado';
    }
    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $base = ' FROM tbcomprador c INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid ';
        $conteo = $this->conexion->prepare('SELECT COUNT(*)' . $base . $where);
        $conteo->execute($parametros);
        $sentencia = $this->conexion->prepare('SELECT ' . $this->seleccion() . $base . $where
            . ' ORDER BY (c.tbcompradorestado * pe.tbpersonaestado) DESC, pe.tbpersonanombre,
                pe.tbpersonaidentificacionnumero LIMIT :limite OFFSET :desplazamiento');
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'compradores' => array_map(fn (array $fila): array => $this->mapear($fila), $sentencia->fetchAll()),
            'total' => (int) $conteo->fetchColumn(),
        ];
    }
    public function buscar(string $id): ?array
    {
        $sentencia = $this->conexion->prepare('SELECT ' . $this->seleccion()
            . ' FROM tbcomprador c INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
                WHERE pe.tbpersonaidentificacionnumero = :id');
        $sentencia->execute(['id' => $id]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('La identificación no conserva un único comprador.');
        }

        return $filas === [] ? null : $this->mapear($filas[0]);
    }
    public function bloquear(string $id): ?array
    {
        $persona = $this->persona->bloquear($id);
        if ($persona === null) return null;
        $sentencia = $this->conexion->prepare('SELECT c.*, pe.tbpersonaestado FROM tbcomprador c
            INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
            WHERE c.tbpersonaid = :id FOR UPDATE');
        $sentencia->execute(['id' => $persona['tbpersonaid']]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) throw new \RuntimeException('La capacidad de comprador está duplicada.');
        return $filas[0] ?? null;
    }
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        return $this->persona->ejecutarConBloqueoAlta($operacion);
    }
    public function crear(array $d): int
    {
        $persona = $this->persona->obtenerOCrear($d);
        $siguiente = $this->conexion->prepare('SELECT COALESCE(MAX(tbcompradorid), 0) + 1 FROM tbcomprador');
        $siguiente->execute();
        $id = (int) $siguiente->fetchColumn();
        $sentencia = $this->conexion->prepare('INSERT INTO tbcomprador
            (tbcompradorid, tbpersonaid, tbcompradorestado) VALUES (:id, :personaId, 1)');
        $sentencia->execute(['id' => $id, 'personaId' => $persona['tbpersonaid']]);
        return $id;
    }
    public function actualizar(string $id, array $datos): void { $this->persona->actualizar($id, $datos); }
    public function cambiarEstado(string $id, bool $activo): void
    {
        $persona = $this->persona->buscar($id);
        if ($persona === null) return;
        $sentencia = $this->conexion->prepare('UPDATE tbcomprador SET tbcompradorestado = :estado WHERE tbpersonaid = :personaId');
        $sentencia->execute(['estado' => $activo ? 1 : 0, 'personaId' => $persona['tbpersonaid']]);
    }
    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(pe.tbpersonanombre LIKE :nombre OR pe.tbpersonacorreoelectronico LIKE :correo
                OR pe.tbpersonaidentificacionnumero LIKE :identificacion)';
            $parametros = [
                ':nombre' => "%{$busqueda}%",
                ':correo' => "%{$busqueda}%",
                ':identificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = '(c.tbcompradorestado * pe.tbpersonaestado) = :estado';
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
            'estado' => (int) $fila['tbcompradorestado'] === 1 && (int) $fila['tbpersonaestado'] === 1
                ? 'ACTIVO' : 'INACTIVO',
        ];
    }
}
