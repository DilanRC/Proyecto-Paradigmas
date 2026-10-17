<?php

declare(strict_types=1);

namespace Application\Service;

require_once dirname(__DIR__) . '/HttpException.php';
require_once dirname(__DIR__) . '/Auth/ActorContext.php';
foreach (['NamedLock', 'Persona', 'Administrador', 'Bitacora', 'PersonaCorreoCambio'] as $modelo) {
    require_once dirname(__DIR__) . "/Model/{$modelo}.php";
}

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Administrador;
use Application\Model\Bitacora;
use Application\Model\Persona;
use Application\Model\PersonaCorreoCambio;
use PDO;
use Throwable;

/**
 * Mantiene iguales el correo de la cuenta de Supabase y el de tbpersona cuando un cambio
 * de correo se interrumpe. No hay transacción entre los dos sistemas: el cambio se anota
 * PENDIENTE antes de llamar a Supabase y aquí se termina o se descarta después.
 *
 * Fuente de verdad: Supabase. La sesión nace de su cuenta, así que si la cuenta ya tiene
 * el correo nuevo, tbpersona avanza a ese correo; nunca se devuelve la cuenta a ciegas.
 */
final class CorreoCambioService
{
    private const REVISION_MANUAL = 'Hay un cambio de correo anterior sin resolver. Pida a un administrador que lo revise.';

    private PersonaCorreoCambio $cambios;
    private Persona $personas;

    public function __construct(private PDO $conexion)
    {
        $this->cambios = new PersonaCorreoCambio($conexion);
        $this->personas = new Persona($conexion);
    }

    /**
     * Anota el cambio antes de llamar a Supabase. Debe llamarse fuera de una transacción
     * (la fila tiene que sobrevivir al ROLLBACK del perfil) y con el bloqueo de alta de
     * Persona tomado. Un cambio anterior sin resolver se cierra primero.
     *
     * @param callable(): string $correoRemoto correo confirmado actual de la cuenta de Supabase
     */
    public function solicitar(ActorContext $actor, string $anterior, string $nuevo, callable $correoRemoto): int
    {
        $personaId = (int) $actor->personaId;
        $anterior = mb_strtolower($anterior, 'UTF-8');
        $pendiente = $this->cambios->pendientePorPersona($personaId);
        if ($pendiente !== null) {
            if ($pendiente['tbpersonacorreocambiosujeto'] !== (string) $actor->proveedorSujeto) {
                // Lo dejó otra cuenta de Supabase: el correo de esta no dice nada sobre aquel cambio.
                throw new HttpException(self::REVISION_MANUAL, 409);
            }
            if ($anterior === $pendiente['tbpersonacorreocambionuevo']) {
                $this->cambios->cerrar((int) $pendiente['tbpersonacorreocambioid'], PersonaCorreoCambio::APLICADO);
            } elseif ($correoRemoto() === $pendiente['tbpersonacorreocambionuevo']) {
                throw new HttpException($this->aplicar($pendiente, $actor) === null
                    ? self::REVISION_MANUAL
                    : 'Se completó un cambio de correo que había quedado pendiente. Inicie sesión de nuevo.', 409);
            } else {
                $this->cambios->cerrar((int) $pendiente['tbpersonacorreocambioid'], PersonaCorreoCambio::CANCELADO);
            }
        }

        return $this->cambios->crear($personaId, (string) $actor->proveedorSujeto, $anterior, mb_strtolower($nuevo, 'UTF-8'));
    }

    public function cerrar(int $cambioId, string $estado): void
    {
        $this->cambios->cerrar($cambioId, $estado);
    }

    /**
     * La sesión verificada trae un correo sin Persona. Si esa cuenta dejó un cambio
     * PENDIENTE hacia ese mismo correo, Supabase lo aplicó y tbpersona no: se termina
     * aquí. Devuelve la Persona vinculada o null si no hay nada que conciliar.
     */
    public function conciliar(string $sujeto, string $correoSesion, ?string $rol, bool $correoConfirmado): ?int
    {
        try {
            $pendiente = $this->cambios->pendientePorSujeto($sujeto);
        } catch (\PDOException) {
            // Toda sesión sin Persona pasa por aquí (registro, administradores): el motivo debe quedar claro.
            throw new HttpException(
                'No se pudo consultar tbpersonacorreocambio. En una base anterior aplique Database/Migrations/026personacorreocambio.sql.',
                503,
            );
        }
        if ($pendiente === null || $pendiente['tbpersonacorreocambionuevo'] !== mb_strtolower($correoSesion, 'UTF-8')) {
            return null;
        }

        return $this->aplicar(
            $pendiente,
            ActorContext::usuarioVerificado(
                (int) $pendiente['tbpersonaid'],
                $sujeto,
                $correoSesion,
                $rol,
                $correoConfirmado,
            ),
        );
    }

    /** Se puede repetir: si tbpersona ya tiene el correo nuevo solo cierra el cambio. */
    private function aplicar(array $pendiente, ActorContext $actor): ?int
    {
        $cambioId = (int) $pendiente['tbpersonacorreocambioid'];
        $personaId = (int) $pendiente['tbpersonaid'];
        $anterior = $pendiente['tbpersonacorreocambioanterior'];
        $nuevo = $pendiente['tbpersonacorreocambionuevo'];
        $bitacora = new Bitacora($this->conexion, $actor);

        // Mismo orden de bloqueos que Mi perfil: alta de Persona → bitácora → transacción.
        return $this->personas->ejecutarConBloqueoAlta(fn (): ?int => $bitacora->ejecutarConBloqueoAlta(
            function () use ($bitacora, $cambioId, $personaId, $anterior, $nuevo): ?int {
                $this->conexion->beginTransaction();
                try {
                    $persona = $this->personas->buscarPorId($personaId);
                    $actual = $persona === null ? null : mb_strtolower((string) $persona['tbpersonacorreoelectronico'], 'UTF-8');
                    if ($actual === $nuevo) {
                        $this->cambios->cerrar($cambioId, PersonaCorreoCambio::APLICADO);
                    } elseif ($actual !== $anterior || $this->personas->existeCorreo($nuevo)
                        || (new Administrador($this->conexion))->existeCorreo($nuevo)) {
                        // La Persona cambió por otra vía o el correo ya es de otra o de un administrador: no se adivina.
                        $this->conexion->rollBack();
                        return null;
                    } else {
                        $this->personas->actualizarPerfil($personaId, ['correo' => $nuevo]);
                        $bitacora->registrar(
                            'ACTUALIZAR',
                            'PERSONA:' . $personaId,
                            ['correoElectronico' => $anterior],
                            ['correoElectronico' => $nuevo, 'conciliado' => true],
                            bin2hex(random_bytes(16)),
                            entidad: 'PERSONA',
                            origen: 'CONCILIACION_CORREO',
                        );
                        if (!$this->cambios->cerrar($cambioId, PersonaCorreoCambio::APLICADO)) {
                            // La copia leída antes del bloqueo quedó vieja: el cambio se canceló
                            // mientras se esperaba y Supabase ya volvió al correo anterior.
                            $this->conexion->rollBack();
                            return null;
                        }
                    }
                    $this->conexion->commit();

                    return $personaId;
                } catch (Throwable) {
                    if ($this->conexion->inTransaction()) {
                        $this->conexion->rollBack();
                    }
                    // El cambio sigue PENDIENTE: la siguiente petición lo vuelve a intentar.
                    throw new HttpException('No fue posible validar la sesión.', 503);
                }
            },
        ));
    }
}
