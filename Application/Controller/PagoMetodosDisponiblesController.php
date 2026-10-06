<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\Model\PagoMetodo;
use PDO;

/**
 * Métodos de pago que un cliente con sesión puede proponer en una solicitud de compra: solo los activos,
 * con id y nombre (la descripción interna no sale). `api/v1/metodos-pago` sigue siendo solo del administrador.
 */
final class PagoMetodosDisponiblesController
{
    private PagoMetodo $pagos;

    public function __construct(PDO $conexion, private readonly ActorContext $actor)
    {
        $this->pagos = new PagoMetodo($conexion);
    }

    public function procesar(string $metodo): array
    {
        if (!$this->actor->tienePersona()) {
            return $this->respuesta(false, 'Debe iniciar sesión para ver los métodos de pago.', null, 401);
        }
        if ($metodo !== 'GET') {
            return $this->respuesta(false, 'Método no permitido.', null, 405);
        }

        return $this->respuesta(true, 'Métodos de pago consultados correctamente.', ['metodos' => $this->pagos->listarActivos()]);
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200): array
    {
        return ['status' => $estado, 'body' => ['success' => $exito, 'message' => $mensaje, 'data' => $datos]];
    }
}
