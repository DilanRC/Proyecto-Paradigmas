<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Participante
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly ProductorFinca $productorFinca,
    ) {
    }

    public function listarProductores(string $busqueda, string $estado, int $pagina, int $tamanoPagina): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
        $conteo = $this->conexion->prepare(
            "SELECT COUNT(DISTINCT p.tbparticipanteId)
             FROM tbparticipante p
             INNER JOIN tbparticipanterol pr ON pr.tbparticipanteId = p.tbparticipanteId AND pr.tbparticipanterolEstado = 1
             INNER JOIN tbrol r ON r.tbrolId = pr.tbrolId AND r.tbrolCodigo = 'PRODUCTOR' AND r.tbrolEstado = 1
             INNER JOIN tbparticipanteidentificacion i ON i.tbparticipanteId = p.tbparticipanteId
               AND i.tbparticipanteidentificacionEsPrincipal = 1 AND i.tbparticipanteidentificacionEstado = 1
             {$where}"
        );
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $desplazamiento = ($pagina - 1) * $tamanoPagina;
        $consulta = $this->conexion->prepare(
            "SELECT p.tbparticipanteId, p.tbparticipanteNombre, p.tbparticipanteTelefono,
                    p.tbparticipanteCorreoElectronico, p.tbparticipanteEstado,
                    i.tbidentificaciontipoId, t.tbidentificaciontipoCodigo,
                    i.tbparticipanteidentificacionNumero,
                    d.tbparticipantedireccionProvincia, d.tbparticipantedireccionCanton,
                    d.tbparticipantedireccionDistrito, d.tbparticipantedireccionPueblo,
                    d.tbparticipantedireccionSenas
             FROM tbparticipante p
             INNER JOIN tbparticipanterol pr ON pr.tbparticipanteId = p.tbparticipanteId AND pr.tbparticipanterolEstado = 1
             INNER JOIN tbrol r ON r.tbrolId = pr.tbrolId AND r.tbrolCodigo = 'PRODUCTOR' AND r.tbrolEstado = 1
             INNER JOIN tbparticipanteidentificacion i ON i.tbparticipanteId = p.tbparticipanteId
               AND i.tbparticipanteidentificacionEsPrincipal = 1 AND i.tbparticipanteidentificacionEstado = 1
             INNER JOIN tbidentificaciontipo t ON t.tbidentificaciontipoId = i.tbidentificaciontipoId
             INNER JOIN tbparticipantedireccion d ON d.tbparticipanteId = p.tbparticipanteId
               AND d.tbparticipantedireccionEsPrincipal = 1 AND d.tbparticipantedireccionEstado = 1
             {$where}
             ORDER BY p.tbparticipanteEstado DESC, p.tbparticipanteNombre, p.tbparticipanteId
             LIMIT :limite OFFSET :desplazamiento"
        );
        foreach ($parametros as $nombre => $valor) {
            $consulta->bindValue(':' . $nombre, $valor, PDO::PARAM_STR);
        }
        $consulta->bindValue(':limite', $tamanoPagina, PDO::PARAM_INT);
        $consulta->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);
        $consulta->execute();
        $filas = $consulta->fetchAll();
        $fincas = $this->productorFinca->listarPorParticipantes(
            array_map(static fn (array $fila): int => (int) $fila['tbparticipanteId'], $filas)
        );

        return [
            'productores' => array_map(fn (array $fila): array => $this->mapear($fila, $fincas), $filas),
            'total' => $total,
        ];
    }

    public function buscarPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT p.tbparticipanteId, p.tbparticipanteNombre, p.tbparticipanteTelefono,
                    p.tbparticipanteCorreoElectronico, p.tbparticipanteEstado,
                    i.tbidentificaciontipoId, t.tbidentificaciontipoCodigo,
                    i.tbparticipanteidentificacionNumero,
                    d.tbparticipantedireccionProvincia, d.tbparticipantedireccionCanton,
                    d.tbparticipantedireccionDistrito, d.tbparticipantedireccionPueblo,
                    d.tbparticipantedireccionSenas
             FROM tbparticipante p
             INNER JOIN tbparticipanterol pr ON pr.tbparticipanteId = p.tbparticipanteId AND pr.tbparticipanterolEstado = 1
             INNER JOIN tbrol r ON r.tbrolId = pr.tbrolId AND r.tbrolCodigo = 'PRODUCTOR' AND r.tbrolEstado = 1
             INNER JOIN tbparticipanteidentificacion i ON i.tbparticipanteId = p.tbparticipanteId
               AND i.tbparticipanteidentificacionEsPrincipal = 1 AND i.tbparticipanteidentificacionEstado = 1
             INNER JOIN tbidentificaciontipo t ON t.tbidentificaciontipoId = i.tbidentificaciontipoId
             INNER JOIN tbparticipantedireccion d ON d.tbparticipanteId = p.tbparticipanteId
               AND d.tbparticipantedireccionEsPrincipal = 1 AND d.tbparticipantedireccionEstado = 1
             WHERE p.tbparticipanteId = :id LIMIT 1"
        );
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();
        if ($fila === false) {
            return null;
        }
        $fincas = $this->productorFinca->listarPorParticipantes([$id]);

        return $this->mapear($fila, $fincas);
    }

    public function bloquearPorId(int $id): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbparticipanteId, tbparticipanteEstado FROM tbparticipante WHERE tbparticipanteId = :id FOR UPDATE'
        );
        $sentencia->execute(['id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function crear(array $datos): int
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbparticipante
             (tbparticipanteNombre, tbparticipanteTelefono, tbparticipanteCorreoElectronico, tbparticipanteEstado)
             VALUES (:nombre, :telefono, :correoElectronico, 1)'
        );
        $sentencia->execute([
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);

        return (int) $this->conexion->lastInsertId();
    }

    public function actualizarContacto(int $id, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbparticipante
             SET tbparticipanteNombre = :nombre, tbparticipanteTelefono = :telefono,
                 tbparticipanteCorreoElectronico = :correoElectronico
             WHERE tbparticipanteId = :id'
        );
        $sentencia->execute([
            'id' => $id,
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbparticipante SET tbparticipanteEstado = :estado WHERE tbparticipanteId = :id'
        );
        $sentencia->execute(['id' => $id, 'estado' => $activo ? 1 : 0]);
    }

    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(p.tbparticipanteNombre LIKE :busquedaNombre
                OR p.tbparticipanteCorreoElectronico LIKE :busquedaCorreo
                OR i.tbparticipanteidentificacionNumero LIKE :busquedaNumero
                OR i.tbparticipanteidentificacionNumeroNormalizado LIKE :busquedaNormalizada)';
            $termino = '%' . $busqueda . '%';
            $parametros['busquedaNombre'] = $termino;
            $parametros['busquedaCorreo'] = $termino;
            $parametros['busquedaNumero'] = $termino;
            $parametros['busquedaNormalizada'] = $termino;
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'p.tbparticipanteEstado = :estado';
            $parametros['estado'] = $estado === 'ACTIVO' ? '1' : '0';
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
    }

    private function mapear(array $fila, array $fincas): array
    {
        $id = (int) $fila['tbparticipanteId'];

        return [
            'participanteId' => $id,
            'rol' => 'PRODUCTOR',
            'identificacion' => [
                'tipoId' => (int) $fila['tbidentificaciontipoId'],
                'tipoCodigo' => $fila['tbidentificaciontipoCodigo'],
                'numero' => $fila['tbparticipanteidentificacionNumero'],
            ],
            'nombre' => $fila['tbparticipanteNombre'],
            'telefono' => $fila['tbparticipanteTelefono'],
            'correoElectronico' => $fila['tbparticipanteCorreoElectronico'],
            'estado' => (int) $fila['tbparticipanteEstado'] === 1 ? 'ACTIVO' : 'INACTIVO',
            'direccionPrincipal' => [
                'provincia' => $fila['tbparticipantedireccionProvincia'],
                'canton' => $fila['tbparticipantedireccionCanton'],
                'distrito' => $fila['tbparticipantedireccionDistrito'],
                'pueblo' => $fila['tbparticipantedireccionPueblo'],
                'senas' => $fila['tbparticipantedireccionSenas'],
            ],
            'fincas' => $fincas[$id] ?? [],
        ];
    }
}
