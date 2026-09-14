<?php

declare(strict_types=1);

namespace Application\Model;

use Application\Auth\ActorContext;
use JsonException;
use PDO;

final class Bitacora
{
    private readonly ActorContext $actor;
    private int $profundidadBloqueoAlta = 0;

    public function __construct(private readonly PDO $conexion, ?ActorContext $actor = null)
    {
        $this->actor = $actor ?? ActorContext::noAutenticado();
    }

    /**
     * Mantiene el lock del consecutivo durante todo el callback. Los procesos
     * compuestos deben envolver aquí la transacción completa para impedir que
     * otra conexión calcule el mismo MAX+1 antes del COMMIT.
     */
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $exterior = $this->profundidadBloqueoAlta === 0;
        if ($exterior) {
            NamedLock::acquire($this->conexion, 'tindercows_bitacora_alta');
        }
        $this->profundidadBloqueoAlta++;
        try {
            return $operacion();
        } finally {
            $this->profundidadBloqueoAlta--;
            if ($exterior) {
                NamedLock::release($this->conexion, 'tindercows_bitacora_alta');
            }
        }
    }

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
        if ($this->profundidadBloqueoAlta > 0) {
            $this->insertar($accion, $identificacionNumero, $anteriores, $nuevos, $solicitudId, $entidad, $origen);
            return;
        }

        $this->ejecutarConBloqueoAlta(
            fn (): null => $this->insertar(
                $accion,
                $identificacionNumero,
                $anteriores,
                $nuevos,
                $solicitudId,
                $entidad,
                $origen,
            ),
        );
    }

    /** @throws JsonException */
    private function insertar(
        string $accion,
        string $identificacionNumero,
        ?array $anteriores,
        ?array $nuevos,
        string $solicitudId,
        string $entidad,
        string $origen,
    ): null {
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
            'actorTipo' => $this->actor->tipo,
            'usuarioId' => $this->actor->personaId,
            'origen' => $origen,
            'solicitudId' => $solicitudId,
        ]);

        return null;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbbitacoraid), 0) + 1 FROM tbbitacora');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
