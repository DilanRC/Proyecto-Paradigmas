<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Direccion
{
    public function __construct(private readonly PDO $conexion) {}

    /**
     * Envuelve la operación bajo el lock 'tindercows_direccion_alta'. Debe
     * usarse siempre que la operación incluya crear() en algún punto, y el
     * lock debe cubrir la transacción completa (incluyendo el commit), no
     * solo el INSERT puntual — de lo contrario dos transacciones concurrentes
     * pueden calcular el mismo MAX(tbdireccionid)+1 antes de que ninguna
     * haga commit.
     */
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        try {
            return $operacion();
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    /**
     * Crea una dirección de forma segura, adquiriendo automáticamente el lock
     * de alta y liberándolo al finalizar (incluso si hay excepción).
     * Este es el ÚNICO método público permitido para crear direcciones
     * desde código externo que NO posee ya el lock.
     */
    public function crearConBloqueo(array $direccion): int
    {
        return $this->ejecutarConBloqueoAlta(fn (): int => $this->crear($direccion));
    }

    /**
     * @internal Solo para uso de ProductorDireccion y FincaDireccion cuando
     *           YA adquirieron el lock de direccion vía ejecutarConBloqueoAlta()
     *           anidado. NO llamar directamente desde controllers ni otros modelos.
     *           Si se llama sin el lock activo, se generarán IDs duplicados.
     */
    public function crearSinBloqueo(array $direccion): int
    {
        return $this->crear($direccion);
    }

    /**
     * Implementación interna. Toda creación pasa por aquí.
     */
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
        NamedLock::acquire($this->conexion, 'tindercows_direccion_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_direccion_alta');
    }
}