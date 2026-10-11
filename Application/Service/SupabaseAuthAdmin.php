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
    /** @var callable(string, string, string): array{status:int, body:string} */
    private $transporte;

    public function __construct(?callable $transporte = null)
    {
        $this->transporte = $transporte ?? self::transportePorDefecto(...);
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
        $respuesta = ($this->transporte)(
            "{$url}/auth/v1/admin/users/{$usuarioId}",
            $clave,
            json_encode(['email' => $correo, 'email_confirm' => true], JSON_THROW_ON_ERROR),
        );
        $estado = (int) ($respuesta['status'] ?? 0);
        if ($estado === 200) {
            return;
        }
        $datos = json_decode($respuesta['body'] ?? '', true);
        $codigo = is_array($datos) ? (string) ($datos['error_code'] ?? $datos['code'] ?? '') : '';
        if ($codigo === 'email_exists' || $estado === 422) {
            throw new HttpException('Revise los campos indicados.', 409, null, [
                'correoElectronico' => 'Ese correo ya tiene una cuenta.',
            ]);
        }
        throw new HttpException('No fue posible cambiar el correo de la cuenta. Intenta de nuevo.', 502);
    }

    private static function transportePorDefecto(string $url, string $clave, string $cuerpo): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'PUT',
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
