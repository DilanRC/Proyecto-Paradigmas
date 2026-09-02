<?php

declare(strict_types=1);

namespace Application;

use RuntimeException;

/**
 * Excepción HTTP unificada de la API (antigua duplicación de XxxHttpException en
 * cada controlador). Lleva el estado HTTP, los datos opcionales y la lista de
 * errores por campo para responder 422 + errors. Extiende RuntimeException para
 * que los catch (\RuntimeException) existentes sigan funcionando.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $estadoHttp,
        public readonly ?array $datos = null,
        public readonly array $errores = [],
    ) {
        parent::__construct($message);
    }
}
