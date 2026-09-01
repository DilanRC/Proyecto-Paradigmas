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

    public function crearAnimal(?string $codigo, ?string $sexo, ?string $raza, string $origen,
        ?string $caracteristicas = null): int
    {
        $this->exigirLock('tbanimal');
        $animalId = $this->siguienteId('tbanimal', 'tbanimalid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbanimal
             (tbanimalid, tbanimalidentificacion, tbanimalsexo, tbanimalraza,
              tbanimalcaracteristicas, tbanimalfecharegistroensistema,
              tbanimalorigenregistro)
             VALUES (:id, :codigo, :sexo, :raza, :caracteristicas, :fechaRegistro, :origen)'
        );
        $sentencia->execute([
            'id' => $animalId,
            'codigo' => $codigo,
            'sexo' => $sexo,
            'raza' => $raza,
            'caracteristicas' => $caracteristicas,
            'fechaRegistro' => date('Y-m-d H:i:s'),
            'origen' => $origen,
        ]);

        return $animalId;
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
              tbanimalpublicacionorigen)
             VALUES (:id, :animalId, :vendedorId, :fincaId, :fecha, :precio,
              :titulo, :descripcion, :origen)'
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

    public function registrarCompra(int $animalId, int $productorCompradorId, ?int $fincaOrigenId, array $datos): int
    {
        $this->exigirLock('tbcompra');
        $compraId = $this->siguienteId('tbcompra', 'tbcompraid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbcompra
             (tbcompraid, tbanimalid, tbproductorcompradorid, tbfincaorigenid,
              tbcomprafecha, tbcomprahora, tbcompralugar, tbcompraprecio,
              tbpagometodoid, tbcompraorigen)
             VALUES (:id, :animalId, :compradorId, :fincaOrigenId, :fecha,
              :hora, :lugar, :precio, :pagoMetodoId, :origen)'
        );
        $sentencia->execute([
            'id' => $compraId,
            'animalId' => $animalId,
            'compradorId' => $productorCompradorId,
            'fincaOrigenId' => $fincaOrigenId,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'lugar' => $datos['lugar'] ?? null,
            'precio' => $datos['precio'],
            'pagoMetodoId' => $datos['pagoMetodoId'],
            'origen' => $datos['origen'],
        ]);

        return $compraId;
    }

    public function registrarVenta(int $animalId, int $productorVendedorId, int $productorCompradorId,
        ?int $fincaId, ?int $compraId, array $datos): int
    {
        $this->exigirLock('tbventa');
        $ventaId = $this->siguienteId('tbventa', 'tbventaid');
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbventa
             (tbventaid, tbanimalid, tbproductorvendedorid, tbproductorcompradorid,
              tbfincaid, tbcompraid, tbventafecha, tbventahora, tbventalugar,
              tbventadireccionid, tbventaproposito, tbventaprecio,
              tbpagometodoid, tbventaedadmeses, tbventapeso,
              tbventarazasnapshot, tbventaorigen)
             VALUES (:id, :animalId, :vendedorId, :compradorId, :fincaId,
              :compraId, :fecha, :hora, :lugar, :direccionId, :proposito,
              :precio, :pagoMetodoId, :edadMeses, :peso, :razaSnapshot, :origen)'
        );
        $sentencia->execute([
            'id' => $ventaId,
            'animalId' => $animalId,
            'vendedorId' => $productorVendedorId,
            'compradorId' => $productorCompradorId,
            'fincaId' => $fincaId,
            'compraId' => $compraId,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'lugar' => $datos['lugar'] ?? null,
            'direccionId' => $datos['direccionId'] ?? null,
            'proposito' => $datos['proposito'] ?? null,
            'precio' => $datos['precio'],
            'pagoMetodoId' => $datos['pagoMetodoId'],
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
     * Abre el primer periodo de estado de una entidad cuyo estado dejó de ser
     * columna mutable. Cerrar y reabrir periodos es responsabilidad de Backend;
     * aquí solo se registra el estado inicial sin perder historia.
     */
    private function abrirEstadoPeriodo(string $tabla, string $columnaEntidad, int $entidadId,
        string $estado, string $origen): int
    {
        return $this->ejecutarConBloqueoAlta($tabla, function () use ($tabla, $columnaEntidad, $entidadId, $estado, $origen): int {
            $this->exigirLock($tabla);
            $periodoId = $this->siguienteId($tabla, "{$tabla}id");
            $sentencia = $this->conexion->prepare(
                "INSERT INTO {$tabla}
                 ({$tabla}id, {$columnaEntidad}, {$tabla}estado,
                  {$tabla}fechainicio, {$tabla}fechafin, {$tabla}motivo, {$tabla}origen)
                 VALUES (:id, :entidadId, :estado, :fechaInicio, NULL, NULL, :origen)"
            );
            $sentencia->execute([
                'id' => $periodoId,
                'entidadId' => $entidadId,
                'estado' => strtoupper(trim($estado)),
                'fechaInicio' => date('Y-m-d H:i:s'),
                'origen' => $origen,
            ]);

            return $periodoId;
        });
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
