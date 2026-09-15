<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\Service\CapacidadService;
use PDO;

/**
 * Contrato HTTP del tramo 4: /api/capacidades.php inscribir/abandonar/reactivar
 * sobre los tres contextos con la MISMA estructura de respuesta. Delegar todo
 * en CapacidadService mantiene la semántica idempotente fuera de esta capa y
 * permite que la UI de comprar/vender/fletes se escriba una sola vez.
 */
final class CapacidadController
{
    private CapacidadService $capacidades;

    public function __construct(
        private readonly PDO $conexion,
        private readonly string $solicitudId,
        private readonly ?ActorContext $actor = null,
    ) {
        $this->capacidades = new CapacidadService($this->conexion, $solicitudId, $actor);
    }

    /**
     * @param array $cuerpo { accion, contexto, identificacionNumero, motivo?, datosPersona? }
     * @return array{body: array, status: int}
     */
    public function procesar(array $cuerpo): array
    {
        $accion = is_string($cuerpo['accion'] ?? null) ? strtolower(trim($cuerpo['accion'])) : '';
        if (!in_array($accion, ['inscribir', 'abandonar', 'reactivar'], true)) {
            throw new \Application\HttpException('La acción no está soportada.', 422, null, [
                'accion' => 'Use inscribir, abandonar o reactivar.',
            ]);
        }
        $contexto = is_string($cuerpo['contexto'] ?? null) ? $cuerpo['contexto'] : '';
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null) ? $cuerpo['identificacionNumero'] : '';
        $motivo = is_string($cuerpo['motivo'] ?? null)
            ? $cuerpo['motivo']
            : match ($accion) {
                'inscribir' => 'INSCRIPCION',
                'abandonar' => 'ABANDONO',
                'reactivar' => 'REACTIVACION',
            };
        $datosPersona = is_array($cuerpo['datosPersona'] ?? null) ? $cuerpo['datosPersona'] : null;

        $resultado = match ($accion) {
            'inscribir' => $this->capacidades->inscribir($contexto, $identificacion, $motivo, $datosPersona),
            'abandonar' => $this->capacidades->abandonar($contexto, $identificacion, $motivo),
            'reactivar' => $this->capacidades->reactivar($contexto, $identificacion, $motivo),
        };

        $estado = $resultado['estado'];
        [$mensaje, $status] = match ($estado) {
            'INSCRITO', 'REACTIVADO' => [
                "El contexto {$contexto} quedó inscrito correctamente.",
                201,
            ],
            'ACTIVO' => ["El contexto {$contexto} ya estaba activo.", 200],
            'INACTIVO' => ["El contexto {$contexto} quedó abandonado y puede reactivarse.", 200],
            'COMPLETAR_PERSONA' => [
                'Complete los datos personales para inscribirse.',
                200,
            ],
            default => ['No fue posible resolver el estado del contexto.', 500],
        };

        return ['body' => ['success' => $status < 400, 'message' => $mensaje, 'data' => $resultado], 'status' => $status];
    }
}