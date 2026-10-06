<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Direccion;
use Application\Model\TransportistaOferta;
use PDO;

/** Ofertas de flete cercanas a un punto (DEC-FLETE-001). Solo lectura, con sesión. */
final class FletesController
{
    private TransportistaOferta $ofertas;

    public function __construct(PDO $conexion, private readonly ?ActorContext $actor = null)
    {
        $this->ofertas = new TransportistaOferta($conexion, new Direccion($conexion));
    }

    public function procesar(string $metodo, array $consulta = []): array
    {
        try {
            if ($metodo !== 'GET') return $this->respuesta(false, 'Método no permitido.', null, 405);
            return $this->consultar($consulta);
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function consultar(array $consulta): array
    {
        $errores = [];
        $latitud = $this->coordenada($consulta['latitud'] ?? null, 'latitud', 90, $errores);
        $longitud = $this->coordenada($consulta['longitud'] ?? null, 'longitud', 180, $errores);
        $capacidad = $this->entero($consulta['capacidadMinima'] ?? 1, 'capacidadMinima', 1, 200, $errores);
        $pagina = $this->entero($consulta['pagina'] ?? 1, 'pagina', 1, PHP_INT_MAX, $errores);
        $tamano = $this->entero($consulta['tamanoPagina'] ?? 25, 'tamanoPagina', 1, 50, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $resultado = $this->ofertas->listarCercanas(
            $latitud, $longitud, $capacidad, $pagina, $tamano,
            $this->actor?->tienePersona() ? (int) $this->actor->personaId : null,
        );
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;

        return $this->respuesta(true, 'Ofertas de flete consultadas correctamente.', $resultado);
    }

    private function coordenada(mixed $valor, string $campo, int $limite, array &$errores): float
    {
        if (!is_numeric($valor) || abs((float) $valor) > $limite) {
            $errores[$campo] = $valor === null || $valor === ''
                ? 'Comparte tu ubicación para ver los fletes cercanos.'
                : "La {$campo} debe ser un número entre -{$limite} y {$limite}.";
            return 0.0;
        }
        return (float) $valor;
    }

    private function entero(mixed $valor, string $campo, int $minimo, int $maximo, array &$errores): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);
        if ($entero === false) {
            $errores[$campo] = 'Valor no válido.';
            return 0;
        }
        return $entero;
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
