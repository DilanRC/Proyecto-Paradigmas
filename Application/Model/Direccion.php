<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Direccion
{
    private const LOCK_ALTA = 'tindercows_direccion_alta';
    private int $profundidadBloqueoAlta = 0;

    public function __construct(private readonly PDO $conexion) {}

    /**
     * Envuelve la operación bajo el lock global de alta de direcciones.
     * El llamador debe incluir dentro del callback toda la transacción que
     * contiene el cálculo MAX(tbdireccionid)+1, el INSERT y su COMMIT/ROLLBACK.
     *
     * La profundidad se controla en PHP para no depender de funciones de
     * inspección específicas de MySQL. NamedLock ya abstrae la adquisición y
     * liberación para MySQL y PostgreSQL.
     */
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        $this->profundidadBloqueoAlta++;
        try {
            return $operacion();
        } finally {
            $this->profundidadBloqueoAlta--;
            $this->liberarBloqueoAlta();
        }
    }

    /**
     * Crea una dirección de forma segura cuando no existe una transacción
     * exterior. Si ya hay una transacción abierta, el lock no puede liberarse
     * antes de su COMMIT/ROLLBACK, por lo que se obliga a usar
     * ejecutarConBloqueoAlta() alrededor de la transacción completa.
     */
    public function crearConBloqueo(array $direccion): int
    {
        if ($this->conexion->inTransaction()) {
            throw new \LogicException(
                'No use crearConBloqueo() dentro de una transacción ya abierta; '
                . 'envuelva la transacción completa con ejecutarConBloqueoAlta().'
            );
        }

        return $this->ejecutarConBloqueoAlta(fn (): int => $this->crear($direccion));
    }

    /**
     * Crea usando el lock adquirido por esta misma instancia mediante
     * ejecutarConBloqueoAlta(). ProductorDireccion y FincaDireccion comparten
     * esta instancia y por eso pueden crear la fila sin consultar funciones
     * específicas del motor de base de datos.
     */
    public function crearConBloqueoExistente(array $direccion): int
    {
        if ($this->profundidadBloqueoAlta <= 0) {
            throw new \LogicException(
                'La conexión debe poseer el lock de alta de dirección antes de crear la fila.'
            );
        }

        return $this->crear($direccion);
    }

    private function crear(array $direccion): int
    {
        $direccionId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbdireccion
             (tbdireccionid, tbdireccionprovincia, tbdireccioncanton,
              tbdirecciondistrito, tbdireccionpueblo, tbdireccionsenas)
             VALUES (:direccionId, :provincia, :canton, :distrito, :pueblo, :senas)'
        );
        $sentencia->execute(['direccionId' => $direccionId, ...$direccion]);

        return $direccionId;
    }

    public function actualizar(int $direccionId, array $direccion): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbdireccion
             SET tbdireccionprovincia = :provincia,
                 tbdireccioncanton = :canton,
                 tbdirecciondistrito = :distrito,
                 tbdireccionpueblo = :pueblo,
                 tbdireccionsenas = :senas
             WHERE tbdireccionid = :direccionId'
        );
        $sentencia->execute(['direccionId' => $direccionId, ...$direccion]);
    }

    public function buscar(int $direccionId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionprovincia AS provincia,
                    tbdireccioncanton AS canton,
                    tbdirecciondistrito AS distrito,
                    tbdireccionpueblo AS pueblo,
                    tbdireccionsenas AS senas
             FROM tbdireccion
             WHERE tbdireccionid = :direccionId'
        );
        $sentencia->execute(['direccionId' => $direccionId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbdireccionid), 0) + 1 FROM tbdireccion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, self::LOCK_ALTA);
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, self::LOCK_ALTA);
    }
}
