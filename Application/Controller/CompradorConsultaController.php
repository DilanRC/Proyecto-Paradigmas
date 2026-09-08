<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\HttpException;
use Application\Model\Comprador;
use PDO;

/**
 * Consulta de solo lectura del contexto Comprador relacionado con Persona.
 *
 * Durante Avance 2 no se expone un CRUD administrativo de Comprador: la fila de
 * contexto debe originarse en el proceso de negocio correspondiente. Esta vista
 * solo consulta lo que efectivamente existe en tbcomprador.
 */
final class CompradorConsultaController
{
    private Comprador $comprador;

    public function __construct(PDO $conexion)
    {
        $this->comprador = new Comprador($conexion);
    }

    public function procesar(string $metodo, array $consulta): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST', 'PUT', 'DELETE', 'PATCH' => $this->respuesta(
                    false,
                    'Comprador no se administra como un rol manual; su contexto se genera desde el proceso de negocio.',
                    null,
                    405,
                ),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores,
            );
        }
    }

    private function consultar(array $consulta): array
    {
        if (array_key_exists('identificacionNumero', $consulta)) {
            $identificacion = $this->normalizar($this->texto($consulta['identificacionNumero'], 250));
            if ($identificacion === '') {
                throw new HttpException('La identificación no es válida.', 422);
            }
            $comprador = $this->comprador->buscar($identificacion);
            if ($comprador === null) {
                throw new HttpException('Comprador no encontrado.', 404);
            }

            return $this->respuesta(true, 'Comprador consultado correctamente.', $comprador);
        }

        $busqueda = $this->texto($consulta['q'] ?? '', 150);
        $pagina = array_key_exists('pagina', $consulta) ? $this->entero($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->entero($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new HttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }

        $resultado = $this->comprador->listar($busqueda, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['fuente'] = 'tbcomprador + tbpersona';

        return $this->respuesta(true, 'Compradores consultados correctamente.', $resultado);
    }

    private function normalizar(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[ -]+/u', '', trim($valor)) ?? '', 'UTF-8');
    }

    private function texto(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new HttpException('La consulta no es válida.', 422);
        }

        return trim($valor);
    }

    private function entero(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new HttpException("{$campo} debe ser un entero positivo.", 422);
        }

        return $entero;
    }

    private function respuesta(
        bool $exito,
        string $mensaje,
        ?array $datos,
        int $estado = 200,
        array $errores = [],
    ): array {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
