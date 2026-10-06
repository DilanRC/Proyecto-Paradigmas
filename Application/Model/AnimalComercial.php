<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Capa de datos para animal, publicaciones, hechos compra/venta, funnel y
 * carrito. No expone endpoints ni algoritmo; solo registra hechos aprobados
 * con sentencias preparadas y consecutivos protegidos por NamedLock.
 */
final class AnimalComercial
{
    private const TIPOS_INTERACCION = ['ME_GUSTA', 'SEGUIR', 'CARRITO', 'COMPRA'];
    private const ACCIONES_INTERACCION = ['AGREGAR', 'RETIRAR'];
    private const LOCKS_ALTA = [
        'tbanimal' => 'tindercows_animal_alta',
        'tbanimalproduccionsalud' => 'tindercows_animal_observacion_alta',
        'tbanimalpublicacion' => 'tindercows_animal_publicacion_alta',
        'tbanimalpublicacionestadoperiodo' => 'tindercows_animal_publicacion_estado_alta',
        'tbanimalpublicacionanimal' => 'tindercows_animal_publicacion_animal_alta',
        'tbcompra' => 'tindercows_compra_alta',
        'tbventa' => 'tindercows_venta_alta',
        'tbanimalinteraccion' => 'tindercows_animal_interaccion_alta',
        'tbcarrito' => 'tindercows_carrito_alta',
        'tbcarritoestadoperiodo' => 'tindercows_carrito_estado_alta',
        'tbcarritoanimal' => 'tindercows_carrito_animal_alta',
    ];

    /** @var array<string,int> */
    private array $profundidad = [];

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoAlta(string $tabla, callable $operacion): mixed
    {
        $lock = self::LOCKS_ALTA[$tabla] ?? null;
        if ($lock === null) {
            throw new \InvalidArgumentException('Tabla comercial sin lock de alta registrado.');
        }

        NamedLock::acquire($this->conexion, $lock);
        $this->profundidad[$tabla] = ($this->profundidad[$tabla] ?? 0) + 1;
        try {
            return $operacion();
        } finally {
            $this->profundidad[$tabla]--;
            NamedLock::release($this->conexion, $lock);
        }
    }

    public const ESTADOS_ANIMAL = ['ACTIVO', 'PUBLICADO', 'VENDIDO', 'INACTIVO'];

    /**
     * $extra (P2-2, todo opcional): especieId, tipoId, razaId, fechaNacimiento (AAAA-MM-DD),
     * fechaNacimientoEstimada (bool), partos, estado (ESTADOS_ANIMAL) y productorId (dueño explícito).
     *
     * @param array<string,mixed> $extra
     */
    public function crearAnimal(?string $codigo, ?string $sexo, ?string $raza, string $origen,
        ?string $caracteristicas = null, array $extra = []): int
    {
        $this->exigirLock('tbanimal');
        $animalId = $this->siguienteId('tbanimal', 'tbanimalid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbanimal
             (tbanimalid, tbanimalidentificacion, tbanimalsexo, tbanimalraza,
              tbanimalcaracteristicas, tbanimalfecharegistroensistema,
              tbanimalorigenregistro, tbespecieid, tbanimaltipoid, tbrazaid,
              tbanimalfechanacimiento, tbanimalfechanacimientoestimada, tbanimalpartos,
              tbanimalestado, tbproductorid)
             VALUES (:id, :codigo, :sexo, :raza, :caracteristicas, :fechaRegistro, :origen,
              :especieId, :tipoId, :razaId, :fechaNacimiento, :estimada, :partos, :estado, :productorId)'
        );
        $estado = $extra['estado'] ?? null;
        if ($estado !== null && !in_array($estado, self::ESTADOS_ANIMAL, true)) {
            throw new \InvalidArgumentException('Estado de animal no aprobado.');
        }
        $estimada = $extra['fechaNacimientoEstimada'] ?? null;
        $sentencia->execute([
            'id' => $animalId,
            'codigo' => $codigo,
            'sexo' => $sexo,
            'raza' => $raza,
            'caracteristicas' => $caracteristicas,
            'fechaRegistro' => date('Y-m-d H:i:s'),
            'origen' => $origen,
            'especieId' => $extra['especieId'] ?? null,
            'tipoId' => $extra['tipoId'] ?? null,
            'razaId' => $extra['razaId'] ?? null,
            'fechaNacimiento' => $extra['fechaNacimiento'] ?? null,
            'estimada' => $estimada === null ? null : ($estimada ? 1 : 0),
            'partos' => $extra['partos'] ?? null,
            'estado' => $estado,
            'productorId' => $extra['productorId'] ?? null,
        ]);

        return $animalId;
    }

    /** ¿Hay otro animal vigente (no vendido ni inactivo) con este arete? El arete identifica a un solo animal. */
    public function existeAreteVigente(string $arete): bool
    {
        $sentencia = $this->conexion->prepare(
            "SELECT COUNT(*) FROM tbanimal
             WHERE tbanimalidentificacion = :arete AND COALESCE(tbanimalestado, 'ACTIVO') NOT IN ('VENDIDO', 'INACTIVO')"
        );
        $sentencia->execute(['arete' => $arete]);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /** Estado del animal (ACTIVO, PUBLICADO, VENDIDO, INACTIVO) para uno o varios animales. */
    public function marcarEstadoAnimales(array $animalIds, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS_ANIMAL, true)) {
            throw new \InvalidArgumentException('Estado de animal no aprobado.');
        }
        $actualizar = $this->conexion->prepare('UPDATE tbanimal SET tbanimalestado = :estado WHERE tbanimalid = :id');
        foreach ($animalIds as $animalId) {
            $actualizar->execute(['estado' => $estado, 'id' => (int) $animalId]);
        }
    }

    /** Lote (DEC-ANIMAL-001): enlaza los N animales de una publicación. Una publicación de un animal no lleva filas. */
    public function enlazarAnimalesPublicacion(int $publicacionId, array $animalIds): void
    {
        $this->exigirLock('tbanimalpublicacionanimal');
        $insertar = $this->conexion->prepare(
            'INSERT INTO tbanimalpublicacionanimal (tbanimalpublicacionanimalid, tbanimalpublicacionid, tbanimalid)
             VALUES (:id, :publicacionId, :animalId)'
        );
        foreach ($animalIds as $animalId) {
            $insertar->execute([
                'id' => $this->siguienteId('tbanimalpublicacionanimal', 'tbanimalpublicacionanimalid'),
                'publicacionId' => $publicacionId,
                'animalId' => (int) $animalId,
            ]);
        }
    }

    /**
     * Ids de los animales de una publicación: el principal solo, o todos los del lote.
     *
     * @return array<int,int>
     */
    public function animalesDePublicacion(int $publicacionId): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbanimalid FROM tbanimalpublicacionanimal
             WHERE tbanimalpublicacionid = :id ORDER BY tbanimalpublicacionanimalid'
        );
        $sentencia->execute(['id' => $publicacionId]);
        $ids = array_map('intval', $sentencia->fetchAll(PDO::FETCH_COLUMN));
        if ($ids !== []) {
            return $ids;
        }
        $principal = $this->conexion->prepare('SELECT tbanimalid FROM tbanimalpublicacion WHERE tbanimalpublicacionid = :id');
        $principal->execute(['id' => $publicacionId]);
        $animalId = $principal->fetchColumn();

        return $animalId === false ? [] : [(int) $animalId];
    }

    public function registrarObservacion(int $animalId, array $datos): int
    {
        $this->exigirLock('tbanimalproduccionsalud');
        $observacionId = $this->siguienteId('tbanimalproduccionsalud', 'tbanimalproduccionsaludid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbanimalproduccionsalud
             (tbanimalproduccionsaludid, tbanimalid, tbanimalproduccionsaludfecha,
              tbanimalproduccionsaludorigen, tbanimalproduccionsaludcontexto,
              tbanimalproduccionsaludedadmeses, tbanimalproduccionsaludpeso,
              tbanimalproduccionsaludproposito, tbanimalproduccionsaludestadoreproductivo,
              tbanimalproduccionsaludpartos, tbanimalproduccionsaludlitrosleche,
              tbanimalproduccionsaludproduccion, tbanimalproduccionsaludsalud)
             VALUES (:id, :animalId, :fecha, :origen, :contexto, :edadMeses,
              :peso, :proposito, :estadoReproductivo, :partos, :litrosLeche,
              :produccion, :salud)'
        );
        $sentencia->execute([
            'id' => $observacionId,
            'animalId' => $animalId,
            'fecha' => $datos['fecha'] ?? date('Y-m-d H:i:s'),
            'origen' => $datos['origen'],
            'contexto' => $datos['contexto'] ?? null,
            'edadMeses' => $datos['edadMeses'] ?? null,
            'peso' => $datos['peso'] ?? null,
            'proposito' => $datos['proposito'] ?? null,
            'estadoReproductivo' => $datos['estadoReproductivo'] ?? null,
            'partos' => $datos['partos'] ?? null,
            'litrosLeche' => $datos['litrosLeche'] ?? null,
            'produccion' => $this->jsonNullable($datos['produccion'] ?? null),
            'salud' => $this->jsonNullable($datos['salud'] ?? null),
        ]);

        return $observacionId;
    }

    public function publicarAnimal(int $animalId, int $productorVendedorId, int $fincaId, array $datos): int
    {
        $this->exigirLock('tbanimalpublicacion');
        $publicacionId = $this->siguienteId('tbanimalpublicacion', 'tbanimalpublicacionid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbanimalpublicacion
             (tbanimalpublicacionid, tbanimalid, tbproductorvendedorid, tbfincaid,
              tbanimalpublicacionfecha, tbanimalpublicacionprecio,
              tbanimalpublicaciontitulo, tbanimalpublicaciondescripcion,
              tbanimalpublicacionimagenurl, tbanimalpublicacionorigen)
             VALUES (:id, :animalId, :vendedorId, :fincaId, :fecha, :precio,
              :titulo, :descripcion, :imagenUrl, :origen)'
        );
        $sentencia->execute([
            'id' => $publicacionId,
            'animalId' => $animalId,
            'vendedorId' => $productorVendedorId,
            'fincaId' => $fincaId,
            'fecha' => $datos['fecha'] ?? date('Y-m-d H:i:s'),
            'precio' => $datos['precio'] ?? null,
            'titulo' => $datos['titulo'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'imagenUrl' => $datos['imagenUrl'] ?? null,
            'origen' => $datos['origen'],
        ]);
        $this->abrirEstadoPeriodo(
            'tbanimalpublicacionestadoperiodo',
            'tbanimalpublicacionid',
            $publicacionId,
            $datos['estado'],
            $datos['origen']
        );

        return $publicacionId;
    }

    /**
     * El comprador es un Productor ($productorCompradorId) o un Comprador normal
     * ($datos['compradorId']); el método de pago es opcional (DEC-COMPRA-001).
     */
    public function registrarCompra(int $animalId, ?int $productorCompradorId, ?int $fincaOrigenId, array $datos): int
    {
        $this->exigirLock('tbcompra');
        $compraId = $this->siguienteId('tbcompra', 'tbcompraid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbcompra
             (tbcompraid, tbanimalid, tbproductorcompradorid, tbcompradorid, tbfincaorigenid,
              tbcomprafecha, tbcomprahora, tbcompralugar, tbcompraprecio,
              tbpagometodoid, tbcompraorigen)
             VALUES (:id, :animalId, :productorCompradorId, :compradorId, :fincaOrigenId, :fecha,
              :hora, :lugar, :precio, :pagoMetodoId, :origen)'
        );
        $sentencia->execute([
            'id' => $compraId,
            'animalId' => $animalId,
            'productorCompradorId' => $productorCompradorId,
            'compradorId' => $datos['compradorId'] ?? null,
            'fincaOrigenId' => $fincaOrigenId,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'lugar' => $datos['lugar'] ?? null,
            'precio' => $datos['precio'],
            'pagoMetodoId' => $datos['pagoMetodoId'] ?? null,
            'origen' => $datos['origen'],
        ]);

        return $compraId;
    }

    /** Igual que registrarCompra: `compradorId` y `solicitudId` en $datos enlazan al Comprador y a su solicitud. */
    public function registrarVenta(int $animalId, int $productorVendedorId, ?int $productorCompradorId,
        ?int $fincaId, ?int $compraId, array $datos): int
    {
        $this->exigirLock('tbventa');
        $ventaId = $this->siguienteId('tbventa', 'tbventaid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbventa
             (tbventaid, tbanimalid, tbproductorvendedorid, tbproductorcompradorid, tbcompradorid,
              tbfincaid, tbcompraid, tbcomprasolicitudid, tbventafecha, tbventahora, tbventalugar,
              tbventadireccionid, tbventaproposito, tbventaprecio,
              tbpagometodoid, tbventaedadmeses, tbventapeso,
              tbventarazasnapshot, tbventaorigen)
             VALUES (:id, :animalId, :vendedorId, :productorCompradorId, :compradorId, :fincaId,
              :compraId, :solicitudId, :fecha, :hora, :lugar, :direccionId, :proposito,
              :precio, :pagoMetodoId, :edadMeses, :peso, :razaSnapshot, :origen)'
        );
        $sentencia->execute([
            'id' => $ventaId,
            'animalId' => $animalId,
            'vendedorId' => $productorVendedorId,
            'productorCompradorId' => $productorCompradorId,
            'compradorId' => $datos['compradorId'] ?? null,
            'fincaId' => $fincaId,
            'compraId' => $compraId,
            'solicitudId' => $datos['solicitudId'] ?? null,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'lugar' => $datos['lugar'] ?? null,
            'direccionId' => $datos['direccionId'] ?? null,
            'proposito' => $datos['proposito'] ?? null,
            'precio' => $datos['precio'],
            'pagoMetodoId' => $datos['pagoMetodoId'] ?? null,
            'edadMeses' => $datos['edadMeses'] ?? null,
            'peso' => $datos['peso'] ?? null,
            'razaSnapshot' => $datos['razaSnapshot'] ?? null,
            'origen' => $datos['origen'],
        ]);

        return $ventaId;
    }

    public function registrarInteraccion(int $productorId, int $animalId, string $tipo, string $accion,
        ?string $origen): int
    {
        $this->exigirLock('tbanimalinteraccion');
        $tipo = $this->normalizar($tipo, self::TIPOS_INTERACCION, 'Tipo de interacción no aprobado.');
        $accion = $this->normalizar($accion, self::ACCIONES_INTERACCION, 'Acción de interacción no aprobada.');
        $interaccionId = $this->siguienteId('tbanimalinteraccion', 'tbanimalinteraccionid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbanimalinteraccion
             (tbanimalinteraccionid, tbproductorid, tbanimalid,
              tbanimalinteracciontipo, tbanimalinteraccionaccion,
              tbanimalinteraccionfecha, tbanimalinteraccionorigen)
             VALUES (:id, :productorId, :animalId, :tipo, :accion, :fecha, :origen)'
        );
        $sentencia->execute([
            'id' => $interaccionId,
            'productorId' => $productorId,
            'animalId' => $animalId,
            'tipo' => $tipo,
            'accion' => $accion,
            'fecha' => date('Y-m-d H:i:s'),
            'origen' => $origen,
        ]);

        return $interaccionId;
    }

    public function crearCarrito(int $productorId, string $estado): int
    {
        $this->exigirLock('tbcarrito');
        $carritoId = $this->siguienteId('tbcarrito', 'tbcarritoid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbcarrito
             (tbcarritoid, tbproductorid, tbcarritofechacreacion)
             VALUES (:id, :productorId, :fechaCreacion)'
        );
        $sentencia->execute([
            'id' => $carritoId,
            'productorId' => $productorId,
            'fechaCreacion' => date('Y-m-d H:i:s'),
        ]);
        $this->abrirEstadoPeriodo('tbcarritoestadoperiodo', 'tbcarritoid', $carritoId, $estado, 'CARRITO_ALTA');

        return $carritoId;
    }

    public function registrarCarritoAnimal(int $carritoId, int $animalId, string $accion, ?string $origen): int
    {
        $this->exigirLock('tbcarritoanimal');
        $accion = $this->normalizar($accion, self::ACCIONES_INTERACCION, 'Acción de carrito no aprobada.');
        $carritoAnimalId = $this->siguienteId('tbcarritoanimal', 'tbcarritoanimalid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbcarritoanimal
             (tbcarritoanimalid, tbcarritoid, tbanimalid, tbcarritoanimalaccion,
              tbcarritoanimalfecha, tbcarritoanimalorigen)
             VALUES (:id, :carritoId, :animalId, :accion, :fecha, :origen)'
        );
        $sentencia->execute([
            'id' => $carritoAnimalId,
            'carritoId' => $carritoId,
            'animalId' => $animalId,
            'accion' => $accion,
            'fecha' => date('Y-m-d H:i:s'),
            'origen' => $origen,
        ]);

        return $carritoAnimalId;
    }

    /**
     * Lista publicaciones para la vista Explorar.
     *
     * Tres decisiones que el esquema impone y que no son negociables aquí:
     *
     * 1. El estado vigente es el periodo abierto (fechafin NULL), no una
     *    columna mutable. Filtrar por el periodo abierto es la única lectura
     *    correcta del estado actual.
     * 2. Edad, peso y propósito son observaciones históricas: hay N filas por
     *    animal. Se toma la más reciente por animal con ROW_NUMBER, y el
     *    desempate por id descendente evita que dos observaciones con la misma
     *    fecha devuelvan una fila distinta en cada ejecución.
     * 3. Los tres campos salen de la MISMA observación. Con subconsultas
     *    escalares separadas podrían venir de filas distintas y describir un
     *    animal que no existe.
     *
     * La validación de los argumentos es del controlador, como en
     * Productor::listar(): aquí llegan ya normalizados.
     *
     * @return array{publicaciones:array<int,array<string,mixed>>,total:int}
     */
    /** Marca vigente de ME_INTERESA: la última acción del par persona/publicación es REGISTRAR. */
    private const SQL_ME_INTERESA = "EXISTS (SELECT 1 FROM tbanimalpublicacioninteraccion i
        WHERE i.tbpersonaid = %s AND i.tbanimalpublicacionid = p.tbanimalpublicacionid
          AND i.tbanimalpublicacioninteracciontipo = 'ME_INTERESA'
          AND i.tbanimalpublicacioninteraccionaccion = 'REGISTRAR'
          AND i.tbanimalpublicacioninteraccionid = (
              SELECT MAX(j.tbanimalpublicacioninteraccionid) FROM tbanimalpublicacioninteraccion j
              WHERE j.tbpersonaid = i.tbpersonaid
                AND j.tbanimalpublicacionid = i.tbanimalpublicacionid
                AND j.tbanimalpublicacioninteracciontipo = 'ME_INTERESA'))";

    /**
     * $personaId agrega meInteresa a cada fila; con $soloMarcadas devuelve solo
     * las publicaciones que esa persona tiene en "Me interesa" (sea cual sea su estado).
     * $excluirVendedorId deja fuera las publicaciones de ese vendedor (Explorar no muestra las propias).
     */
    public function listarPublicaciones(string $busqueda, string $estado, int $pagina, int $tamano,
        ?int $productorVendedorId = null, ?int $personaId = null, bool $soloMarcadas = false,
        ?int $excluirVendedorId = null): array
    {
        $condiciones = ['ep.tbanimalpublicacionestadoperiodofechafin IS NULL'];
        $parametros = [];
        if ($productorVendedorId !== null) {
            $condiciones[] = 'p.tbproductorvendedorid = :productorVendedorId';
            $parametros[':productorVendedorId'] = $productorVendedorId;
        }
        if ($excluirVendedorId !== null) {
            $condiciones[] = 'p.tbproductorvendedorid <> :excluirVendedorId';
            $parametros[':excluirVendedorId'] = $excluirVendedorId;
        }
        if ($estado !== 'TODOS') {
            $condiciones[] = 'ep.tbanimalpublicacionestadoperiodoestado = :estado';
            $parametros[':estado'] = $estado;
        }
        if ($soloMarcadas && $personaId !== null) {
            $condiciones[] = sprintf(self::SQL_ME_INTERESA, ':guardadaPersona');
            $parametros[':guardadaPersona'] = $personaId;
        }
        if ($busqueda !== '') {
            $condiciones[] = '(p.tbanimalpublicaciontitulo LIKE :busquedaTitulo'
                . ' OR a.tbanimalraza LIKE :busquedaRaza'
                . ' OR pe.tbpersonanombre LIKE :busquedaVendedor'
                . ' OR f.tbfincanombre LIKE :busquedaFinca'
                . ' OR d.tbdireccioncanton LIKE :busquedaCanton'
                . ' OR d.tbdireccionprovincia LIKE :busquedaProvincia)';
            foreach (['Titulo', 'Raza', 'Vendedor', 'Finca', 'Canton', 'Provincia'] as $campo) {
                $parametros[":busqueda{$campo}"] = "%{$busqueda}%";
            }
        }
        $where = 'WHERE ' . implode(' AND ', $condiciones);

        $desde = <<<SQL
            FROM tbanimalpublicacion p
            INNER JOIN tbanimal a ON a.tbanimalid = p.tbanimalid
            LEFT JOIN tbespecie es ON es.tbespecieid = a.tbespecieid
            LEFT JOIN tbanimaltipo ti ON ti.tbanimaltipoid = a.tbanimaltipoid
            INNER JOIN tbanimalpublicacionestadoperiodo ep
                ON ep.tbanimalpublicacionid = p.tbanimalpublicacionid
            INNER JOIN tbproductor pr ON pr.tbproductorid = p.tbproductorvendedorid
            INNER JOIN tbpersona pe ON pe.tbpersonaid = pr.tbpersonaid
            INNER JOIN tbfinca f ON f.tbfincaid = p.tbfincaid
            LEFT JOIN tbfincadireccion fd ON fd.tbfincaid = f.tbfincaid
            LEFT JOIN tbdireccion d ON d.tbdireccionid = fd.tbdireccionid
            LEFT JOIN (
                SELECT s.tbanimalid,
                       s.tbanimalproduccionsaludedadmeses AS edadmeses,
                       s.tbanimalproduccionsaludpeso AS peso,
                       s.tbanimalproduccionsaludproposito AS proposito,
                       s.tbanimalproduccionsaludestadoreproductivo AS estadoreproductivo,
                       ROW_NUMBER() OVER (
                           PARTITION BY s.tbanimalid
                           ORDER BY s.tbanimalproduccionsaludfecha DESC,
                                    s.tbanimalproduccionsaludid DESC
                       ) AS fila
                FROM tbanimalproduccionsalud s
            ) obs ON obs.tbanimalid = a.tbanimalid AND obs.fila = 1
            {$where}
            SQL;

        $conteo = $this->conexion->prepare("SELECT COUNT(*) {$desde}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $columnaMarca = $personaId === null ? '' : ', CASE WHEN '
            . sprintf(self::SQL_ME_INTERESA, ':marcaPersona') . ' THEN 1 ELSE 0 END AS meinteresa';
        $sentencia = $this->conexion->prepare(
            "SELECT p.tbanimalpublicacionid AS publicacionid,
                    p.tbanimalpublicaciontitulo AS titulo,
                    p.tbanimalpublicaciondescripcion AS descripcion,
                    p.tbanimalpublicacionimagenurl AS imagenurl,
                    p.tbanimalpublicacionprecio AS precio,
                    p.tbanimalpublicacionfecha AS fecha,
                    ep.tbanimalpublicacionestadoperiodoestado AS estado,
                    a.tbanimalid AS animalid,
                    a.tbanimalidentificacion AS animalidentificacion,
                    a.tbanimalsexo AS sexo,
                    a.tbanimalraza AS raza,
                    a.tbanimalcaracteristicas AS caracteristicas,
                    a.tbespecieid AS especieid,
                    es.tbespecienombre AS especienombre,
                    a.tbanimaltipoid AS tipoid,
                    ti.tbanimaltiponombre AS tiponombre,
                    a.tbrazaid AS razaid,
                    a.tbanimalfechanacimiento AS fechanacimiento,
                    a.tbanimalfechanacimientoestimada AS fechanacimientoestimada,
                    a.tbanimalpartos AS partos,
                    a.tbanimalestado AS animalestado,
                    (SELECT COUNT(*) FROM tbanimalpublicacionanimal l
                      WHERE l.tbanimalpublicacionid = p.tbanimalpublicacionid) AS lotecantidad,
                    obs.edadmeses AS edadmeses,
                    obs.peso AS peso,
                    obs.proposito AS proposito,
                    obs.estadoreproductivo AS estadoreproductivo,
                    pe.tbpersonanombre AS vendedornombre,
                    f.tbfincanombre AS fincanombre,
                    d.tbdireccionprovincia AS provincia,
                    d.tbdireccioncanton AS canton,
                    d.tbdirecciondistrito AS distrito,
                    d.tbdireccionpueblo AS pueblo{$columnaMarca}
             {$desde}
             ORDER BY p.tbanimalpublicacionfecha DESC, p.tbanimalpublicacionid DESC
             LIMIT :limite OFFSET :desplazamiento"
        );
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        if ($personaId !== null) {
            $sentencia->bindValue(':marcaPersona', $personaId, PDO::PARAM_INT);
        }
        $sentencia->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue(':desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'publicaciones' => array_map(
                static fn (array $fila): array => self::mapearPublicacion($fila),
                $sentencia->fetchAll()
            ),
            'total' => $total,
        ];
    }

    public const VACUNAS_POR_PUBLICACION = 8;

    /**
     * Agrega `vacunas` a cada publicación (P2-3), de forma pública y acotada: solo vacuna, fecha de la última
     * aplicación y próxima dosis (nunca lote, quién la aplicó ni observaciones), a lo sumo 8 por publicación. En un
     * lote suma las vacunas de todos sus animales (una fila por vacuna, con la fecha más reciente).
     *
     * @param array<int,array<string,mixed>> $publicaciones
     * @return array<int,array<string,mixed>>
     */
    public function adjuntarVacunas(array $publicaciones): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $p): int => (int) $p['publicacionId'], $publicaciones)));
        if ($ids === []) {
            return $publicaciones;
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sentencia = $this->conexion->prepare(
            "SELECT p.tbanimalpublicacionid AS publicacionid, c.tbvacunanombre AS vacuna,
                    MAX(v.tbanimalvacunacionfecha) AS fecha, MAX(v.tbanimalvacunacionproximadosis) AS proxima
             FROM tbanimalpublicacion p
             INNER JOIN tbanimalvacunacion v
                ON v.tbanimalid = p.tbanimalid
                OR v.tbanimalid IN (SELECT l.tbanimalid FROM tbanimalpublicacionanimal l
                                    WHERE l.tbanimalpublicacionid = p.tbanimalpublicacionid)
             INNER JOIN tbvacuna c ON c.tbvacunaid = v.tbvacunaid
             WHERE p.tbanimalpublicacionid IN ({$marcas})
             GROUP BY p.tbanimalpublicacionid, c.tbvacunaid, c.tbvacunanombre
             ORDER BY p.tbanimalpublicacionid, fecha DESC, c.tbvacunaid"
        );
        $sentencia->execute($ids);
        $porPublicacion = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $lista = &$porPublicacion[(int) $fila['publicacionid']];
            if (count($lista ?? []) < self::VACUNAS_POR_PUBLICACION) {
                $lista[] = ['vacuna' => $fila['vacuna'], 'fecha' => $fila['fecha'], 'proximaDosis' => $fila['proxima']];
            }
            unset($lista);
        }

        return array_map(static function (array $p) use ($porPublicacion): array {
            $p['vacunas'] = $porPublicacion[(int) $p['publicacionId']] ?? [];
            return $p;
        }, $publicaciones);
    }

    private const COLUMNAS_PUBLICACION_EDITABLES = [
        'titulo' => 'tbanimalpublicaciontitulo',
        'descripcion' => 'tbanimalpublicaciondescripcion',
        'precio' => 'tbanimalpublicacionprecio',
        'imagenUrl' => 'tbanimalpublicacionimagenurl',
    ];

    /** Publicación del vendedor con su estado vigente, o null si no existe o es de otro. */
    public function buscarPublicacionPropia(int $publicacionId, int $productorVendedorId): ?array
    {
        return $this->buscarPublicacion($publicacionId, $productorVendedorId);
    }

    /** Igual, pero con $productorVendedorId = null busca de cualquier vendedor (moderación). */
    public function buscarPublicacion(int $publicacionId, ?int $productorVendedorId = null): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT p.tbanimalpublicacionid AS publicacionid,
                    p.tbanimalpublicaciontitulo AS titulo,
                    p.tbanimalpublicaciondescripcion AS descripcion,
                    p.tbanimalpublicacionprecio AS precio,
                    p.tbanimalpublicacionimagenurl AS imagenurl,
                    ep.tbanimalpublicacionestadoperiodoestado AS estado
             FROM tbanimalpublicacion p
             INNER JOIN tbanimalpublicacionestadoperiodo ep
                ON ep.tbanimalpublicacionid = p.tbanimalpublicacionid
               AND ep.tbanimalpublicacionestadoperiodofechafin IS NULL
             WHERE p.tbanimalpublicacionid = :id'
            . ($productorVendedorId === null ? '' : ' AND p.tbproductorvendedorid = :vendedorId')
        );
        $sentencia->execute(['id' => $publicacionId]
            + ($productorVendedorId === null ? [] : ['vendedorId' => $productorVendedorId]));
        $fila = $sentencia->fetch();
        if ($fila === false) {
            return null;
        }

        return [
            'publicacionId' => (int) $fila['publicacionid'],
            'titulo' => $fila['titulo'],
            'descripcion' => $fila['descripcion'],
            'precio' => $fila['precio'] === null ? null : (float) $fila['precio'],
            'imagenUrl' => $fila['imagenurl'],
            'estado' => $fila['estado'],
        ];
    }

    /** @param array<string,mixed> $campos claves de COLUMNAS_PUBLICACION_EDITABLES */
    public function actualizarPublicacion(int $publicacionId, array $campos): void
    {
        $asignaciones = [];
        $parametros = ['id' => $publicacionId];
        foreach (self::COLUMNAS_PUBLICACION_EDITABLES as $clave => $columna) {
            if (array_key_exists($clave, $campos)) {
                $asignaciones[] = "{$columna} = :{$clave}";
                $parametros[$clave] = $campos[$clave];
            }
        }
        if ($asignaciones === []) {
            return;
        }
        $this->conexion->prepare(
            'UPDATE tbanimalpublicacion SET ' . implode(', ', $asignaciones)
            . ' WHERE tbanimalpublicacionid = :id'
        )->execute($parametros);
    }

    /** Cierra el periodo vigente y abre otro con el estado nuevo (la historia se conserva). */
    public function cambiarEstadoPublicacion(int $publicacionId, string $estado, ?string $motivo,
        string $origen): void
    {
        $this->ejecutarConBloqueoAlta('tbanimalpublicacionestadoperiodo', function () use ($publicacionId, $estado, $motivo, $origen): void {
            $this->conexion->prepare(
                'UPDATE tbanimalpublicacionestadoperiodo
                 SET tbanimalpublicacionestadoperiodofechafin = :fin
                 WHERE tbanimalpublicacionid = :id AND tbanimalpublicacionestadoperiodofechafin IS NULL'
            )->execute(['fin' => date('Y-m-d H:i:s'), 'id' => $publicacionId]);
            $this->insertarEstadoPeriodo(
                'tbanimalpublicacionestadoperiodo', 'tbanimalpublicacionid',
                $publicacionId, $estado, $origen, $motivo
            );
        });
    }

    /** Normaliza tipos: PDO devuelve DECIMAL e INT como texto en MySQL. */
    private static function mapearPublicacion(array $fila): array
    {
        $publicacion = [
            'publicacionId' => (int) $fila['publicacionid'],
            'animalId' => (int) $fila['animalid'],
            'titulo' => $fila['titulo'],
            'descripcion' => $fila['descripcion'],
            'imagenUrl' => $fila['imagenurl'] ?? null,
            'precio' => $fila['precio'] === null ? null : (float) $fila['precio'],
            'fecha' => $fila['fecha'],
            'estado' => $fila['estado'],
            // Lote (DEC-ANIMAL-001): 1 = un solo animal; N > 1 = lote de N.
            'loteCantidad' => max(1, (int) $fila['lotecantidad']),
            'animal' => [
                'identificacion' => $fila['animalidentificacion'],
                'sexo' => $fila['sexo'],
                'raza' => $fila['raza'],
                'caracteristicas' => $fila['caracteristicas'],
                'especieId' => $fila['especieid'] === null ? null : (int) $fila['especieid'],
                'especie' => $fila['especienombre'],
                'tipoId' => $fila['tipoid'] === null ? null : (int) $fila['tipoid'],
                'tipo' => $fila['tiponombre'],
                'razaId' => $fila['razaid'] === null ? null : (int) $fila['razaid'],
                'fechaNacimiento' => $fila['fechanacimiento'],
                'fechaNacimientoEstimada' => $fila['fechanacimientoestimada'] === null
                    ? null : (int) $fila['fechanacimientoestimada'] === 1,
                'partos' => $fila['partos'] === null ? null : (int) $fila['partos'],
                'estado' => $fila['animalestado'],
                'edadMeses' => $fila['edadmeses'] === null ? null : (int) $fila['edadmeses'],
                'peso' => $fila['peso'] === null ? null : (float) $fila['peso'],
                'proposito' => $fila['proposito'],
                'estadoReproductivo' => $fila['estadoreproductivo'],
            ],
            'vendedor' => ['nombre' => $fila['vendedornombre']],
            'finca' => ['nombre' => $fila['fincanombre']],
            'direccion' => [
                'provincia' => $fila['provincia'],
                'canton' => $fila['canton'],
                'distrito' => $fila['distrito'],
                'pueblo' => $fila['pueblo'],
            ],
        ];
        if (array_key_exists('meinteresa', $fila)) {
            $publicacion['meInteresa'] = (int) $fila['meinteresa'] === 1;
        }

        return $publicacion;
    }

    /**
     * Abre el primer periodo de estado de una entidad cuyo estado dejó de ser
     * columna mutable. Cerrar y reabrir periodos es responsabilidad de Backend;
     * aquí solo se registra el estado inicial sin perder historia.
     */
    private function abrirEstadoPeriodo(string $tabla, string $columnaEntidad, int $entidadId,
        string $estado, string $origen): int
    {
        return $this->ejecutarConBloqueoAlta($tabla, fn (): int => $this->insertarEstadoPeriodo(
            $tabla, $columnaEntidad, $entidadId, $estado, $origen
        ));
    }

    /** Requiere el lock de alta de $tabla (lo toma abrirEstadoPeriodo o el llamador). */
    private function insertarEstadoPeriodo(string $tabla, string $columnaEntidad, int $entidadId,
        string $estado, string $origen, ?string $motivo = null): int
    {
        $this->exigirLock($tabla);
        $periodoId = $this->siguienteId($tabla, "{$tabla}id");
        $sentencia = $this->conexion->prepare(
            "INSERT INTO {$tabla}
             ({$tabla}id, {$columnaEntidad}, {$tabla}estado,
              {$tabla}fechainicio, {$tabla}fechafin, {$tabla}motivo, {$tabla}origen)
             VALUES (:id, :entidadId, :estado, :fechaInicio, NULL, :motivo, :origen)"
        );
        $sentencia->execute([
            'id' => $periodoId,
            'entidadId' => $entidadId,
            'estado' => strtoupper(trim($estado)),
            'fechaInicio' => date('Y-m-d H:i:s'),
            'motivo' => $motivo,
            'origen' => $origen,
        ]);

        return $periodoId;
    }

    private function exigirLock(string $tabla): void
    {
        if (($this->profundidad[$tabla] ?? 0) <= 0) {
            throw new \LogicException("La conexión debe poseer el lock de alta de {$tabla}.");
        }
        if (!$this->conexion->inTransaction()) {
            throw new \LogicException("La escritura de {$tabla} debe ejecutarse dentro de una transacción.");
        }
    }

    private function siguienteId(string $tabla, string $columna): int
    {
        $sentencia = $this->conexion->prepare(
            "SELECT {$columna} FROM {$tabla} ORDER BY {$columna} DESC LIMIT 1 FOR UPDATE"
        );
        $sentencia->execute();
        $maximo = $sentencia->fetchColumn();

        return $maximo === false ? 1 : ((int) $maximo) + 1;
    }

    private function jsonNullable(mixed $valor): ?string
    {
        if ($valor === null) return null;

        return json_encode($valor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function normalizar(string $valor, array $permitidos, string $mensaje): string
    {
        $normalizado = strtoupper(trim($valor));
        if (!in_array($normalizado, $permitidos, true)) {
            throw new \InvalidArgumentException($mensaje);
        }

        return $normalizado;
    }
}
