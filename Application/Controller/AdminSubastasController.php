<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/TiempoReal.php';
require_once dirname(__DIR__) . '/Service/SubastaCierreService.php';

use Application\HttpException;
use Application\Model\Subasta;
use Application\Service\SubastaCierreService;
use Application\Service\TiempoReal;
use PDO;

/**
 * Subastas para el administrador: solo lectura (ve todo, no participa).
 *  GET/POST { consulta: { vista: ACTIVAS|HISTORIAL, q, pagina } } → lista
 *  GET/POST { subastaId }                                     → detalle con pujas y comentarios (nombres completos)
 */
final class AdminSubastasController
{
    private Subasta $subastas;
    private SubastaCierreService $cierre;

    public function __construct(PDO $conexion, ?TiempoReal $tiempoReal = null)
    {
        $this->subastas = new Subasta($conexion);
        $this->cierre = new SubastaCierreService($conexion, $tiempoReal ?? new TiempoReal());
    }

    public function procesar(string $metodo, array $entrada = []): array
    {
        try {
            if (!in_array($metodo, ['GET', 'POST'], true)) return $this->respuesta(false, 'Método no permitido.', null, 405);
            $this->cierre->cerrarVencidas();
            if (array_key_exists('subastaId', $entrada)) return $this->detalle($entrada['subastaId']);
            $consulta = is_array($entrada['consulta'] ?? null) ? $entrada['consulta'] : $entrada;
            return $this->listar($consulta);
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function listar(array $consulta): array
    {
        $errores = [];
        $vista = $consulta['vista'] ?? 'ACTIVAS';
        $vista = is_string($vista) ? mb_strtoupper(trim($vista), 'UTF-8') : '';
        if (!in_array($vista, ['ACTIVAS', 'HISTORIAL'], true)) $errores['vista'] = 'Use ACTIVAS o HISTORIAL.';
        $q = $consulta['q'] ?? '';
        if (!is_string($q) || mb_strlen($q) > 100) $errores['q'] = 'La búsqueda es texto de hasta 100 caracteres.';
        $pagina = filter_var(is_bool($consulta['pagina'] ?? 1) ? false : $consulta['pagina'] ?? 1, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($pagina === false) $errores['pagina'] = 'Debe ser un entero positivo.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $resultado = $this->subastas->listar($vista, null, null, trim((string) $q), (int) $pagina, 25);
        $resultado['subastas'] = array_map(static fn (array $s): array => [
            'subastaId' => $s['subastaId'], 'titulo' => $s['titulo'], 'estado' => $s['estado'],
            'precioBase' => $s['precioBase'], 'precioActual' => $s['precioActual'], 'pujasTotal' => $s['pujasTotal'],
            'fechaInicio' => $s['fechaInicio'], 'fechaFin' => $s['fechaFin'], 'fechaCierre' => $s['fechaCierre'],
            'vendedor' => $s['vendedor'], 'finca' => $s['finca'], 'animal' => $s['animal'],
        ], $resultado['subastas']);

        return $this->respuesta(true, 'Subastas consultadas correctamente.', $resultado + [
            'pagina' => (int) $pagina, 'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ]);
    }

    private function detalle(mixed $id): array
    {
        $subastaId = filter_var(is_bool($id) ? false : $id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($subastaId === false) throw new HttpException('Revise los campos indicados.', 422, null, ['subastaId' => 'Debe ser un entero positivo.']);
        $s = $this->subastas->buscar($subastaId) ?? throw new HttpException('La subasta no existe.', 404);
        $ganador = $s['ganadorPersonaId'] === null ? null : $this->subastas->contacto($s['ganadorPersonaId']);

        return $this->respuesta(true, 'Subasta consultada correctamente.', [
            'subasta' => [
                'subastaId' => $s['subastaId'], 'titulo' => $s['titulo'], 'descripcion' => $s['descripcion'],
                'estado' => $s['estado'], 'precioBase' => $s['precioBase'], 'incrementoMinimo' => $s['incrementoMinimo'],
                'precioActual' => $s['precioActual'], 'pujasTotal' => $s['pujasTotal'],
                'fechaInicio' => $s['fechaInicio'], 'fechaFin' => $s['fechaFin'], 'fechaCierre' => $s['fechaCierre'],
                'vendedor' => $s['vendedor'], 'finca' => $s['finca'], 'animal' => $s['animal'],
                'ganador' => $ganador === null ? null : ['nombre' => $ganador['nombre']],
                'pujas' => array_map(static fn (array $p): array => [
                    'monto' => $p['monto'], 'fecha' => $p['fecha'], 'participante' => $p['nombre'],
                ], $this->subastas->pujas($subastaId, 200)),
                'comentarios' => array_map(static fn (array $c): array => [
                    'texto' => $c['texto'], 'fecha' => $c['fecha'], 'autor' => $c['nombre'],
                ], $this->subastas->comentarios($subastaId, 200)),
            ],
            'ahora' => Subasta::iso(gmdate('Y-m-d H:i:s')),
        ]);
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
