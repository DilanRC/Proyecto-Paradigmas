<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;

/**
 * Cambia el correo de una cuenta de Supabase Auth desde el servidor (API admin, con
 * SUPABASE_SECRET_KEY). La sesión se vincula con la Persona por el correo, así que el
 * correo de la cuenta y el de tbpersona deben cambiar juntos (Ajustes → Perfil).
 */
final class SupabaseAuthAdmin
{
    /** @var callable(string, string, string, string=): array{status:int, body:string} */
    private $transporte;

    public function __construct(?callable $transporte = null)
    {
        $this->transporte = $transporte ?? \Closure::fromCallable([self::class, 'transportePorDefecto']);
    }

    /** Deja el correo nuevo ya confirmado: la app no exige confirmación de correo (MEMORIA, P0-1). */
    public function cambiarCorreo(string $usuarioId, string $correo): void
    {
        $url = rtrim(trim((string) (getenv('SUPABASE_URL') ?: '')), '/');
        $clave = trim((string) (getenv('SUPABASE_SECRET_KEY') ?: ''));
        if ($url === '' || $clave === '') {
            throw new HttpException('Falta configurar SUPABASE_SECRET_KEY en el servidor para cambiar el correo.', 503);
        }
        if (!preg_match('/^[0-9a-f-]{36}$/i', $usuarioId)) {
            throw new HttpException('La sesión no identifica una cuenta de Supabase.', 409);
        }
        try {
            $respuesta = ($this->transporte)(
                "{$url}/auth/v1/admin/users/{$usuarioId}",
                $clave,
                json_encode(['email' => $correo, 'email_confirm' => true], JSON_THROW_ON_ERROR),
                'PUT',
            );
        } catch (\Throwable) {
            $respuesta = ['status' => 0, 'body' => ''];
        }
        $estado = (int) ($respuesta['status'] ?? 0);
        if ($estado === 200 && $this->coincideUsuario($respuesta, $usuarioId, $correo)) {
            return;
        }
        $datos = json_decode($respuesta['body'] ?? '', true);
        $codigo = is_array($datos) ? (string) ($datos['error_code'] ?? $datos['code'] ?? '') : '';
        if ($codigo === 'email_exists' || $estado === 422) {
            throw new HttpException('Revise los campos indicados.', 409, null, [
                'correoElectronico' => 'Ese correo ya tiene una cuenta.',
            ]);
        }
        // Un timeout puede ocurrir después de que Auth aplique el cambio.
        if ($this->coincideUsuario($this->consultarUsuario($usuarioId), $usuarioId, $correo)) {
            return;
        }
        throw $this->errorSincronizacion();
    }

    /** Solo restaura una modificación atribuible a esta operación. */
    public function recuperarCorreo(string $usuarioId, string $correoNuevo, string $correoAnterior): void
    {
        $actual = $this->consultarUsuario($usuarioId);
        if ($this->coincideUsuario($actual, $usuarioId, $correoAnterior)) return;
        if (!$this->coincideUsuario($actual, $usuarioId, $correoNuevo)) {
            throw $this->errorSincronizacion();
        }
        $this->cambiarCorreo($usuarioId, $correoAnterior);
    }

    /** Correo confirmado que la cuenta tiene ahora mismo, en minúscula. Sin respuesta verificable: 503. */
    public function correoConfirmado(string $usuarioId): string
    {
        $respuesta = $this->consultarUsuario($usuarioId);
        $usuario = json_decode((string) ($respuesta['body'] ?? ''), true);
        $correo = is_array($usuario) ? ($usuario['email'] ?? null) : null;
        if (!is_string($correo) || !$this->coincideUsuario($respuesta, $usuarioId, $correo)) {
            throw $this->errorSincronizacion();
        }

        return mb_strtolower($correo, 'UTF-8');
    }

    private function coincideUsuario(array $respuesta, string $usuarioId, string $correo): bool
    {
        $usuario = json_decode((string) ($respuesta['body'] ?? ''), true);
        return (int) ($respuesta['status'] ?? 0) === 200
            && is_array($usuario)
            && ($usuario['id'] ?? null) === $usuarioId
            && is_string($usuario['email'] ?? null)
            && mb_strtolower($usuario['email'], 'UTF-8') === mb_strtolower($correo, 'UTF-8')
            && is_string($usuario['email_confirmed_at'] ?? null)
            && $usuario['email_confirmed_at'] !== '';
    }

    private function consultarUsuario(string $usuarioId): array
    {
        $url = rtrim(trim((string) (getenv('SUPABASE_URL') ?: '')), '/');
        $clave = trim((string) (getenv('SUPABASE_SECRET_KEY') ?: ''));
        if ($url === '' || $clave === '' || !preg_match('/^[0-9a-f-]{36}$/i', $usuarioId)) {
            throw $this->errorSincronizacion();
        }
        try {
            return ($this->transporte)("{$url}/auth/v1/admin/users/{$usuarioId}", $clave, '', 'GET');
        } catch (\Throwable) {
            throw $this->errorSincronizacion();
        }
    }

    private function errorSincronizacion(): HttpException
    {
        return new HttpException('No se pudo confirmar la sincronización del correo. La cuenta requiere comprobar el correo antes de reintentar.', 503);
    }

    private static function transportePorDefecto(string $url, string $clave, string $cuerpo, string $metodo = 'PUT'): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => $metodo,
                'header' => "Authorization: Bearer {$clave}\r\napikey: {$clave}\r\nContent-Type: application/json\r\n",
                'content' => $cuerpo,
                'ignore_errors' => true,
                'timeout' => 8,
            ],
        ]);
        $body = @file_get_contents($url, false, $contexto);
        if ($body === false) {
            return ['status' => 0, 'body' => ''];
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $cabecera) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $cabecera, $coincidencia)) {
                $status = (int) $coincidencia[1];
                break;
            }
        }

        return ['status' => $status, 'body' => $body];
    }
}
