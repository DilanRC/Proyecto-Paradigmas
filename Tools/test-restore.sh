#!/usr/bin/env bash
set -Eeuo pipefail

readonly SOURCE_DATABASE='dbtindervacas'
readonly RESTORE_DATABASE='dbtindervacas_restore_test'
readonly PARTS_DATABASE='dbtindervacas_restore_parts_test'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
readonly INJECT_INVALID_METADATA="${RESTORE_TEST_INJECT_INVALID_METADATA:-0}"
readonly INJECT_SCHEMA_DIFFERENCE="${RESTORE_TEST_INJECT_SCHEMA_DIFFERENCE:-0}"

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
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h 127.0.0.1 -uroot --silent' >/dev/null

manifest_pending="$(mktemp "${TMPDIR:-/tmp}/tindercows-manifest-pending.XXXXXX")"
sed \
    -e 's/^- Intercalación comprobada: .*/- Intercalación comprobada: Pendiente/' \
    -e 's/^- Restauración completa comprobada: .*/- Restauración completa comprobada: Pendiente/' \
    -e 's/^- Restauración estructura + datos comprobada: .*/- Restauración estructura + datos comprobada: Pendiente/' \
    -e 's/^- Cantidad de tablas: .*/- Cantidad de tablas: Pendiente/' \
    -e 's/^- Cantidad de restricciones: .*/- Cantidad de restricciones: Pendiente/' \
    -e 's/^- Cantidad de índices: .*/- Cantidad de índices: Pendiente/' \
    -e 's/^- Cantidad de PRIMARY KEY: .*/- Cantidad de PRIMARY KEY: Pendiente/' \
    -e 's/^- Cantidad de FOREIGN KEY: .*/- Cantidad de FOREIGN KEY: Pendiente/' \
    -e 's/^- Cantidad de CHECK: .*/- Cantidad de CHECK: Pendiente/' \
    -e 's/^- Resultado final: .*/- Resultado final: Pendiente/' \
    "$manifest_file" > "$manifest_pending"
mv -- "$manifest_pending" "$manifest_file"

(
    cd -- "$backup_dir"
    sha256sum -c SHA256SUMS.txt
)

mysql_query() {
    local query="$1"
    local error_file output
    error_file="$(mktemp "${TMPDIR:-/tmp}/tindercows-mysql-stderr.XXXXXX")"
    if ! output="$(docker compose exec -T -e "CHECK_SQL=$query" db sh -c \
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -N -B -uroot -e "$CHECK_SQL"' 2>"$error_file")"; then
        cat "$error_file" >&2
        rm -f -- "$error_file"
        return 1
    fi
    if [[ -s "$error_file" ]]; then
        cat "$error_file" >&2
        rm -f -- "$error_file"
        return 1
    fi
    rm -f -- "$error_file"
    printf '%s\n' "$output"
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
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot dbtindervacas_restore_test' \
    < "$complete_file"

mysql_query "CREATE DATABASE ${PARTS_DATABASE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
parts_created=1
docker compose exec -T db sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot dbtindervacas_restore_parts_test' \
    < "$schema_file"
docker compose exec -T db sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot dbtindervacas_restore_parts_test' \
    < "$data_file"

if [[ "$INJECT_SCHEMA_DIFFERENCE" == '1' ]]; then
    mysql_query "ALTER TABLE ${PARTS_DATABASE}.tbproductor
        ADD CONSTRAINT ck_injected_metadata CHECK (tbproductorid IS NOT NULL);" >/dev/null
fi

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
    local tables_metadata='TABLES'
    if [[ "$INJECT_INVALID_METADATA" == '1' ]]; then
        tables_metadata='TABLES_INVALIDA'
    fi
    local definitions=(
        "TABLE_SCHEMA|${tables_metadata}|TABLE_NAME, ENGINE, TABLE_COLLATION|AND TABLE_TYPE = 'BASE TABLE'"
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
    if ! database_settings="$(mysql_query "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
        FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${database}';")"; then
        exit 1
    fi
    read -r database_charset database_collation <<< "$database_settings"
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
    constraint_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = '${database}';")"
    if [[ "$constraint_count" -ne 0 ]]; then
        echo "Error: ${database} contiene ${constraint_count} restricciones." >&2
        exit 1
    fi
    foreign_key_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = '${database}' AND CONSTRAINT_TYPE = 'FOREIGN KEY';")"
    if [[ "$foreign_key_count" -ne 0 ]]; then
        echo "Error: ${database} contiene ${foreign_key_count} FOREIGN KEY." >&2
        exit 1
    fi
    primary_key_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = '${database}' AND CONSTRAINT_TYPE = 'PRIMARY KEY';")"
    check_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = '${database}' AND CONSTRAINT_TYPE = 'CHECK';")"
    if [[ "$primary_key_count" -ne 0 || "$check_count" -ne 0 ]]; then
        echo "Error: ${database} contiene PK=${primary_key_count} o CHECK=${check_count}." >&2
        exit 1
    fi
    tables_csv="$(mysql_query "SELECT GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '${database}' AND TABLE_TYPE = 'BASE TABLE';")"
    if [[ "$tables_csv" != 'tbbitacora,tbfinca,tbproductor,tbproductordireccion' ]]; then
        echo "Error: ${database} contiene tablas inesperadas: ${tables_csv}." >&2
        exit 1
    fi
    invalid_indexes="$(mysql_query "SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = '${database}';")"
    if [[ "$invalid_indexes" -ne 0 ]]; then
        echo "Error: ${database} contiene ${invalid_indexes} índices." >&2
        exit 1
    fi
    productor_id_extra="$(mysql_query "SELECT EXTRA FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '${database}' AND TABLE_NAME = 'tbproductor' AND COLUMN_NAME = 'tbproductorid';")"
    if [[ -n "$productor_id_extra" ]]; then
        echo "Error: ${database}.tbproductorid usa EXTRA=${productor_id_extra}." >&2
        exit 1
    fi
    automatic_columns="$(mysql_query "SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '${database}'
          AND (COLUMN_DEFAULT IS NOT NULL OR EXTRA <> '' OR GENERATION_EXPRESSION <> '');")"
    programmable_objects="$(mysql_query "SELECT
        (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '${database}') +
        (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '${database}') +
        (SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = '${database}');")"
    if [[ "$automatic_columns" -ne 0 || "$programmable_objects" -ne 0 ]]; then
        echo "Error: ${database} contiene columnas automáticas=${automatic_columns} u objetos programables=${programmable_objects}." >&2
        exit 1
    fi
done

printf '%-38s %10s %10s %10s\n' 'Tabla' 'Origen' 'Completo' 'Partes'
if ! tables_output="$(mysql_query "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME;")"; then
    exit 1
fi
mapfile -t tables <<< "$tables_output"
for table in "${tables[@]}"; do
    source_count="$(mysql_query "SELECT COUNT(*) FROM ${SOURCE_DATABASE}.${table};")"
    restored_count="$(mysql_query "SELECT COUNT(*) FROM ${RESTORE_DATABASE}.${table};")"
    parts_count="$(mysql_query "SELECT COUNT(*) FROM ${PARTS_DATABASE}.${table};")"
    printf '%-38s %10s %10s %10s\n' "$table" "$source_count" "$restored_count" "$parts_count"
    if [[ "$source_count" != "$restored_count" || "$restored_count" != "$parts_count" ]]; then
        echo "Error: el conteo difiere para $table." >&2
        exit 1
    fi
    source_checksum_output="$(mysql_query "CHECKSUM TABLE ${SOURCE_DATABASE}.${table};")"
    restored_checksum_output="$(mysql_query "CHECKSUM TABLE ${RESTORE_DATABASE}.${table};")"
    parts_checksum_output="$(mysql_query "CHECKSUM TABLE ${PARTS_DATABASE}.${table};")"
    source_checksum="$(awk '{print $2}' <<< "$source_checksum_output")"
    restored_checksum="$(awk '{print $2}' <<< "$restored_checksum_output")"
    parts_checksum="$(awk '{print $2}' <<< "$parts_checksum_output")"
    if [[ "$source_checksum" != "$restored_checksum" || "$restored_checksum" != "$parts_checksum" ]]; then
        echo "Error: los datos difieren para ${table}." >&2
        exit 1
    fi
done

mysql_query "SELECT tbproductorid, tbproductoridentificacionnumero, tbproductornombre
    FROM ${RESTORE_DATABASE}.tbproductor ORDER BY tbproductorid LIMIT 1;" >/dev/null

source_tables="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}' AND TABLE_TYPE = 'BASE TABLE';")"
source_constraints="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '${SOURCE_DATABASE}';")"
source_indexes="$(mysql_query "SELECT COUNT(DISTINCT TABLE_NAME, INDEX_NAME)
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '${SOURCE_DATABASE}';")"
source_primary_keys="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = '${SOURCE_DATABASE}' AND CONSTRAINT_TYPE = 'PRIMARY KEY';")"
source_foreign_keys="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = '${SOURCE_DATABASE}' AND CONSTRAINT_TYPE = 'FOREIGN KEY';")"
source_check_count="$(mysql_query "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = '${SOURCE_DATABASE}' AND CONSTRAINT_TYPE = 'CHECK';")"

manifest_temp="$(mktemp "${TMPDIR:-/tmp}/tindercows-manifest.XXXXXX")"
sed \
    -e 's/^- Intercalación comprobada: .*/- Intercalación comprobada: utf8mb4\/utf8mb4_unicode_ci en base y cuatro tablas/' \
    -e 's/^- Restauración completa comprobada: .*/- Restauración completa comprobada: Sí/' \
    -e 's/^- Restauración estructura + datos comprobada: .*/- Restauración estructura + datos comprobada: Sí/' \
    -e "s/^- Cantidad de tablas: .*/- Cantidad de tablas: ${source_tables}/" \
    -e "s/^- Cantidad de restricciones: .*/- Cantidad de restricciones: ${source_constraints}/" \
    -e "s/^- Cantidad de índices: .*/- Cantidad de índices: ${source_indexes}/" \
    -e "s/^- Cantidad de PRIMARY KEY: .*/- Cantidad de PRIMARY KEY: ${source_primary_keys}/" \
    -e "s/^- Cantidad de FOREIGN KEY: .*/- Cantidad de FOREIGN KEY: ${source_foreign_keys}/" \
    -e "s/^- Cantidad de CHECK: .*/- Cantidad de CHECK: ${source_check_count}/" \
    -e 's/^- Resultado final: .*/- Resultado final: APROBADO/' \
    -e "s|^- Observaciones: .*|- Observaciones: Estructura, datos, cero PK, cero FK, cero CHECK, cero índices, cero AUTO_INCREMENT, intercalación y conteos sin diferencias.|" \
    "$manifest_file" > "$manifest_temp"
mv -- "$manifest_temp" "$manifest_file"

echo "Restauración correcta: tablas=$source_tables, restricciones=$source_constraints, indices=$source_indexes."
echo "La consulta funcional de productores se ejecutó correctamente."
echo "La base temporal ${RESTORE_DATABASE} se eliminará al finalizar."
echo "La base temporal ${PARTS_DATABASE} se eliminará al finalizar."
