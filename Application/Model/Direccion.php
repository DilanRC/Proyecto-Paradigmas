<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Direccion
{
    private const LOCK_ALTA = 'tindercows_direccion_alta';
    private int $profundidadBloqueoAlta = 0;

    public function __construct(private readonly PDO $conexion) {}

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
              tbdirecciondistrito, tbdireccionpueblo, tbdireccionsenas,
              tbdireccionlatitud, tbdireccionlongitud)
             VALUES (:direccionId, :provincia, :canton, :distrito, :pueblo, :senas,
              :latitud, :longitud)'
        );
        $sentencia->execute($this->parametros($direccion, $direccionId));

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
                 tbdireccionsenas = :senas,
                 tbdireccionlatitud = :latitud,
                 tbdireccionlongitud = :longitud
             WHERE tbdireccionid = :direccionId'
        );
        $sentencia->execute($this->parametros($direccion, $direccionId));
    }

    public function buscar(int $direccionId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionprovincia AS provincia,
                    tbdireccioncanton AS canton,
                    tbdirecciondistrito AS distrito,
                    tbdireccionpueblo AS pueblo,
                    tbdireccionsenas AS senas,
                    tbdireccionlatitud AS latitud,
                    tbdireccionlongitud AS longitud
             FROM tbdireccion
             WHERE tbdireccionid = :direccionId'
        );
        $sentencia->execute(['direccionId' => $direccionId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function parametros(array $direccion, int $direccionId): array
    {
        return [
            'direccionId' => $direccionId,
            'provincia' => $direccion['provincia'],
            'canton' => $direccion['canton'],
            'distrito' => $direccion['distrito'],
            'pueblo' => $direccion['pueblo'] ?? null,
            'senas' => $direccion['senas'] ?? null,
            'latitud' => $direccion['latitud'] ?? null,
            'longitud' => $direccion['longitud'] ?? null,
        ];
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
