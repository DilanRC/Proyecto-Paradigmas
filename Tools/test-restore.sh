#!/usr/bin/env bash
set -Eeuo pipefail

readonly SOURCE_DATABASE='dbtindercows'
readonly RESTORE_DATABASE='dbtindercows_restore_test'
readonly PARTS_DATABASE='dbtindercows_restore_parts_test'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"

usage() {
    echo "Uso: $0 AvanceNN[CorreccionNN]" >&2
}

advance="${1:-}"
if [[ ! "$advance" =~ ^Avance([0-9]{2})(Correccion([0-9]{2}))?$ ]]; then
    usage
    exit 2
fi

advance_number="${BASH_REMATCH[1]}"
correction_number="${BASH_REMATCH[3]:-}"
advance_slug="avance${advance_number}"
if [[ -n "$correction_number" ]]; then
    advance_slug+="_correccion${correction_number}"
fi
backup_dir="$PROJECT_ROOT/Database/Backups/$advance"
complete_file="$backup_dir/${SOURCE_DATABASE}_${advance_slug}_completo.sql"
schema_file="$backup_dir/${SOURCE_DATABASE}_${advance_slug}_estructura.sql"
data_file="$backup_dir/${SOURCE_DATABASE}_${advance_slug}_datos.sql"
checksums_file="$backup_dir/SHA256SUMS.txt"
manifest_file="$backup_dir/MANIFEST.md"

if [[ ! -s "$complete_file" || ! -s "$schema_file" || ! -s "$data_file" || ! -s "$checksums_file" || ! -s "$manifest_file" ]]; then
    echo "Error: faltan el respaldo completo, sus sumas o el manifiesto en $backup_dir" >&2
    exit 1
fi

cd -- "$PROJECT_ROOT"
docker compose config --quiet
docker compose exec -T db sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null

(
    cd -- "$backup_dir"
    sha256sum -c SHA256SUMS.txt
)

mysql_query() {
    local query="$1"
    docker compose exec -T -e "CHECK_SQL=$query" db sh -c \
        'exec mysql -N -B -uroot -p"$MYSQL_ROOT_PASSWORD" -e "$CHECK_SQL"'
}

restore_created=0
parts_created=0
cleanup() {
    if [[ "$restore_created" -eq 1 ]]; then
        mysql_query "DROP DATABASE IF EXISTS ${RESTORE_DATABASE};" >/dev/null 2>&1 || true
    fi
    if [[ "$parts_created" -eq 1 ]]; then
        mysql_query "DROP DATABASE IF EXISTS ${PARTS_DATABASE};" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

if [[ "$(mysql_query "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${RESTORE_DATABASE}';")" -ne 0 ]]; then
    echo "Error: ${RESTORE_DATABASE} ya existe; no se eliminará una base temporal ajena." >&2
    exit 1
fi
if [[ "$(mysql_query "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${PARTS_DATABASE}';")" -ne 0 ]]; then
    echo "Error: ${PARTS_DATABASE} ya existe; no se eliminará una base temporal ajena." >&2
    exit 1
fi
mysql_query "CREATE DATABASE ${RESTORE_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
restore_created=1
docker compose exec -T db sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dbtindercows_restore_test' \
    < "$complete_file"

mysql_query "CREATE DATABASE ${PARTS_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
parts_created=1
docker compose exec -T db sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dbtindercows_restore_parts_test' \
    < "$schema_file"
docker compose exec -T db sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dbtindercows_restore_parts_test' \
    < "$data_file"

table_diff="$(mysql_query "
    SELECT COUNT(*) FROM (
        SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE'
        UNION ALL
        SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '${RESTORE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE'
    ) all_tables
    GROUP BY TABLE_NAME HAVING COUNT(*) <> 2;
" | wc -l)"

constraint_diff="$(mysql_query "
    SELECT COUNT(*) FROM (
        SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE
        FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '${SOURCE_DATABASE}'
        UNION ALL
        SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE
        FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '${RESTORE_DATABASE}'
    ) all_constraints
    GROUP BY TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE HAVING COUNT(*) <> 2;
" | wc -l)"

index_diff="$(mysql_query "
    SELECT COUNT(*) FROM (
        SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE
        FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}'
        UNION ALL
        SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE
        FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${RESTORE_DATABASE}'
    ) all_indexes
    GROUP BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE HAVING COUNT(*) <> 2;
" | wc -l)"

if [[ "$table_diff" -ne 0 || "$constraint_diff" -ne 0 || "$index_diff" -ne 0 ]]; then
    echo "Error de integridad: tablas=$table_diff, restricciones=$constraint_diff, indices=$index_diff" >&2
    exit 1
fi

parts_table_diff="$(mysql_query "
    SELECT COUNT(*) FROM (
        SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${RESTORE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE'
        UNION ALL
        SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${PARTS_DATABASE}' AND TABLE_TYPE = 'BASE TABLE'
    ) x GROUP BY TABLE_NAME HAVING COUNT(*) <> 2;
" | wc -l)"
parts_constraint_diff="$(mysql_query "
    SELECT COUNT(*) FROM (
        SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '${RESTORE_DATABASE}'
        UNION ALL
        SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '${PARTS_DATABASE}'
    ) x GROUP BY TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE HAVING COUNT(*) <> 2;
" | wc -l)"
parts_index_diff="$(mysql_query "
    SELECT COUNT(*) FROM (
        SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${RESTORE_DATABASE}'
        UNION ALL
        SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${PARTS_DATABASE}'
    ) x GROUP BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE HAVING COUNT(*) <> 2;
" | wc -l)"
if [[ "$parts_table_diff" -ne 0 || "$parts_constraint_diff" -ne 0 || "$parts_index_diff" -ne 0 ]]; then
    echo "Error: estructura+datos difiere del respaldo completo." >&2
    exit 1
fi

printf '%-38s %10s %10s %10s\n' 'Tabla' 'Origen' 'Completo' 'Partes'
mapfile -t tables < <(mysql_query "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME;")
for table in "${tables[@]}"; do
    source_count="$(mysql_query "SELECT COUNT(*) FROM ${SOURCE_DATABASE}.${table};")"
    restored_count="$(mysql_query "SELECT COUNT(*) FROM ${RESTORE_DATABASE}.${table};")"
    parts_count="$(mysql_query "SELECT COUNT(*) FROM ${PARTS_DATABASE}.${table};")"
    printf '%-38s %10s %10s %10s\n' "$table" "$source_count" "$restored_count" "$parts_count"
    if [[ "$source_count" != "$restored_count" || "$restored_count" != "$parts_count" ]]; then
        echo "Error: el conteo difiere para $table." >&2
        exit 1
    fi
done

if [[ " ${tables[*]} " == *' tbproductores '* ]]; then
    mysql_query "SELECT tbproductoresIdentificacionNumero, tbproductoresNombre
        FROM ${RESTORE_DATABASE}.tbproductores
        ORDER BY tbproductoresIdentificacionNumero LIMIT 1;" >/dev/null
else
    mysql_query "SELECT p.tbparticipanteId, p.tbparticipanteNombre
        FROM ${RESTORE_DATABASE}.tbparticipante p
        INNER JOIN ${RESTORE_DATABASE}.tbparticipanterol pr ON pr.tbparticipanteId = p.tbparticipanteId
        INNER JOIN ${RESTORE_DATABASE}.tbrol r ON r.tbrolId = pr.tbrolId
        WHERE r.tbrolCodigo = 'PRODUCTOR' LIMIT 1;" >/dev/null
fi

source_tables="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE';")"
source_constraints="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '${SOURCE_DATABASE}';")"
source_indexes="$(mysql_query "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}';")"

manifest_temp="$(mktemp "${TMPDIR:-/tmp}/tindercows-manifest.XXXXXX")"
sed \
    -e 's/^- Restauración probada: .*/- Restauración probada: Sí/' \
    -e 's/^- Resultado de integridad: .*/- Resultado de integridad: Correcto/' \
    -e "s|^- Observaciones: .*|- Observaciones: Restauración completa y estructura+datos verificadas; tablas=${source_tables}, restricciones=${source_constraints}, filas de índice=${source_indexes}.|" \
    "$manifest_file" > "$manifest_temp"
mv -- "$manifest_temp" "$manifest_file"

echo "Restauración correcta: tablas=$source_tables, restricciones=$source_constraints, filas_de_indice=$source_indexes."
echo "La consulta funcional de productores se ejecutó correctamente."
echo "La base temporal ${RESTORE_DATABASE} se eliminará al finalizar."
echo "La base temporal ${PARTS_DATABASE} se eliminará al finalizar."
