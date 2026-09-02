<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\HttpException;
use Application\Model\AnimalComercial;
use PDO;

/**
 * Lectura de publicaciones para la vista Explorar.
 *
 * Solo expone GET: publicar, comprar y vender son escrituras que
 * AnimalComercial hace bajo lock y transacción, y no tienen todavía un
 * contrato aprobado de entrada por HTTP. Exponerlas aquí sería inventar ese
 * contrato.
 */
final class AnimalPublicacionController
{
    private const ESTADOS = ['TODOS', 'ACTIVO', 'VENDIDO', 'RETIRADO'];

    private readonly AnimalComercial $animales;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->animales = new AnimalComercial($conexion);
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    private function consultar(array $consulta): array
    {
        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'ACTIVO', 10), 'UTF-8');
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new HttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use ' . implode(', ', self::ESTADOS) . '.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta)
            ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new HttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }

        $resultado = $this->animales->listarPublicaciones($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Publicaciones consultadas correctamente.', $resultado);
    }

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new HttpException('La consulta no es válida.', 422);
        }

        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new HttpException("{$campo} debe ser un entero positivo.", 422);
        }

        return $entero;
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200,
        array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
