<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Contexto de negocio Comprador sobre una Persona.
 *
 * No duplica identidad ni contacto: esos datos viven en tbpersona. Este modelo
 * es de consulta durante el Avance 2; la creación del contexto debe provenir
 * del proceso de negocio correspondiente y no de un rol administrativo manual.
 */
final class Comprador
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT c.tbcompradorid, c.tbpersonaid, c.tbcompradorestado,
                    p.tbpersonaidentificacionnumero, p.tbpersonaidentificaciontipo,
                    p.tbpersonanombre, p.tbpersonaalias, p.tbpersonatelefono,
                    p.tbpersonacorreoelectronico, p.tbpersonaestado
             FROM tbcomprador c
             INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid
             WHERE p.tbpersonaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if ($filas === []) {
            return null;
        }
        if (count($filas) !== 1) {
            throw new \RuntimeException('La identificación no conserva un único contexto Comprador.');
        }

        return $this->mapear($filas[0]);
    }

    public function listar(string $busqueda, int $pagina, int $tamano): array
    {
        $where = '';
        $parametros = [];
        if ($busqueda !== '') {
            $where = 'WHERE (p.tbpersonanombre LIKE :nombre
                       OR p.tbpersonaalias LIKE :alias
                       OR p.tbpersonaidentificacionnumero LIKE :identificacion
                       OR p.tbpersonatelefono LIKE :telefono)';
            $parametros = [
                'nombre' => "%{$busqueda}%",
                'alias' => "%{$busqueda}%",
                'identificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
                'telefono' => "%{$busqueda}%",
            ];
        }

        $conteo = $this->conexion->prepare(
            "SELECT COUNT(*) FROM tbcomprador c
             INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid {$where}"
        );
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sentencia = $this->conexion->prepare(
            "SELECT c.tbcompradorid, c.tbpersonaid, c.tbcompradorestado,
                    p.tbpersonaidentificacionnumero, p.tbpersonaidentificaciontipo,
                    p.tbpersonanombre, p.tbpersonaalias, p.tbpersonatelefono,
                    p.tbpersonacorreoelectronico, p.tbpersonaestado
             FROM tbcomprador c
             INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid
             {$where}
             ORDER BY p.tbpersonanombre, p.tbpersonaidentificacionnumero
             LIMIT :limite OFFSET :desplazamiento"
        );
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue(':' . $nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'compradores' => array_map(fn (array $fila): array => $this->mapear($fila), $sentencia->fetchAll()),
            'total' => $total,
        ];
    }

    private function mapear(array $fila): array
    {
        return [
            'compradorId' => (int) $fila['tbcompradorid'],
            'personaId' => (int) $fila['tbpersonaid'],
            'identificacionNumero' => $fila['tbpersonaidentificacionnumero'],
            'identificacion' => [
                'tipoCodigo' => $fila['tbpersonaidentificaciontipo'],
                'numero' => $fila['tbpersonaidentificacionnumero'],
            ],
            'nombre' => $fila['tbpersonanombre'],
            'alias' => $fila['tbpersonaalias'],
            'telefono' => $fila['tbpersonatelefono'],
            'correoElectronico' => $fila['tbpersonacorreoelectronico'],
            'estado' => (int) $fila['tbcompradorestado'] === 1 && (int) $fila['tbpersonaestado'] === 1
                ? 'ACTIVO' : 'INACTIVO',
        ];
    }
}
