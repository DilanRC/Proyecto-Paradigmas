<?php

declare(strict_types=1);

namespace Application\Auth;

use Application\HttpException;
use PDO;
use Throwable;

final class SupabaseActorResolver
{
    private const DEFAULT_VERIFY_URL = 'http://supabase-server:3000/v1/auth/verify';

    /** @var callable(string, string): array{status:int, body:string} */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? $this->defaultTransport(...);
    }

    /** Endpoints normales: un JWT válido sin Persona sigue siendo conflicto. */
    public static function fromGlobals(PDO $conexion): ActorContext
    {
        return (new self())->resolve($conexion, $_SERVER);
    }

    /**
     * Exclusivo para registro público: verifica el JWT, pero permite que todavía
     * no exista tbpersona. No convierte al usuario en Persona ni le concede
     * permisos; esa vinculación ocurre dentro de la transacción de registro.
     */
    public static function fromGlobalsPermitiendoPersonaNoVinculada(PDO $conexion): ActorContext
    {
        return (new self())->resolvePermitiendoPersonaNoVinculada($conexion, $_SERVER);
    }

    public function resolve(PDO $conexion, array $server): ActorContext
    {
        return $this->resolver($conexion, $server, true);
    }

    public function resolvePermitiendoPersonaNoVinculada(PDO $conexion, array $server): ActorContext
    {
        return $this->resolver($conexion, $server, false);
    }

    private function resolver(PDO $conexion, array $server, bool $exigirPersona): ActorContext
    {
        $authorization = $this->authorizationHeader($server);
        if ($authorization === null) {
            return ActorContext::noAutenticado();
        }
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization)) {
            throw new HttpException('Authorization debe usar Bearer.', 401);
        }

        $verifyUrl = getenv('SUPABASE_AUTH_VERIFY_URL') ?: self::DEFAULT_VERIFY_URL;
        try {
            $response = ($this->transport)($verifyUrl, $authorization);
        } catch (Throwable) {
            throw new HttpException('No fue posible validar la sesión.', 503);
        }

        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        if ($status === 0 || $status === 503) {
            throw new HttpException('No fue posible validar la sesión.', 503);
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new HttpException('No fue posible validar la sesión.', 503);
        }
        if ($status === 401 || $status === 403) {
            throw new HttpException($payload['error']['message'] ?? 'Sesión inválida.', $status);
        }
        if ($status !== 200 || ($payload['success'] ?? false) !== true) {
            throw new HttpException('No fue posible validar la sesión.', 503);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $subject = is_string($data['id'] ?? null) ? trim($data['id']) : '';
        $email = is_string($data['email'] ?? null)
            ? mb_strtolower(trim($data['email']), 'UTF-8')
            : '';
        $role = is_string($data['role'] ?? null) ? trim($data['role']) : null;
        if ($subject === '' || $email === '') {
            throw new HttpException('La sesión verificada no contiene una identidad utilizable.', 409);
        }

        $personaId = $this->personaIdPorCorreo($conexion, $email);
        if ($exigirPersona && $personaId === null) {
            throw new HttpException('La sesión verificada no tiene vínculo con una persona.', 409);
        }

        return ActorContext::usuarioVerificado($personaId, $subject, $email, $role);
    }

    private function authorizationHeader(array $server): ?string
    {
        $authorization = $server['HTTP_AUTHORIZATION']
            ?? $server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
        if (!is_string($authorization) || trim($authorization) === '') {
            return null;
        }

        return trim($authorization);
    }

    private function personaIdPorCorreo(PDO $conexion, string $email): ?int
    {
        $sentencia = $conexion->prepare(
            'SELECT tbpersonaid FROM tbpersona
             WHERE LOWER(tbpersonacorreoelectronico) = LOWER(:correo)
             ORDER BY tbpersonaid'
        );
        $sentencia->execute(['correo' => $email]);
        $filas = $sentencia->fetchAll(PDO::FETCH_COLUMN);
        if (count($filas) > 1) {
            throw new HttpException('El correo autenticado está vinculado a más de una Persona.', 409);
        }

        return $filas === [] ? null : (int) $filas[0];
    }

    /** @return array{status:int, body:string} */
    private function defaultTransport(string $url, string $authorization): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: {$authorization}\r\n",
                'ignore_errors' => true,
                'timeout' => 3,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return ['status' => 0, 'body' => ''];
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match)) {
                $status = (int) $match[1];
                break;
            }
        }

        return ['status' => $status, 'body' => $body];
    }
}
