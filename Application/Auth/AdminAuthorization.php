<?php

declare(strict_types=1);

namespace Application\Auth;

use Application\HttpException;

final class AdminAuthorization
{
    /**
     * La allowlist vive en el servidor y no se acepta desde el navegador.
     * El JWT ya fue validado por SupabaseActorResolver antes de llegar aquí.
     */
    public static function require(ActorContext $actor): void
    {
        if (!$actor->estaAutenticado()) {
            throw new HttpException('Debe autenticarse para ejecutar esta operación.', 401);
        }

        $permitidos = self::emailsPermitidos();
        if ($permitidos === []) {
            throw new HttpException('La autorización administrativa no está configurada.', 503);
        }

        if (!in_array((string) $actor->correoElectronico, $permitidos, true)) {
            throw new HttpException('La cuenta no tiene autorización administrativa.', 403);
        }
    }

    /** @return list<string> */
    private static function emailsPermitidos(): array
    {
        $configuracion = getenv('SUPABASE_ADMIN_EMAILS');
        if (!is_string($configuracion) || trim($configuracion) === '') {
            return [];
        }

        $emails = preg_split('/[,;\s]+/', $configuracion, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $emails = array_map(static fn (string $email): string => mb_strtolower(trim($email), 'UTF-8'), $emails);
        $emails = array_values(array_unique(array_filter($emails, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));

        return $emails;
    }
}
