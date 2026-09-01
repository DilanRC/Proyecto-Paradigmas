<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\HttpException;
use Application\Model\ProductorClasificacionPeriodo;
use Application\Service\CompradorClasificacionService;
use PDO;

/**
 * Consulta de solo lectura de los productores clasificados como COMPRADOR
 * (paso (d) de DEC-DBREADY-005, detallado en DEC-DBREADY-008).
 *
 * Reemplaza al CRUD legacy de comprador. No expone alta, edición, baja ni
 * reactivación **a propósito**: Comprador es una clasificación derivada del
 * comportamiento del Productor, no una decisión administrativa. Marcar a
 * alguien como comprador desde una pantalla convertiría la clasificación en un
 * dato capturado a mano, que es justo lo que la evidencia de Calidad descarta.
 *
 * Mientras T10 no exista, el sistema conserva las clasificaciones que ya tiene
 * (las del backfill) y no genera nuevas. Esa carencia es deliberada y visible.
 */
final class CompradorConsultaController
{
    private ProductorClasificacionPeriodo $clasificacion;

    public function __construct(PDO $conexion)
    {
        $this->clasificacion = new ProductorClasificacionPeriodo($conexion);
    }

    public function procesar(string $metodo, array $consulta): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST', 'PUT', 'DELETE', 'PATCH' => $this->respuesta(
                    false,
                    'La clasificación Comprador se deriva del comportamiento del productor y no se administra a mano.',
                    null,
                    405,
                ),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos,
                $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function consultar(array $consulta): array
    {
        if (array_key_exists('identificacionNumero', $consulta)) {
            $identificacion = $this->normalizar($this->texto($consulta['identificacionNumero'], 250));
            if ($identificacion === '') {
                throw new HttpException('La identificación no es válida.', 422);
            }
            $resultado = $this->clasificacion->listarClasificados(
                CompradorClasificacionService::TIPO,
                $identificacion,
                1,
                2,
            );
            $exactos = array_values(array_filter(
                $resultado['clasificados'],
                static fn (array $fila): bool => $fila['identificacionNumero'] === $identificacion,
            ));
            if ($exactos === []) {
                throw new HttpException('Ese productor no tiene una clasificación Comprador abierta.', 404);
            }

            // `estado` conserva el contrato que consume el panel de capacidades:
            // aquí significa "la clasificación está abierta y la persona
            // disponible", no el bit de una tabla de perfil.
            $clasificado = $exactos[0] + [
                'estado' => $exactos[0]['personaEstado'] === 'ACTIVA' ? 'ACTIVO' : 'INACTIVO',
            ];

            return $this->respuesta(true, 'Clasificación consultada correctamente.', $clasificado);
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

        $resultado = $this->clasificacion->listarClasificados(
            CompradorClasificacionService::TIPO,
            $busqueda,
            $pagina,
            $tamano,
        );
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['fuente'] = 'tbproductorclasificacionperiodo';

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
