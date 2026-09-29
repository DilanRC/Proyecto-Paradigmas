<?php

declare(strict_types=1);

namespace Application\Controller;

use Closure;

final class RegistroCorreoController
{
    private Closure $correoRegistrado;

    public function __construct(callable $correoRegistrado)
    {
        $this->correoRegistrado = Closure::fromCallable($correoRegistrado);
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        if (strtoupper($metodo) !== 'POST') {
            return $this->respuesta(false, 'Método no permitido.', null, 405);
        }
        if (array_diff(array_keys($cuerpo), ['correoElectronico']) !== []) {
            return $this->respuesta(false, 'Revise el correo electrónico.', null, 422);
        }

        $correo = mb_strtolower(trim((string) ($cuerpo['correoElectronico'] ?? '')), 'UTF-8');
        if (
            strlen($correo) > 150
            || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
        ) {
            return $this->respuesta(false, 'Ingrese un correo electrónico válido.', null, 422);
        }

        $registrado = ($this->correoRegistrado)($correo);
        return $this->respuesta(
            true,
            $registrado ? 'Ese correo ya tiene una cuenta. Inicie sesión para continuar.' : 'Correo disponible.',
            ['disponible' => !$registrado],
        );
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200): array
    {
        return [
            'status' => $estado,
            'body' => [
                'success' => $exito,
                'message' => $mensaje,
                'data' => $datos,
                'errors' => [],
            ],
        ];
    }
}
