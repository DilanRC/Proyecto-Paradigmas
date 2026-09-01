<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Residencia del productor como hechos históricos: cada cambio de dirección
 * cierra el enlace vigente (tbproductordireccionfechafin) e inserta un
 * enlace nuevo con tbproductordireccionfechainicio asignada por PHP. Las
 * direcciones de periodos cerrados quedan intocables para siempre.
 *
 * El periodo abierto es el enlace con tbproductordireccionfechafin NULL y es
 * el único editable; las consultas de dirección del productor se leen de él.
 */
final class ProductorDireccion
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly Direccion $direccion,
    ) {}

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
     * Envuelve la operación bajo el lock nombrado POR PRODUCTOR de dirección y,
     * dentro de él, delega al lock global de alta (requisito de
     * Direccion::crearConBloqueoExistente). El callback debe contener toda la
     * transacción de cierre+alta: el lock se libera en finally SIEMPRE, incluso
     * ante excepción, para no dejar un productor bloqueado.
     */
    public function ejecutarConBloqueoProducto(int $productorId, callable $operacion): mixed
    {
        $this->adquirirBloqueoProducto($productorId);
        try {
            return $this->ejecutarConBloqueoAlta($operacion);
        } finally {
            $this->liberarBloqueoProducto($productorId);
        }
    }

    public function crearVacia(int $productorId): void
    {
        $this->abrirPeriodo($productorId, [
            'provincia' => '',
            'canton' => '',
            'distrito' => '',
            'pueblo' => null,
            'senas' => null,
        ]);
    }

    /**
     * Abre un periodo de dirección nuevo. Solo se rechaza si ya existe un
     * periodo abierto: que existan enlaces cerrados es el histórico normal.
     */
    public function crear(int $productorId, array $direccion): void
    {
        if ($this->consultarPeriodoAbierto($productorId) !== null) {
            throw new \RuntimeException('El productor ya tiene una dirección registrada; use actualizar.');
        }
        $this->abrirPeriodo($productorId, $direccion);
    }

    /**
     * Fin del UPDATE destructivo de dirección (DEC-21): un cambio de residencia
     * cierra el periodo abierto (fechafin = ahora) e inserta un periodo nuevo
     * con la dirección recibida. La dirección anterior queda intocable en el
     * histórico. Debe ejecutarse bajo ejecutarConBloqueoProducto() para que la
     * transacción posea el lock por productor y el lock global de alta.
     */
    public function actualizar(int $productorId, array $direccion): void
    {
        if ($this->consultarPeriodoAbierto($productorId) === null) {
            throw new \RuntimeException('El productor no conserva una dirección abierta para actualizar.');
        }
        $this->cerrarPeriodo($productorId);
        $this->abrirPeriodo($productorId, $direccion);
    }

    /** La dirección del periodo abierto, con la misma forma que siempre. */
    public function buscar(int $productorId): ?array
    {
        $periodo = $this->consultarPeriodoAbierto($productorId);
        if ($periodo === null) {
            return null;
        }

        return [
            'provincia' => $periodo['provincia'],
            'canton' => $periodo['canton'],
            'distrito' => $periodo['distrito'],
            'pueblo' => $periodo['pueblo'],
            'senas' => $periodo['senas'],
        ];
    }

    /**
     * Transaccional: INSERT en tbdireccion (MAX+1 bajo el lock de alta
     * global que ya posee la conexión) + INSERT del enlace con fechainicio.
     * Devuelve el identificador del enlace abierto.
     */
    public function abrirPeriodo(int $productorId, array $direccion): int
    {
        $direccionId = $this->direccion->crearConBloqueoExistente($direccion);
        $enlaceId = $this->siguienteEnlaceId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductordireccion
             (tbproductordireccionid, tbproductorid, tbdireccionid, tbproductordireccionfechainicio)
             VALUES (:enlaceId, :productorId, :direccionId, :fechaInicio)'
        );
        $sentencia->execute([
            'enlaceId' => $enlaceId,
            'productorId' => $productorId,
            'direccionId' => $direccionId,
            'fechaInicio' => date('Y-m-d H:i:s'),
        ]);

        return $enlaceId;
    }

    /**
     * Cierra el periodo abierto del productor con fechafin asignada por PHP.
     * Debe afectar exactamente una fila: cero significa que no hay enlace
     * abierto y más de uno revelaría integridad rota.
     */
    public function cerrarPeriodo(int $productorId): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductordireccion
             SET tbproductordireccionfechafin = :fechaFin
             WHERE tbproductorid = :productorId
               AND tbproductordireccionfechafin IS NULL'
        );
        $sentencia->execute([
            'fechaFin' => date('Y-m-d H:i:s'),
            'productorId' => $productorId,
        ]);
        if ($sentencia->rowCount() !== 1) {
            throw new \RuntimeException(
                'Cerrar el periodo de dirección debía afectar exactamente una fila y afectó '
                . $sentencia->rowCount() . '; revise la integridad de los datos.'
            );
        }
    }

    /** El enlace abierto junto a los datos de su dirección en tbdireccion. */
    public function consultarPeriodoAbierto(int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT pd.tbproductordireccionid, pd.tbproductorid, pd.tbdireccionid,
                    pd.tbproductordireccionfechainicio, pd.tbproductordireccionfechafin,
                    d.tbdireccionprovincia AS provincia,
                    d.tbdireccioncanton AS canton,
                    d.tbdirecciondistrito AS distrito,
                    d.tbdireccionpueblo AS pueblo,
                    d.tbdireccionsenas AS senas
             FROM tbproductordireccion pd
             INNER JOIN tbdireccion d
                 ON d.tbdireccionid = pd.tbdireccionid
             WHERE pd.tbproductorid = :productorId
               AND pd.tbproductordireccionfechafin IS NULL'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException(
                'El productor conserva más de un periodo de dirección abierto; revise la integridad de los datos.'
            );
        }
        if ($filas === []) {
            return null;
        }
        $fila = $filas[0];

        return [
            'tbproductordireccionid' => (int) $fila['tbproductordireccionid'],
            'tbproductorid' => (int) $fila['tbproductorid'],
            'tbdireccionid' => (int) $fila['tbdireccionid'],
            'tbproductordireccionfechainicio' => $fila['tbproductordireccionfechainicio'],
            'tbproductordireccionfechafin' => $fila['tbproductordireccionfechafin'],
            'provincia' => $fila['provincia'],
            'canton' => $fila['canton'],
            'distrito' => $fila['distrito'],
            'pueblo' => $fila['pueblo'],
            'senas' => $fila['senas'],
        ];
    }

    /** El periodo cuya vigencia contiene la fecha indicada. */
    public function consultarVigenteEn(int $productorId, string $fecha): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT pd.tbproductordireccionid, pd.tbproductorid, pd.tbdireccionid,
                    pd.tbproductordireccionfechainicio, pd.tbproductordireccionfechafin,
                    d.tbdireccionprovincia AS provincia,
                    d.tbdireccioncanton AS canton,
                    d.tbdirecciondistrito AS distrito,
                    d.tbdireccionpueblo AS pueblo,
                    d.tbdireccionsenas AS senas
             FROM tbproductordireccion pd
             INNER JOIN tbdireccion d
                 ON d.tbdireccionid = pd.tbdireccionid
             WHERE pd.tbproductorid = :productorId
               AND (pd.tbproductordireccionfechainicio IS NULL OR pd.tbproductordireccionfechainicio <= :fechaInicio)
               AND (pd.tbproductordireccionfechafin IS NULL OR pd.tbproductordireccionfechafin > :fechaFin)
             ORDER BY pd.tbproductordireccionfechainicio DESC, pd.tbproductordireccionid DESC'
        );
        $sentencia->execute(['productorId' => $productorId, 'fechaInicio' => $fecha, 'fechaFin' => $fecha]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : [
            'tbproductordireccionid' => (int) $fila['tbproductordireccionid'],
            'tbproductorid' => (int) $fila['tbproductorid'],
            'tbdireccionid' => (int) $fila['tbdireccionid'],
            'tbproductordireccionfechainicio' => $fila['tbproductordireccionfechainicio'],
            'tbproductordireccionfechafin' => $fila['tbproductordireccionfechafin'],
            'provincia' => $fila['provincia'],
            'canton' => $fila['canton'],
            'distrito' => $fila['distrito'],
            'pueblo' => $fila['pueblo'],
            'senas' => $fila['senas'],
        ];
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

    private function adquirirBloqueoProducto(int $productorId): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_productor_direccion_' . $productorId);
    }

    private function liberarBloqueoProducto(int $productorId): void
    {
        NamedLock::release($this->conexion, 'tindercows_productor_direccion_' . $productorId);
    }
}
