<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/TiempoReal.php';
require_once dirname(__DIR__) . '/Service/SubastaCierreService.php';
require_once dirname(__DIR__) . '/Service/DashboardService.php';

use Application\HttpException;
use Application\Model\AdminDashboard;
use Application\Model\AdminDiagnostico;
use Application\Model\Subasta;
use Application\Service\DashboardService;
use Application\Service\SubastaCierreService;
use Application\Service\TiempoReal;
use PDO;

/**
 * Centro de inteligencia del administrador (DEC-DASHBOARD-002). Solo lectura; el endpoint exige administrador antes de
 * llegar aquí. Cuatro vistas, elegidas por `vista`:
 *  - RESUMEN (por defecto): secciones A-F con el periodo `desde`/`hasta` (días de Costa Rica, inclusive).
 *  - EVIDENCIA: filas paginadas que explican un nodo (`nodo`, `estado` opcional, `pagina`, `tamanoPagina`).
 *  - TRANSPORTISTAS: métricas por transportista (paginadas).
 *  - PERSONA: expediente de una Persona (`personaId`) o búsqueda por nombre (`q`).
 *  - DIAGNOSTICO: calidad de datos; sin `tipo` el resumen de los seis, con `tipo` sus filas paginadas (lista cerrada).
 * Cada indicador declara `instrumentado`: false cuando ningún flujo de la aplicación escribe ese hecho (p. ej. viajes
 * realizados), para que la pantalla diga "sin instrumentar" en vez de mostrar un 0 que parezca ausencia de actividad.
 */
final class AdminDashboardController
{
    public const VISTAS = ['RESUMEN', 'EVIDENCIA', 'TRANSPORTISTAS', 'PERSONA', 'DIAGNOSTICO'];
    /** Nodos con evidencia y los estados que aceptan (null = sin filtro de estado). */
    public const NODOS = [
        'subastas' => ['ACTIVA', 'FINALIZADA', 'DESIERTA', 'CANCELADA'],
        'solicitudes' => ['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'],
        'fletes' => ['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'],
        'ventas' => ['SOLICITUD', 'SUBASTA', 'OTRO'],
        'publicaciones' => ['ACTIVO', 'PAUSADO', 'VENDIDO', 'RETIRADO'],
        'interes' => ['REGISTRAR', 'RETIRAR'],
        'viajes' => ['REGISTRADO'],
    ];

    private AdminDashboard $consultas;

    public function __construct(private PDO $conexion, private ?TiempoReal $tiempoReal = null)
    {
        $this->consultas = new AdminDashboard($conexion);
    }

    public function procesar(string $metodo, array $consulta): array
    {
        try {
            if ($metodo !== 'GET') return $this->respuesta(false, 'Método no permitido.', null, 405);
            $vista = is_string($consulta['vista'] ?? null) ? mb_strtoupper(trim($consulta['vista']), 'UTF-8') : 'RESUMEN';
            if (!in_array($vista, self::VISTAS, true)) {
                throw new HttpException('Revise los campos indicados.', 422, null, ['vista' => 'Use ' . implode(', ', self::VISTAS) . '.']);
            }
            $periodo = $this->periodo($consulta);
            // Sin tarea programada: una subasta vencida sigue ACTIVA hasta que una petición la cierra. Se cierran antes
            // de contar, igual que en Mi dashboard y en /admin/subastas.
            (new SubastaCierreService($this->conexion, $this->tiempoReal ?? new TiempoReal()))->cerrarVencidas();
            $datos = match ($vista) {
                'RESUMEN' => $this->resumen($periodo),
                'EVIDENCIA' => $this->evidencia($consulta, $periodo),
                'TRANSPORTISTAS' => $this->transportistas($consulta),
                'PERSONA' => $this->persona($consulta),
                'DIAGNOSTICO' => $this->diagnostico($consulta),
            };
            $datos['periodo'] = $periodo['publico'];
            $datos['generado'] = Subasta::iso(gmdate('Y-m-d H:i:s'));
            return $this->respuesta(true, 'Dashboard consultado correctamente.', $datos);
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function resumen(array $periodo): array
    {
        $sql = ['desde' => $periodo['desde'], 'hasta' => $periodo['hasta']];
        $subastas = new Subasta($this->conexion);
        $lista = $subastas->paraDashboardAdmin($sql);
        $postores = $subastas->postores(array_column($lista, 'subastaId'));
        $indicadores = DashboardService::indicadoresVendedor($lista, $postores);
        // Mismas fórmulas que el dashboard del vendedor, sobre todas las subastas. Los ids se quitan: la evidencia se
        // pide paginada por nodo (no se manda la base entera).
        $sinIds = static function (array $bloque) use (&$sinIds): array {
            unset($bloque['ids']);
            foreach ($bloque as $k => $v) if (is_array($v)) $bloque[$k] = $sinIds($v);
            return $bloque;
        };
        $pujas = array_sum(array_column($lista, 'pujasTotal'));
        $porGrupo = static function (array $filas, callable $clave): array {
            $grupos = [];
            foreach ($filas as $s) {
                $k = $clave($s) ?: 'SIN_DATO';
                $grupos[$k] ??= ['subastas' => 0, 'adjudicadas' => 0, 'monto' => 0.0];
                $grupos[$k]['subastas']++;
                if ($s['estado'] === 'FINALIZADA' && $s['montoAdjudicado'] !== null) {
                    $grupos[$k]['adjudicadas']++;
                    $grupos[$k]['monto'] = round($grupos[$k]['monto'] + $s['montoAdjudicado'], 2);
                }
            }
            ksort($grupos);
            return $grupos;
        };

        return [
            'personas' => $this->consultas->personas(),
            'inventario' => $this->consultas->inventario($sql),
            'comercializacion' => $this->consultas->comercializacion($sql) + ['ventasDiarias' => $this->consultas->ventasDiarias($sql)],
            'subastas' => $sinIds($indicadores) + [
                'pujas' => $pujas,
                'porEspecie' => $porGrupo($lista, static fn (array $s): ?string => $s['animal']['especie'] ?? null),
                'porProvincia' => $porGrupo($lista, static fn (array $s): ?string => $s['zona']['provincia'] ?? null),
                'adjudicadasPorDia' => array_values(array_map(static fn (array $s): array => [
                    'fecha' => $s['fechaFin'], 'monto' => $s['montoAdjudicado'],
                ], array_filter($lista, static fn (array $s): bool => $s['estado'] === 'FINALIZADA' && $s['montoAdjudicado'] !== null))),
            ],
            'transporte' => $this->consultas->transporte($sql) + [
                // Ningún flujo de la aplicación escribe tbtransportistaflete (solo TransportistaHistorico, sin callers):
                // un flete aceptado no es un viaje realizado y no se infiere.
                'viajesInstrumentados' => false,
            ],
            'control' => $this->consultas->control($sql),
            'instrumentacion' => [
                'altasDePersonas' => ['instrumentado' => false, 'motivo' => 'tbpersona no guarda fecha de alta; la bitácora no sustituye el hecho.'],
                'viajesRealizados' => ['instrumentado' => false, 'motivo' => 'No hay flujo que registre un viaje en tbtransportistaflete; un flete aceptado no es un viaje.'],
                'pagos' => ['instrumentado' => false, 'motivo' => 'La adjudicación y la venta se registran; el pago efectivo no.'],
                'reputacion' => ['instrumentado' => false, 'motivo' => 'tbtransportistaresena no tiene flujo y Calidad no aprobó una política de confiabilidad.'],
            ],
        ];
    }

    private function evidencia(array $consulta, array $periodo): array
    {
        $nodo = $consulta['nodo'] ?? '';
        if (!is_string($nodo) || !array_key_exists($nodo, self::NODOS)) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['nodo' => 'Elija un indicador de la lista.']);
        }
        $estado = $consulta['estado'] ?? '';
        $estado = is_string($estado) ? mb_strtoupper(trim($estado), 'UTF-8') : null;
        if ($estado === '') $estado = null;
        if ($estado !== null && !in_array($estado, self::NODOS[$nodo], true)) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['estado' => 'Estado no válido para este indicador.']);
        }
        [$pagina, $tamano] = $this->paginacion($consulta);
        return ['nodo' => $nodo, 'estado' => $estado, 'pagina' => $pagina, 'tamanoPagina' => $tamano]
            + $this->consultas->evidencia($nodo, $estado, ['desde' => $periodo['desde'], 'hasta' => $periodo['hasta']], $pagina, $tamano);
    }

    /** Tabla de transportistas: `q` (2-80 caracteres), `estado` ACTIVO|INACTIVO y `orden` de una lista cerrada. */
    private function transportistas(array $consulta): array
    {
        $errores = [];
        $q = $consulta['q'] ?? '';
        if (!is_string($q) || mb_strlen($q) > 80 || (trim($q) !== '' && mb_strlen(trim($q)) < 2)) $errores['q'] = 'Escriba entre 2 y 80 caracteres.';
        $estado = $consulta['estado'] ?? '';
        if (!is_string($estado) || !in_array($estado, ['', 'ACTIVO', 'INACTIVO'], true)) $errores['estado'] = 'Use ACTIVO o INACTIVO.';
        $orden = $consulta['orden'] ?? 'nombre';
        if (!is_string($orden) || !array_key_exists($orden, AdminDashboard::ORDEN_TRANSPORTISTAS)) {
            $errores['orden'] = 'Use ' . implode(', ', array_keys(AdminDashboard::ORDEN_TRANSPORTISTAS)) . '.';
        }
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        [$pagina, $tamano] = $this->paginacion($consulta);
        return ['pagina' => $pagina, 'tamanoPagina' => $tamano, 'orden' => $orden]
            + $this->consultas->transportistas($pagina, $tamano, trim($q), $estado === '' ? null : $estado === 'ACTIVO', $orden);
    }

    private function diagnostico(array $consulta): array
    {
        $diagnostico = new AdminDiagnostico($this->conexion);
        $tipo = $consulta['tipo'] ?? '';
        if ($tipo === '') return ['diagnosticos' => array_values($diagnostico->resumen())];
        if (!is_string($tipo) || !in_array($tipo, AdminDiagnostico::TIPOS, true)) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['tipo' => 'Elija un diagnóstico de la lista.']);
        }
        return $diagnostico->filas($tipo, ...$this->paginacion($consulta));
    }

    private function persona(array $consulta): array
    {
        $errores = [];
        $entero = DashboardService::entero($consulta['personaId'] ?? '', 'personaId', 1, 2147483647, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        if ($entero !== null) {
            $expediente = $this->consultas->expedientePersona($entero);
            if ($expediente === null) throw new HttpException('La persona no existe.', 404);
            return ['expediente' => $expediente];
        }
        $q = $consulta['q'] ?? '';
        if (!is_string($q) || mb_strlen(trim($q)) < 2 || mb_strlen($q) > 80) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['q' => 'Escriba entre 2 y 80 caracteres.']);
        }
        return ['personas' => $this->consultas->buscarPersonas(trim($q), 20)];
    }

    /** @return array{0:int,1:int} página y tamaño (por defecto 1 y 10; máximo 50 filas). */
    private function paginacion(array $consulta): array
    {
        $errores = [];
        $pagina = DashboardService::entero($consulta['pagina'] ?? '', 'pagina', 1, 2147483647, $errores) ?? 1;
        $tamano = DashboardService::entero($consulta['tamanoPagina'] ?? '', 'tamanoPagina', 1, 50, $errores) ?? 10;
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        return [$pagina, $tamano];
    }

    private function periodo(array $consulta): array
    {
        $errores = [];
        $periodo = DashboardService::periodo($consulta, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        return $periodo;
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
