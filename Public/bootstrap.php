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
