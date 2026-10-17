<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    $message = 'Este proyecto necesita PHP 8.0 o posterior: utiliza tipos unión, mixed y propiedades promovidas.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message, 'data' => null, 'errors' => []]);
    }
    exit(1);
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $index = 0;
        foreach ($array as $key => $value) {
            if ($key !== $index++) return false;
        }
        return true;
    }
}

if (PHP_SAPI !== 'cli') {
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $publicRoot = realpath(__DIR__ . '/../Public');
    $publicIsDocumentRoot = $documentRoot !== false && $documentRoot === $publicRoot;
    $rootHtaccess = isset($_SERVER['TC_ROOT_HTACCESS']) || isset($_SERVER['REDIRECT_TC_ROOT_HTACCESS']);
    $publicHtaccess = isset($_SERVER['TC_HTACCESS'])
        || isset($_SERVER['REDIRECT_TC_HTACCESS'])
        || isset($_SERVER['TC_REWRITE'])
        || isset($_SERVER['REDIRECT_TC_REWRITE']);
    $rewriteActive = isset($_SERVER['TC_REWRITE']) || isset($_SERVER['REDIRECT_TC_REWRITE']);

    if (!$rewriteActive || !$publicHtaccess || (!$publicIsDocumentRoot && !$rootHtaccess)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Instalación no compatible: Apache no está aplicando las reglas de acceso del paquete. '
            . 'No publiques la carpeta del proyecto en este servidor. XAMPP debe tener mod_rewrite '
            . 'y respetar .htaccess en htdocs, o DocumentRoot debe apuntar directamente a Public/ '
            . 'con las reglas de Public/ habilitadas.';
        exit;
    }
}
