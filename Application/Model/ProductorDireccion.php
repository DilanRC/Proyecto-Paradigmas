<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorDireccion
{
    public function __construct(private readonly PDO $conexion) {}

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
     * Se ejecuta automáticamente dentro de la transacción de alta del productor.
     * Instancia la fila 1:1 con valores en blanco; el detalle se completa después con actualizar().
     */
    public function crearVacia(int $productorId): void
    {
        $this->insertar($productorId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    /**
     * Creación explícita de reparación: solo permitida cuando el productor
     * todavía no tiene ninguna fila de dirección (por ejemplo, datos heredados
     * o corrupción manual). El flujo normal para un productor nuevo es
     * crearVacia() + actualizar(); este método NO se usa en el alta estándar.
     */
    public function crear(int $productorId, array $direccion): void
    {
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $comprobar->execute(['productorId' => $productorId]);
        if ((int) $comprobar->fetchColumn() !== 0) {
            throw new \RuntimeException('El productor ya tiene una dirección registrada; use actualizar.');
        }
        $this->insertar($productorId, $direccion);
    }

    public function actualizar(int $productorId, array $direccion): void
    {
        $comprobar = $this->conexion->prepare(
            'SELECT COUNT(*) FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $comprobar->execute(['productorId' => $productorId]);
        if ((int) $comprobar->fetchColumn() !== 1) {
            throw new \RuntimeException('El productor no conserva exactamente una dirección.');
        }
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductordireccion
             SET tbproductordireccionprovincia = :provincia,
                 tbproductordireccioncanton = :canton,
                 tbproductordirecciondistrito = :distrito,
                 tbproductordireccionpueblo = :pueblo,
                 tbproductordireccionsenas = :senas
             WHERE tbproductorid = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId, ...$direccion]);
    }

    private function insertar(int $productorId, array $direccion): void
    {
        $direccionId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductordireccion
             (tbproductordireccionid, tbproductorid, tbproductordireccionprovincia,
              tbproductordireccioncanton, tbproductordirecciondistrito,
              tbproductordireccionpueblo, tbproductordireccionsenas)
             VALUES (:direccionId, :productorId, :provincia, :canton, :distrito, :pueblo, :senas)'
        );
        $sentencia->execute(['direccionId' => $direccionId, 'productorId' => $productorId, ...$direccion]);
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbproductordireccionid), 0) + 1 FROM tbproductordireccion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        $sentencia = $this->conexion->prepare("SELECT GET_LOCK('tindercows_direccion_alta', 10)");
        $sentencia->execute();
        if ((int) $sentencia->fetchColumn() !== 1) {
            throw new \RuntimeException('No fue posible reservar la secuencia de direcciones.');
        }
    }

    private function liberarBloqueoAlta(): void
    {
        $sentencia = $this->conexion->prepare("SELECT RELEASE_LOCK('tindercows_direccion_alta')");
        $sentencia->execute();
    }
}
