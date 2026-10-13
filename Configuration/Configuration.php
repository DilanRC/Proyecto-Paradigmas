<?php

declare(strict_types=1);

namespace Configuration;

require_once __DIR__ . '/Compatibility.php';

date_default_timezone_set('UTC');

const APPLICATION_NAME = 'TinderCows';

// Apache/XAMPP no carga `.env` por sí mismo. Las variables definidas por el
// servidor conservan prioridad; el archivo solo completa las que falten.
loadEnvironmentFile(dirname(__DIR__) . '/.env');

/**
 * Variables que completó el archivo `.env` (nombre => valor). Son valores por defecto de la
 * instalación: admiten las alternativas locales de `LocalDatabaseBootstrap`; las que fija el servidor, no.
 *
 * @return array<string, string>
 */
function environmentFileValues(?string $name = null, string $value = ''): array
{
    static $values = [];
    if ($name !== null) {
        $values[$name] = $value;
    }

    return $values;
}

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
        $current = getenv($name);
        // Apache con hilos (XAMPP en Windows): putenv es de todo el proceso y PHP lo deshace al terminar cada
        // petición, así que otra petición simultánea podía perder SUPABASE_URL a medio camino. apache_setenv
        // deja el valor en la petición; un valor del proceso igual al del archivo es ese putenv de otro hilo.
        $perRequest = function_exists('apache_setenv') && apache_getenv($name) === false;
        if ($current === false || ($perRequest && $current === $value)) {
            if ($perRequest) {
                apache_setenv($name, $value);
            }
            putenv("{$name}={$value}");
            environmentFileValues($name, $value);
        }
    }
}

/** Reads a JSON request body as an associative array. */
function readJsonBody(): array
{
    $content = file_get_contents('php://input');

    if ($content === false || trim($content) === '') {
        throw new \UnexpectedValueException('No recibimos los datos del formulario. Recarga la página e intenta de nuevo.');
    }
    // Las fotos van a Supabase Storage: ningún formulario envía tanto texto.
    if (strlen($content) > 1048576) {
        throw new \UnexpectedValueException('La solicitud es demasiado grande (máximo 1 MB).');
    }

    try {
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        throw new \UnexpectedValueException('Los datos del formulario llegaron dañados. Recarga la página e intenta de nuevo.', 0, $exception);
    }

    // Un formulario sin datos envía "{}", que también se decodifica como []: se distingue de una
    // lista ("[...]") por su primer carácter. Si faltan datos, cada endpoint lo dice con su mensaje.
    if (!is_array($data) || (array_is_list($data) && !str_starts_with(ltrim($content), '{'))) {
        throw new \UnexpectedValueException('Los datos del formulario no tienen el formato esperado. Recarga la página e intenta de nuevo.');
    }
    // Postgres no guarda el carácter nulo (la consulta falla con 500) y ningún
    // campo necesita caracteres de control: se rechazan todos salvo tabulación
    // y saltos de línea (las descripciones los usan).
    array_walk_recursive($data, static function (mixed $valor): void {
        if (is_string($valor) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $valor) === 1) {
            throw new \UnexpectedValueException('Un campo trae caracteres no permitidos. Escríbalo de nuevo sin copiar y pegar símbolos especiales.');
        }
    });

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
