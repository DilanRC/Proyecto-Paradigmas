<?php

declare(strict_types=1);

namespace Application\Controller;

// El repositorio usa loaders manuales en API y pruebas; este servicio nuevo
// se carga aqui tambien para mantener el controlador autocontenido.
require_once dirname(__DIR__) . '/Service/PublicacionCercaniaService.php';

use Application\HttpException;
use Application\Model\AnimalComercial;
use Application\Service\PublicacionCercaniaService;
use PDO;

/** Lectura de publicaciones para la vista Explorar. */
final class AnimalPublicacionController
{
    private const ESTADOS = ['TODOS', 'ACTIVO', 'VENDIDO', 'RETIRADO'];

    private readonly AnimalComercial $animales;
    private readonly PublicacionCercaniaService $cercania;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->animales = new AnimalComercial($conexion);
        $this->cercania = new PublicacionCercaniaService($conexion);
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
        $ubicacion = $this->ubicacionConsulta($consulta);

        if ($ubicacion === null) {
            $resultado = $this->animales->listarPublicaciones($busqueda, $estado, $pagina, $tamano);
            $resultado['ranking'] = 'RECIENTE';
        } else {
            // La capa de datos conserva su paginación tradicional. Para ordenar
            // correctamente por distancia antes de paginar se obtiene el total
            // filtrado y el servicio aplica Haversine en PHP. Si el catálogo
            // crece de forma sustancial deberá evolucionar a candidatos por zona.
            $conteo = $this->animales->listarPublicaciones($busqueda, $estado, 1, 1);
            $total = (int) ($conteo['total'] ?? 0);
            $todas = $total === 0
                ? []
                : $this->animales->listarPublicaciones($busqueda, $estado, 1, $total)['publicaciones'];
            $resultado = $this->cercania->ordenarYPaginar(
                $todas,
                $ubicacion['latitud'],
                $ubicacion['longitud'],
                $pagina,
                $tamano
            );
        }
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Publicaciones consultadas correctamente.', $resultado);
    }

    private function ubicacionConsulta(array $consulta): ?array
    {
        $tieneLatitud = array_key_exists('latitud', $consulta) && trim((string) $consulta['latitud']) !== '';
        $tieneLongitud = array_key_exists('longitud', $consulta) && trim((string) $consulta['longitud']) !== '';
        if (!$tieneLatitud && !$tieneLongitud) return null;
        if ($tieneLatitud !== $tieneLongitud) {
            throw new HttpException('Latitud y longitud deben enviarse juntas.', 422, null, [
                'latitud' => 'Envíe ambas coordenadas.',
                'longitud' => 'Envíe ambas coordenadas.',
            ]);
        }
        if (!is_numeric($consulta['latitud']) || !is_numeric($consulta['longitud'])) {
            throw new HttpException('Las coordenadas no son válidas.', 422);
        }
        $latitud = (float) $consulta['latitud'];
        $longitud = (float) $consulta['longitud'];
        if (!is_finite($latitud) || $latitud < -90 || $latitud > 90
            || !is_finite($longitud) || $longitud < -180 || $longitud > 180) {
            throw new HttpException('Las coordenadas están fuera de rango.', 422, null, [
                'latitud' => 'Debe estar entre -90 y 90.',
                'longitud' => 'Debe estar entre -180 y 180.',
            ]);
        }
        return ['latitud' => $latitud, 'longitud' => $longitud];
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
