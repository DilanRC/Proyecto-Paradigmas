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

    /**
     * Usuario cuya cuenta Supabase ya fue verificada por el sidecar. Persona
     * puede ser null únicamente durante el proceso público de primera alta.
     */
    public static function usuarioVerificado(
        ?int $personaId,
        string $proveedorSujeto,
        ?string $correoElectronico,
        ?string $rolTecnico,
    ): self {
        return new self(
            $personaId === null ? 'USUARIO_VERIFICADO' : 'PERSONA_AUTENTICADA',
            $personaId,
            $proveedorSujeto,
            $correoElectronico === null ? null : mb_strtolower(trim($correoElectronico), 'UTF-8'),
            $rolTecnico,
        );
    }

    public static function personaAutenticada(
        int $personaId,
        string $proveedorSujeto,
        ?string $correoElectronico,
        ?string $rolTecnico,
    ): self {
        return self::usuarioVerificado($personaId, $proveedorSujeto, $correoElectronico, $rolTecnico);
    }

    public function estaAutenticado(): bool
    {
        return $this->proveedorSujeto !== null && $this->correoElectronico !== null;
    }

    public function tienePersona(): bool
    {
        return $this->estaAutenticado() && $this->personaId !== null;
    }
}
