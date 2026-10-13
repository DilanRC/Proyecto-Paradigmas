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

    public function __construct(private PDO $conexion) {}

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
     * IP del cliente. Solo en producción (Vercel) la conexión llega desde su proxy, que escribe
     * X-Real-IP con la IP real y descarta la que mande el cliente. En cualquier otro servidor (XAMPP,
     * Docker) la cabecera la escribe quien hace la petición: se ignora y cuenta la dirección de la conexión.
     * Producción se reconoce por la variable VERCEL o, si el contenedor no la recibe, por su POSTGRES_URL.
     */
    public static function ipCliente(array $servidor): string
    {
        $trasProxyDeVercel = trim((string) getenv('VERCEL')) !== ''
            || (trim((string) getenv('POSTGRES_URL')) !== '' && strtolower(trim((string) getenv('DB_DRIVER'))) !== 'mysql');
        $ip = trim((string) ($trasProxyDeVercel && isset($servidor['HTTP_X_REAL_IP'])
            ? $servidor['HTTP_X_REAL_IP']
            : ($servidor['REMOTE_ADDR'] ?? '')));
        return $ip !== '' ? $ip : 'desconocida';
    }
}
