<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

require_once __DIR__ . '/PersonaTelefonoHistorico.php';

final class PersonaConflictException extends \RuntimeException
{
}

/** Fuente única de identidad y contacto para todas las capacidades. */
final class Persona
{
    private PersonaTelefonoHistorico $telefonoHistorico;

    public function __construct(private readonly PDO $conexion)
    {
        $this->telefonoHistorico = new PersonaTelefonoHistorico($conexion);
    }

    public function buscar(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new PersonaConflictException('La identificación está duplicada en la base de datos.');
        }

        return $filas[0] ?? null;
    }

    public function existeIdentificacion(string $identificacionNumero): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT 1 FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacionNumero LIMIT 1'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);

        return $sentencia->fetchColumn() !== false;
    }

    /**
     * Estado del documento de identidad para la propia persona, o null si no
     * subió ninguno. Nunca expone la ruta: el archivo es privado (P2-5).
     */
    public static function documentoPublico(array $fila): ?array
    {
        $estado = $fila['tbpersonadocumentoestado'] ?? null;
        return $estado === null ? null : [
            'estado' => $estado,
            'fecha' => $fila['tbpersonadocumentofecha'] ?? null,
        ];
    }

    /** Sin distinguir mayúsculas, igual que RegistroPublicoService al registrar. */
    public function existeCorreo(string $correoElectronico): bool
    {
        $sentencia = $this->conexion->prepare(
            'SELECT 1 FROM tbpersona WHERE LOWER(tbpersonacorreoelectronico) = LOWER(:correo) LIMIT 1'
        );
        $sentencia->execute(['correo' => $correoElectronico]);

        return $sentencia->fetchColumn() !== false;
    }

    /**
     * Resuelve una Persona por su identificador interno. Esta vía existe para
     * procesos autenticados: SupabaseActorResolver ya vinculó el JWT con
     * tbpersonaid en el servidor, por lo que el navegador no necesita enviar
     * una cédula para operar sobre "Mi actividad".
     */
    public function buscarPorId(int $personaId): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbpersona WHERE tbpersonaid = :personaId'
        );
        $sentencia->execute(['personaId' => $personaId]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new PersonaConflictException('El identificador interno está duplicado en la base de datos.');
        }

        return $filas[0] ?? null;
    }

    public function bloquear(string $identificacionNumero): ?array
    {
        $sentencia = $this->conexion->prepare(
            'SELECT * FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacionNumero FOR UPDATE'
        );
        $sentencia->execute(['identificacionNumero' => $identificacionNumero]);
        $filas = $sentencia->fetchAll();
        if (count($filas) > 1) {
            throw new PersonaConflictException('La identificación está duplicada en la base de datos.');
        }

        return $filas[0] ?? null;
    }

    public function obtenerOCrear(array $datos): array
    {
        $persona = $this->bloquear($datos['identificacionNumero']);
        if ($persona !== null) {
            if ((int) $persona['tbpersonaestado'] !== 1) {
                throw new PersonaConflictException('La persona está inactiva y no puede agregar capacidades.');
            }
            if (!$this->coincide($persona, $datos)) {
                throw new PersonaConflictException('La identificación ya existe con datos personales diferentes.');
            }
            return $persona;
        }

        $personaId = $this->siguienteId();
        $sentencia = $this->conexion->prepare(
            'INSERT INTO tbpersona
             (tbpersonaid, tbpersonaidentificacionnumero, tbpersonaidentificaciontipo,
              tbpersonanombre, tbpersonaalias, tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
             VALUES (:personaId, :identificacionNumero, :identificacionTipo, :nombre,
                     :alias, :telefono, :correoElectronico, 1)'
        );
        $sentencia->execute([
            'personaId' => $personaId,
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'alias' => $datos['alias'] ?? null,
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);

        return $this->bloquear($datos['identificacionNumero'])
            ?? throw new \RuntimeException('No fue posible leer la persona recién creada.');
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $persona = $this->bloquear($identificacionNumero);
        if ($persona === null) {
            throw new PersonaConflictException('La persona no existe.');
        }

        $telefonoNuevo = $datos['telefono'];
        if ($persona['tbpersonatelefono'] !== $telefonoNuevo) {
            $this->telefonoHistorico->registrarCambio(
                (int) $persona['tbpersonaid'],
                $telefonoNuevo,
                gmdate('Y-m-d H:i:s'),
            );
        }

        // Los contextos que todavía no exponen Alias (por ejemplo Transportista)
        // envían null desde el validador. Ese null significa "no tocar" para no
        // borrar un alias existente al editar otro contexto de la misma Persona.
        $alias = ($datos['alias'] ?? null) !== null
            ? $datos['alias']
            : $persona['tbpersonaalias'];

        $sentencia = $this->conexion->prepare(
            'UPDATE tbpersona SET tbpersonaidentificaciontipo = :identificacionTipo,
                    tbpersonanombre = :nombre, tbpersonaalias = :alias,
                    tbpersonatelefono = :telefono,
                    tbpersonacorreoelectronico = :correoElectronico
             WHERE tbpersonaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute([
            'identificacionNumero' => $identificacionNumero,
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'alias' => $alias,
            'telefono' => $telefonoNuevo,
            'correoElectronico' => $datos['correoElectronico'],
        ]);
    }

    /**
     * Edición que hace la propia persona: alias, teléfono y foto. Solo cambian
     * las claves presentes en $cambios (alias, telefono, fotoUrl). Un teléfono
     * nuevo deja su histórico, igual que actualizar(). Debe correr dentro de una
     * transacción (bloquea la fila y toma NamedLock para el histórico).
     */
    public function actualizarPerfil(int $personaId, array $cambios): array
    {
        $sentencia = $this->conexion->prepare('SELECT * FROM tbpersona WHERE tbpersonaid = :personaId FOR UPDATE');
        $sentencia->execute(['personaId' => $personaId]);
        $persona = $sentencia->fetch();
        if ($persona === false) {
            throw new PersonaConflictException('La persona no existe.');
        }

        if (array_key_exists('telefono', $cambios) && $persona['tbpersonatelefono'] !== $cambios['telefono']) {
            $this->telefonoHistorico->registrarCambio($personaId, $cambios['telefono'], gmdate('Y-m-d H:i:s'));
        }

        $columnas = [
            'alias' => 'tbpersonaalias',
            'telefono' => 'tbpersonatelefono',
            'fotoUrl' => 'tbpersonafotourl',
            'documentoRuta' => 'tbpersonadocumentoruta',
            'documentoEstado' => 'tbpersonadocumentoestado',
            'documentoFecha' => 'tbpersonadocumentofecha',
        ];
        $asignaciones = [];
        $parametros = ['personaId' => $personaId];
        foreach ($columnas as $clave => $columna) {
            if (array_key_exists($clave, $cambios)) {
                $asignaciones[] = "{$columna} = :{$clave}";
                $parametros[$clave] = $cambios[$clave];
            }
        }
        if ($asignaciones !== []) {
            $this->conexion->prepare(
                'UPDATE tbpersona SET ' . implode(', ', $asignaciones) . ' WHERE tbpersonaid = :personaId'
            )->execute($parametros);
        }

        return $this->buscarPorId($personaId)
            ?? throw new \RuntimeException('No fue posible leer la persona actualizada.');
    }

    public function ejecutarConBloqueoAlta(callable $operacion): mixed
    {
        NamedLock::acquire($this->conexion, 'tindercows_persona_alta');
        try {
            return $operacion();
        } finally {
            NamedLock::release($this->conexion, 'tindercows_persona_alta');
        }
    }

    private function siguienteId(): int
    {
        $sentencia = $this->conexion->prepare('SELECT COALESCE(MAX(tbpersonaid), 0) + 1 FROM tbpersona');
        $sentencia->execute();

        return (int) $sentencia->fetchColumn();
    }

    private function coincide(array $persona, array $datos): bool
    {
        $aliasCoincide = ($datos['alias'] ?? null) === null
            || $persona['tbpersonaalias'] === $datos['alias'];

        return $persona['tbpersonaidentificaciontipo'] === $datos['identificacionTipo']
            && $persona['tbpersonanombre'] === $datos['nombre']
            && $aliasCoincide
            && $persona['tbpersonatelefono'] === $datos['telefono']
            && $persona['tbpersonacorreoelectronico'] === $datos['correoElectronico'];
    }
}
