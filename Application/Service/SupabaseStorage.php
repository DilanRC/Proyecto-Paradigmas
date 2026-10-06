<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;

/**
 * Enlaces firmados de Supabase Storage para archivos privados (P2-6: documento
 * de identidad). Usa SUPABASE_SECRET_KEY, que solo vive en el servidor: el
 * navegador recibe un enlace que caduca a los pocos minutos y nunca la clave.
 */
final class SupabaseStorage
{
    public const SEGUNDOS_ENLACE = 300;

    /** @var callable(string, string, string): array{status:int, body:string} */
    private $transporte;

    public function __construct(?callable $transporte = null)
    {
        $this->transporte = $transporte ?? self::transportePorDefecto(...);
    }

    /** URL firmada del archivo, válida por SEGUNDOS_ENLACE segundos. */
    public function enlaceFirmado(string $bucket, string $ruta): string
    {
        $url = rtrim(trim((string) (getenv('SUPABASE_URL') ?: '')), '/');
        $clave = trim((string) (getenv('SUPABASE_SECRET_KEY') ?: ''));
        if ($url === '' || $clave === '') {
            throw new HttpException('Falta configurar SUPABASE_SECRET_KEY en el servidor para ver documentos.', 503);
        }
        // Cada segmento se codifica; las barras de la ruta se conservan.
        $camino = implode('/', array_map('rawurlencode', explode('/', $ruta)));
        $respuesta = ($this->transporte)(
            "{$url}/storage/v1/object/sign/" . rawurlencode($bucket) . "/{$camino}",
            $clave,
            json_encode(['expiresIn' => self::SEGUNDOS_ENLACE], JSON_THROW_ON_ERROR),
        );
        $datos = json_decode($respuesta['body'] ?? '', true);
        $firmado = is_array($datos) ? ($datos['signedURL'] ?? $datos['signedUrl'] ?? null) : null;
        if (($respuesta['status'] ?? 0) === 404 || ($respuesta['status'] ?? 0) === 400) {
            throw new HttpException('El archivo del documento ya no está en el almacenamiento.', 404);
        }
        if (($respuesta['status'] ?? 0) !== 200 || !is_string($firmado) || $firmado === '') {
            throw new HttpException('No fue posible generar el enlace del documento.', 502);
        }

        return str_starts_with($firmado, 'http') ? $firmado : "{$url}/storage/v1{$firmado}";
    }

    private static function transportePorDefecto(string $url, string $clave, string $cuerpo): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$clave}\r\napikey: {$clave}\r\nContent-Type: application/json\r\n",
                'content' => $cuerpo,
                'ignore_errors' => true,
                'timeout' => 5,
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
