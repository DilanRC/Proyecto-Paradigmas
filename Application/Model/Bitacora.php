<?php

declare(strict_types=1);

namespace Application\Model;

use Application\Auth\ActorContext;
use JsonException;
use PDO;

final class Bitacora
{
    private readonly ActorContext $actor;
    private int $profundidadBloqueoAlta = 0;

    public function __construct(private readonly PDO $conexion, ?ActorContext $actor = null)
    {
        $this->actor = $actor ?? ActorContext::noAutenticado();
    }

    /**
     * Mantiene el lock del consecutivo durante todo el callback. Los procesos
     * compuestos deben envolver aquí la transacción completa para impedir que
     * otra conexión calcule el mismo MAX+1 antes del COMMIT.
     */
    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        $exterior = $this->profundidadBloqueoAlta === 0;
        if ($exterior) {
            NamedLock::acquire($this->conexion, 'tindercows_bitacora_alta');
        }
        $this->profundidadBloqueoAlta++;
        try {
            return $operacion();
        } finally {
            $this->profundidadBloqueoAlta--;
            if ($exterior) {
                NamedLock::release($this->conexion, 'tindercows_bitacora_alta');
            }
        }
    }

    /** @throws JsonException */
    public function registrar(
        string $accion,
        string $identificacionNumero,
        ?array $anteriores,
        ?array $nuevos,
        string $solicitudId,
        string $entidad = 'PRODUCTOR',
        string $origen = 'API_PRODUCTORES',
    ): void {
        if ($this->profundidadBloqueoAlta > 0) {
            $this->insertar($accion, $identificacionNumero, $anteriores, $nuevos, $solicitudId, $entidad, $origen);
            return;
        }

        $this->ejecutarConBloqueoAlta(
            fn (): null => $this->insertar(
                $accion,
                $identificacionNumero,
                $anteriores,
                $nuevos,
                $solicitudId,
                $entidad,
                $origen,
            ),
        );
    }

    /** @throws JsonException */
    private function insertar(
        string $accion,
        string $identificacionNumero,
        ?array $anteriores,
        ?array $nuevos,
        string $solicitudId,
        string $entidad,
        string $origen,
    ): null {
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbbitacora
             (tbbitacoraid, tbbitacoraentidad, tbbitacoraregistroidentificacionnumero, tbbitacoraaccion, tbbitacorafecha,
              tbbitacoradatosanteriores, tbbitacoradatosnuevos, tbbitacoraactortipo,
              tbbitacorausuarioid, tbbitacoraorigen, tbbitacorasolicitudid)
             VALUES (:bitacoraId, :entidad, :registroId, :accion, :fecha, :anteriores, :nuevos,
                     :actorTipo, :usuarioId, :origen, :solicitudId)'
        );
        $sentencia->execute([
            'bitacoraId' => $this->siguienteId(),
            'entidad' => $entidad,
            'registroId' => $identificacionNumero,
            'accion' => $accion,
            'fecha' => gmdate('Y-m-d H:i:s'),
            'anteriores' => $anteriores === null ? null : json_encode($anteriores, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'nuevos' => $nuevos === null ? null : json_encode($nuevos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'actorTipo' => $this->actor->tipo,
            'usuarioId' => $this->actor->personaId,
            'origen' => $origen,
            'solicitudId' => $solicitudId,
        ]);

        return null;
    }

    /**
     * Visor de la bitácora para el administrador (P3-4), del más reciente al más
     * antiguo. Filtros opcionales: entidad exacta, rango de fechas UTC
     * (YYYY-MM-DD, ambos inclusive) y texto que busca en la persona que hizo el
     * cambio (nombre, identificación o correo) o en el registro afectado.
     *
     * @param array{entidad?: string, desde?: string, hasta?: string, q?: string} $filtros
     */
    public function listar(array $filtros, int $pagina, int $tamano): array
    {
        $condiciones = [];
        $parametros = [];
        if (($filtros['entidad'] ?? '') !== '') {
            $condiciones[] = 'b.tbbitacoraentidad = :entidad';
            $parametros['entidad'] = $filtros['entidad'];
        }
        if (($filtros['desde'] ?? '') !== '') {
            $condiciones[] = 'b.tbbitacorafecha >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (($filtros['hasta'] ?? '') !== '') {
            $condiciones[] = 'b.tbbitacorafecha < :hastaSiguiente';
            $parametros['hastaSiguiente'] = gmdate('Y-m-d 00:00:00', strtotime($filtros['hasta'] . ' 00:00:00 UTC') + 86400);
        }
        if (($filtros['q'] ?? '') !== '') {
            // LOWER en los dos lados: Postgres (producción) distingue mayúsculas en LIKE.
            $condiciones[] = '(LOWER(p.tbpersonanombre) LIKE :q1 OR LOWER(p.tbpersonaidentificacionnumero) LIKE :q2
                OR LOWER(p.tbpersonacorreoelectronico) LIKE :q3 OR LOWER(b.tbbitacoraregistroidentificacionnumero) LIKE :q4)';
            $texto = '%' . mb_strtolower($filtros['q'], 'UTF-8') . '%';
            $parametros += ['q1' => $texto, 'q2' => $texto, 'q3' => $texto, 'q4' => $texto];
        }
        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $desde = 'FROM tbbitacora b LEFT JOIN tbpersona p ON p.tbpersonaid = b.tbbitacorausuarioid';

        $conteo = $this->conexion->prepare("SELECT COUNT(*) {$desde} {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sentencia = $this->conexion->prepare(
            "SELECT b.tbbitacoraid AS bitacoraid, b.tbbitacoraentidad AS entidad, b.tbbitacoraaccion AS accion,
                    b.tbbitacorafecha AS fecha, b.tbbitacoraregistroidentificacionnumero AS registro,
                    b.tbbitacoraorigen AS origen, b.tbbitacoraactortipo AS actortipo, b.tbbitacorausuarioid AS usuarioid,
                    b.tbbitacoradatosanteriores AS anteriores, b.tbbitacoradatosnuevos AS nuevos,
                    p.tbpersonanombre AS personanombre
             {$desde} {$where}
             ORDER BY b.tbbitacorafecha DESC, b.tbbitacoraid DESC
             LIMIT :limite OFFSET :desplazamiento"
        );
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor);
        }
        $sentencia->bindValue('limite', $tamano, PDO::PARAM_INT);
        $sentencia->bindValue('desplazamiento', ($pagina - 1) * $tamano, PDO::PARAM_INT);
        $sentencia->execute();

        return [
            'eventos' => array_map(static fn (array $fila): array => [
                'bitacoraId' => (int) $fila['bitacoraid'],
                'fecha' => $fila['fecha'],
                'entidad' => $fila['entidad'],
                'accion' => $fila['accion'],
                'registro' => $fila['registro'],
                'origen' => $fila['origen'],
                'actor' => [
                    'tipo' => $fila['actortipo'],
                    'personaId' => $fila['usuarioid'] === null ? null : (int) $fila['usuarioid'],
                    'nombre' => $fila['personanombre'],
                ],
                'datosAnteriores' => self::decodificar($fila['anteriores']),
                'datosNuevos' => self::decodificar($fila['nuevos']),
            ], $sentencia->fetchAll()),
            'total' => $total,
        ];
    }

    /** Entidades que ya aparecen en la bitácora, para el filtro del visor. */
    public function entidades(): array
    {
        $sentencia = $this->conexion->prepare('SELECT DISTINCT tbbitacoraentidad FROM tbbitacora ORDER BY tbbitacoraentidad');
        $sentencia->execute();

        return $sentencia->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function decodificar(mixed $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $datos = json_decode((string) $json, true);
        return is_array($datos) ? $datos : null;
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbbitacoraid), 0) + 1 FROM tbbitacora');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }
}
