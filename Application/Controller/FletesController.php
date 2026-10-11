<?php

declare(strict_types=1);

namespace Application\Controller;

// Los fletes del ganador de una subasta salen del punto de su finca.
require_once dirname(__DIR__) . '/Model/Subasta.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\CompraSolicitud;
use Application\Model\Direccion;
use Application\Model\Subasta;
use Application\Model\TransportistaOferta;
use PDO;

/** Ofertas de flete cercanas a un punto (DEC-FLETE-001). Solo lectura, con sesión. */
final class FletesController
{
    private TransportistaOferta $ofertas;
    private CompraSolicitud $solicitudes;
    private Subasta $subastas;

    public function __construct(PDO $conexion, private readonly ?ActorContext $actor = null)
    {
        $this->ofertas = new TransportistaOferta($conexion, new Direccion($conexion));
        $this->solicitudes = new CompraSolicitud($conexion);
        $this->subastas = new Subasta($conexion);
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
        $conContacto = false;
        if (($consulta['subastaId'] ?? '') !== '') {
            // El ganador de una subasta ve los fletes que cubren la finca, con el teléfono del transportista: ya hay trato.
            $subastaId = $this->entero($consulta['subastaId'], 'subastaId', 1, 2147483647, $errores);
            $subasta = $errores === [] ? $this->subastas->buscar($subastaId) : null;
            if ($errores === [] && ($subasta === null || $subasta['estado'] !== 'FINALIZADA'
                || $subasta['ganadorPersonaId'] !== ($this->actor?->tienePersona() ? (int) $this->actor->personaId : -1))) {
                throw new HttpException('La subasta no existe o no la ganaste.', 404);
            }
            $ubicacion = $subasta === null ? null : $this->subastas->ubicacionFinca($subasta['fincaId']);
            if ($errores === [] && ($ubicacion['latitud'] ?? null) === null && ($ubicacion['provincia'] ?? '') === '') {
                $errores['subastaId'] = 'La finca de esta subasta no tiene dirección: no se pueden buscar fletes.';
            }
            $latitud = $ubicacion['latitud'] ?? null;
            $longitud = $ubicacion['longitud'] ?? null;
            $provincia = $ubicacion['provincia'] ?? null;
            $conContacto = true;
        } elseif (($consulta['publicacionId'] ?? '') !== '') {
            // Con publicacionId el punto es el de la finca del animal (sus coordenadas no se exponen al navegador).
            $publicacionId = $this->entero($consulta['publicacionId'], 'publicacionId', 1, 2147483647, $errores);
            // Sin punto en el mapa se usa la provincia de la finca.
            $ubicacion = $errores === [] ? $this->solicitudes->ubicacionDeFinca($publicacionId) : null;
            if ($errores === [] && $ubicacion === null) {
                $errores['publicacionId'] = 'La finca de esta publicación no tiene dirección: no se pueden buscar fletes.';
            }
            $latitud = $ubicacion['latitud'] ?? null;
            $longitud = $ubicacion['longitud'] ?? null;
            $provincia = $ubicacion['provincia'] ?? null;
        } else {
            $latitud = $this->coordenada($consulta['latitud'] ?? null, 'latitud', 90, $errores);
            $longitud = $this->coordenada($consulta['longitud'] ?? null, 'longitud', 180, $errores);
            // Opcional: la provincia de quien consulta, para sumar las ofertas que no marcaron punto en el mapa.
            $provincia = is_string($consulta['provincia'] ?? null) && mb_strlen($consulta['provincia']) <= 100 ? $consulta['provincia'] : null;
        }
        $capacidad = $this->entero($consulta['capacidadMinima'] ?? 1, 'capacidadMinima', 1, 200, $errores);
        $pagina = $this->entero($consulta['pagina'] ?? 1, 'pagina', 1, 2147483647, $errores);
        $tamano = $this->entero($consulta['tamanoPagina'] ?? 25, 'tamanoPagina', 1, 50, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $resultado = $this->ofertas->listarCercanas(
            $latitud, $longitud, $capacidad, $pagina, $tamano,
            $this->actor?->tienePersona() ? (int) $this->actor->personaId : null,
            $conContacto,
            $provincia,
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
        $entero = filter_var(is_bool($valor) ? false : $valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);
        if ($entero === false) {
            $errores[$campo] = "Debe ser un número entero entre {$minimo} y " . number_format($maximo, 0, ',', '.') . '.';
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
