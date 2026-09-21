<?php

declare(strict_types=1);

require __DIR__ . '/../Application/HttpException.php';
require __DIR__ . '/../Application/Auth/ActorContext.php';
require __DIR__ . '/../Application/Auth/AdminAuthorization.php';
require __DIR__ . '/bootstrap.php';

use Application\Auth\ActorContext;
use Application\Auth\AdminAuthorization;
use Application\HttpException;

$conexion = test_db();
$correoAutorizado = 'admin-authorization-test@example.test';
$actor = ActorContext::personaAutenticada(7, 'subject-7', 'Admin-Authorization-Test@Example.test', 'authenticated');

try {
    $conexion->prepare('INSERT INTO tbadministrador (
        tbadministradorid, tbadministradorcorreoelectronico, tbadministradorestado
    ) VALUES (:id, :correo, 1)')->execute(['id' => -910, 'correo' => $correoAutorizado]);
    AdminAuthorization::require($actor, $conexion);

    try {
        AdminAuthorization::require(
            ActorContext::personaAutenticada(8, 'subject-8', 'otro@example.test', 'authenticated'),
            $conexion,
        );
        throw new RuntimeException('Una cuenta fuera de la política almacenada debe ser rechazada.');
    } catch (HttpException $exception) {
        if ($exception->estadoHttp !== 403) {
            throw new RuntimeException('La cuenta fuera de la política debe responder 403.');
        }
    }

    try {
        AdminAuthorization::require(ActorContext::noAutenticado(), $conexion);
        throw new RuntimeException('Un actor no autenticado debe ser rechazado.');
    } catch (HttpException $exception) {
        if ($exception->estadoHttp !== 401) {
            throw new RuntimeException('Un actor no autenticado debe responder 401.');
        }
    }
} finally {
    $conexion->prepare('DELETE FROM tbadministrador WHERE tbadministradorid = :id')->execute(['id' => -910]);
}

echo "OK admin_authorization_test: política server-side en base, fail-closed y estados HTTP verificados.\n";
