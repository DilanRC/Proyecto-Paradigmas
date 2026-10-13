<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Configuration/Compatibility.php';

function tc_instalador_local_visible(): bool
{
    require_once dirname(__DIR__) . '/Application/Service/LocalDatabaseInstaller.php';

    return \Application\Service\LocalDatabaseInstaller::requestIsLocal($_SERVER);
}

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
 * Primera visita en XAMPP: si la base local no existe o está incompleta, lleva al asistente, que
 * explica qué encontró, en vez de mostrar una portada vacía. Solo lee, y solo para visitas desde la misma computadora.
 */
function tc_exigir_base_local(): void
{
    if (PHP_SAPI === 'cli' || basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'instalacion.php'
        || !tc_instalador_local_visible()) {
        return;
    }
    // Configuration.php carga .env: sin él, environmentIsSupported() no vería una base ajena configurada.
    require_once dirname(__DIR__) . '/Configuration/Configuration.php';
    if (!\Application\Service\LocalDatabaseInstaller::environmentIsSupported()) {
        return;
    }
    try {
        require_once dirname(__DIR__) . '/Configuration/Database.php';
        $tablas = \Configuration\Database::getConnection()->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->fetchColumn();
        if ((int) $tablas >= \Application\Service\LocalDatabaseInstaller::TABLE_COUNT) {
            return;
        }
    } catch (Throwable) {
        // Sin conexión equivale a base ausente: el asistente explica el motivo.
    }
    header('Cache-Control: no-store');
    header('Location: ' . tc_public_base_path() . 'instalacion', true, 302);
    exit;
}

tc_exigir_base_local();
