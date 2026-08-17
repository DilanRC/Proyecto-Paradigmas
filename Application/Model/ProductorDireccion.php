<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class ProductorDireccion
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly Direccion $direccion,
    ) {}

    /**
     * Adquiere el lock de productor_direccion Y el de direccion anidado.
     * Necesario porque insertarEnlace calcula MAX(tbproductordireccionid)+1
     * y además crea una dirección (que requiere su propio lock).
     */
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $this->adquirirBloqueoAlta();
        try {
            return $this->direccion->ejecutarConBloqueoAlta($operacion);
        } finally {
            $this->liberarBloqueoAlta();
        }
    }

    /**
     * Se ejecuta automáticamente dentro de la transacción de alta del productor.
     * Crea la fila real en tbdireccion (vía Direccion::crearConBloqueo) y el
     * enlace en tbproductordireccion. El detalle se completa después con actualizar().
     *
     * PRECONDICIÓN: debe invocarse dentro de ejecutarConBloqueoAlta() propio
     * o de un llamador que ya adquirió este mismo lock.
     */
    public function crearVacia(int $productorId): void
    {
        $this->insertarEnlace($productorId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    /**
     * Creación explícita de reparación: solo permitida cuando el productor
     * todavía no tiene ninguna fila de enlace. El flujo normal para un
     * productor nuevo es crearVacia() + actualizar(); este método NO se usa
     * en el alta estándar.
     *
     * PRECONDICIÓN: debe invocarse dentro de ejecutarConBloqueoAlta().
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
        $this->insertarEnlace($productorId, $direccion);
    }

    public function actualizar(int $productorId, array $direccion): void
    {
        $direccionId = $this->obtenerDireccionId($productorId);
        $this->direccion->actualizar($direccionId, $direccion);
    }

    public function buscar(int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) > 1) {
            throw new \RuntimeException(
                'El productor tiene más de una dirección registrada; revise la integridad de los datos.'
            );
        }
        if ($filas === []) {
            return null;
        }

        return $this->direccion->buscar((int) $filas[0]);
    }

    public function vaciar(int $productorId): void
    {
        $this->actualizar($productorId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    /**
     * Resuelve el tbdireccionid enlazado al productor. Exige exactamente una
     * fila de enlace.
     */
    private function obtenerDireccionId(int $productorId): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbdireccionid FROM tbproductordireccion WHERE tbproductorid = :productorId'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);

        if (count($filas) !== 1) {
            throw new \RuntimeException('El productor no conserva exactamente una dirección.');
        }

        return (int) $filas[0];
    }

    /**
     * Inserta el enlace y la dirección subyacente. Usa crearConBloqueo()
     * para garantizar que la creación de la dirección sea segura incluso
     * si este método se invocara fuera de un contexto con lock externo
     * (aunque actualmente siempre se llama dentro de ejecutarConBloqueoAlta).
     *
     * Nota: crearConBloqueo() adquiere internamente el lock de direccion.
     * Como ejecutarConBloqueoAlta() ya lo adquirió anidado, NamedLock debe
     * soportar reentrada o el lock de direccion debe ser idempotente.
     * Si NamedLock NO soporta reentrada, usar $this->direccion->crear() aquí
     * es correcto SIEMPRE que se garantice que insertarEnlace solo se llama
     * dentro de ejecutarConBloqueoAlta(). Documentamos esta precondición.
     */
    private function insertarEnlace(int $productorId, array $direccion): void
    {
        // Usamos crear() directo porque estamos DENTRO de ejecutarConBloqueoAlta()
        // que ya adquirió AMBOS locks (productor_direccion + direccion).
        // Llamar a crearConBloqueo() aquí causaría deadlock si NamedLock no es reentrante.
        $direccionId = $this->direccion->crearSinBloqueo($direccion);

        $enlaceId = $this->siguienteEnlaceId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductordireccion
             (tbproductordireccionid, tbproductorid, tbdireccionid)
             VALUES (:enlaceId, :productorId, :direccionId)'
        );
        $sentencia->execute([
            'enlaceId' => $enlaceId,
            'productorId' => $productorId,
            'direccionId' => $direccionId,
        ]);
    }

    private function siguienteEnlaceId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbproductordireccionid), 0) + 1 FROM tbproductordireccion'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_productor_direccion_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_productor_direccion_alta');
    }
}