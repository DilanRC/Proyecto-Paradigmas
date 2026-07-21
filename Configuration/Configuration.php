<?php

declare(strict_types=1);

namespace Configuration;

const APPLICATION_NAME = 'TinderCows';

/** Reads a JSON request body as an associative array. */
function readJsonBody(): array
{
    $content = file_get_contents('php://input');

    if ($content === false || trim($content) === '') {
        return [];
    }

    try {
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        throw new \UnexpectedValueException('The request body does not contain valid JSON.', 0, $exception);
    }

    if (!is_array($data) || array_is_list($data)) {
        throw new \UnexpectedValueException('The request body must be a JSON object.');
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
