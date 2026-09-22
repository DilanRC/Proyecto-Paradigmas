<?php

declare(strict_types=1);

namespace Configuration;

const APPLICATION_NAME = 'TinderCows';

// Apache/XAMPP no carga `.env` por sí mismo. Las variables definidas por el
// servidor conservan prioridad; el archivo solo completa las que falten.
loadEnvironmentFile(dirname(__DIR__) . '/.env');

function loadEnvironmentFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }
        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) {
            putenv("{$name}={$value}");
        }
    }
}

/** Reads a JSON request body as an associative array. */
function readJsonBody(): array
{
    $content = file_get_contents('php://input');

    if ($content === false || trim($content) === '') {
        throw new \UnexpectedValueException('El cuerpo JSON no puede estar vacío.');
    }

    try {
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        throw new \UnexpectedValueException('El cuerpo no contiene JSON válido.', 0, $exception);
    }

    if (!is_array($data) || array_is_list($data)) {
        throw new \UnexpectedValueException('El cuerpo debe ser un objeto JSON.');
    }

    return $data;
}

/** Sends a consistent JSON response and ends the request. */
function sendJsonResponse(array $content, int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $content,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}
