<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/TiempoReal.php';
require_once dirname(__DIR__) . '/Service/SubastaCierreService.php';
require_once dirname(__DIR__) . '/Service/DashboardService.php';
require_once dirname(__DIR__) . '/Service/PublicacionCercaniaService.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Comprador;
use Application\Model\Direccion;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Model\Subasta;
use Application\Model\TransportistaOferta;
use Application\Service\DashboardService;
use Application\Service\PublicacionCercaniaService;
use Application\Service\SubastaCierreService;
use Application\Service\TiempoReal;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Dashboards de subastas de la persona autenticada (DEC-DASHBOARD-001). Solo lectura.
 *  GET ?rol=VENDEDOR|COMPRADOR&desde=AAAA-MM-DD&hasta=AAAA-MM-DD&especieId&tipoId&razaId&provincia
 *      (COMPRADOR además: latitud, longitud y radioKm para las oportunidades cercanas)
 * Responde `roles` (qué dashboards puede ver: vendedor si tiene Productor, comprador si tiene Comprador) y, para el rol
 * pedido, `indicadores` y `subastas` (con foto). Las fechas del filtro son días de Costa Rica, ambos inclusive, sobre
 * la fecha en que empezó la subasta. Un rol que la persona no tiene responde 409. Las coordenadas de las fincas nunca
 * salen en la respuesta: solo la distancia.
 */
final class MiDashboardController
{
    public const ROLES = ['VENDEDOR', 'COMPRADOR'];
    public const RADIO_POR_DEFECTO = 50;
    private const ZONA = 'America/Costa_Rica';

    public function __construct(
        private PDO $conexion,
        private ActorContext $actor,
        private ?TiempoReal $tiempoReal = null,
    ) {}

    public function procesar(string $metodo, array $consulta = []): array
    {
        try {
            if (!$this->actor->tienePersona()) {
                throw new HttpException('Debe iniciar sesión para ver su dashboard.', 401);
            }
            if ($metodo !== 'GET') return $this->respuesta(false, 'Método no permitido.', null, 405);

            $filtros = $this->filtros($consulta);
            $personaId = (int) $this->actor->personaId;
            $productor = (new Productor($this->conexion, new ProductorFinca($this->conexion)))->buscarPorPersonaId($personaId);
            $roles = [
                'vendedor' => $productor !== null,
                'comprador' => (new Comprador($this->conexion))->buscarPorPersona($personaId) !== null,
            ];
            // Los estados deben estar al día antes de contarlos (no hay tarea programada que cierre las vencidas).
            (new SubastaCierreService($this->conexion, $this->tiempoReal ?? new TiempoReal()))->cerrarVencidas();

            $datos = ['roles' => $roles, 'rol' => $filtros['rol'], 'filtros' => $filtros['publicos']];
            $datos += $filtros['rol'] === 'VENDEDOR'
                ? $this->vendedor($roles, $productor, $filtros['sql'])
                : $this->comprador($roles, $personaId, $filtros);
            $datos['ahora'] = Subasta::iso(gmdate('Y-m-d H:i:s'));

            return $this->respuesta(true, 'Dashboard consultado correctamente.', $datos);
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function vendedor(array $roles, ?array $productor, array $filtrosSql): array
    {
        if (!$roles['vendedor']) {
            throw new HttpException('Activa la actividad Vendedor para ver este dashboard.', 409, ['roles' => $roles]);
        }
        $subastas = new Subasta($this->conexion);
        $lista = $subastas->paraDashboardVendedor((int) $productor['tbproductorid'], $filtrosSql);
        $postores = $subastas->postores(array_column($lista, 'subastaId'));
        $porSubasta = [];
        foreach ($postores as $par) $porSubasta[$par['subastaId']] = ($porSubasta[$par['subastaId']] ?? 0) + 1;
        foreach ($lista as &$s) {
            $s['postores'] = $porSubasta[$s['subastaId']] ?? 0;
            $s['incrementoPorcentaje'] = $s['montoAdjudicado'] !== null && $s['precioBase'] > 0
                ? round(($s['montoAdjudicado'] - $s['precioBase']) / $s['precioBase'] * 100, 1) : null;
            $s['minutosAdjudicacion'] = $s['estado'] === 'FINALIZADA'
                ? DashboardService::minutosEntre($s['fechaInicio'], $s['fechaCierre'] ?? $s['fechaFin']) : null;
        }
        unset($s);

        return [
            'indicadores' => DashboardService::indicadoresVendedor($lista, $postores),
            'subastas' => array_map([$this, 'publica'], $lista),
        ];
    }

    private function comprador(array $roles, int $personaId, array $filtros): array
    {
        if (!$roles['comprador']) {
            throw new HttpException('Activa la actividad Comprador para ver este dashboard.', 409, ['roles' => $roles]);
        }
        $subastas = new Subasta($this->conexion);
        $historicos = $subastas->preciosAdjudicados();
        $comparar = static fn (array $s): array => DashboardService::promedioComparable(
            $s['animal'], $historicos, (float) ($s['precioActual'] ?? $s['precioBase']));

        $participaciones = $subastas->paraDashboardComprador($personaId, $filtros['sql']);
        $ofertas = new TransportistaOferta($this->conexion, new Direccion($this->conexion));
        foreach ($participaciones as &$s) {
            $s['soyGanador'] = $s['estado'] === 'FINALIZADA' && $s['ganadorPersonaId'] === $personaId;
            $s['voyGanando'] = $s['estado'] === 'ACTIVA' && $s['miPuja'] !== null && $s['miPuja'] >= (float) $s['precioActual'];
            if ($s['estado'] === 'ACTIVA') {
                $s['comparacion'] = $comparar($s);
                $s['costoFlete'] = $this->fleteMasBarato($ofertas, $s, $personaId);
                $s['costoEstimado'] = $s['miPuja'] === null ? null : round($s['miPuja'] + ($s['costoFlete'] ?? 0), 2);
            }
        }
        unset($s);

        // Oportunidades: activas de otros vendedores, sin las que ya puja, dentro del radio desde la ubicación compartida.
        $oportunidades = null;
        $punto = $filtros['punto'];
        if ($punto !== null) {
            $participa = array_flip(array_column($participaciones, 'subastaId'));
            $oportunidades = [];
            foreach ($subastas->activasDeOtros($personaId, $filtros['sql']) as $s) {
                if (isset($participa[$s['subastaId']]) || $s['punto'] === null) continue;
                $distancia = PublicacionCercaniaService::calcularDistanciaKm(
                    $punto['latitud'], $punto['longitud'], $s['punto']['latitud'], $s['punto']['longitud']);
                if ($distancia > $filtros['radioKm']) continue;
                $s['distanciaKm'] = round($distancia, 1);
                $s['comparacion'] = $comparar($s);
                $oportunidades[] = $s;
            }
            usort($oportunidades, static fn (array $a, array $b): int => $a['distanciaKm'] <=> $b['distanciaKm']);
        }

        $indicadores = DashboardService::indicadoresComprador($participaciones, $personaId, $oportunidades, $filtros['radioKm']);
        $lista = array_merge($participaciones, $oportunidades ?? []);
        foreach ($lista as &$s) $s['oportunidad'] = !isset($s['soyGanador']);
        unset($s);

        return ['indicadores' => $indicadores, 'subastas' => array_map([$this, 'publica'], $lista)];
    }

    /** El flete activo más barato (con precio) que cubre la finca de la subasta; null si no hay o todos son "a convenir". */
    private function fleteMasBarato(TransportistaOferta $ofertas, array $subasta, int $personaId): ?float
    {
        $punto = $subasta['punto'];
        $provincia = $subasta['zona']['provincia'] ?? null;
        if ($punto === null && ($provincia ?? '') === '') return null;
        $cercanas = $ofertas->listarCercanas($punto['latitud'] ?? null, $punto['longitud'] ?? null, 1, 1, 50, $personaId, false, $provincia);
        $precios = array_filter(array_column($cercanas['ofertas'], 'precio'), static fn (mixed $p): bool => $p !== null);
        return $precios === [] ? null : (float) min($precios);
    }

    /** Quita lo que no debe salir de la API: coordenadas de la finca y la persona que ganó. */
    private function publica(array $s): array
    {
        unset($s['punto'], $s['ganadorPersonaId']);
        return $s;
    }

    /** Valida la consulta. Devuelve el rol, los filtros para el SQL (fechas en UTC), el punto y radio, y lo público. */
    private function filtros(array $consulta): array
    {
        $permitidos = ['rol', 'desde', 'hasta', 'especieId', 'tipoId', 'razaId', 'provincia', 'latitud', 'longitud', 'radioKm'];
        $errores = [];
        foreach (array_diff(array_keys($consulta), $permitidos) as $campo) $errores[(string) $campo] = 'Campo no permitido.';

        $rol = $consulta['rol'] ?? 'VENDEDOR';
        $rol = is_string($rol) ? mb_strtoupper(trim($rol), 'UTF-8') : '';
        if (!in_array($rol, self::ROLES, true)) $errores['rol'] = 'Use ' . implode(' o ', self::ROLES) . '.';

        $zona = new DateTimeZone(self::ZONA);
        $fechas = [];
        foreach (['desde', 'hasta'] as $campo) {
            $valor = $consulta[$campo] ?? '';
            if ($valor === '' || $valor === null) { $fechas[$campo] = null; continue; }
            $dia = is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $zona) : false;
            if ($dia === false || $dia->format('Y-m-d') !== $valor) {
                $errores[$campo] = 'Use una fecha AAAA-MM-DD.';
                continue;
            }
            $fechas[$campo] = $dia;
        }
        if (($fechas['desde'] ?? null) && ($fechas['hasta'] ?? null) && $fechas['desde'] > $fechas['hasta']) {
            $errores['hasta'] = 'La fecha final no puede ser anterior a la inicial.';
        }

        $ids = [];
        foreach (['especieId', 'tipoId', 'razaId'] as $campo) {
            $ids[$campo] = $this->entero($consulta[$campo] ?? '', $campo, 1, 2147483647, $errores);
        }
        $radio = $this->entero($consulta['radioKm'] ?? '', 'radioKm', 1, 500, $errores) ?? self::RADIO_POR_DEFECTO;

        $provincia = $consulta['provincia'] ?? '';
        if (!is_string($provincia) || mb_strlen(trim($provincia)) > 50) $errores['provincia'] = 'La provincia es texto de hasta 50 caracteres.';
        $provincia = is_string($provincia) ? trim($provincia) : '';

        // La ubicación es opcional, pero va completa: latitud y longitud juntas.
        $punto = null;
        $latitud = $consulta['latitud'] ?? '';
        $longitud = $consulta['longitud'] ?? '';
        if ($latitud !== '' || $longitud !== '') {
            foreach (['latitud' => [$latitud, 90], 'longitud' => [$longitud, 180]] as $campo => [$valor, $limite]) {
                if (!is_string($valor) || !is_numeric($valor) || abs((float) $valor) > $limite) {
                    $errores[$campo] = "Envíe la latitud y la longitud: la {$campo} es un número entre -{$limite} y {$limite}.";
                }
            }
            if (!isset($errores['latitud']) && !isset($errores['longitud'])) {
                $punto = ['latitud' => (float) $latitud, 'longitud' => (float) $longitud];
            }
        }

        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $utc = new DateTimeZone('UTC');
        return [
            'rol' => $rol,
            'sql' => $ids + [
                'desde' => $fechas['desde']?->setTimezone($utc)->format('Y-m-d H:i:s'),
                // "hasta" es inclusive: se busca antes del inicio del día siguiente.
                'hasta' => $fechas['hasta']?->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
                'provincia' => $provincia,
            ],
            'punto' => $punto,
            'radioKm' => $radio,
            'publicos' => $ids + [
                'desde' => $fechas['desde']?->format('Y-m-d'),
                'hasta' => $fechas['hasta']?->format('Y-m-d'),
                'provincia' => $provincia === '' ? null : $provincia,
                'radioKm' => $radio,
                'conUbicacion' => $punto !== null,
            ],
        ];
    }

    /** Entero opcional: vacío → null; fuera de rango, booleano o lista → error del campo. */
    private function entero(mixed $valor, string $campo, int $minimo, int $maximo, array &$errores): ?int
    {
        if ($valor === '' || $valor === null) return null;
        $entero = is_string($valor) || is_int($valor)
            ? filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]) : false;
        if ($entero === false) {
            $errores[$campo] = "Debe ser un número entero entre {$minimo} y " . number_format($maximo, 0, ',', '.') . '.';
            return null;
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
