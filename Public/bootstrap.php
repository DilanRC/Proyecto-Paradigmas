<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Configuration/Compatibility.php';

/**
 * URL base de Public: "/" con VirtualHost, "/GanadoCerca/Public/" dentro de htdocs.
 */
function tc_public_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return rtrim(substr($script, 0, (int) strrpos($script, '/')), '/') . '/';
}

function tc_public_base_attribute(): string
{
    return htmlspecialchars(tc_public_base_path(), ENT_QUOTES, 'UTF-8');
}

/**
 * XAMPP: cada página comprueba la base local antes de dibujarse. Si falta, se crea; si está lista, sigue.
 * Cuando no puede usarse (MySQL apagado, otra estructura, sin permisos) muestra un aviso breve en vez de
 * una página que se queda cargando. No aplica en producción (PostgreSQL/Vercel) ni en Docker.
 */
function tc_preparar_base_local(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    require_once dirname(__DIR__) . '/Configuration/Database.php';
    if (!\Configuration\LocalDatabaseBootstrap::applies()) {
        return;
    }
    try {
        \Configuration\LocalDatabaseBootstrap::verify(\Configuration\Database::getConnection());
    } catch (\Throwable $problema) {
        if (!$problema instanceof \Application\LocalDatabaseUnavailable) {
            // Cualquier otro fallo (consulta rechazada, SQL del paquete dañado) también termina en el aviso,
            // nunca en un error fatal con rutas o argumentos de conexión a la vista.
            \Configuration\LocalDatabaseBootstrap::log('fallo inesperado: ' . get_class($problema) . ' ' . $problema->getMessage());
            $problema = new \Application\LocalDatabaseUnavailable(\Application\LocalDatabaseUnavailable::INSTALACION);
        }
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $base = tc_public_base_attribute();
        $mensaje = htmlspecialchars($problema->getMessage(), ENT_QUOTES, 'UTF-8');
        // Con <base>, un href vacío llevaría a la portada: se repite la dirección pedida.
        $actual = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo <<<HTML
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Ganado Cerca · Base de datos no disponible</title>
                <base href="{$base}">
                <link rel="stylesheet" href="css/base.css?v=product-9">
            </head>
            <body>
                <main style="max-width: 36rem; margin: 12vh auto; padding: 0 16px;">
                    <h1>Ganado Cerca no pudo abrir su base de datos</h1>
                    <p>{$mensaje}</p>
                    <p><a href="{$actual}">Volver a intentar</a></p>
                </main>
            </body>
            </html>
            HTML;
        exit;
    }
}

tc_preparar_base_local();
