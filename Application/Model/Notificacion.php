<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/** Avisos para una persona (campana del encabezado). Los ids salen de MAX()+1 bajo su NamedLock. */
final class Notificacion
{
    private const LOCK = 'tindercows_notificacion_alta';

    public function __construct(private readonly PDO $conexion) {}

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, self::LOCK);
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, self::LOCK);
        }
    }

    public function crear(int $personaId, string $tipo, string $titulo, string $mensaje, ?string $enlace): int
    {
        $siguiente = $this->conexion->prepare('SELECT COALESCE(MAX(tbnotificacionid), 0) + 1 FROM tbnotificacion');
        $siguiente->execute();
        $id = (int) $siguiente->fetchColumn();
        $this->conexion->prepare(
            'INSERT INTO tbnotificacion (tbnotificacionid, tbpersonaid, tbnotificaciontipo, tbnotificaciontitulo,
                tbnotificacionmensaje, tbnotificacionenlace, tbnotificacionfecha, tbnotificacionleida)
             VALUES (:id, :personaId, :tipo, :titulo, :mensaje, :enlace, :fecha, 0)'
        )->execute([
            'id' => $id,
            'personaId' => $personaId,
            'tipo' => $tipo,
            'titulo' => mb_substr($titulo, 0, 150),
            'mensaje' => mb_substr($mensaje, 0, 500),
            'enlace' => $enlace,
            'fecha' => gmdate('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /** @return array{notificaciones:array<int,array<string,mixed>>,sinLeer:int} las más recientes primero */
    public function listar(int $personaId, int $limite = 20): array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT tbnotificacionid AS id, tbnotificaciontipo AS tipo, tbnotificaciontitulo AS titulo,
                    tbnotificacionmensaje AS mensaje, tbnotificacionenlace AS enlace, tbnotificacionfecha AS fecha,
                    tbnotificacionleida AS leida
             FROM tbnotificacion WHERE tbpersonaid = :personaId
             ORDER BY tbnotificacionid DESC LIMIT :limite'
        );
        $sentencia->bindValue('personaId', $personaId, PDO::PARAM_INT);
        $sentencia->bindValue('limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();
        $conteo = $this->conexion->prepare('SELECT COUNT(*) FROM tbnotificacion WHERE tbpersonaid = :personaId AND tbnotificacionleida = 0');
        $conteo->execute(['personaId' => $personaId]);

        return [
            'notificaciones' => array_map(static fn (array $f): array => [
                'notificacionId' => (int) $f['id'],
                'tipo' => $f['tipo'],
                'titulo' => $f['titulo'],
                'mensaje' => $f['mensaje'],
                'enlace' => $f['enlace'],
                'fecha' => Subasta::iso($f['fecha']),
                'leida' => (int) $f['leida'] === 1,
            ], $sentencia->fetchAll()),
            'sinLeer' => (int) $conteo->fetchColumn(),
        ];
    }

    public function marcarLeidas(int $personaId): void
    {
        $this->conexion->prepare('UPDATE tbnotificacion SET tbnotificacionleida = 1 WHERE tbpersonaid = :personaId AND tbnotificacionleida = 0')
            ->execute(['personaId' => $personaId]);
    }
}
