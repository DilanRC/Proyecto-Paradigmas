<?php

declare(strict_types=1);

/**
 * Selección determinista de imágenes a borrar del Vercel Container Registry.
 *
 * El registro tiene un tope duro de imágenes por repositorio. Cuando se llena,
 * el push falla y todo despliegue queda en ERROR aunque la build haya salido
 * bien. Este archivo decide qué borrar; no habla con la red. La parte que sí
 * llama a la CLI vive en Tools/vercel-prune-registry.sh, de modo que la regla
 * se pueda probar sin credenciales ni llamadas remotas.
 *
 * Uso: php Tools/vercel-prune-registry.php --conservar=10 --proteger=tag1,tag2 < imagenes.json
 * Entrada: el JSON de `vercel vcr image ls <repo> --format json`.
 * Salida: un id de imagen por línea, las que deben borrarse.
 */

/**
 * @param list<array{id?:string,createdAt?:string,tags?:list<string>}> $imagenes
 * @param list<string> $etiquetasProtegidas
 * @return list<string>
 */
function vercel_registry_borrables(array $imagenes, int $conservar, array $etiquetasProtegidas): array
{
    if ($conservar < 1) {
        throw new InvalidArgumentException('conservar debe ser al menos 1.');
    }

    $ordenadas = $imagenes;
    // Orden estable: más reciente primero; el id desempata para que dos
    // imágenes con la misma fecha no cambien de lugar entre ejecuciones.
    usort($ordenadas, static function (array $a, array $b): int {
        $fecha = strtotime($b['createdAt'] ?? '') <=> strtotime($a['createdAt'] ?? '');

        return $fecha !== 0 ? $fecha : strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $protegidas = array_flip($etiquetasProtegidas);
    $borrables = [];
    $conservadas = 0;
    foreach ($ordenadas as $imagen) {
        $id = (string) ($imagen['id'] ?? '');
        if ($id === '') {
            continue;
        }

        $esProtegida = false;
        foreach ($imagen['tags'] ?? [] as $etiqueta) {
            if (isset($protegidas[$etiqueta])) {
                $esProtegida = true;
                break;
            }
        }

        // Una imagen protegida nunca gasta cupo: es la que sirve producción y
        // borrarla dejaría el alias apuntando a un manifiesto inexistente.
        if ($esProtegida) {
            continue;
        }

        if ($conservadas < $conservar) {
            $conservadas++;
            continue;
        }

        $borrables[] = $id;
    }

    return $borrables;
}

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$conservar = 10;
$proteger = [];
foreach (array_slice($argv, 1) as $argumento) {
    if (str_starts_with($argumento, '--conservar=')) {
        $conservar = (int) substr($argumento, 12);
        continue;
    }
    if (str_starts_with($argumento, '--proteger=')) {
        $proteger = array_values(array_filter(explode(',', substr($argumento, 11)), static fn (string $t): bool => $t !== ''));
        continue;
    }
    fwrite(STDERR, "Argumento no reconocido: {$argumento}\n");
    exit(64);
}

$entrada = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$imagenes = $entrada['images'] ?? [];
if (!is_array($imagenes)) {
    fwrite(STDERR, "La entrada no contiene una lista images.\n");
    exit(65);
}

foreach (vercel_registry_borrables($imagenes, $conservar, $proteger) as $id) {
    echo $id, "\n";
}
