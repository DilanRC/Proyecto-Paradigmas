<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Calidad de datos del panel admin: seis diagnósticos de una lista cerrada. Cada uno define la condición que lo detecta,
 * la clasificación de cada fila (en SQL, para que el resumen y la lista coincidan) y la política que la explica.
 * Solo lectura: nada aquí corrige datos. Un dato opcional no se cuenta como error y un registro de demostración se
 * marca como tal (por las marcas que dejan las cargas de demo, nunca por parecido de nombres).
 *
 * Clasificaciones: REVISION (falta algo que el responsable puede completar), INCONSISTENCIA (contradice una regla del
 * sistema), OPCIONAL (el dato no es obligatorio), SIN_EVIDENCIA (no hay de dónde reconstruirlo).
 */
final class AdminDiagnostico
{
    public const TIPOS = ['adjudicadasSinVenta', 'ventasSinComprador', 'fincasSinDireccion', 'animalesSinEspecie',
        'publicacionesSinFoto', 'ofertasActivasSinPrecio'];
    public const CLASIFICACIONES = ['INCONSISTENCIA', 'REVISION', 'SIN_EVIDENCIA', 'OPCIONAL'];

    /** Marcas de las cargas de demostración (Tools/seed-*.php y la muestra académica de subastas). */
    private const DEMO_SUBASTA = "(s.tbsubastadescripcion LIKE '%[demo-dashboard]%' OR s.tbsubastadescripcion LIKE 'MUESTRA ACAD%'
        OR pe.tbpersonaidentificacionnumero LIKE 'DMDASH%' OR pe.tbpersonaidentificacionnumero LIKE 'DEMO-%')";
    private const DEMO_PERSONA = "(pe.tbpersonaidentificacionnumero LIKE 'DMDASH%' OR pe.tbpersonaidentificacionnumero LIKE 'DEMO-%')";
    /** Venta de una subasta: el cierre la escribe con el mismo animal, origen API_SUBASTAS y fecha desde el inicio. */
    private const VENTA_DE_SUBASTA = "SELECT 1 FROM tbventa v WHERE v.tbanimalid = s.tbanimalid AND v.tbventaorigen = 'API_SUBASTAS'
        AND v.tbventafecha >= CAST(s.tbsubastafechainicio AS DATE)";

    public function __construct(private PDO $conexion) {}

    /** Resumen de los seis: total y conteo por clasificación. */
    public function resumen(): array
    {
        $resultado = [];
        foreach (self::TIPOS as $tipo) {
            $d = $this->definicion($tipo);
            $porClase = array_fill_keys(self::CLASIFICACIONES, 0);
            $sentencia = $this->conexion->query(
                "SELECT x.clave AS clave, COUNT(*) AS total FROM (SELECT {$d['clasificacion']} AS clave {$d['desde']} WHERE {$d['donde']}) x GROUP BY x.clave");
            foreach ($sentencia->fetchAll(PDO::FETCH_ASSOC) as $f) $porClase[$f['clave']] = (int) $f['total'];
            $resultado[$tipo] = ['tipo' => $tipo, 'total' => array_sum($porClase), 'porClasificacion' => $porClase] + $this->politica($tipo);
        }
        return $resultado;
    }

    public function total(string $tipo): int
    {
        $d = $this->definicion($tipo);
        return (int) $this->conexion->query("SELECT COUNT(*) {$d['desde']} WHERE {$d['donde']}")->fetchColumn();
    }

    /** Filas de un diagnóstico, paginadas. $tipo ya viene validado contra TIPOS. */
    public function filas(string $tipo, int $pagina, int $tamano): array
    {
        $d = $this->definicion($tipo);
        $sentencia = $this->conexion->prepare(
            "SELECT {$d['campos']}, {$d['clasificacion']} AS clasificacion {$d['desde']} WHERE {$d['donde']} ORDER BY id DESC LIMIT :limite OFFSET :desplazamiento");
        $sentencia->bindValue('limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue('desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();
        $items = array_map(fn (array $f): array => $this->fila($tipo, $f), $sentencia->fetchAll(PDO::FETCH_ASSOC));

        return ['tipo' => $tipo, 'total' => $this->total($tipo), 'pagina' => $pagina, 'tamanoPagina' => $tamano, 'items' => $items]
            + $this->politica($tipo);
    }

    private function definicion(string $tipo): array
    {
        $persona = 'pe.tbpersonaid AS personaid, COALESCE(NULLIF(pe.tbpersonaalias, \'\'), pe.tbpersonanombre) AS persona';
        return match ($tipo) {
            'adjudicadasSinVenta' => [
                'desde' => 'FROM tbsubasta s LEFT JOIN tbproductor pr ON pr.tbproductorid = s.tbproductorid
                            LEFT JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid
                            LEFT JOIN tbsubastapuja pg ON pg.tbsubastapujaid = s.tbsubastapujaganadoraid',
                'donde' => "s.tbsubastaestado = 'FINALIZADA' AND NOT EXISTS (" . self::VENTA_DE_SUBASTA . ')',
                'clasificacion' => "'INCONSISTENCIA'",
                'campos' => 's.tbsubastaid AS id, s.tbsubastatitulo AS registro, s.tbsubastafechafin AS fecha, ' . $persona . ',
                    CASE WHEN ' . self::DEMO_SUBASTA . " THEN 'DEMO' ELSE 'REAL' END AS origen,
                    pg.tbsubastapujamonto AS monto, s.tbsubastafechafin AS fin, s.tbsubastafechacierre AS cierre,
                    CASE WHEN pg.tbsubastapujaid IS NULL THEN 0 WHEN EXISTS (SELECT 1 FROM tbpersona g WHERE g.tbpersonaid = pg.tbpersonaid) THEN 1 ELSE 0 END AS ganadorexiste,
                    CASE WHEN EXISTS (SELECT 1 FROM tbcompra c WHERE c.tbanimalid = s.tbanimalid AND c.tbcompraorigen = 'API_SUBASTAS') THEN 1 ELSE 0 END AS concompra,
                    (SELECT a.tbanimalestado FROM tbanimal a WHERE a.tbanimalid = s.tbanimalid) AS animalestado",
            ],
            'ventasSinComprador' => [
                'desde' => 'FROM tbventa v LEFT JOIN tbcompra c ON c.tbcompraid = v.tbcompraid
                            LEFT JOIN tbproductor pr ON pr.tbproductorid = v.tbproductorvendedorid
                            LEFT JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid',
                'donde' => 'v.tbcompradorid IS NULL AND v.tbproductorcompradorid IS NULL',
                // La compra espejo se escribe en la misma operación: si ella identifica al comprador, el vínculo se perdió.
                'clasificacion' => "CASE WHEN c.tbcompradorid IS NOT NULL OR c.tbproductorcompradorid IS NOT NULL THEN 'INCONSISTENCIA'
                    WHEN v.tbventaorigen = 'API_SUBASTAS' AND EXISTS (
                        SELECT 1 FROM tbsubasta s INNER JOIN tbsubastapuja pg ON pg.tbsubastapujaid = s.tbsubastapujaganadoraid
                        WHERE s.tbanimalid = v.tbanimalid AND s.tbsubastaestado = 'FINALIZADA'
                          AND NOT EXISTS (SELECT 1 FROM tbpersona g WHERE g.tbpersonaid = pg.tbpersonaid)) THEN 'SIN_EVIDENCIA'
                    ELSE 'OPCIONAL' END",
                'campos' => "v.tbventaid AS id, COALESCE(NULLIF(v.tbventarazasnapshot, ''), 'Venta') AS registro, v.tbventafecha AS fecha, {$persona},
                    CASE WHEN " . self::DEMO_PERSONA . " THEN 'DEMO' ELSE 'REAL' END AS origen,
                    v.tbventaprecio AS monto, v.tbventaorigen AS canal, v.tbcompraid AS compraid, v.tbcomprasolicitudid AS solicitudid",
            ],
            'fincasSinDireccion' => [
                'desde' => 'FROM tbfinca f LEFT JOIN tbproductor pr ON pr.tbproductorid = f.tbproductorid
                            LEFT JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid',
                'donde' => 'NOT EXISTS (SELECT 1 FROM tbfincadireccion fd WHERE fd.tbfincaid = f.tbfincaid)',
                'clasificacion' => "CASE WHEN pe.tbpersonaid IS NULL THEN 'INCONSISTENCIA' WHEN f.tbfincaestado = 1 THEN 'REVISION' ELSE 'OPCIONAL' END",
                'campos' => "f.tbfincaid AS id, f.tbfincanombre AS registro, NULL AS fecha, {$persona},
                    CASE WHEN " . self::DEMO_PERSONA . " THEN 'DEMO' ELSE 'REAL' END AS origen, f.tbfincaestado AS activo,
                    (SELECT COUNT(*) FROM tbsubasta s WHERE s.tbfincaid = f.tbfincaid) AS subastas",
            ],
            'animalesSinEspecie' => [
                'desde' => 'FROM tbanimal a LEFT JOIN tbproductor pr ON pr.tbproductorid = a.tbproductorid
                            LEFT JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid',
                'donde' => 'a.tbespecieid IS NULL',
                // Sin dueño no hay quién complete la clasificación ni registro del que tomarla (no se adivina por la raza escrita).
                'clasificacion' => "CASE WHEN pe.tbpersonaid IS NULL THEN 'SIN_EVIDENCIA' ELSE 'REVISION' END",
                'campos' => "a.tbanimalid AS id, COALESCE(NULLIF(a.tbanimalraza, ''), 'Animal') AS registro, a.tbanimalfecharegistroensistema AS fecha, {$persona},
                    CASE WHEN " . self::DEMO_PERSONA . " THEN 'DEMO' ELSE 'REAL' END AS origen,
                    CASE WHEN a.tbanimaltipoid IS NULL THEN 0 ELSE 1 END AS contipo, CASE WHEN a.tbrazaid IS NULL THEN 0 ELSE 1 END AS conraza,
                    (SELECT COUNT(*) FROM tbanimalpublicacionanimal x WHERE x.tbanimalid = a.tbanimalid)
                      + (SELECT COUNT(*) FROM tbventa x WHERE x.tbanimalid = a.tbanimalid)
                      + (SELECT COUNT(*) FROM tbsubasta x WHERE x.tbanimalid = a.tbanimalid) AS usos",
            ],
            'publicacionesSinFoto' => [
                'desde' => 'FROM tbanimalpublicacion p LEFT JOIN tbanimalpublicacionestadoperiodo ep ON ep.tbanimalpublicacionid = p.tbanimalpublicacionid
                                 AND ep.tbanimalpublicacionestadoperiodofechafin IS NULL
                            LEFT JOIN tbproductor pr ON pr.tbproductorid = p.tbproductorvendedorid
                            LEFT JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid',
                'donde' => "(p.tbanimalpublicacionimagenurl IS NULL OR p.tbanimalpublicacionimagenurl = '')",
                'clasificacion' => "'OPCIONAL'",
                'campos' => "p.tbanimalpublicacionid AS id, p.tbanimalpublicaciontitulo AS registro, p.tbanimalpublicacionfecha AS fecha, {$persona},
                    CASE WHEN " . self::DEMO_PERSONA . " THEN 'DEMO' ELSE 'REAL' END AS origen,
                    ep.tbanimalpublicacionestadoperiodoestado AS estado",
            ],
            'ofertasActivasSinPrecio' => [
                'desde' => 'FROM tbtransportistaoferta o LEFT JOIN tbtransportista t ON t.tbtransportistaid = o.tbtransportistaid
                            LEFT JOIN tbpersona pe ON pe.tbpersonaid = t.tbpersonaid',
                'donde' => "o.tbtransportistaofertaestado = 'ACTIVA' AND o.tbtransportistaofertaprecio IS NULL",
                'clasificacion' => "'OPCIONAL'",
                'campos' => "o.tbtransportistaofertaid AS id, COALESCE(NULLIF(o.tbtransportistaofertadescripcion, ''), 'Oferta de flete') AS registro,
                    NULL AS fecha, {$persona}, CASE WHEN " . self::DEMO_PERSONA . " THEN 'DEMO' ELSE 'REAL' END AS origen,
                    o.tbtransportistaofertacapacidad AS capacidad",
            ],
        };
    }

    /** Qué significa el diagnóstico, si el dato es obligatorio y cómo se resuelve. Sin nombres de tablas. */
    private function politica(string $tipo): array
    {
        return match ($tipo) {
            'adjudicadasSinVenta' => ['titulo' => 'Subastas adjudicadas sin venta', 'dato' => 'OBLIGATORIO', 'prioridad' => 1,
                'regla' => 'Al cerrar una subasta con ganador, la aplicación registra la compra y la venta en la misma operación. Una adjudicada sin venta no pasó por ese cierre.',
                'solucion' => 'No se completa a mano: la venta solo se reconstruye si hay un ganador existente y el cierre consta en la bitácora. Las de demostración se limpian con autorización del equipo.',
                'enlace' => 'admin/subastas'],
            'ventasSinComprador' => ['titulo' => 'Ventas sin comprador identificado', 'dato' => 'OPCIONAL', 'prioridad' => 2,
                'regla' => 'La venta es un hecho histórico y el comprador puede ser desconocido. Solo es inconsistencia si la compra de la misma operación sí lo identifica.',
                'solucion' => 'Si la compra relacionada identifica al comprador, se puede reparar el vínculo. Si la persona ganadora ya no existe, el comprador queda desconocido: no se asigna otra.',
                'enlace' => 'admin/bitacora'],
            'fincasSinDireccion' => ['titulo' => 'Fincas sin dirección', 'dato' => 'OPCIONAL', 'prioridad' => 3,
                'regla' => 'La dirección de la finca no es obligatoria al crearla, pero sin ella la finca no aparece en búsquedas por zona ni en fletes.',
                'solucion' => 'La persona dueña la completa en Mi panel → Fincas. Una finca cuyo dueño ya no existe es un registro huérfano: se limpia con autorización.',
                'enlace' => 'admin/productores'],
            'animalesSinEspecie' => ['titulo' => 'Animales sin especie', 'dato' => 'OBLIGATORIO', 'prioridad' => 4,
                'regla' => 'Desde los catálogos, todo animal nuevo trae especie. Los anteriores pueden no tenerla; no se deduce por la raza escrita ni por la foto.',
                'solucion' => 'La persona dueña elige especie, tipo y raza en Mis animales. Sin dueño no hay quién lo confirme: se conserva como registro heredado.',
                'enlace' => 'admin/catalogos'],
            'publicacionesSinFoto' => ['titulo' => 'Publicaciones sin foto', 'dato' => 'OPCIONAL', 'prioridad' => 5,
                'regla' => 'La foto de una publicación es opcional; se revisa solo la dirección de la imagen, no se descargan archivos.',
                'solucion' => 'La persona vendedora puede agregarla al editar la publicación.', 'enlace' => 'admin/publicaciones'],
            'ofertasActivasSinPrecio' => ['titulo' => 'Ofertas de flete sin precio', 'dato' => 'OPCIONAL', 'prioridad' => 6,
                'regla' => 'Una oferta de flete puede quedar «a convenir»: el precio se acuerda con el transportista.',
                'solucion' => 'No requiere acción.', 'enlace' => 'admin/fletes'],
        };
    }

    private function fila(string $tipo, array $f): array
    {
        $si = static fn (mixed $v): bool => (int) $v === 1;
        $monto = static fn (mixed $v): ?float => $v === null ? null : round((float) $v, 2);
        $base = [
            'id' => (int) $f['id'], 'registro' => (string) $f['registro'],
            'fecha' => $f['fecha'] === null ? null : Subasta::iso((string) $f['fecha']),
            'clasificacion' => $f['clasificacion'], 'origen' => $f['origen'],
            'personaId' => $f['personaid'] === null ? null : (int) $f['personaid'], 'persona' => $f['persona'],
        ];
        [$causa, $detalles] = match ($tipo) {
            'adjudicadasSinVenta' => [
                !$this->cierreEnBitacora((int) $f['id'])
                    ? 'El cierre no consta en la bitácora: la subasta se marcó adjudicada fuera de la aplicación.'
                    : 'El cierre consta en la bitácora pero la venta no se registró.',
                [['Monto ganador', $monto($f['monto'])], ['Ganador', $si($f['ganadorexiste']) ? 'Existe' : 'No existe'],
                 ['Compra registrada', $si($f['concompra']) ? 'Sí' : 'No'], ['Estado del animal', $f['animalestado']],
                 ['Cierre procesado', $f['cierre'] === null ? null : Subasta::iso((string) $f['cierre'])]],
            ],
            'ventasSinComprador' => [
                match ($f['clasificacion']) {
                    'INCONSISTENCIA' => 'La compra de la misma operación identifica al comprador: el vínculo de la venta se perdió.',
                    'SIN_EVIDENCIA' => 'La persona que ganó la subasta ya no existe cuando se procesó el cierre: no hay comprador que enlazar.',
                    default => 'No hay registro que identifique al comprador.',
                },
                [['Monto', $monto($f['monto'])], ['Canal', $f['canal'] === 'API_SUBASTAS' ? 'Subasta' : ($f['solicitudid'] !== null ? 'Solicitud' : 'Otro')],
                 ['Compra relacionada', $f['compraid'] === null ? 'No' : 'Sí']],
            ],
            'fincasSinDireccion' => [
                $f['personaid'] === null ? 'La persona dueña ya no existe.' : 'La finca no tiene dirección registrada.',
                [['Activa', $si($f['activo']) ? 'Sí' : 'No'], ['Subastas', (int) $f['subastas']]],
            ],
            'animalesSinEspecie' => [
                $f['personaid'] === null ? 'Registro anterior a los catálogos y sin dueño.' : 'Falta elegir la especie en el catálogo.',
                [['Tipo', $si($f['contipo']) ? 'Registrado' : 'Falta'], ['Raza del catálogo', $si($f['conraza']) ? 'Registrada' : 'Falta'],
                 ['Usado en publicaciones, ventas o subastas', (int) $f['usos'] > 0 ? 'Sí' : 'No']],
            ],
            'publicacionesSinFoto' => ['Sin foto.', [['Estado', $f['estado']]]],
            'ofertasActivasSinPrecio' => ['Precio a convenir.', [['Capacidad', $f['capacidad'] === null ? null : (int) $f['capacidad']]]],
        };
        return $base + ['causa' => $causa, 'detalles' => array_map(static fn (array $d): array => ['etiqueta' => $d[0], 'valor' => $d[1]], $detalles)];
    }

    private function cierreEnBitacora(int $subastaId): bool
    {
        $sentencia = $this->conexion->prepare("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraentidad = 'SUBASTA'
            AND tbbitacoraaccion = 'FINALIZAR' AND tbbitacoraregistroidentificacionnumero = :id");
        $sentencia->execute(['id' => (string) $subastaId]);
        return (int) $sentencia->fetchColumn() > 0;
    }
}
