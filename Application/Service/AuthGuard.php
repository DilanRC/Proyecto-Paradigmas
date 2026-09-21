<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Auth\ActorContext;
use Application\HttpException;

/**
 * Superficie autenticada de la API (DEC-30).
 *
 * El backend no implementa login propio: solo exige el encabezado
 * Authorization cuando la superficie lo requiere. El actor lo resuelve
 * `SupabaseActorResolver`; este guard convierte el modo local
 * NO_AUTENTICADO en un 401 con mensaje claro para que el frontend muestre
 * "inicie sesión", y protege 403 cuando la operación exige un rol técnico de
 * administración que el proveedor no trae.
 *
 * La demo local sin `Bearer` queda restringida a la superficie pública
 * (solo lecturas), coherente con DEC-27 y el plan de ubicación de navegador.
 */
final class AuthGuard
{
    public const ERROR_SIN_SESION = 'SIN_SESION';
    public const ERROR_ROL_REQUERIDO = 'ROL_REQUERIDO';

    /**
     * Exige un actor autenticado. Lanza HttpException(401) si el actor es
     * NO_AUTENTICADO; con $rolAdministrador definido lanza 403 si el rol
     * técnico del proveedor no coincide.
     */
    public static function requerirAutenticado(
        ActorContext $actor,
        ?string $rolAdministrador = null,
    ): ActorContext {
        if ($actor->tipo === 'NO_AUTENTICADO') {
            throw new HttpException(
                'Debe iniciar sesión para acceder a esta superficie. El modo público solo permite leer.',
                401,
                null,
                ['auth' => self::ERROR_SIN_SESION],
            );
        }

        if ($rolAdministrador !== null && $actor->rolTecnico !== $rolAdministrador) {
            throw new HttpException(
                'Se requiere un rol de administración para esta operación.',
                403,
                null,
                ['auth' => self::ERROR_ROL_REQUERIDO],
            );
        }

        return $actor;
    }
}