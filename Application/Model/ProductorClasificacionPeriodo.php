<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Clasificación comercial histórica del productor.
 *
 * Productor es la entidad núcleo; COMPRADOR y VENDEDOR son clasificaciones
 * temporales independientes. El motor no tiene CHECK ni UNIQUE: PHP conserva
 * el vocabulario aprobado y evita periodos abiertos duplicados por
 * productor+tipo bajo locks nombrados.
 */
final class ProductorClasificacionPeriodo
{
    private const TIPOS = ['COMPRADOR', 'VENDEDOR'];
    private const PREFIJO_LOCK = 'tindercows_productor_clasificacion_';
    private const LOCK_ALTA = 'tindercows_productor_clasificacion_alta';
    private int $profundidadBloqueo = 0;
    private string $lockActual = '';

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueo(int $productorId, string $tipo, callable $operacion): mixed
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $lockEntidad = self::PREFIJO_LOCK . $productorId . '_' . $tipoNormalizado;

        // Orden fijo: entidad+tipo, despues alta global. El primero protege
        // la invariante local; el segundo protege MAX(id)+1 de toda la tabla.
        NamedLock::acquire($this->conexion, $lockEntidad);
        try {
            NamedLock::acquire($this->conexion, self::LOCK_ALTA);
            $this->profundidadBloqueo++;
            $this->lockActual = $lockEntidad;
            try {
                return $operacion($tipoNormalizado);
            } finally {
                $this->profundidadBloqueo--;
                $this->lockActual = '';
                NamedLock::release($this->conexion, self::LOCK_ALTA);
            }
        } finally {
            NamedLock::release($this->conexion, $lockEntidad);
        }
    }

    public function abrir(int $productorId, string $tipo, ?string $motivo): int
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $this->exigirLock($productorId, $tipoNormalizado);
        if ($this->consultarAbierto($productorId, $tipoNormalizado) !== null) {
            throw new \RuntimeException('El productor ya tiene esa clasificación abierta.');
        }

        $periodoId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbproductorclasificacionperiodo
             (tbproductorclasificacionperiodoid, tbproductorid, tbproductorclasificacionperiodotipo,
              tbproductorclasificacionperiodofechainicio, tbproductorclasificacionperiodofechafin,
              tbproductorclasificacionperiodomotivo)
             VALUES (:id, :productorId, :tipo, :fechaInicio, NULL, :motivo)'
        );
        $sentencia->execute([
            'id' => $periodoId,
            'productorId' => $productorId,
            'tipo' => $tipoNormalizado,
            'fechaInicio' => date('Y-m-d H:i:s'),
            'motivo' => $motivo,
        ]);

        return $periodoId;
    }

    public function cerrar(int $productorId, string $tipo): void
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $this->exigirLock($productorId, $tipoNormalizado);
        $sentencia = $this->conexion->prepare(
            'UPDATE tbproductorclasificacionperiodo
             SET tbproductorclasificacionperiodofechafin = :fechaFin
             WHERE tbproductorid = :productorId
               AND tbproductorclasificacionperiodotipo = :tipo
               AND tbproductorclasificacionperiodofechafin IS NULL'
        );
        $sentencia->execute([
            'fechaFin' => date('Y-m-d H:i:s'),
            'productorId' => $productorId,
            'tipo' => $tipoNormalizado,
        ]);
        if ($sentencia->rowCount() !== 1) {
            throw new \RuntimeException('Cerrar clasificación debía afectar exactamente una fila.');
        }
    }

    public function consultarAbierto(int $productorId, string $tipo): ?array
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorclasificacionperiodo
             WHERE tbproductorid = :productorId
               AND tbproductorclasificacionperiodotipo = :tipo
               AND tbproductorclasificacionperiodofechafin IS NULL'
        );
        $sentencia->execute(['productorId' => $productorId, 'tipo' => $tipoNormalizado]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new \RuntimeException('El productor conserva clasificaciones abiertas duplicadas.');
        }

        return $filas === [] ? null : $this->mapear($filas[0]);
    }

    /**
     * Responde "¿este productor es comprador?" leyendo la única fuente de
     * verdad de la clasificación: un periodo COMPRADOR abierto.
     *
     * Paso (a) del retiro de la tabla legacy de comprador (DEC-DBREADY-005).
     * Ninguna lectura nueva debe preguntarlo con el bit de alta/baja del CRUD
     * heredado: ese bit no representa la clasificación. Un periodo
     * cerrado significa que lo fue y ya no lo es; COMPRADOR y VENDEDOR son
     * independientes, así que tener VENDEDOR abierto no altera la respuesta.
     */
    public function esComprador(int $productorId): bool
    {
        return $this->consultarAbierto($productorId, 'COMPRADOR') !== null;
    }

    public function listarAbiertas(int $productorId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbproductorclasificacionperiodo
             WHERE tbproductorid = :productorId
               AND tbproductorclasificacionperiodofechafin IS NULL
             ORDER BY tbproductorclasificacionperiodotipo ASC'
        );
        $sentencia->execute(['productorId' => $productorId]);

        return array_map(fn (array $fila): array => $this->mapear($fila), $sentencia->fetchAll());
    }

    /**
     * Productores con una clasificación abierta de ese tipo, con su identidad y
     * desde cuándo están clasificados.
     *
     * Es la lectura que reemplaza al listado del CRUD legacy de comprador
     * (DEC-DBREADY-008): la lista sale de los periodos, así que no puede
     * mostrar a nadie que la clasificación no respalde.
     *
     * @return array{clasificados: array<int,array<string,mixed>>, total: int}
     */
    public function listarClasificados(string $tipo, string $busqueda, int $pagina, int $tamano): array
    {
        $tipoNormalizado = $this->normalizarTipo($tipo);
        $origen = ' FROM tbproductorclasificacionperiodo cp
                    INNER JOIN tbproductor pr ON pr.tbproductorid = cp.tbproductorid
                    INNER JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid
                    WHERE cp.tbproductorclasificacionperiodotipo = :tipo
                      AND cp.tbproductorclasificacionperiodofechafin IS NULL ';
        $parametros = [':tipo' => $tipoNormalizado];
        if ($busqueda !== '') {
            $origen .= ' AND (pe.tbpersonanombre LIKE :nombre
                              OR pe.tbpersonacorreoelectronico LIKE :correo
                              OR pe.tbpersonaidentificacionnumero LIKE :identificacion) ';
            $parametros[':nombre'] = "%{$busqueda}%";
            $parametros[':correo'] = "%{$busqueda}%";
            $parametros[':identificacion'] = '%'
                . mb_strtoupper(preg_replace('/[ -]+/u', '', $busqueda) ?? '', 'UTF-8') . '%';
        }

        $conteo = $this->conexion->prepare('SELECT COUNT(*)' . $origen);
        $conteo->execute($parametros);

        $sentencia = $this->conexion->prepare(
            'SELECT cp.tbproductorclasificacionperiodoid, cp.tbproductorid,
                    cp.tbproductorclasificacionperiodofechainicio,
                    cp.tbproductorclasificacionperiodomotivo,
                    pe.tbpersonaidentificacionnumero, pe.tbpersonaidentificaciontipo,
                    pe.tbpersonanombre, pe.tbpersonatelefono, pe.tbpersonacorreoelectronico,
                    pe.tbpersonaestado'
            . $origen
            . ' ORDER BY pe.tbpersonanombre, pe.tbpersonaidentificacionnumero
                LIMIT :limite OFFSET :desplazamiento'
        );
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'clasificados' => array_map(static fn (array $fila): array => [
                'periodoId' => (int) $fila['tbproductorclasificacionperiodoid'],
                'productorId' => (int) $fila['tbproductorid'],
                'identificacionNumero' => $fila['tbpersonaidentificacionnumero'],
                'identificacion' => [
                    'tipoCodigo' => $fila['tbpersonaidentificaciontipo'],
                    'numero' => $fila['tbpersonaidentificacionnumero'],
                ],
                'nombre' => $fila['tbpersonanombre'],
                'telefono' => $fila['tbpersonatelefono'],
                'correoElectronico' => $fila['tbpersonacorreoelectronico'],
                'clasificadoDesde' => $fila['tbproductorclasificacionperiodofechainicio'],
                'motivo' => $fila['tbproductorclasificacionperiodomotivo'],
                'personaEstado' => (int) $fila['tbpersonaestado'] === 1 ? 'ACTIVA' : 'INACTIVA',
            ], $sentencia->fetchAll()),
            'total' => (int) $conteo->fetchColumn(),
        ];
    }

    private function exigirLock(int $productorId, string $tipo): void
    {
        $esperado = self::PREFIJO_LOCK . $productorId . '_' . $tipo;
        if ($this->profundidadBloqueo <= 0 || $this->lockActual !== $esperado) {
            throw new \LogicException('La conexión debe poseer el lock de clasificación antes de modificar periodos.');
        }
        if (!$this->conexion->inTransaction()) {
            throw new \LogicException('La modificación de clasificación debe ejecutarse dentro de una transacción.');
        }
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbproductorclasificacionperiodoid
             FROM tbproductorclasificacionperiodo
             ORDER BY tbproductorclasificacionperiodoid DESC
             LIMIT 1 FOR UPDATE'
        );
        $sentencia->execute();
        $maximo = $sentencia->fetchColumn();

        return $maximo === false ? 1 : ((int) $maximo) + 1;
    }

    private function normalizarTipo(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Clasificación de productor no aprobada.');
        }

        return $tipo;
    }

    private function mapear(array $fila): array
    {
        return [
            'tbproductorclasificacionperiodoid' => (int) $fila['tbproductorclasificacionperiodoid'],
            'tbproductorid' => (int) $fila['tbproductorid'],
            'tbproductorclasificacionperiodotipo' => $fila['tbproductorclasificacionperiodotipo'],
            'tbproductorclasificacionperiodofechainicio' => $fila['tbproductorclasificacionperiodofechainicio'],
            'tbproductorclasificacionperiodofechafin' => $fila['tbproductorclasificacionperiodofechafin'],
            'tbproductorclasificacionperiodomotivo' => $fila['tbproductorclasificacionperiodomotivo'],
        ];
    }
}
