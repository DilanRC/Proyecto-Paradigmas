#!/bin/sh
# Crea (o recrea) una base MySQL aislada con el esquema y las semillas de ESTE árbol de trabajo,
# dentro del contenedor de MySQL que ya corre (docker compose up -d db). Sirve para probar un
# cambio de esquema sin tocar la base `bdmercadoganadero` que usa la app en :8080.
#
# Uso:  sh Tools/db-aislada.sh <nombre>        p. ej. sh Tools/db-aislada.sh bdmercadoganadero_animal
# Luego: sh Tools/php-test.sh -d <nombre> Tests/archivo_test.php
set -e
NOMBRE="${1:?Uso: sh Tools/db-aislada.sh <nombre>}"
case "$NOMBRE" in *[!a-zA-Z0-9_]*|"") echo "El nombre solo admite letras, números y guion bajo." >&2; exit 2 ;; esac
[ "$NOMBRE" = "bdmercadoganadero" ] && { echo "Ese es el nombre de la base principal: elige otro." >&2; exit 2; }

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
COMUN="$(cd "$RAIZ" && git rev-parse --git-common-dir)"
PRINCIPAL="$(cd "$RAIZ" && cd "$COMUN/.." && pwd)"   # el .env vive solo en el repositorio principal
DB_ROOT_PASS="$(grep '^DB_ROOT_PASS=' "$PRINCIPAL/.env" | cut -d= -f2-)"
export MYSQL_PWD="$DB_ROOT_PASS"
MYSQL="docker exec -i -e MYSQL_PWD proyecto-paradigmas-db-1 mysql -uroot --default-character-set=utf8mb4"

$MYSQL -e "DROP DATABASE IF EXISTS \`$NOMBRE\`; CREATE DATABASE \`$NOMBRE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
for archivo in Database/SqlScripts/000instalacioncompleta.sql Database/SeedData/101initialpagometodo.sql \
    Database/SeedData/102administrador.sql Database/SeedData/103exampleproductores.sql; do
    sed "s/bdmercadoganadero/$NOMBRE/g" "$RAIZ/$archivo" | $MYSQL
done
echo "Base '$NOMBRE' lista: $($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$NOMBRE'") tablas."
