#!/usr/bin/env bash

set -u

# Vercel omite el despliegue cuando este comando termina con 0.
# main conserva producción; dev es la única rama con preview automático.
if [[ "${VERCEL_ENV:-}" == "production" || "${VERCEL_GIT_COMMIT_REF:-}" == "dev" ]]; then
    # Vercel hace el push de la imagen después de este comando. Liberar el
    # cupo antes de construir evita que el push falle cuando el registro llega
    # al límite duro de imágenes por repositorio.
    if [[ "${VERCEL:-}" == "1" && "${VERCEL_REGISTRY_AUTO_PRUNE:-1}" != "0" ]]; then
        if ! bash Tools/vercel-prune-registry.sh --conservar "${VERCEL_REGISTRY_KEEP:-15}"; then
            echo "vercel_build_policy=registry_prune_failed" >&2
            exit 2
        fi
    fi

    echo "vercel_build_policy=build environment=${VERCEL_ENV:-unknown} branch=${VERCEL_GIT_COMMIT_REF:-unknown}"
    exit 1
fi

echo "vercel_build_policy=skip environment=${VERCEL_ENV:-unknown} branch=${VERCEL_GIT_COMMIT_REF:-unknown}"
exit 0
