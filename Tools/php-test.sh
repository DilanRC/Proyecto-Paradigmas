#!/bin/sh
# Corre un script PHP (normalmente una prueba) con el código de ESTE árbol de trabajo, aunque sea un
# git worktree, contra la base MySQL indicada. `docker compose exec app` no sirve en un worktree: el
# contenedor solo monta el repositorio principal.
#
# Uso:  sh Tools/php-test.sh Tests/mi_ofertas_test.php
#       sh Tools/php-test.sh -d bdmercadoganadero_animal Tests/instalacion_limpia_test.php
# Sin -d usa la base principal (bdmercadoganadero).
set -e
BASE="bdmercadoganadero"
if [ "$1" = "-d" ]; then BASE="$2"; shift 2; fi
[ $# -ge 1 ] || { echo "Uso: sh Tools/php-test.sh [-d base] Tests/archivo.php" >&2; exit 2; }

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
COMUN="$(cd "$RAIZ" && git rev-parse --git-common-dir)"
PRINCIPAL="$(cd "$RAIZ" && cd "$COMUN/.." && pwd)"
DB_PASS="$(grep '^DB_ROOT_PASS=' "$PRINCIPAL/.env" | cut -d= -f2-)"
export DB_PASS
RAIZ_DOCKER="$(cd "$RAIZ" && (pwd -W 2>/dev/null || pwd))"

MSYS_NO_PATHCONV=1 docker run --rm --network proyecto-paradigmas_default \
    -v "$RAIZ_DOCKER:/var/www/html" -w /var/www/html \
    -e DB_HOST=db -e DB_PORT=3306 -e "DB_NAME=$BASE" -e DB_USER=root -e DB_PASS \
    proyecto-paradigmas-app php "$@"
