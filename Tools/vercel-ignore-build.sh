#!/usr/bin/env bash

set -u

# Vercel omite el despliegue cuando este comando termina con 0.
# main conserva producción; dev es la única rama con preview automático.
if [[ "${VERCEL_ENV:-}" == "production" || "${VERCEL_GIT_COMMIT_REF:-}" == "dev" ]]; then
    echo "vercel_build_policy=build environment=${VERCEL_ENV:-unknown} branch=${VERCEL_GIT_COMMIT_REF:-unknown}"
    exit 1
fi

echo "vercel_build_policy=skip environment=${VERCEL_ENV:-unknown} branch=${VERCEL_GIT_COMMIT_REF:-unknown}"
exit 0
