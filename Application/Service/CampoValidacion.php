<?php

declare(strict_types=1);

namespace Application\Service;

require_once dirname(__DIR__) . '/HttpException.php';

use Application\HttpException;

/**
 * Validación de un campo suelto del cuerpo JSON (texto, número, URL de imagen) y reunión
 * de los errores de varios campos en un solo 422. La comparten los controladores de
 * publicaciones, animales, subastas, vehículos y perfil; antes vivía en uno de ellos.
 */
final class CampoValidacion
{
    public static function texto(mixed $valor, string $campo, int $maximo, bool $obligatorio = false): ?string
    {
        // Una lista u objeto se convertiría en el texto "Array": se rechaza con un mensaje claro.
        if ($valor !== null && !is_string($valor) && !is_int($valor) && !is_float($valor)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser texto.']);
        }
        if ($valor === null || trim((string) $valor) === '') {
            if ($obligatorio) {
                throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Este campo es obligatorio.']);
            }
            return null;
        }
        $valor = trim((string) $valor);
        if (mb_strlen($valor) > $maximo) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => "No puede superar {$maximo} caracteres."]);
        }
        return $valor;
    }

    /** Topes por campo: los de la columna (precio NUMERIC(12,2)) o uno razonable para el dato. */
    private const MAXIMOS = ['precio' => 9999999999.99, 'peso' => 5000, 'edadMeses' => 600];

    public static function numero(mixed $valor, string $campo, bool $entero = false): ?float
    {
        if ($valor === null || $valor === '') return null;
        if (is_bool($valor) || !is_numeric($valor) || (float) $valor < 0 || !is_finite((float) $valor)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un número mayor o igual a cero.']);
        }
        $maximo = self::MAXIMOS[$campo] ?? 2147483647;
        if ((float) $valor > $maximo) {
            $texto = number_format($maximo, $maximo == floor($maximo) ? 0 : 2, ',', '.');
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => "No puede ser mayor que {$texto}."]);
        }
        if ($entero && floor((float) $valor) !== (float) $valor) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser un entero no negativo.']);
        }
        return (float) $valor;
    }

    /**
     * Corre todas las reglas y junta sus errores en un solo 422: la persona ve de una vez todo lo que debe
     * corregir (antes solo el primero, p. ej. el peso, y el precio aparecía recién al reenviar).
     *
     * @param array<string,callable():mixed> $reglas
     * @return array<string,mixed>
     */
    public static function validarTodos(array $reglas): array
    {
        $datos = [];
        $errores = [];
        foreach ($reglas as $clave => $regla) {
            try {
                $datos[$clave] = $regla();
            } catch (HttpException $error) {
                if ($error->errores === []) throw $error;
                $errores += $error->errores;
                $datos[$clave] = null;
            }
        }
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return $datos;
    }

    /**
     * Imagen opcional: solo URLs https absolutas, sin credenciales embebidas.
     * Vale para Supabase Storage y para direcciones externas; un esquema como
     * javascript: o data: nunca llega a la base ni a un <img>.
     */
    public static function imagenUrl(mixed $valor, string $campo = 'imagenUrl'): ?string
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return null;
        }
        if (!is_string($valor)) {
            throw new HttpException('Revise los campos indicados.', 422, null, [$campo => 'Debe ser la dirección (URL) de la imagen.']);
        }
        $url = trim($valor);
        $partes = parse_url($url);
        $valida = mb_strlen($url) <= 500
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($partes)
            && strtolower((string) ($partes['scheme'] ?? '')) === 'https'
            && ($partes['host'] ?? '') !== ''
            && !isset($partes['user'])
            && !isset($partes['pass']);
        if (!$valida) {
            throw new HttpException('Revise los campos indicados.', 422, null, [
                $campo => 'Usa una dirección https válida de hasta 500 caracteres.',
            ]);
        }
        return $url;
    }
}
