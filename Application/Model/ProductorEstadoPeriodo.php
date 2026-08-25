<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Estado del productor como hechos históricos (plan §7): cada cambio de
 * estado abre una fila nueva en tbproductorestadoperiodo y ningún periodo
 * cerrado se edita ni se elimina; solo se cierra con fechafin.
 *
 * El periodo abierto (tbproductorestadoperiodofechafin NULL) es el estado
 * vigente. La invariante de máximo un periodo abierto por productor no puede
 * garantizarla el motor (cero restricciones): la garantiza PHP al ejecutar
 * abrir/cerrar bajo el bloqueo nombrado por productor dentro de la
 * transacción completa.
 */
final class ProductorEstadoPeriodo
{
    private const PREFIJO_LOCK = 'tindercows_productor_estado_';
    private int $profundidadBloqueo = 0;
    private int $productorBloqueado = 0;

    public function __construct(private readonly PDO $conexion) {}

    /**
     * Envuelve la operación bajo el lock nombrado del productor indicado.
     * El callback debe contener la transacción completa que cierra el periodo
     * abierto y abre el nuevo: liberar el lock antes del COMMIT permitiría a
     * otra conexión abrir un segundo periodo en paralelo.
     */
    public function ejecutarConBloqueo(int $productorId, callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::PREFIJO_LOCK . $productorId);
        $this->profundidadBloqueo++;
        $this->productorBloqueado = $productorId;
        try {
            return $operacion();
        } finally {
            $this->profundidadBloqueo--;
            NamedLock::release($this->conexion, self::PREFIJO_LOCK . $productorId);
        }
    }

    /**
     * Abre un periodo con el estado indicado. Exige que la conexión posea el
     * lock del productor y que no exista un periodo abierto previo.
     * La fecha de inicio la asigna PHP con su reloj.
     */
    public function abrir(int $productorId, int $estado, ?string $motivo): int
    {
        $this->exigirLock($productorId);
        if ($this->consultarAbierto($productorId) !== null) {
            throw new \RuntimeException(
                'El productor ya tiene un periodo de estado abierto; ciérrelo antes de abrir otro.'
            );
        }

        $periodoId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductorestadoperiodo
             (tbproductorestadoperiodoid, tbproductorid, tbproductorestadoperiodoestado,
              tbproductorestadoperiodofechainicio, tbproductorestadoperiodofechafin,
              tbproductorestadoperiodomotivo)
             VALUES (:periodoId, :productorId, :estado, :fechaInicio, NULL, :motivo)'
        );
        $sentencia->execute([
            'periodoId' => $periodoId,
            'productorId' => $productorId,
            'estado' => $estado,
            'fechaInicio' => date('Y-m-d H:i:s'),
            'motivo' => $motivo,
        ]);

        return $periodoId;
    }

    /**
     * Cierra el periodo abierto del productor con fechafin asignada por PHP.
     * Debe afectar exactamente una fila: cero significa que no había periodo
     * abierto y más de uno revelaría integridad rota.
     */
    public function cerrar(int $productorId): void
    {
        $this->exigirLock($productorId);
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductorestadoperiodo
             SET tbproductorestadoperiodofechafin = :fechaFin
             WHERE tbproductorestadoperiodofechafin IS NULL
               AND tbproductorid = :productorId'
        );
        $sentencia->execute([
            'fechaFin' => date('Y-m-d H:i:s'),
            'productorId' => $productorId,
        ]);
        if ($sentencia->rowCount() !== 1) {
            throw new \RuntimeException(
                'Cerrar el periodo de estado debía afectar exactamente una fila y afectó '
                . $sentencia->rowCount() . '; revise la integridad de los datos.'
            );
        }
    }

    /** El periodo vigente: la única fila con fechafin NULL. */
    public function consultarAbierto(int $productorId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorestadoperiodo
             WHERE tbproductorid = :productorId
               AND tbproductorestadoperiodofechafin IS NULL'
        );
        $sentencia->execute(['productorId' => $productorId]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException(
                'El productor conserva más de un periodo de estado abierto; revise la integridad de los datos.'
            );
        }

        return $filas === [] ? null : $this->mapear($filas[0]);
    }

    /** El periodo cuya vigencia contiene la fecha indicada. */
    public function consultarVigenteEn(int $productorId, string $fecha): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorestadoperiodo
             WHERE tbproductorid = :productorId
               AND tbproductorestadoperiodofechainicio <= :fechaInicio
               AND (tbproductorestadoperiodofechafin IS NULL OR tbproductorestadoperiodofechafin > :fechaFin)
             ORDER BY tbproductorestadoperiodofechainicio DESC, tbproductorestadoperiodoid DESC'
        );
        $sentencia->execute(['productorId' => $productorId, 'fechaInicio' => $fecha, 'fechaFin' => $fecha]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $this->mapear($fila);
    }

    private function exigirLock(int $productorId): void
    {
        if ($this->profundidadBloqueo <= 0 || $this->productorBloqueado !== $productorId) {
            throw new \LogicException(
                'La conexión debe poseer el lock de estado del productor antes de modificar sus periodos.'
            );
        }
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT COALESCE(MAX(tbproductorestadoperiodoid), 0) + 1 FROM tbproductorestadoperiodo'
        );
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function mapear(array $fila): array
    {
        return [
            'tbproductorestadoperiodoid' => (int) $fila['tbproductorestadoperiodoid'],
            'tbproductorid' => (int) $fila['tbproductorid'],
            'tbproductorestadoperiodoestado' => (int) $fila['tbproductorestadoperiodoestado'],
            'tbproductorestadoperiodofechainicio' => $fila['tbproductorestadoperiodofechainicio'],
            'tbproductorestadoperiodofechafin' => $fila['tbproductorestadoperiodofechafin'],
            'tbproductorestadoperiodomotivo' => $fila['tbproductorestadoperiodomotivo'],
        ];
    }
}
