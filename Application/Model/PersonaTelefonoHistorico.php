<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Registra los cambios de teléfono relevantes para el negocio por contexto.
 *
 * Calidad definió tablas separadas para Productor y Comprador. Cada fila guarda
 * únicamente el identificador del histórico, la relación conceptual, el valor
 * nuevo y la fecha. No existe estado del histórico ni lógica delegada al motor.
 */
final class PersonaTelefonoHistorico
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function registrarCambio(int $personaId, string $telefonoNuevo, string $fecha): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_persona_telefono_historico');
        try {
            $this->registrarProductores($personaId, $telefonoNuevo, $fecha);
            $this->registrarCompradores($personaId, $telefonoNuevo, $fecha);
        } finally {
            NamedLock::release($this->conexion, 'tindercows_persona_telefono_historico');
        }
    }

    private function registrarProductores(int $personaId, string $telefonoNuevo, string $fecha): void
    {
        $buscar = $this->conexion->prepare(
            'SELECT tbproductorid FROM tbproductor WHERE tbpersonaid = :personaId ORDER BY tbproductorid'
        );
        $buscar->execute(['personaId' => $personaId]);
        $productores = array_map('intval', $buscar->fetchAll(PDO::FETCH_COLUMN));

        foreach ($productores as $productorId) {
            $id = $this->siguienteId('tbproductorpersonatelefonohistorico', 'tbproductorpersonatelefonohistoricoid');
            $insertar = $this->conexion->prepare(
                'INSERT INTO tbproductorpersonatelefonohistorico
                 (tbproductorpersonatelefonohistoricoid, tbproductorid,
                  tbproductorpersonatelefonohistoriconuevo, tbproductorpersonatelefonohistoricofecha)
                 VALUES (:id, :productorId, :telefonoNuevo, :fecha)'
            );
            $insertar->execute([
                'id' => $id,
                'productorId' => $productorId,
                'telefonoNuevo' => $telefonoNuevo,
                'fecha' => $fecha,
            ]);
        }
    }

    private function registrarCompradores(int $personaId, string $telefonoNuevo, string $fecha): void
    {
        $buscar = $this->conexion->prepare(
            'SELECT tbcompradorid FROM tbcomprador WHERE tbpersonaid = :personaId ORDER BY tbcompradorid'
        );
        $buscar->execute(['personaId' => $personaId]);
        $compradores = array_map('intval', $buscar->fetchAll(PDO::FETCH_COLUMN));

        foreach ($compradores as $compradorId) {
            $id = $this->siguienteId('tbcompradorpersonatelefonohistorico', 'tbcompradorpersonatelefonohistoricoid');
            $insertar = $this->conexion->prepare(
                'INSERT INTO tbcompradorpersonatelefonohistorico
                 (tbcompradorpersonatelefonohistoricoid, tbcompradorid,
                  tbcompradorpersonatelefonohistoriconuevo, tbcompradorpersonatelefonohistoricofecha)
                 VALUES (:id, :compradorId, :telefonoNuevo, :fecha)'
            );
            $insertar->execute([
                'id' => $id,
                'compradorId' => $compradorId,
                'telefonoNuevo' => $telefonoNuevo,
                'fecha' => $fecha,
            ]);
        }
    }

    private function siguienteId(string $tabla, string $columna): int
    {
        $permitidas = [
            'tbproductorpersonatelefonohistorico' => 'tbproductorpersonatelefonohistoricoid',
            'tbcompradorpersonatelefonohistorico' => 'tbcompradorpersonatelefonohistoricoid',
        ];
        if (($permitidas[$tabla] ?? null) !== $columna) {
            throw new \InvalidArgumentException('Tabla histórica no permitida.');
        }

        $sentencia = $this->conexion->prepare("SELECT COALESCE(MAX({$columna}), 0) + 1 FROM {$tabla}");
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
