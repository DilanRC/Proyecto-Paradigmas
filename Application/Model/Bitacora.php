<?php

declare(strict_types=1);

namespace Application\Model;

use JsonException;
use PDO;

final class Bitacora
{
    public function __construct(private readonly PDO $conexion) {}

    /** @throws JsonException */
    public function registrar(
        string $accion,
        string $identificacionNumero,
        ?array $anteriores,
        ?array $nuevos,
        string $solicitudId,
        string $entidad = 'PRODUCTOR',
        string $origen = 'API_PRODUCTORES',
    ): void {
        $this->adquirirBloqueoAlta();
        try {
            $sentencia = $this->conexion->prepare(
                'INSERT INTO tbbitacora
                 (tbbitacoraid, tbbitacoraentidad, tbbitacoraregistroidentificacionnumero, tbbitacoraaccion, tbbitacorafecha,
                  tbbitacoradatosanteriores, tbbitacoradatosnuevos, tbbitacoraactortipo,
                  tbbitacorausuarioid, tbbitacoraorigen, tbbitacorasolicitudid)
                 VALUES (:bitacoraId, :entidad, :registroId, :accion, :fecha, :anteriores, :nuevos,
                         :actorTipo, :usuarioId, :origen, :solicitudId)'
            );
            $sentencia->execute([
                'bitacoraId' => $this->siguienteId(),
                'entidad' => $entidad,
                'registroId' => $identificacionNumero,
                'accion' => $accion,
                'fecha' => gmdate('Y-m-d H:i:s'),
                'anteriores' => $anteriores === null ? null : json_encode($anteriores, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'nuevos' => $nuevos === null ? null : json_encode($nuevos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actorTipo' => 'NO_AUTENTICADO',
                'usuarioId' => null,
                'origen' => $origen,
                'solicitudId' => $solicitudId,
            ]);
        } finally {
            $this->liberarBloqueoAlta();
        }
    }
    
    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbbitacoraid), 0) + 1 FROM tbbitacora');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function adquirirBloqueoAlta(): void
    {
        NamedLock::acquire($this->conexion, 'tindercows_bitacora_alta');
    }

    private function liberarBloqueoAlta(): void
    {
        NamedLock::release($this->conexion, 'tindercows_bitacora_alta');
    }
}
