<?php

declare(strict_types=1);

/** URL base de Public para VirtualHost y subcarpetas de XAMPP. */
function tc_public_base_path(): string
{
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $publicRoot = realpath(__DIR__);
    if ($documentRoot === false || $publicRoot === false) {
        return '/';
    }

    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $publicRoot = str_replace('\\', '/', $publicRoot);
    if ($publicRoot === $documentRoot) {
        return '/';
    }

    $prefix = $documentRoot . '/';
    if (!str_starts_with($publicRoot, $prefix)) {
        return '/';
    }

    return '/' . trim(substr($publicRoot, strlen($documentRoot)), '/') . '/';
}

function tc_public_base_attribute(): string
{
    return htmlspecialchars(tc_public_base_path(), ENT_QUOTES, 'UTF-8');
}
