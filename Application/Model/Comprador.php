<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Contexto de negocio Comprador sobre una Persona.
 *
 * Una misma Persona puede participar en Productor, Comprador y Transportista a
 * la vez: cada contexto es un vínculo distinto hacia la misma tbpersona. La
 * identidad y el contacto viven únicamente en tbpersona; tbcomprador solo
 * registra el vínculo y su estado.
 *
 * El alta es una declaración idempotente (inscripción), no un CRUD de rol:
 * volver a declarar a un comprador ya activo no duplica la fila, y el
 * consecutivo se serializa bajo el bloqueo nombrado de alta de persona.
 */
final class Comprador
{
    private Persona $persona;

    public function __construct(private readonly PDO $conexion)
    {
        $this->persona = new Persona($conexion);
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

    public function buscarPorPersona(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT c.tbcompradorid, c.tbpersonaid, c.tbcompradorestado,
                    p.tbpersonaidentificacionnumero, p.tbpersonaidentificaciontipo,
                    p.tbpersonanombre, p.tbpersonaalias, p.tbpersonatelefono,
                    p.tbpersonacorreoelectronico, p.tbpersonaestado
             FROM tbcomprador c
             INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid
             WHERE c.tbpersonaid = :personaId'
        );
        $sentencia->execute(['personaId' => $personaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    public function listar(string $busqueda, string $estado, int $pagina, int $tamano): array
    {
        [$where, $parametros] = $this->filtros($busqueda, $estado);
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
             ORDER BY (c.tbcompradorestado * p.tbpersonaestado) DESC, p.tbpersonanombre,
                      p.tbpersonaidentificacionnumero
             LIMIT :limite OFFSET :desplazamiento"
        );
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'compradores' => array_map(fn (array $fila): array => $this->mapear($fila), $sentencia->fetchAll()),
            'total' => $total,
        ];
    }

    /**
     * Fila cruda del contexto con lock (FOR UPDATE) por identificación.
     * Incluye tbpersonaestado para que el servicio de estado verifique
     * disponibilidad de la Persona antes de operar el contexto.
     */
    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT c.*, pe.tbpersonaestado FROM tbcomprador c
             INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
             WHERE pe.tbpersonaidentificacionnumero = :identificacionNumero FOR UPDATE'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('La identificación está duplicada en la base de datos.');
        }

        return $filas[0] ?? null;
    }

    /** Fila cruda del contexto con lock (FOR UPDATE) por persona. */
    public function bloquearPorPersona(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT c.* FROM tbcomprador c
             WHERE c.tbpersonaid = :personaId FOR UPDATE'
        );
        $sentencia->execute(['personaId' => $personaId]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('La persona conserva más de un contexto Comprador.');
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

    /**
     * Declara el contexto Comprador de la persona. Si la persona no existe se
     * crea (fork de Productor::crear a través de Persona::obtenerOCrear);
     * si el contexto ya existe devuelve su id sin duplicar la fila.
     * Debe ejecutarse dentro de la transacción y del bloqueo nombrado del
     * llamador. Lanza PersonaConflictException para persona inactiva o con
     * datos personales incompatibles.
     */
    public function crear(array $datos, ?array $persona = null): int
    {
        $persona ??= $this->persona->obtenerOCrear($datos);
        $existente = $this->bloquearPorPersona((int) $persona['tbpersonaid']);
        if ($existente !== null) {
            return (int) $existente['tbcompradorid'];
        }

        $compradorId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbcomprador
             (tbcompradorid, tbpersonaid, tbcompradorestado)
             VALUES (:compradorId, :personaId, :estado)'
        );
        $sentencia->execute([
            'compradorId' => $compradorId,
            'personaId' => $persona['tbpersonaid'],
            'estado' => 1,
        ]);

        return $compradorId;
    }

    /** Cambia el estado del contexto Comprador; no borra la fila. */
    public function cambiarEstado(string $identificacionNumero, bool $activo): void
    {
        $persona = $this->persona->buscar($identificacionNumero);
        if ($persona === null) {
            return;
        }
        $sentencia = $this->conexion->prepare(
            'UPDATE tbcomprador SET tbcompradorestado = :estado
             WHERE tbpersonaid = :personaId'
        );
        $sentencia->execute([
            'estado' => $activo ? 1 : 0,
            'personaId' => $persona['tbpersonaid'],
        ]);
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbcompradorid), 0) + 1 FROM tbcomprador');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function filtros(string $busqueda, string $estado): array
    {
        $condiciones = [];
        $parametros = [];
        if ($busqueda !== '') {
            $condiciones[] = '(p.tbpersonanombre LIKE :busquedaNombre
                OR p.tbpersonaalias LIKE :busquedaAlias
                OR p.tbpersonacorreoelectronico LIKE :busquedaCorreo
                OR p.tbpersonaidentificacionnumero LIKE :busquedaIdentificacion)';
            $parametros = [
                ':busquedaNombre' => "%{$busqueda}%",
                ':busquedaAlias' => "%{$busqueda}%",
                ':busquedaCorreo' => "%{$busqueda}%",
                ':busquedaIdentificacion' => '%' . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%',
            ];
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = '(c.tbcompradorestado * p.tbpersonaestado) = :estado';
            $parametros[':estado'] = $estado === 'ACTIVO' ? 1 : 0;
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $parametros];
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