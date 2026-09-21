<?php

declare(strict_types=1);

require __DIR__ . '/../Application/HttpException.php';
require __DIR__ . '/../Application/Auth/ActorContext.php';
require __DIR__ . '/../Application/Auth/AdminAuthorization.php';

use Application\Auth\ActorContext;
use Application\Auth\AdminAuthorization;
use Application\HttpException;

$actor = ActorContext::personaAutenticada(7, 'subject-7', 'Admin@Example.test', 'authenticated');
$previous = getenv('SUPABASE_ADMIN_EMAILS');

try {
    putenv('SUPABASE_ADMIN_EMAILS=admin@example.test');
    AdminAuthorization::require($actor);

    putenv('SUPABASE_ADMIN_EMAILS=otro@example.test');
    try {
        AdminAuthorization::require($actor);
        throw new RuntimeException('Una cuenta fuera de la allowlist debe ser rechazada.');
    } catch (HttpException $exception) {
        if ($exception->estadoHttp !== 403) {
            throw new RuntimeException('La cuenta fuera de la allowlist debe responder 403.');
        }
    }

    putenv('SUPABASE_ADMIN_EMAILS=');
    try {
        AdminAuthorization::require($actor);
        throw new RuntimeException('La ausencia de configuración admin debe fallar cerrada.');
    } catch (HttpException $exception) {
        if ($exception->estadoHttp !== 503) {
            throw new RuntimeException('La allowlist ausente debe responder 503.');
        }
    }

    try {
        AdminAuthorization::require(ActorContext::noAutenticado());
        throw new RuntimeException('Un actor no autenticado debe ser rechazado.');
    } catch (HttpException $exception) {
        if ($exception->estadoHttp !== 401) {
            throw new RuntimeException('Un actor no autenticado debe responder 401.');
        }
    }
} finally {
    if ($previous === false) {
        putenv('SUPABASE_ADMIN_EMAILS');
    } else {
        putenv('SUPABASE_ADMIN_EMAILS=' . $previous);
    }
}

echo "OK admin_authorization_test: allowlist server-side, fail-closed y estados HTTP verificados.\n";
