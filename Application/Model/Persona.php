<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class PersonaConflictException extends \RuntimeException
{
}

/** Fuente única de identidad y contacto para todas las capacidades. */
final class Persona
{
    public function __construct(private readonly PDO $conexion)
    {
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
              tbpersonanombre, tbpersonatelefono, tbpersonacorreoelectronico, tbpersonaestado)
             VALUES (:personaId, :identificacionNumero, :identificacionTipo, :nombre,
                     :telefono, :correoElectronico, 1)'
        );
        $sentencia->execute([
            'personaId' => $personaId,
            'identificacionNumero' => $datos['identificacionNumero'],
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);

        return $this->bloquear($datos['identificacionNumero'])
            ?? throw new \RuntimeException('No fue posible leer la persona recién creada.');
    }

    public function actualizar(string $identificacionNumero, array $datos): void
    {
        $sentencia = $this->conexion->prepare(
            'UPDATE tbpersona SET tbpersonaidentificaciontipo = :identificacionTipo,
                    tbpersonanombre = :nombre, tbpersonatelefono = :telefono,
                    tbpersonacorreoelectronico = :correoElectronico
             WHERE tbpersonaidentificacionnumero = :identificacionNumero'
        );
        $sentencia->execute([
            'identificacionNumero' => $identificacionNumero,
            'identificacionTipo' => $datos['identificacionTipo'],
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'],
            'correoElectronico' => $datos['correoElectronico'],
        ]);
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
        return $persona['tbpersonaidentificaciontipo'] === $datos['identificacionTipo']
            && $persona['tbpersonanombre'] === $datos['nombre']
            && $persona['tbpersonatelefono'] === $datos['telefono']
            && $persona['tbpersonacorreoelectronico'] === $datos['correoElectronico'];
    }
}
