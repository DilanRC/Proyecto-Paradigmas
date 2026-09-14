<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Contexto de negocio Comprador sobre una Persona.
 *
 * No duplica identidad ni contacto: esos datos viven en tbpersona. El panel
 * administrativo sigue siendo solo lectura. La única escritura expuesta por
 * este modelo es crearParaPersona(), pensada para procesos de negocio como el
 * registro público o, más adelante, la compra; nunca para un CRUD manual.
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

    /**
     * Crea el contexto sobre una Persona ya existente. El llamador debe
     * mantener el lock global tindercows_persona_alta hasta COMMIT/ROLLBACK,
     * porque el id se calcula en PHP mediante MAX+1 y la base no impone UNIQUE.
     */
    public function crearParaPersona(int $personaId): int
    {
        $comprobar = $this->conexion->prepare(
            'SELECT tbcompradorid FROM tbcomprador WHERE tbpersonaid = :personaId'
        );
        $comprobar->execute(['personaId' => $personaId]);
        $filas = $comprobar->fetchAll(PDO::FETCH_COLUMN);
        if ($filas !== []) {
            if (count($filas) > 1) {
                throw new \RuntimeException('La Persona conserva más de un contexto Comprador.');
            }
            throw new \RuntimeException('La Persona ya tiene contexto Comprador.');
        }

        $compradorId = $this->siguienteId();
        $insertar = $this->conexion->prepare(
            'INSERT INTO tbcomprador (tbcompradorid, tbpersonaid, tbcompradorestado)
             VALUES (:compradorId, :personaId, :estado)'
        );
        $insertar->execute([
            'compradorId' => $compradorId,
            'personaId' => $personaId,
            'estado' => 1,
        ]);

        return $compradorId;
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

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbcompradorid), 0) + 1 FROM tbcomprador'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
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
