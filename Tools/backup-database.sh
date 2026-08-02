#!/usr/bin/env bash
set -Eeuo pipefail

readonly DATABASE_NAME='dbtindercows'
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"

usage() {
    echo "Uso: $0 AvanceNN[CorreccionNN] [responsable]" >&2
}

advance="${1:-}"
if [[ ! "$advance" =~ ^Avance([0-9]{2})(Correccion([0-9]{2}))?$ ]]; then
    usage
    exit 2
fi

advance_number="${BASH_REMATCH[1]}"
correction_number="${BASH_REMATCH[3]:-}"

responsible="${2:-$(git -C "$PROJECT_ROOT" config user.name || true)}"
if [[ -z "$responsible" ]]; then
    echo 'Error: indique el responsable o configure git user.name.' >&2
    exit 2
fi

advance_slug="avance${advance_number}"
official_tag="avance-${advance_number}"
if [[ -n "$correction_number" ]]; then
    advance_slug+="_correccion${correction_number}"
    official_tag+="-correccion-${correction_number}"
fi
target_dir="$PROJECT_ROOT/Database/Backups/$advance"
complete_file="$target_dir/${DATABASE_NAME}_${advance_slug}_completo.sql"
schema_file="$target_dir/${DATABASE_NAME}_${advance_slug}_estructura.sql"
data_file="$target_dir/${DATABASE_NAME}_${advance_slug}_datos.sql"
manifest_file="$target_dir/MANIFEST.md"
checksums_file="$target_dir/SHA256SUMS.txt"

mkdir -p -- "$target_dir"
for output in "$complete_file" "$schema_file" "$data_file" "$manifest_file" "$checksums_file"; do
    if [[ -e "$output" ]]; then
        echo "Error: no se sobrescribe un respaldo existente: $output" >&2
        exit 1
    fi
done

cd -- "$PROJECT_ROOT"
if [[ -n "$(git status --porcelain --untracked-files=all)" ]]; then
    echo 'Error: cree primero el commit candidato; el árbol de trabajo debe estar limpio.' >&2
    exit 1
fi
docker compose config --quiet
if ! docker compose exec -T db sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null; then
    echo 'Error: el servicio db no esta disponible o saludable.' >&2
    exit 1
fi

candidate_commit="$(git rev-parse HEAD)"
branch="$(git branch --show-current)"
mysql_version="$(docker compose exec -T db sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT VERSION()"' | tr -d '\r')"
generated_at="$(date --iso-8601=minutes)"
temp_dir="$(mktemp -d "${TMPDIR:-/tmp}/tindercows-backup.XXXXXX")"
success=0
writes_frozen=0
read_only_state=''
super_read_only_state=''
restore_writes() {
    if [[ "$writes_frozen" -eq 1 ]]; then
        docker compose exec -T -e "READ_ONLY_STATE=$read_only_state" -e "SUPER_READ_ONLY_STATE=$super_read_only_state" db sh -c \
            'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=$READ_ONLY_STATE; SET GLOBAL super_read_only=$SUPER_READ_ONLY_STATE;"' >/dev/null
        writes_frozen=0
    fi
}
cleanup() {
    restore_writes || true
    rm -f -- "$temp_dir/complete.sql" "$temp_dir/schema.sql" "$temp_dir/data.sql"
    rmdir -- "$temp_dir" 2>/dev/null || true
    if [[ "$success" -ne 1 ]]; then
        rm -f -- "$complete_file" "$schema_file" "$data_file" "$manifest_file" "$checksums_file"
    fi
}
trap cleanup EXIT

read -r read_only_state super_read_only_state < <(
    docker compose exec -T db sh -c \
        'mysql -N -B -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT @@GLOBAL.read_only, @@GLOBAL.super_read_only"' | tr -d '\r'
)
docker compose exec -T db sh -c \
    'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL read_only=ON; SET GLOBAL super_read_only=ON;"' >/dev/null
writes_frozen=1

docker compose exec -T db sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 dbtindercows' \
    > "$temp_dir/complete.sql"
docker compose exec -T db sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --no-data --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 dbtindercows' \
    > "$temp_dir/schema.sql"
docker compose exec -T db sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --no-create-info --single-transaction --skip-triggers --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 dbtindercows' \
    > "$temp_dir/data.sql"

for dump in "$temp_dir/complete.sql" "$temp_dir/schema.sql" "$temp_dir/data.sql"; do
    if [[ ! -s "$dump" ]] || ! grep -q 'MySQL dump' "$dump"; then
        echo "Error: mysqldump produjo un archivo vacio o invalido: $dump" >&2
        exit 1
    fi
done

restore_writes

mv -- "$temp_dir/complete.sql" "$complete_file"
mv -- "$temp_dir/schema.sql" "$schema_file"
mv -- "$temp_dir/data.sql" "$data_file"

cat > "$manifest_file" <<EOF
# Respaldo — $advance

- Proyecto: TinderCows
- Base de datos: $DATABASE_NAME
- Entrega: $advance
- Fecha y hora: $generated_at
- Motor: MySQL $mysql_version
- Rama: $branch
- Commit candidato de código: $candidate_commit
- Etiqueta oficial: $official_tag
- Responsable de exportación: $responsible
- Archivo completo: $(basename -- "$complete_file")
- Archivo de estructura: $(basename -- "$schema_file")
- Archivo de datos: $(basename -- "$data_file")
- Intercalación comprobada: Pendiente
- Restauración completa comprobada: Pendiente
- Restauración estructura + datos comprobada: Pendiente
- Bases temporales utilizadas: dbtindercows_restore_test, dbtindercows_restore_parts_test
- Cantidad de tablas: Pendiente
- Cantidad de restricciones: Pendiente
- Cantidad de índices: Pendiente
- Cantidad de PRIMARY KEY: Pendiente
- Cantidad de FOREIGN KEY: Pendiente
- Cantidad de CHECK: Pendiente
- Resultado final: Pendiente
- Observaciones: Ejecutar Tools/test-restore.sh $advance y registrar el resultado antes de etiquetar.
EOF

(
    cd -- "$target_dir"
    sha256sum "$(basename -- "$complete_file")" "$(basename -- "$schema_file")" "$(basename -- "$data_file")" > SHA256SUMS.txt
    sha256sum -c SHA256SUMS.txt
)

success=1
echo "Respaldo generado sin sobrescribir archivos previos: $target_dir"
echo "Siguiente paso obligatorio: Tools/test-restore.sh $advance"
