<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/TiempoReal.php';
require_once dirname(__DIR__) . '/Service/SubastaCierreService.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Notificacion;
use Application\Service\SubastaCierreService;
use Application\Service\TiempoReal;
use PDO;

/**
 * La campana del encabezado: GET las últimas 20 y cuántas no se han leído; PATCH { accion: 'LEER' } las marca leídas.
 * Antes de leer cierra las subastas vencidas, así el aviso de "ganaste" llega aunque nadie mire la subasta.
 */
final class NotificacionesController
{
    private Notificacion $notificaciones;
    private SubastaCierreService $cierre;

    public function __construct(PDO $conexion, private readonly ActorContext $actor, ?TiempoReal $tiempoReal = null)
    {
        $this->notificaciones = new Notificacion($conexion);
        $this->cierre = new SubastaCierreService($conexion, $tiempoReal ?? new TiempoReal());
    }

    public function procesar(string $metodo, array $cuerpo = []): array
    {
        try {
            if (!$this->actor->tienePersona()) throw new HttpException('Debe iniciar sesión para ver sus avisos.', 401);
            $personaId = (int) $this->actor->personaId;
            if ($metodo === 'GET') {
                $this->cierre->cerrarVencidas();
                return $this->respuesta(true, 'Avisos consultados correctamente.', $this->notificaciones->listar($personaId));
            }
            if ($metodo === 'PATCH') {
                if (array_keys($cuerpo) !== ['accion'] || $cuerpo['accion'] !== 'LEER') {
                    throw new HttpException('Revise los campos indicados.', 422, null, ['accion' => 'Use LEER.']);
                }
                $this->notificaciones->marcarLeidas($personaId);
                return $this->respuesta(true, 'Avisos marcados como leídos.', $this->notificaciones->listar($personaId));
            }
            return $this->respuesta(false, 'Método no permitido.', null, 405);
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
