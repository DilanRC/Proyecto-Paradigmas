<?php

declare(strict_types=1);

namespace Application\Service;

/**
 * Avisos en tiempo real por Supabase Realtime (Broadcast), enviados desde el servidor con SUPABASE_SECRET_KEY.
 * El aviso no lleva datos: solo dice "esto cambió" y cada pantalla vuelve a pedir el estado a la API, que es la
 * única fuente de verdad. Así un mensaje falso en un canal público solo causa una recarga de más.
 * Tópicos: `subastas` (la lista), `subasta-<id>` (una subasta) y `notificaciones-<personaId>` (la campana).
 * Si Realtime no responde, la petición sigue igual: las pantallas también consultan cada pocos segundos.
 */
final class TiempoReal
{
    /** @var callable(string, string, string): void */
    private $transporte;

    public function __construct(?callable $transporte = null)
    {
        $this->transporte = $transporte ?? self::transportePorDefecto(...);
    }

    /** @param array<int,string> $topicos */
    public function avisar(array $topicos, string $evento = 'cambio'): void
    {
        $topicos = array_values(array_unique(array_filter($topicos)));
        $url = rtrim(trim((string) (getenv('SUPABASE_URL') ?: '')), '/');
        $clave = trim((string) (getenv('SUPABASE_SECRET_KEY') ?: ''));
        if ($topicos === [] || $url === '' || $clave === '') return;
        $mensajes = array_map(static fn (string $topico): array => [
            'topic' => $topico, 'event' => $evento, 'payload' => ['t' => time()], 'private' => false,
        ], $topicos);
        try {
            ($this->transporte)("{$url}/realtime/v1/api/broadcast", $clave, json_encode(['messages' => $mensajes], JSON_THROW_ON_ERROR));
        } catch (\Throwable $error) {
            error_log('[TinderCows] Aviso en tiempo real no enviado: ' . $error->getMessage());
        }
    }

    private static function transportePorDefecto(string $url, string $clave, string $cuerpo): void
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "apikey: {$clave}\r\nAuthorization: Bearer {$clave}\r\nContent-Type: application/json\r\n",
                'content' => $cuerpo,
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ]);
        @file_get_contents($url, false, $contexto);
    }
}
