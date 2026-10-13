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
