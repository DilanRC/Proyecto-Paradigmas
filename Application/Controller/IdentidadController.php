<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use PDO;

/**
 * Devuelve la identidad del actor autenticado para que el frontend
 * resuelva su productorId sin exponer datos de terceros.
 *
 * GET api/identidad.php → { esProductor, productorId?, identificacionNumero? }
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
            return $this->respuesta(true, 'Sin sesión.', [
                'esProductor' => false,
                'productorId' => null,
                'identificacionNumero' => null,
            ]);
        }

        $productor = $this->buscarProductorPorPersonaId($this->actor->personaId);
        if ($productor === null) {
            return $this->respuesta(true, 'La persona no tiene perfil de productor.', [
                'esProductor' => false,
                'productorId' => null,
                'identificacionNumero' => null,
            ]);
        }

        return $this->respuesta(true, 'Identidad consultada correctamente.', [
            'esProductor' => true,
            'productorId' => (int) $productor['tbproductorid'],
            'identificacionNumero' => $productor['tbpersonaidentificacionnumero'],
        ]);
    }

    private function buscarProductorPorPersonaId(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT p.tbproductorid, pe.tbpersonaidentificacionnumero
             FROM tbproductor p
             INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
             WHERE p.tbpersonaid = :personaId
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
