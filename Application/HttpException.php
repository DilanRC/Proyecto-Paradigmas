<?php

declare(strict_types=1);

namespace Application;

use RuntimeException;

require_once __DIR__ . '/ReadOnlyProperties.php';

/**
 * Excepción HTTP unificada de la API (antigua duplicación de XxxHttpException en
 * cada controlador). Lleva el estado HTTP, los datos opcionales y la lista de
 * errores por campo para responder 422 + errors. Extiende RuntimeException para
 * que los catch (\RuntimeException) existentes sigan funcionando.
 */
class HttpException extends RuntimeException implements \JsonSerializable
{
    use ReadOnlyProperties;

    private const READ_ONLY_PROPERTIES = ['estadoHttp', 'datos', 'errores'];

    public function __construct(
        string $message,
        private int $estadoHttp,
        private ?array $datos = null,
        private array $errores = [],
    ) {
        parent::__construct($message);
    }
}
