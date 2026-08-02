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

metadata_diff() {
    local left_database="$1"
    local right_database="$2"
    local schema_column="$3"
    local information_table="$4"
    local columns="$5"
    local extra_filter="${6:-}"
    local output=''
    if ! output="$(mysql_query "
        SELECT COUNT(*) FROM (
            SELECT CONCAT_WS(CHAR(31), ${columns}) AS signature FROM information_schema.${information_table}
            WHERE ${schema_column} = '${left_database}' ${extra_filter}
            UNION ALL
            SELECT CONCAT_WS(CHAR(31), ${columns}) AS signature FROM information_schema.${information_table}
            WHERE ${schema_column} = '${right_database}' ${extra_filter}
        ) compared
        GROUP BY signature HAVING COUNT(*) <> 2;
    ")"; then
        return 1
    fi
    if [[ -z "$output" ]]; then
        echo 0
    else
        awk 'END { print NR }' <<< "$output"
    fi
}

compare_structure() {
    local left_database="$1"
    local right_database="$2"
    local label="$3"
    local differences=0
    local current=0
    local definitions=(
        "TABLE_SCHEMA|TABLES|TABLE_NAME, ENGINE, TABLE_COLLATION|AND TABLE_TYPE = 'BASE TABLE'"
        "TABLE_SCHEMA|COLUMNS|TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COALESCE(COLUMN_DEFAULT, '<NULL>'), EXTRA, COALESCE(GENERATION_EXPRESSION, ''), COALESCE(CHARACTER_SET_NAME, ''), COALESCE(COLLATION_NAME, '')|"
        "CONSTRAINT_SCHEMA|TABLE_CONSTRAINTS|TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE|"
        "CONSTRAINT_SCHEMA|KEY_COLUMN_USAGE|TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION, COLUMN_NAME, COALESCE(REFERENCED_TABLE_NAME, ''), COALESCE(REFERENCED_COLUMN_NAME, '')|"
        "CONSTRAINT_SCHEMA|CHECK_CONSTRAINTS|CONSTRAINT_NAME, CHECK_CLAUSE|"
        "CONSTRAINT_SCHEMA|REFERENTIAL_CONSTRAINTS|TABLE_NAME, CONSTRAINT_NAME, UNIQUE_CONSTRAINT_NAME, MATCH_OPTION, UPDATE_RULE, DELETE_RULE|"
        "TABLE_SCHEMA|STATISTICS|TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE|"
    )
    for definition in "${definitions[@]}"; do
        IFS='|' read -r schema_column information_table columns extra_filter <<< "$definition"
        if ! current="$(metadata_diff "$left_database" "$right_database" "$schema_column" "$information_table" "$columns" "$extra_filter")"; then
            echo "Falló la consulta de comparación ${label} en ${information_table}." >&2
            return 1
        fi
        if [[ "$current" -ne 0 ]]; then
            echo "Diferencia ${label} en ${information_table}: ${current}" >&2
            differences=$((differences + current))
        fi
    done
    [[ "$differences" -eq 0 ]]
}

compare_structure "$SOURCE_DATABASE" "$RESTORE_DATABASE" 'origen/completo' \
    || { echo 'Error: la estructura restaurada completa difiere del origen.' >&2; exit 1; }
compare_structure "$RESTORE_DATABASE" "$PARTS_DATABASE" 'completo/estructura+datos' \
    || { echo 'Error: estructura+datos difiere del respaldo completo.' >&2; exit 1; }

for database in "$SOURCE_DATABASE" "$RESTORE_DATABASE" "$PARTS_DATABASE"; do
    read -r database_charset database_collation < <(mysql_query "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
        FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${database}';")
    if [[ "$database_charset" != 'utf8mb4' || "$database_collation" != 'utf8mb4_unicode_ci' ]]; then
        echo "Error: ${database} usa ${database_charset}/${database_collation}." >&2
        exit 1
    fi
    invalid_collations="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '${database}' AND TABLE_TYPE = 'BASE TABLE'
          AND TABLE_COLLATION <> 'utf8mb4_unicode_ci';")"
    if [[ "$invalid_collations" -ne 0 ]]; then
        echo "Error: ${database} contiene ${invalid_collations} tablas con intercalación incorrecta." >&2
        exit 1
    fi
done

invalid_fk_rules="$(mysql_query "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA IN ('${SOURCE_DATABASE}', '${RESTORE_DATABASE}', '${PARTS_DATABASE}')
      AND (UPDATE_RULE <> 'RESTRICT' OR DELETE_RULE <> 'RESTRICT');")"
if [[ "$invalid_fk_rules" -ne 0 ]]; then
    echo "Error: se encontraron ${invalid_fk_rules} FK sin reglas RESTRICT." >&2
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
    source_checksum="$(mysql_query "CHECKSUM TABLE ${SOURCE_DATABASE}.${table};" | awk '{print $2}')"
    restored_checksum="$(mysql_query "CHECKSUM TABLE ${RESTORE_DATABASE}.${table};" | awk '{print $2}')"
    parts_checksum="$(mysql_query "CHECKSUM TABLE ${PARTS_DATABASE}.${table};" | awk '{print $2}')"
    if [[ "$source_checksum" != "$restored_checksum" || "$restored_checksum" != "$parts_checksum" ]]; then
        echo "Error: los datos difieren para ${table}." >&2
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
source_indexes="$(mysql_query "SELECT COUNT(DISTINCT TABLE_NAME, INDEX_NAME)
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}';")"

manifest_temp="$(mktemp "${TMPDIR:-/tmp}/tindercows-manifest.XXXXXX")"
sed \
    -e 's/^- Intercalación comprobada: .*/- Intercalación comprobada: utf8mb4\/utf8mb4_unicode_ci en base y cuatro tablas/' \
    -e 's/^- Restauración completa comprobada: .*/- Restauración completa comprobada: Sí/' \
    -e 's/^- Restauración estructura + datos comprobada: .*/- Restauración estructura + datos comprobada: Sí/' \
    -e "s/^- Cantidad de tablas: .*/- Cantidad de tablas: ${source_tables}/" \
    -e "s/^- Cantidad de restricciones: .*/- Cantidad de restricciones: ${source_constraints}/" \
    -e "s/^- Cantidad de índices: .*/- Cantidad de índices: ${source_indexes}/" \
    -e 's/^- Resultado final: .*/- Resultado final: APROBADO/' \
    -e "s|^- Observaciones: .*|- Observaciones: Estructura, datos, PK, FK, CHECK, índices, reglas RESTRICT, intercalación y conteos sin diferencias.|" \
    "$manifest_file" > "$manifest_temp"
mv -- "$manifest_temp" "$manifest_file"

echo "Restauración correcta: tablas=$source_tables, restricciones=$source_constraints, indices=$source_indexes."
echo "La consulta funcional de productores se ejecutó correctamente."
echo "La base temporal ${RESTORE_DATABASE} se eliminará al finalizar."
echo "La base temporal ${PARTS_DATABASE} se eliminará al finalizar."
