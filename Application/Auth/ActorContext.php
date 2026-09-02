<?php

declare(strict_types=1);

namespace Application\Auth;

final readonly class ActorContext
{
    private function __construct(
        public string $tipo,
        public ?int $personaId,
        public ?string $proveedorSujeto,
        public ?string $correoElectronico,
        public ?string $rolTecnico,
    ) {}

    public static function noAutenticado(): self
    {
        return new self('NO_AUTENTICADO', null, null, null, null);
    }

    public static function personaAutenticada(
        int $personaId,
        string $proveedorSujeto,
        ?string $correoElectronico,
        ?string $rolTecnico,
    ): self {
        return new self('PERSONA_AUTENTICADA', $personaId, $proveedorSujeto, $correoElectronico, $rolTecnico);
    }
}
