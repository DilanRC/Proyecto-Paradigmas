<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Consultas de solo lectura del dashboard administrativo (DEC-DASHBOARD-002). Cada método cuenta hechos guardados en
 * su tabla de origen; nada se calcula desde la bitácora ni desde el estado actual cuando existe un histórico.
 *
 * $periodo = ['desde' => ?'Y-m-d H:i:s' UTC, 'hasta' => ?'Y-m-d H:i:s' UTC (exclusivo)]. Cada consulta aplica el periodo
 * sobre la fecha del hecho que cuenta (fecha de la venta, de la solicitud, del cambio de estado…). Los conteos de
 * "inventario actual" (personas, vehículos, ofertas vigentes) no llevan periodo: son una foto de ahora.
 */
final class AdminDashboard
{
    public function __construct(private PDO $conexion) {}

    // ───────────────────────────── A. Personas y red ganadera ─────────────────────────────

    /** Foto actual de la red: una Persona cuenta una vez aunque tenga varias actividades. */
    public function personas(): array
    {
        $uno = fn (string $sql): int => (int) $this->valor($sql);
        $multi = $this->filas(
            'SELECT p.tbpersonaid AS personaid,
                    (SELECT COUNT(*) FROM tbcomprador c WHERE c.tbpersonaid = p.tbpersonaid) AS comprador,
                    (SELECT COUNT(*) FROM tbproductor pr WHERE pr.tbpersonaid = p.tbpersonaid) AS productor,
                    (SELECT COUNT(*) FROM tbtransportista t WHERE t.tbpersonaid = p.tbpersonaid) AS transportista
             FROM tbpersona p'
        );
        $actividades = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach ($multi as $f) {
            $n = ((int) $f['comprador'] > 0 ? 1 : 0) + ((int) $f['productor'] > 0 ? 1 : 0) + ((int) $f['transportista'] > 0 ? 1 : 0);
            $actividades[$n]++;
        }

        return [
            'personas' => count($multi),
            'personasActivas' => $uno('SELECT COUNT(*) FROM tbpersona WHERE tbpersonaestado = 1'),
            'porNumeroDeActividades' => $actividades,
            'compradores' => ['activos' => $uno('SELECT COUNT(*) FROM tbcomprador WHERE tbcompradorestado = 1'),
                'inactivos' => $uno('SELECT COUNT(*) FROM tbcomprador WHERE tbcompradorestado <> 1')],
            'productores' => $uno('SELECT COUNT(*) FROM tbproductor'),
            'transportistas' => ['activos' => $uno('SELECT COUNT(*) FROM tbtransportista WHERE tbtransportistaestado = 1'),
                'inactivos' => $uno('SELECT COUNT(*) FROM tbtransportista WHERE tbtransportistaestado <> 1')],
            'fincas' => ['activas' => $uno('SELECT COUNT(*) FROM tbfinca WHERE tbfincaestado = 1'),
                'inactivas' => $uno('SELECT COUNT(*) FROM tbfinca WHERE tbfincaestado <> 1')],
            'vehiculos' => ['activos' => $uno("SELECT COUNT(*) FROM tbvehiculo WHERE tbvehiculoestado = 1"),
                'inactivos' => $uno("SELECT COUNT(*) FROM tbvehiculo WHERE tbvehiculoestado <> 1")],
            // Fincas por provincia de su última dirección (la ubicación de la red; no se expone ninguna coordenada).
            'fincasPorProvincia' => $this->conteoPor(
                'SELECT d.tbdireccionprovincia AS clave, COUNT(*) AS total
                 FROM tbfinca f
                 LEFT JOIN tbdireccion d ON d.tbdireccionid = (SELECT MAX(fd.tbdireccionid) FROM tbfincadireccion fd WHERE fd.tbfincaid = f.tbfincaid)
                 GROUP BY d.tbdireccionprovincia'),
        ];
    }

    // ───────────────────────────── B. Inventario y publicaciones ─────────────────────────────

    public function inventario(array $periodo): array
    {
        [$filtroAlta, $paramAlta] = $this->rango('p.tbanimalpublicacionfecha', $periodo);
        [$filtroCambio, $paramCambio] = $this->rango('ep.tbanimalpublicacionestadoperiodofechainicio', $periodo);

        return [
            'animalesPorEstado' => $this->conteoPor('SELECT tbanimalestado AS clave, COUNT(*) AS total FROM tbanimal GROUP BY tbanimalestado'),
            // Estado vigente = el periodo sin fecha de fin (histórico de estados, no una columna "estado").
            'publicacionesPorEstado' => $this->conteoPor(
                'SELECT ep.tbanimalpublicacionestadoperiodoestado AS clave, COUNT(*) AS total
                 FROM tbanimalpublicacionestadoperiodo ep
                 WHERE ep.tbanimalpublicacionestadoperiodofechafin IS NULL
                 GROUP BY ep.tbanimalpublicacionestadoperiodoestado'),
            'publicacionesNuevas' => (int) $this->valor("SELECT COUNT(*) FROM tbanimalpublicacion p WHERE 1 = 1{$filtroAlta}", $paramAlta),
            // Cambios de estado ocurridos en el periodo (cada periodo nuevo es un cambio; el primero es el alta).
            'cambiosDeEstado' => $this->conteoPor(
                "SELECT ep.tbanimalpublicacionestadoperiodoestado AS clave, COUNT(*) AS total
                 FROM tbanimalpublicacionestadoperiodo ep WHERE 1 = 1{$filtroCambio}
                 GROUP BY ep.tbanimalpublicacionestadoperiodoestado", $paramCambio),
            'publicacionesActivasPorEspecie' => $this->conteoPor(
                "SELECT es.tbespecienombre AS clave, COUNT(*) AS total
                 FROM tbanimalpublicacion p
                 INNER JOIN tbanimalpublicacionestadoperiodo ep ON ep.tbanimalpublicacionid = p.tbanimalpublicacionid
                      AND ep.tbanimalpublicacionestadoperiodofechafin IS NULL AND ep.tbanimalpublicacionestadoperiodoestado = 'ACTIVO'
                 INNER JOIN tbanimal a ON a.tbanimalid = p.tbanimalid
                 LEFT JOIN tbespecie es ON es.tbespecieid = a.tbespecieid
                 GROUP BY es.tbespecienombre"),
            'publicacionesActivasPorProvincia' => $this->conteoPor(
                "SELECT d.tbdireccionprovincia AS clave, COUNT(*) AS total
                 FROM tbanimalpublicacion p
                 INNER JOIN tbanimalpublicacionestadoperiodo ep ON ep.tbanimalpublicacionid = p.tbanimalpublicacionid
                      AND ep.tbanimalpublicacionestadoperiodofechafin IS NULL AND ep.tbanimalpublicacionestadoperiodoestado = 'ACTIVO'
                 LEFT JOIN tbdireccion d ON d.tbdireccionid = (SELECT MAX(fd.tbdireccionid) FROM tbfincadireccion fd WHERE fd.tbfincaid = p.tbfincaid)
                 GROUP BY d.tbdireccionprovincia"),
            'conSolicitudesPendientes' => (int) $this->valor(
                "SELECT COUNT(DISTINCT s.tbanimalpublicacionid) FROM tbcomprasolicitud s WHERE s.tbcomprasolicitudestado = 'PENDIENTE'"),
        ];
    }

    // ───────────────────────────── C. Interés y comercialización ─────────────────────────────

    /**
     * Cada etapa cuenta su propio hecho dentro del periodo (no es una cohorte: una solicitud del periodo puede venir de
     * un "Me interesa" anterior, y una venta puede no haber pasado por "Me interesa").
     */
    public function comercializacion(array $periodo): array
    {
        [$fInteres, $pInteres] = $this->rango('i.tbanimalpublicacioninteraccionfecha', $periodo);
        [$fSolicitud, $pSolicitud] = $this->rango('s.tbcomprasolicitudfecha', $periodo);
        [$fRespuesta, $pRespuesta] = $this->rango('s.tbcomprasolicitudrespuestafecha', $periodo);
        [$fVenta, $pVenta] = $this->rango('v.tbventafecha', $periodo, true);

        $interes = $this->fila(
            "SELECT COUNT(*) AS total, COUNT(DISTINCT i.tbpersonaid) AS personas, COUNT(DISTINCT i.tbanimalpublicacionid) AS publicaciones
             FROM tbanimalpublicacioninteraccion i
             WHERE i.tbanimalpublicacioninteracciontipo = 'ME_INTERESA' AND i.tbanimalpublicacioninteraccionaccion = 'REGISTRAR'{$fInteres}",
            $pInteres);
        // Ventas: tbventa es la fuente única (la compra espejo no se suma). Una solicitud de un lote produce una fila por
        // animal, así que "operaciones" cuenta solicitudes distintas y "animales" cuenta filas.
        $ventas = $this->filas(
            "SELECT CASE WHEN v.tbcomprasolicitudid IS NOT NULL THEN 'SOLICITUD'
                         WHEN v.tbventaorigen = 'API_SUBASTAS' THEN 'SUBASTA' ELSE 'OTRO' END AS canal,
                    COUNT(*) AS animales,
                    COUNT(DISTINCT v.tbcomprasolicitudid) AS solicitudes,
                    SUM(v.tbventaprecio) AS monto
             FROM tbventa v WHERE 1 = 1{$fVenta}
             GROUP BY CASE WHEN v.tbcomprasolicitudid IS NOT NULL THEN 'SOLICITUD'
                           WHEN v.tbventaorigen = 'API_SUBASTAS' THEN 'SUBASTA' ELSE 'OTRO' END",
            $pVenta);
        $porCanal = [];
        foreach ($ventas as $f) {
            $porCanal[$f['canal']] = [
                'animales' => (int) $f['animales'],
                // Subasta: una venta = una subasta = un animal. Solicitud: una operación por solicitud.
                'operaciones' => $f['canal'] === 'SOLICITUD' ? (int) $f['solicitudes'] : (int) $f['animales'],
                'monto' => round((float) $f['monto'], 2),
            ];
        }

        return [
            'interes' => ['registros' => (int) $interes['total'], 'personas' => (int) $interes['personas'],
                'publicaciones' => (int) $interes['publicaciones']],
            'solicitudesCreadas' => $this->conteoPor(
                "SELECT s.tbcomprasolicitudestado AS clave, COUNT(*) AS total FROM tbcomprasolicitud s WHERE 1 = 1{$fSolicitud}
                 GROUP BY s.tbcomprasolicitudestado", $pSolicitud),
            'solicitudesRespondidas' => $this->conteoPor(
                "SELECT s.tbcomprasolicitudestado AS clave, COUNT(*) AS total FROM tbcomprasolicitud s
                 WHERE s.tbcomprasolicitudrespuestafecha IS NOT NULL{$fRespuesta}
                 GROUP BY s.tbcomprasolicitudestado", $pRespuesta),
            'ventasPorCanal' => $porCanal,
        ];
    }

    /** Ventas por día (tbventa, sin la compra espejo) para la tendencia. */
    public function ventasDiarias(array $periodo): array
    {
        [$filtro, $parametros] = $this->rango('v.tbventafecha', $periodo, true);
        return array_map(static fn (array $f): array => [
            'fecha' => substr((string) $f['fecha'], 0, 10), 'animales' => (int) $f['animales'], 'monto' => round((float) $f['monto'], 2),
        ], $this->filas(
            "SELECT v.tbventafecha AS fecha, COUNT(*) AS animales, SUM(v.tbventaprecio) AS monto
             FROM tbventa v WHERE 1 = 1{$filtro} GROUP BY v.tbventafecha ORDER BY v.tbventafecha", $parametros));
    }

    // ───────────────────────────── E. Transporte ─────────────────────────────

    public function transporte(array $periodo): array
    {
        [$fSolicitud, $pSolicitud] = $this->rango('s.tbcomprasolicitudfecha', $periodo);
        [$fViaje, $pViaje] = $this->rango('f.tbtransportistafletefecha', $periodo, true);
        $ofertas = $this->filas(
            'SELECT o.tbtransportistaofertaestado AS estado, COUNT(*) AS total, SUM(o.tbtransportistaofertacapacidad) AS capacidad,
                    SUM(CASE WHEN o.tbtransportistaofertaprecio IS NULL THEN 1 ELSE 0 END) AS sinprecio
             FROM tbtransportistaoferta o GROUP BY o.tbtransportistaofertaestado');
        $porEstado = [];
        foreach ($ofertas as $f) {
            $porEstado[$f['estado']] = ['total' => (int) $f['total'], 'capacidad' => (int) $f['capacidad'], 'sinPrecio' => (int) $f['sinprecio']];
        }
        $viajes = $this->fila(
            "SELECT COUNT(*) AS total, SUM(f.tbtransportistafletecantidadcabezas) AS cabezas,
                    SUM(f.tbtransportistafletedistanciakm) AS km, SUM(f.tbtransportistafleteprecio) AS precio
             FROM tbtransportistaflete f WHERE 1 = 1{$fViaje}", $pViaje);

        return [
            'ofertasPorEstado' => $porEstado,
            // Solicitudes de flete: las solicitudes de compra que eligieron una oferta (tbtransportistaofertaid).
            'solicitudesFletePorEstado' => $this->conteoPor(
                "SELECT s.tbcomprasolicitudfleteestado AS clave, COUNT(*) AS total FROM tbcomprasolicitud s
                 WHERE s.tbtransportistaofertaid IS NOT NULL AND s.tbcomprasolicitudfleteestado IS NOT NULL{$fSolicitud}
                 GROUP BY s.tbcomprasolicitudfleteestado", $pSolicitud),
            'viajesRegistrados' => [
                'total' => (int) $viajes['total'],
                'cabezas' => $viajes['cabezas'] === null ? null : (int) $viajes['cabezas'],
                'km' => $viajes['km'] === null ? null : round((float) $viajes['km'], 1),
                'ingresosRegistrados' => $viajes['precio'] === null ? null : round((float) $viajes['precio'], 2),
            ],
        ];
    }

    /** Métricas por transportista (paginadas). Viajes = tbtransportistaflete; solicitudes = flete pedido en una compra. */
    public function transportistas(int $pagina, int $tamano): array
    {
        $total = (int) $this->valor('SELECT COUNT(*) FROM tbtransportista');
        $sentencia = $this->conexion->prepare(
            "SELECT t.tbtransportistaid AS transportistaid, t.tbtransportistaestado AS estado, p.tbpersonaid AS personaid,
                    COALESCE(NULLIF(p.tbpersonaalias, ''), p.tbpersonanombre) AS nombre,
                    (SELECT COUNT(*) FROM tbtransportistaoferta o WHERE o.tbtransportistaid = t.tbtransportistaid) AS ofertas,
                    (SELECT COUNT(*) FROM tbtransportistaoferta o WHERE o.tbtransportistaid = t.tbtransportistaid
                         AND o.tbtransportistaofertaestado = 'ACTIVA') AS ofertasactivas,
                    (SELECT SUM(o.tbtransportistaofertacapacidad) FROM tbtransportistaoferta o WHERE o.tbtransportistaid = t.tbtransportistaid
                         AND o.tbtransportistaofertaestado = 'ACTIVA') AS capacidad,
                    (SELECT COUNT(*) FROM tbtransportistavehiculo tv WHERE tv.tbtransportistaid = t.tbtransportistaid) AS vehiculos,
                    (SELECT COUNT(*) FROM tbcomprasolicitud s INNER JOIN tbtransportistaoferta o ON o.tbtransportistaofertaid = s.tbtransportistaofertaid
                         WHERE o.tbtransportistaid = t.tbtransportistaid AND s.tbcomprasolicitudfleteestado IS NOT NULL) AS recibidas,
                    (SELECT COUNT(*) FROM tbcomprasolicitud s INNER JOIN tbtransportistaoferta o ON o.tbtransportistaofertaid = s.tbtransportistaofertaid
                         WHERE o.tbtransportistaid = t.tbtransportistaid AND s.tbcomprasolicitudfleteestado = 'ACEPTADA') AS aceptadas,
                    (SELECT COUNT(*) FROM tbcomprasolicitud s INNER JOIN tbtransportistaoferta o ON o.tbtransportistaofertaid = s.tbtransportistaofertaid
                         WHERE o.tbtransportistaid = t.tbtransportistaid AND s.tbcomprasolicitudfleteestado = 'RECHAZADA') AS rechazadas,
                    (SELECT COUNT(*) FROM tbtransportistaflete f WHERE f.tbtransportistaid = t.tbtransportistaid) AS viajes,
                    (SELECT SUM(f.tbtransportistafletecantidadcabezas) FROM tbtransportistaflete f WHERE f.tbtransportistaid = t.tbtransportistaid) AS cabezas,
                    (SELECT SUM(f.tbtransportistafletedistanciakm) FROM tbtransportistaflete f WHERE f.tbtransportistaid = t.tbtransportistaid) AS km,
                    (SELECT SUM(f.tbtransportistafleteprecio) FROM tbtransportistaflete f WHERE f.tbtransportistaid = t.tbtransportistaid) AS ingresos
             FROM tbtransportista t
             INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid
             ORDER BY t.tbtransportistaid
             LIMIT :limite OFFSET :desplazamiento"
        );
        $sentencia->bindValue('limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue('desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $numero = static fn (mixed $v): ?float => $v === null ? null : round((float) $v, 2);

        return ['total' => $total, 'items' => array_map(static fn (array $f): array => [
            'transportistaId' => (int) $f['transportistaid'], 'personaId' => (int) $f['personaid'], 'nombre' => $f['nombre'],
            'activo' => (int) $f['estado'] === 1, 'ofertas' => (int) $f['ofertas'], 'ofertasActivas' => (int) $f['ofertasactivas'],
            'capacidadOfertada' => $f['capacidad'] === null ? null : (int) $f['capacidad'], 'vehiculos' => (int) $f['vehiculos'],
            'solicitudesRecibidas' => (int) $f['recibidas'], 'solicitudesAceptadas' => (int) $f['aceptadas'],
            'solicitudesRechazadas' => (int) $f['rechazadas'], 'viajesRegistrados' => (int) $f['viajes'],
            'cabezas' => $f['cabezas'] === null ? null : (int) $f['cabezas'], 'km' => $numero($f['km']),
            'ingresosRegistrados' => $numero($f['ingresos']),
            'promedioPorViaje' => (int) $f['viajes'] > 0 && $f['ingresos'] !== null ? round((float) $f['ingresos'] / (int) $f['viajes'], 2) : null,
        ], $sentencia->fetchAll(PDO::FETCH_ASSOC))];
    }

    // ───────────────────────────── F. Control ─────────────────────────────

    public function control(array $periodo): array
    {
        [$fBitacora, $pBitacora] = $this->rango('b.tbbitacorafecha', $periodo);
        return [
            'documentosPorEstado' => $this->conteoPor(
                'SELECT tbpersonadocumentoestado AS clave, COUNT(*) AS total FROM tbpersona
                 WHERE tbpersonadocumentoruta IS NOT NULL GROUP BY tbpersonadocumentoestado'),
            'bitacoraPorEntidad' => $this->conteoPor(
                "SELECT b.tbbitacoraentidad AS clave, COUNT(*) AS total FROM tbbitacora b WHERE 1 = 1{$fBitacora}
                 GROUP BY b.tbbitacoraentidad", $pBitacora),
            'bitacoraPorAccion' => $this->conteoPor(
                "SELECT b.tbbitacoraaccion AS clave, COUNT(*) AS total FROM tbbitacora b WHERE 1 = 1{$fBitacora}
                 GROUP BY b.tbbitacoraaccion", $pBitacora),
            'correosPendientes' => (int) $this->valor("SELECT COUNT(*) FROM tbpersonacorreocambio WHERE tbpersonacorreocambioestado = 'PENDIENTE'"),
            // Datos incompletos que debilitan un indicador (no son errores: se muestran para saber qué tan completa es la base).
            'incompletos' => [
                'publicacionesSinFoto' => (int) $this->valor(
                    "SELECT COUNT(*) FROM tbanimalpublicacion WHERE tbanimalpublicacionimagenurl IS NULL OR tbanimalpublicacionimagenurl = ''"),
                'animalesSinEspecie' => (int) $this->valor('SELECT COUNT(*) FROM tbanimal WHERE tbespecieid IS NULL'),
                'fincasSinDireccion' => (int) $this->valor(
                    'SELECT COUNT(*) FROM tbfinca f WHERE NOT EXISTS (SELECT 1 FROM tbfincadireccion fd WHERE fd.tbfincaid = f.tbfincaid)'),
                'ofertasActivasSinPrecio' => (int) $this->valor(
                    "SELECT COUNT(*) FROM tbtransportistaoferta WHERE tbtransportistaofertaestado = 'ACTIVA' AND tbtransportistaofertaprecio IS NULL"),
                'ventasSinComprador' => (int) $this->valor('SELECT COUNT(*) FROM tbventa WHERE tbcompradorid IS NULL AND tbproductorcompradorid IS NULL'),
                // El cierre escribe la venta en la misma transacción: una adjudicada sin venta vino de otro camino (datos cargados a mano).
                'adjudicadasSinVenta' => (int) $this->valor(
                    "SELECT COUNT(*) FROM tbsubasta s WHERE s.tbsubastaestado = 'FINALIZADA' AND NOT EXISTS (
                        SELECT 1 FROM tbventa v WHERE v.tbanimalid = s.tbanimalid AND v.tbventaorigen = 'API_SUBASTAS')"),
            ],
        ];
    }

    // ───────────────────────────── Evidencia (listas paginadas) ─────────────────────────────

    /**
     * Filas que explican un indicador. $nodo elige la consulta (lista cerrada en el controlador); solo salen datos que la
     * pantalla de administración ya muestra (sin correo, teléfono, identificación ni ruta de documentos).
     */
    public function evidencia(string $nodo, ?string $estado, array $periodo, int $pagina, int $tamano): array
    {
        $definiciones = [
            'subastas' => [
                'desde' => "FROM tbsubasta s INNER JOIN tbproductor pr ON pr.tbproductorid = s.tbproductorid
                            INNER JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid",
                'fecha' => Subasta::SQL_FECHA_HECHO, 'estado' => 's.tbsubastaestado',
                'campos' => "s.tbsubastaid AS id, s.tbsubastatitulo AS titulo, s.tbsubastaestado AS estado,
                             " . Subasta::SQL_FECHA_HECHO . " AS fecha, s.tbsubastaimagenurl AS imagenurl,
                             s.tbsubastapreciobase AS preciobase,
                             (SELECT pg.tbsubastapujamonto FROM tbsubastapuja pg WHERE pg.tbsubastapujaid = s.tbsubastapujaganadoraid) AS monto,
                             (SELECT COUNT(*) FROM tbsubastapuja pp WHERE pp.tbsubastaid = s.tbsubastaid) AS pujas,
                             pe.tbpersonaid AS personaid, COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 's.tbsubastaid DESC',
            ],
            'solicitudes' => [
                'desde' => "FROM tbcomprasolicitud s INNER JOIN tbanimalpublicacion p ON p.tbanimalpublicacionid = s.tbanimalpublicacionid
                            INNER JOIN tbcomprador c ON c.tbcompradorid = s.tbcompradorid INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid",
                'fecha' => 's.tbcomprasolicitudfecha', 'estado' => 's.tbcomprasolicitudestado',
                'campos' => "s.tbcomprasolicitudid AS id, p.tbanimalpublicaciontitulo AS titulo, s.tbcomprasolicitudestado AS estado,
                             s.tbcomprasolicitudfecha AS fecha, p.tbanimalpublicacionimagenurl AS imagenurl, NULL AS preciobase,
                             s.tbcomprasolicitudprecio AS monto, NULL AS pujas, pe.tbpersonaid AS personaid,
                             COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 's.tbcomprasolicitudid DESC',
            ],
            'fletes' => [
                'desde' => "FROM tbcomprasolicitud s INNER JOIN tbtransportistaoferta o ON o.tbtransportistaofertaid = s.tbtransportistaofertaid
                            INNER JOIN tbtransportista t ON t.tbtransportistaid = o.tbtransportistaid INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid
                            INNER JOIN tbanimalpublicacion p ON p.tbanimalpublicacionid = s.tbanimalpublicacionid",
                'fecha' => 's.tbcomprasolicitudfecha', 'estado' => 's.tbcomprasolicitudfleteestado',
                'campos' => "s.tbcomprasolicitudid AS id, p.tbanimalpublicaciontitulo AS titulo, s.tbcomprasolicitudfleteestado AS estado,
                             s.tbcomprasolicitudfecha AS fecha, p.tbanimalpublicacionimagenurl AS imagenurl, NULL AS preciobase,
                             o.tbtransportistaofertaprecio AS monto, NULL AS pujas, pe.tbpersonaid AS personaid,
                             COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 's.tbcomprasolicitudid DESC',
            ],
            'ventas' => [
                'desde' => "FROM tbventa v INNER JOIN tbanimal a ON a.tbanimalid = v.tbanimalid
                            INNER JOIN tbproductor pr ON pr.tbproductorid = v.tbproductorvendedorid INNER JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid",
                'fecha' => 'v.tbventafecha', 'estado' => "CASE WHEN v.tbcomprasolicitudid IS NOT NULL THEN 'SOLICITUD' WHEN v.tbventaorigen = 'API_SUBASTAS' THEN 'SUBASTA' ELSE 'OTRO' END",
                'campos' => "v.tbventaid AS id, COALESCE(NULLIF(v.tbventarazasnapshot, ''), a.tbanimalraza) AS titulo,
                             CASE WHEN v.tbcomprasolicitudid IS NOT NULL THEN 'SOLICITUD' WHEN v.tbventaorigen = 'API_SUBASTAS' THEN 'SUBASTA' ELSE 'OTRO' END AS estado,
                             v.tbventafecha AS fecha, NULL AS imagenurl, NULL AS preciobase, v.tbventaprecio AS monto, NULL AS pujas,
                             pe.tbpersonaid AS personaid, COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 'v.tbventaid DESC', 'soloDia' => true,
            ],
            'publicaciones' => [
                'desde' => "FROM tbanimalpublicacion p INNER JOIN tbanimalpublicacionestadoperiodo ep ON ep.tbanimalpublicacionid = p.tbanimalpublicacionid
                                 AND ep.tbanimalpublicacionestadoperiodofechafin IS NULL
                            INNER JOIN tbproductor pr ON pr.tbproductorid = p.tbproductorvendedorid INNER JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid",
                'fecha' => 'p.tbanimalpublicacionfecha', 'estado' => 'ep.tbanimalpublicacionestadoperiodoestado',
                'campos' => "p.tbanimalpublicacionid AS id, p.tbanimalpublicaciontitulo AS titulo, ep.tbanimalpublicacionestadoperiodoestado AS estado,
                             p.tbanimalpublicacionfecha AS fecha, p.tbanimalpublicacionimagenurl AS imagenurl, NULL AS preciobase,
                             p.tbanimalpublicacionprecio AS monto, NULL AS pujas, pe.tbpersonaid AS personaid,
                             COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 'p.tbanimalpublicacionid DESC',
            ],
            'interes' => [
                'desde' => "FROM tbanimalpublicacioninteraccion i INNER JOIN tbanimalpublicacion p ON p.tbanimalpublicacionid = i.tbanimalpublicacionid
                            INNER JOIN tbpersona pe ON pe.tbpersonaid = i.tbpersonaid",
                'fecha' => 'i.tbanimalpublicacioninteraccionfecha', 'estado' => 'i.tbanimalpublicacioninteraccionaccion',
                'filtroFijo' => "i.tbanimalpublicacioninteracciontipo = 'ME_INTERESA'",
                'campos' => "i.tbanimalpublicacioninteraccionid AS id, p.tbanimalpublicaciontitulo AS titulo,
                             i.tbanimalpublicacioninteraccionaccion AS estado, i.tbanimalpublicacioninteraccionfecha AS fecha,
                             p.tbanimalpublicacionimagenurl AS imagenurl, NULL AS preciobase, NULL AS monto, NULL AS pujas,
                             pe.tbpersonaid AS personaid, COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 'i.tbanimalpublicacioninteraccionid DESC',
            ],
            'viajes' => [
                'desde' => "FROM tbtransportistaflete f INNER JOIN tbtransportista t ON t.tbtransportistaid = f.tbtransportistaid
                            INNER JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid",
                'fecha' => 'f.tbtransportistafletefecha', 'estado' => "'REGISTRADO'",
                'campos' => "f.tbtransportistafleteid AS id, f.tbtransportistafletedescripcion AS titulo, 'REGISTRADO' AS estado,
                             f.tbtransportistafletefecha AS fecha, NULL AS imagenurl, NULL AS preciobase, f.tbtransportistafleteprecio AS monto,
                             f.tbtransportistafletecantidadcabezas AS pujas, pe.tbpersonaid AS personaid,
                             COALESCE(NULLIF(pe.tbpersonaalias, ''), pe.tbpersonanombre) AS persona",
                'orden' => 'f.tbtransportistafleteid DESC', 'soloDia' => true,
            ],
        ];
        $d = $definiciones[$nodo];
        $condiciones = [$d['filtroFijo'] ?? '1 = 1'];
        $parametros = [];
        [$filtro, $parametros] = $this->rango($d['fecha'], $periodo, $d['soloDia'] ?? false);
        if ($estado !== null) {
            $filtro .= " AND {$d['estado']} = :estado";
            $parametros['estado'] = $estado;
        }
        $where = 'WHERE ' . implode(' AND ', $condiciones) . $filtro;
        $total = (int) $this->valor("SELECT COUNT(*) {$d['desde']} {$where}", $parametros);
        $sentencia = $this->conexion->prepare("SELECT {$d['campos']} {$d['desde']} {$where} ORDER BY {$d['orden']} LIMIT :limite OFFSET :desplazamiento");
        foreach ($parametros as $nombre => $valor) $sentencia->bindValue($nombre, $valor);
        $sentencia->bindValue('limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue('desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $numero = static fn (mixed $v): ?float => $v === null ? null : round((float) $v, 2);

        return ['total' => $total, 'items' => array_map(static fn (array $f): array => [
            'id' => (int) $f['id'], 'titulo' => $f['titulo'], 'estado' => $f['estado'],
            'fecha' => $f['fecha'] === null ? null : Subasta::iso($f['fecha']),
            'imagenUrl' => $f['imagenurl'], 'precioBase' => $numero($f['preciobase']), 'monto' => $numero($f['monto']),
            'cantidad' => $f['pujas'] === null ? null : (int) $f['pujas'],
            'personaId' => (int) $f['personaid'], 'persona' => $f['persona'],
        ], $sentencia->fetchAll(PDO::FETCH_ASSOC))];
    }

    // ───────────────────────────── Expediente de una Persona ─────────────────────────────

    /** Una Persona con todas sus actividades. null si no existe. Sin correo, teléfono, identificación ni documento. */
    public function expedientePersona(int $personaId): ?array
    {
        $persona = $this->fila(
            "SELECT p.tbpersonaid AS personaid, p.tbpersonanombre AS nombre, p.tbpersonaalias AS alias, p.tbpersonaestado AS estado,
                    p.tbpersonadocumentoestado AS documento,
                    (SELECT c.tbcompradorid FROM tbcomprador c WHERE c.tbpersonaid = p.tbpersonaid ORDER BY c.tbcompradorid LIMIT 1) AS compradorid,
                    (SELECT c.tbcompradorestado FROM tbcomprador c WHERE c.tbpersonaid = p.tbpersonaid ORDER BY c.tbcompradorid LIMIT 1) AS compradorestado,
                    (SELECT pr.tbproductorid FROM tbproductor pr WHERE pr.tbpersonaid = p.tbpersonaid ORDER BY pr.tbproductorid LIMIT 1) AS productorid,
                    (SELECT t.tbtransportistaid FROM tbtransportista t WHERE t.tbpersonaid = p.tbpersonaid ORDER BY t.tbtransportistaid LIMIT 1) AS transportistaid,
                    (SELECT t.tbtransportistaestado FROM tbtransportista t WHERE t.tbpersonaid = p.tbpersonaid ORDER BY t.tbtransportistaid LIMIT 1) AS transportistaestado
             FROM tbpersona p WHERE p.tbpersonaid = :id", ['id' => $personaId]);
        if ($persona === null) return null;
        $id = ['id' => $personaId];
        $c = $persona['compradorid'] === null ? null : (int) $persona['compradorid'];
        $pr = $persona['productorid'] === null ? null : (int) $persona['productorid'];
        $t = $persona['transportistaid'] === null ? null : (int) $persona['transportistaid'];
        $n = fn (string $sql, array $p): int => (int) $this->valor($sql, $p);
        $suma = fn (string $sql, array $p): ?float => ($v = $this->valor($sql, $p)) === null ? null : round((float) $v, 2);

        return [
            'personaId' => (int) $persona['personaid'], 'nombre' => $persona['nombre'], 'alias' => $persona['alias'],
            'activa' => (int) $persona['estado'] === 1, 'documento' => $persona['documento'],
            'comprador' => $c === null ? null : [
                'activo' => (int) $persona['compradorestado'] === 1,
                'pujas' => $n('SELECT COUNT(*) FROM tbsubastapuja WHERE tbpersonaid = :id', $id),
                'subastasGanadas' => $n("SELECT COUNT(*) FROM tbsubasta s INNER JOIN tbsubastapuja pg ON pg.tbsubastapujaid = s.tbsubastapujaganadoraid
                                         WHERE s.tbsubastaestado = 'FINALIZADA' AND pg.tbpersonaid = :id", $id),
                'solicitudes' => $this->conteoPor('SELECT tbcomprasolicitudestado AS clave, COUNT(*) AS total FROM tbcomprasolicitud
                                                   WHERE tbcompradorid = :c GROUP BY tbcomprasolicitudestado', ['c' => $c]),
                'compras' => $n('SELECT COUNT(*) FROM tbcompra WHERE tbcompradorid = :c', ['c' => $c]),
                'montoComprado' => $suma('SELECT SUM(tbcompraprecio) FROM tbcompra WHERE tbcompradorid = :c', ['c' => $c]),
                'meInteresa' => $n("SELECT COUNT(*) FROM tbanimalpublicacioninteraccion WHERE tbpersonaid = :id
                                    AND tbanimalpublicacioninteracciontipo = 'ME_INTERESA' AND tbanimalpublicacioninteraccionaccion = 'REGISTRAR'", $id),
            ],
            'vendedor' => $pr === null ? null : [
                'fincas' => $n('SELECT COUNT(*) FROM tbfinca WHERE tbproductorid = :p', ['p' => $pr]),
                'animales' => $n('SELECT COUNT(*) FROM tbanimal WHERE tbproductorid = :p', ['p' => $pr]),
                'publicaciones' => $n('SELECT COUNT(*) FROM tbanimalpublicacion WHERE tbproductorvendedorid = :p', ['p' => $pr]),
                'subastas' => $this->conteoPor('SELECT tbsubastaestado AS clave, COUNT(*) AS total FROM tbsubasta WHERE tbproductorid = :p
                                                GROUP BY tbsubastaestado', ['p' => $pr]),
                'ventas' => $n('SELECT COUNT(*) FROM tbventa WHERE tbproductorvendedorid = :p', ['p' => $pr]),
                'montoVendido' => $suma('SELECT SUM(tbventaprecio) FROM tbventa WHERE tbproductorvendedorid = :p', ['p' => $pr]),
            ],
            'transportista' => $t === null ? null : ['activo' => (int) $persona['transportistaestado'] === 1] + $this->expedienteTransportista($t),
        ];
    }

    private function expedienteTransportista(int $transportistaId): array
    {
        $p = ['t' => $transportistaId];
        $viajes = $this->filas(
            'SELECT f.tbtransportistafletefecha AS fecha, f.tbtransportistafletecantidadcabezas AS cabezas,
                    f.tbtransportistafletedistanciakm AS km, f.tbtransportistafleteprecio AS precio, f.tbvehiculoid AS vehiculoid
             FROM tbtransportistaflete f WHERE f.tbtransportistaid = :t ORDER BY f.tbtransportistafletefecha', $p);
        $meses = [];
        foreach ($viajes as $v) {
            $mes = substr((string) $v['fecha'], 0, 7);
            $meses[$mes] ??= ['mes' => $mes, 'viajes' => 0, 'cabezas' => 0, 'km' => 0.0, 'ingresos' => 0.0];
            $meses[$mes]['viajes']++;
            $meses[$mes]['cabezas'] += (int) $v['cabezas'];
            $meses[$mes]['km'] += (float) $v['km'];
            $meses[$mes]['ingresos'] += (float) $v['precio'];
        }

        return [
            'ofertas' => $this->conteoPor('SELECT tbtransportistaofertaestado AS clave, COUNT(*) AS total FROM tbtransportistaoferta
                                           WHERE tbtransportistaid = :t GROUP BY tbtransportistaofertaestado', $p),
            'capacidadOfertada' => ($v = $this->valor("SELECT SUM(tbtransportistaofertacapacidad) FROM tbtransportistaoferta
                                                       WHERE tbtransportistaid = :t AND tbtransportistaofertaestado = 'ACTIVA'", $p)) === null ? null : (int) $v,
            'vehiculos' => (int) $this->valor('SELECT COUNT(*) FROM tbtransportistavehiculo WHERE tbtransportistaid = :t', $p),
            'solicitudesFlete' => $this->conteoPor(
                'SELECT s.tbcomprasolicitudfleteestado AS clave, COUNT(*) AS total FROM tbcomprasolicitud s
                 INNER JOIN tbtransportistaoferta o ON o.tbtransportistaofertaid = s.tbtransportistaofertaid
                 WHERE o.tbtransportistaid = :t AND s.tbcomprasolicitudfleteestado IS NOT NULL GROUP BY s.tbcomprasolicitudfleteestado', $p),
            'viajesRegistrados' => count($viajes),
            'vehiculosUsadosEnViajes' => count(array_unique(array_filter(array_column($viajes, 'vehiculoid'), static fn ($v): bool => $v !== null))),
            'viajesPorMes' => array_values(array_map(static fn (array $m): array => ['km' => round($m['km'], 1), 'ingresos' => round($m['ingresos'], 2)] + $m, $meses)),
        ];
    }

    /** Personas por nombre o alias (para abrir un expediente). Solo id, nombre, alias y actividades. */
    public function buscarPersonas(string $texto, int $limite): array
    {
        $sentencia = $this->conexion->prepare(
            "SELECT p.tbpersonaid AS personaid, p.tbpersonanombre AS nombre, p.tbpersonaalias AS alias,
                    (SELECT COUNT(*) FROM tbcomprador c WHERE c.tbpersonaid = p.tbpersonaid) AS comprador,
                    (SELECT COUNT(*) FROM tbproductor pr WHERE pr.tbpersonaid = p.tbpersonaid) AS productor,
                    (SELECT COUNT(*) FROM tbtransportista t WHERE t.tbpersonaid = p.tbpersonaid) AS transportista
             FROM tbpersona p
             WHERE LOWER(p.tbpersonanombre) LIKE :q OR LOWER(COALESCE(p.tbpersonaalias, '')) LIKE :q2
             ORDER BY p.tbpersonanombre LIMIT :limite"
        );
        $patron = '%' . mb_strtolower($texto, 'UTF-8') . '%';
        $sentencia->bindValue('q', $patron);
        $sentencia->bindValue('q2', $patron);
        $sentencia->bindValue('limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();
        return array_map(static fn (array $f): array => [
            'personaId' => (int) $f['personaid'], 'nombre' => $f['nombre'], 'alias' => $f['alias'],
            'actividades' => array_values(array_filter([
                (int) $f['comprador'] > 0 ? 'COMPRADOR' : null, (int) $f['productor'] > 0 ? 'VENDEDOR' : null,
                (int) $f['transportista'] > 0 ? 'TRANSPORTISTA' : null,
            ])),
        ], $sentencia->fetchAll(PDO::FETCH_ASSOC));
    }

    // ───────────────────────────── Utilidades ─────────────────────────────

    /**
     * Condición " AND col >= :desde AND col < :hasta". $soloDia para columnas DATE (tbventafecha, tbtransportistafletefecha):
     * se comparan solo los 10 primeros caracteres del límite.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function rango(string $columna, array $periodo, bool $soloDia = false): array
    {
        $sql = '';
        $parametros = [];
        foreach (['desde' => '>=', 'hasta' => '<'] as $clave => $operador) {
            if (($periodo[$clave] ?? null) === null) continue;
            $sql .= " AND {$columna} {$operador} :{$clave}";
            $parametros[$clave] = $soloDia ? substr($periodo[$clave . 'Dia'] ?? $periodo[$clave], 0, 10) : $periodo[$clave];
        }
        return [$sql, $parametros];
    }

    /** @return array<string,int> clave → total (las claves NULL salen como "SIN_DATO"). */
    private function conteoPor(string $sql, array $parametros = []): array
    {
        $resultado = [];
        foreach ($this->filas($sql, $parametros) as $f) {
            $clave = $f['clave'] === null || $f['clave'] === '' ? 'SIN_DATO' : (string) $f['clave'];
            $resultado[$clave] = ($resultado[$clave] ?? 0) + (int) $f['total'];
        }
        ksort($resultado);
        return $resultado;
    }

    private function filas(string $sql, array $parametros = []): array
    {
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fila(string $sql, array $parametros = []): ?array
    {
        $filas = $this->filas($sql, $parametros);
        return $filas[0] ?? null;
    }

    private function valor(string $sql, array $parametros = []): mixed
    {
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute($parametros);
        $valor = $sentencia->fetchColumn();
        return $valor === false ? null : $valor;
    }
}
