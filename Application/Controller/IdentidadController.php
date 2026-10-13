<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Persona;
use PDO;

require_once dirname(__DIR__) . '/Model/Persona.php';

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
        private PDO $conexion,
        private ActorContext $actor,
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

        $personas = new Persona($this->conexion);
        $persona = $personas->buscarIdentidad($this->actor->personaId);
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

        $ids = $personas->capacidadIds((int) $persona['tbpersonaid']);

        return $this->respuesta(true, 'Identidad consultada correctamente.', [
            'esProductor' => $ids['productor'] !== null,
            'esComprador' => $ids['comprador'] !== null,
            'esTransportista' => $ids['transportista'] !== null,
            'productorId' => $ids['productor'],
            'compradorId' => $ids['comprador'],
            'transportistaId' => $ids['transportista'],
            'identificacionNumero' => $persona['tbpersonaidentificacionnumero'],
            'persona' => $persona,
        ]);
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200): array
    {
        return [
            'status' => $estado,
            'body' => ['success' => $exito, 'message' => $mensaje, 'data' => $datos],
        ];
    }
}