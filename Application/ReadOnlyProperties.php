<?php

declare(strict_types=1);

namespace Application;

/** Conserva las lecturas públicas y bloquea escrituras también en PHP 8.0. */
trait ReadOnlyProperties
{
    public function __get(string $name): mixed
    {
        if (!in_array($name, self::READ_ONLY_PROPERTIES, true)) {
            throw new \Error('Propiedad desconocida: ' . $name);
        }
        return $this->$name;
    }

    public function __isset(string $name): bool
    {
        return in_array($name, self::READ_ONLY_PROPERTIES, true) && isset($this->$name);
    }

    public function __set(string $name, mixed $value): void
    {
        throw new \Error('No se puede modificar una propiedad de solo lectura: ' . $name);
    }

    public function __unset(string $name): void
    {
        throw new \Error('No se puede eliminar una propiedad de solo lectura: ' . $name);
    }

    public function jsonSerialize(): array
    {
        $values = [];
        foreach (self::READ_ONLY_PROPERTIES as $name) $values[$name] = $this->$name;
        return $values;
    }
}
