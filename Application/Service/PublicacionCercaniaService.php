<?php

declare(strict_types=1);

namespace Application\Service;

use PDO;

/**
 * Ordena publicaciones por la distancia entre la ubicación efímera del usuario
 * y el punto exacto opcional de la finca. La ubicación del usuario nunca se
 * persiste y las coordenadas de finca no se exponen en la respuesta pública.
 */
final class PublicacionCercaniaService
{
    public function __construct(private readonly PDO $conexion) {}

    /**
     * @param array<int,array<string,mixed>> $publicaciones
     * @return array{publicaciones:array<int,array<string,mixed>>,total:int,ranking:string}
     */
    public function ordenarYPaginar(array $publicaciones, float $latitud, float $longitud,
        int $pagina, int $tamano): array
    {
        $coordenadas = $this->coordenadasPorPublicacion($publicaciones);
        foreach ($publicaciones as &$publicacion) {
            $id = (int) ($publicacion['publicacionId'] ?? 0);
            $punto = $coordenadas[$id] ?? null;
            $publicacion['distanciaKm'] = $punto === null
                ? null
                : round(self::calcularDistanciaKm(
                    $latitud,
                    $longitud,
                    (float) $punto['latitud'],
                    (float) $punto['longitud']
                ), 2);
        }
        unset($publicacion);

        usort($publicaciones, static function (array $a, array $b): int {
            $distanciaA = $a['distanciaKm'] ?? null;
            $distanciaB = $b['distanciaKm'] ?? null;
            if ($distanciaA !== null && $distanciaB !== null && $distanciaA !== $distanciaB) {
                return $distanciaA <=> $distanciaB;
            }
            if ($distanciaA !== null && $distanciaB === null) return -1;
            if ($distanciaA === null && $distanciaB !== null) return 1;

            $fecha = strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? ''));
            if ($fecha !== 0) return $fecha;
            return (int) ($b['publicacionId'] ?? 0) <=> (int) ($a['publicacionId'] ?? 0);
        });

        $total = count($publicaciones);
        return [
            'publicaciones' => array_values(array_slice($publicaciones, ($pagina - 1) * $tamano, $tamano)),
            'total' => $total,
            'ranking' => 'CERCANIA',
        ];
    }

    /** @param array<int,array<string,mixed>> $publicaciones */
    private function coordenadasPorPublicacion(array $publicaciones): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $publicacion): int => (int) ($publicacion['publicacionId'] ?? 0),
            $publicaciones
        ), static fn (int $id): bool => $id > 0)));
        if ($ids === []) return [];

        $marcadores = [];
        $parametros = [];
        foreach ($ids as $indice => $id) {
            $nombre = ':publicacion' . $indice;
            $marcadores[] = $nombre;
            $parametros[$nombre] = $id;
        }

        $sentencia = $this->conexion->prepare(
            'SELECT p.tbanimalpublicacionid AS publicacionId,
                    d.tbdireccionlatitud AS latitud,
                    d.tbdireccionlongitud AS longitud
             FROM tbanimalpublicacion p
             LEFT JOIN tbfincadireccion fd ON fd.tbfincaid = p.tbfincaid
             LEFT JOIN tbdireccion d ON d.tbdireccionid = fd.tbdireccionid
             WHERE p.tbanimalpublicacionid IN (' . implode(', ', $marcadores) . ')'
        );
        foreach ($parametros as $nombre => $valor) {
            $sentencia->bindValue($nombre, $valor, PDO::PARAM_INT);
        }
        $sentencia->execute();

        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $id = (int) $fila['publicacionId'];
            if (array_key_exists($id, $resultado)) {
                throw new \RuntimeException(
                    'Una publicación resolvió más de una dirección de finca; revise la integridad de datos.'
                );
            }
            $resultado[$id] = $fila['latitud'] === null || $fila['longitud'] === null
                ? null
                : ['latitud' => $fila['latitud'], 'longitud' => $fila['longitud']];
        }
        return $resultado;
    }

    /** Fórmula Haversine pura, también útil para pruebas de regresión. */
    public static function calcularDistanciaKm(float $latitudA, float $longitudA,
        float $latitudB, float $longitudB): float
    {
        $radioTierraKm = 6371.0088;
        $lat1 = deg2rad($latitudA);
        $lat2 = deg2rad($latitudB);
        $deltaLat = deg2rad($latitudB - $latitudA);
        $deltaLon = deg2rad($longitudB - $longitudA);
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        return $radioTierraKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
