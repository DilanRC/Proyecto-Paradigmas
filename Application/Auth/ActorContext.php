<?php

declare(strict_types=1);

namespace Application\Auth;

require_once dirname(__DIR__) . '/ReadOnlyProperties.php';

final class ActorContext implements \JsonSerializable
{
    use \Application\ReadOnlyProperties;

    private const READ_ONLY_PROPERTIES = ['tipo', 'personaId', 'proveedorSujeto', 'correoElectronico', 'rolTecnico'];

    private function __construct(
        private string $tipo,
        private ?int $personaId,
        private ?string $proveedorSujeto,
        private ?string $correoElectronico,
        private ?string $rolTecnico,
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
