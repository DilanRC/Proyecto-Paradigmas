<?php

declare(strict_types=1);

http_response_code(405);
header('Allow: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'success' => false,
    'message' => 'Método no permitido.',
    'data' => null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
