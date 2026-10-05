<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

/**
 * Límite de consultas de disponibilidad del registro (cédula y correo) por IP,
 * para que nadie recorra la base averiguando quién está registrado. Guarda una
 * fila por consulta con el hash de la IP y borra lo que sale de la ventana.
 */
final class RegistroConsulta
{
    public const LIMITE = 20;
    public const VENTANA_SEGUNDOS = 60;

    public function __construct(private readonly PDO $conexion) {}

    /** Registra la consulta si cabe en la ventana; false = se pasó del límite. */
    public function permitir(string $ip): bool
    {
        $clave = hash('sha256', $ip);
        NamedLock::acquire($this->conexion, 'tindercows_registro_consulta');
        try {
            $limpiar = $this->conexion->prepare(
                'DELETE FROM tbregistroconsulta WHERE tbregistroconsultafecha < :desde'
            );
            $limpiar->execute(['desde' => gmdate('Y-m-d H:i:s', time() - self::VENTANA_SEGUNDOS)]);

            $conteo = $this->conexion->prepare(
                'SELECT COUNT(*) FROM tbregistroconsulta WHERE tbregistroconsultaclave = :clave'
            );
            $conteo->execute(['clave' => $clave]);
            if ((int) $conteo->fetchColumn() >= self::LIMITE) {
                return false;
            }

            $siguiente = $this->conexion->prepare(
                'SELECT COALESCE(MAX(tbregistroconsultaid), 0) + 1 FROM tbregistroconsulta'
            );
            $siguiente->execute();
            $insertar = $this->conexion->prepare(
                'INSERT INTO tbregistroconsulta (tbregistroconsultaid, tbregistroconsultaclave, tbregistroconsultafecha)
                 VALUES (:id, :clave, :fecha)'
            );
            $insertar->execute([
                'id' => (int) $siguiente->fetchColumn(),
                'clave' => $clave,
                'fecha' => gmdate('Y-m-d H:i:s'),
            ]);
            return true;
        } finally {
            NamedLock::release($this->conexion, 'tindercows_registro_consulta');
        }
    }

    /**
     * IP del cliente. En Vercel la conexión llega desde su proxy, que escribe
     * X-Real-IP con la IP real (no la puede fijar el cliente).
     * ponytail: fuera de Vercel un cliente podría enviar X-Real-IP falso; si
     * se despliega en otro lugar, leer la cabecera solo detrás de un proxy confiable.
     */
    public static function ipCliente(array $servidor): string
    {
        $ip = trim((string) ($servidor['HTTP_X_REAL_IP'] ?? $servidor['REMOTE_ADDR'] ?? ''));
        return $ip !== '' ? $ip : 'desconocida';
    }
}
