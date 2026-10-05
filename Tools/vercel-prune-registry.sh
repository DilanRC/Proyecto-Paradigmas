#!/usr/bin/env bash

# Poda el Vercel Container Registry del proyecto.
#
# El repositorio "app" tiene un tope de imágenes. Cada commit a main o dev
# empuja una imagen, así que sin poda el registro se llena y a partir de ahí
# toda build termina en ERROR con "repository has reached the maximum allowed
# number of images". Este script conserva las N más recientes y las que sirven
# las últimas producciones listas, y borra el resto. Lo ejecuta
# .github/workflows/vercel-prune-registry.yml en cada push a dev o main.
#
# Uso:
#   bash Tools/vercel-prune-registry.sh                 # conserva 10, borra el resto
#   bash Tools/vercel-prune-registry.sh --conservar 20
#   bash Tools/vercel-prune-registry.sh --simular       # muestra qué borraría
#
# Requiere la CLI de Vercel autenticada (sesión local o VERCEL_TOKEN) y el
# proyecto enlazado (.vercel/project.json, o VERCEL_ORG_ID y VERCEL_PROJECT_ID).

set -euo pipefail

raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
repositorio="app"
conservar=10
simular=0
read -r -a vercel_cmd <<< "${VERCEL_CLI:-npx -y vercel@latest}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --conservar) conservar="$2"; shift 2 ;;
        --repositorio) repositorio="$2"; shift 2 ;;
        --simular) simular=1; shift ;;
        -h|--help) sed -n '3,18p' "${BASH_SOURCE[0]}"; exit 0 ;;
        *) echo "Argumento no reconocido: $1" >&2; exit 64 ;;
    esac
done

cd "$raiz"

# Las etiquetas son los primeros 12 caracteres del sha del commit. Se protegen
# las 3 producciones READY más recientes (la vigente y margen para volver
# atrás) para no dejar un alias sin manifiesto. Protegerlas todas llenaría el
# registro: no gastan cupo de --conservar y cada push a main suma una.
protegidas="$("${vercel_cmd[@]}" ls --prod --limit 100 --format json \
    | php -r '
        $d = json_decode((string) stream_get_contents(STDIN), true) ?: [];
        $tags = [];
        foreach ($d["deployments"] ?? [] as $dep) {
            if (($dep["state"] ?? "") !== "READY") { continue; }
            $sha = $dep["meta"]["githubCommitSha"] ?? "";
            if ($sha !== "") { $tags[substr($sha, 0, 12)] = true; }
            if (count($tags) === 3) { break; }
        }
        echo implode(",", array_keys($tags));
    ')"

# Sin producción a la vista no se borra nada: una respuesta vacía (proyecto o
# equipo equivocado) dejaría sin protección la imagen que sirve el dominio.
if [[ -z "$protegidas" ]]; then
    echo "vercel_prune resultado=sin_produccion_lista: no se borra nada" >&2
    exit 1
fi

echo "vercel_prune protegidas=${protegidas} conservar=${conservar}"

imagenes="$("${vercel_cmd[@]}" vcr image ls "$repositorio" --limit 100 --format json)"
total="$(printf '%s' "$imagenes" | php -r 'echo count((json_decode((string) stream_get_contents(STDIN), true)["images"] ?? []));')"
borrables="$(printf '%s' "$imagenes" \
    | php Tools/vercel-prune-registry.php --conservar="$conservar" --proteger="$protegidas")"

cantidad=0
[[ -n "$borrables" ]] && cantidad="$(printf '%s\n' "$borrables" | wc -l | tr -d ' ')"
echo "vercel_prune total=${total} borrables=${cantidad}"

if [[ "$cantidad" -eq 0 ]]; then
    echo "vercel_prune resultado=sin_cambios"
    exit 0
fi

if [[ "$simular" -eq 1 ]]; then
    printf '%s\n' "$borrables"
    echo "vercel_prune resultado=simulacion"
    exit 0
fi

borradas=0
while read -r id; do
    [[ -z "$id" ]] && continue
    "${vercel_cmd[@]}" vcr image rm "$repositorio" "$id" --yes > /dev/null
    borradas=$((borradas + 1))
done <<< "$borrables"

echo "vercel_prune resultado=ok borradas=${borradas} restantes=$((total - borradas))"
