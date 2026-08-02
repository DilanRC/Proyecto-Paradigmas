<?php

declare(strict_types=1);

namespace Application\Model;

use JsonException;
use PDO;

final class Bitacora
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    /** @throws JsonException */
    public function registrar(string $accion, int $participanteId, ?array $anteriores, ?array $nuevos, string $solicitudId): void
    {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbbitacora
             (tbbitacoraEntidad, tbbitacoraRegistroId, tbbitacoraAccion,
              tbbitacoraDatosAnteriores, tbbitacoraDatosNuevos, tbbitacoraActorTipo,
              tbusuarioId, tbbitacoraOrigen, tbbitacoraSolicitudId)
             VALUES (\'PARTICIPANTE\', :registroId, :accion, :anteriores, :nuevos,
                     \'NO_AUTENTICADO\', NULL, \'API_PRODUCTORES\', :solicitudId)'
        );
        $sentencia->execute([
            'registroId' => $participanteId,
            'accion' => $accion,
            'anteriores' => $anteriores === null ? null : json_encode($anteriores, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'nuevos' => $nuevos === null ? null : json_encode($nuevos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'solicitudId' => $solicitudId,
        ]);
    }
}
