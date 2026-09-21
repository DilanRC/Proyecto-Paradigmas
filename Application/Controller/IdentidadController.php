<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use PDO;

/**
 * Devuelve la identidad del actor autenticado para que el frontend
 * resuelva sus contextos sin exponer datos de terceros.
 *
 * Persona ≠ Productor ≠ Comprador ≠ Transportista: los tres perfiles son
 * contextos de la misma Persona (DEC-28/29) y se resuelven desde
 * `tbpersona/tbproductor/tbcomprador/tbtransportista` (DEC-30).
 *
 * GET api/identidad.php →
 *   público (sin Bearer):  { esProductor:false, esComprador:false,
 *     esTransportista:false, productorId:null, compradorId:null,
 *     transportistaId:null, identificacionNumero:null, persona:null }
 *   autenticado:           { esProductor, esComprador, esTransportista,
 *     productorId?, compradorId?, transportistaId?, identificacionNumero,
 *     persona }
 */
final class IdentidadController
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
    ) {}

    public function procesar(): array
    {
        if ($this->actor->tipo === 'NO_AUTENTICADO') {
            return $this->respuesta(true, 'Modo público: sin sesión no se resuelven contextos.', [
                'esProductor' => false,
                'esComprador' => false,
                'esTransportista' => false,
                'productorId' => null,
                'compradorId' => null,
                'transportistaId' => null,
                'identificacionNumero' => null,
                'persona' => null,
            ]);
        }

        $persona = $this->buscarPersonaPorId($this->actor->personaId);
        if ($persona === null) {
            return $this->respuesta(true, 'La sesión no corresponde a una persona registrada.', [
                'esProductor' => false,
                'esComprador' => false,
                'esTransportista' => false,
                'productorId' => null,
                'compradorId' => null,
                'transportistaId' => null,
                'identificacionNumero' => null,
                'persona' => null,
            ]);
        }

        $productor = $this->buscarProductorPorPersonaId((int) $persona['tbpersonaid']);
        $comprador = $this->buscarCompradorPorPersonaId((int) $persona['tbpersonaid']);
        $transportista = $this->buscarTransportistaPorPersonaId((int) $persona['tbpersonaid']);

        return $this->respuesta(true, 'Identidad consultada correctamente.', [
            'esProductor' => $productor !== null,
            'esComprador' => $comprador !== null,
            'esTransportista' => $transportista !== null,
            'productorId' => $productor === null ? null : (int) $productor['tbproductorid'],
            'compradorId' => $comprador === null ? null : (int) $comprador['tbcompradorid'],
            'transportistaId' => $transportista === null ? null : (int) $transportista['tbtransportistaid'],
            'identificacionNumero' => $persona['tbpersonaidentificacionnumero'],
            'persona' => $persona,
        ]);
    }

    private function buscarPersonaPorId(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo,
                    tbpersonanombre, tbpersonaalias, tbpersonatelefono, tbpersonacorreoelectronico,
                    tbpersonaestado
             FROM tbpersona
             WHERE tbpersonaid = :personaId
             LIMIT 1"
        );
        $sentencia->execute(['personaId' => $personaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function buscarProductorPorPersonaId(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT p.tbproductorid
             FROM tbproductor p
             WHERE p.tbpersonaid = :personaId
             LIMIT 1"
        );
        $sentencia->execute(['personaId' => $personaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function buscarCompradorPorPersonaId(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT c.tbcompradorid
             FROM tbcomprador c
             WHERE c.tbpersonaid = :personaId
             LIMIT 1"
        );
        $sentencia->execute(['personaId' => $personaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function buscarTransportistaPorPersonaId(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT t.tbtransportistaid
             FROM tbtransportista t
             WHERE t.tbpersonaid = :personaId
             LIMIT 1"
        );
        $sentencia->execute(['personaId' => $personaId]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200): array
    {
        return [
            'status' => $estado,
            'body' => ['success' => $exito, 'message' => $mensaje, 'data' => $datos],
        ];
    }
}