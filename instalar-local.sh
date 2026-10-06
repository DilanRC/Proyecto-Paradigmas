#!/usr/bin/env bash
#
# instalar-local.sh
# Instala la base de datos del proyecto en una PC nueva (Linux).
# Equivalente de instalar-local.ps1 para Windows.
#
# Detecta automaticamente:
#   - El cliente mysql o mariadb disponible en el PATH.
#   - Como entrar como root: contrasena vacia, DB_ROOT_PASS del .env,
#     autenticacion por socket con sudo (lo normal en Ubuntu/Debian) y,
#     si nada sirve, la pide por pantalla.
#   - Usuario, contrasena y nombre de la base desde el .env.
#
# Uso (con MySQL/MariaDB encendido), en la raiz del proyecto:
#     bash instalar-local.sh
#
# Para borrar la base y reinstalar desde cero:
#     bash instalar-local.sh --reinstalar

set -Eeuo pipefail

readonly PROYECTO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

REINSTALAR=0
for arg in "$@"; do
    case "$arg" in
        --reinstalar|-r) REINSTALAR=1 ;;
        -h|--help) sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opcion desconocida: $arg (usa --reinstalar)" >&2; exit 2 ;;
    esac
done

if [[ -t 1 ]]; then
    ROJO=$'\e[31m'; AMARILLO=$'\e[33m'; VERDE=$'\e[32m'; NORMAL=$'\e[0m'
else
    ROJO=''; AMARILLO=''; VERDE=''; NORMAL=''
fi

aviso() { echo "${AMARILLO}[AVISO] $*${NORMAL}"; }
fallo() {
    echo
    echo "${ROJO}ERROR: $*${NORMAL}" >&2
    exit 1
}

# ---------------------------------------------------------------
# 1. Leer el .env
# ---------------------------------------------------------------
declare -A CFG=()
ENV_RUTA="$PROYECTO/.env"
if [[ -f "$ENV_RUTA" ]]; then
    while IFS= read -r linea || [[ -n "$linea" ]]; do
        linea="${linea%$'\r'}"
        [[ "$linea" =~ ^[[:space:]]*# ]] && continue
        if [[ "$linea" =~ ^[[:space:]]*([A-Za-z0-9_]+)[[:space:]]*=[[:space:]]*(.*)$ ]]; then
            clave="${BASH_REMATCH[1]}"
            valor="${BASH_REMATCH[2]}"
            valor="${valor%"${valor##*[![:space:]]}"}"
            if [[ "$valor" =~ ^\"(.*)\"$ || "$valor" =~ ^\'(.*)\'$ ]]; then
                valor="${BASH_REMATCH[1]}"
            fi
            CFG["$clave"]="$valor"
        fi
    done < "$ENV_RUTA"
    echo "[OK] .env leido."
else
    aviso "No hay .env en $PROYECTO; se usan valores por defecto."
fi

DB_NAME="${CFG[DB_NAME]:-bdmercadoganadero}"
DB_USER="${CFG[DB_USER]:-tinder_cows}"
DB_PASS="${CFG[DB_PASS]:-tinder_vacas_dev}"
ROOT_ENV="${CFG[DB_ROOT_PASS]:-}"

if [[ "$DB_NAME" != "bdmercadoganadero" ]]; then
    aviso "DB_NAME es '$DB_NAME' pero 000instalacioncompleta.sql crea 'bdmercadoganadero'."
    aviso "Ajusta el .env o el script SQL para que coincidan."
fi

# ---------------------------------------------------------------
# 2. Encontrar el cliente
# ---------------------------------------------------------------
CLI=""
for candidato in mysql mariadb /opt/lampp/bin/mysql; do
    if command -v "$candidato" >/dev/null 2>&1; then
        CLI="$(command -v "$candidato")"
        break
    fi
done
[[ -n "$CLI" ]] || fallo "No encontre el cliente mysql/mariadb. Instalalo (ej. sudo apt install mariadb-client) y vuelve a intentar."
echo "[OK] Cliente: $CLI"

# ---------------------------------------------------------------
# 3. Encontrar como entrar como root
# ---------------------------------------------------------------
# ROOT_CMD guarda el prefijo para correr el cliente como root.
ROOT_CMD=()
ROOT_PASS=""

probar_root() {
    MYSQL_PWD="$1" "$CLI" -u root --connect-timeout=5 -e "SELECT 1" >/dev/null 2>&1
}

for intento in "" "$ROOT_ENV"; do
    if probar_root "$intento"; then
        ROOT_CMD=("$CLI" -u root)
        ROOT_PASS="$intento"
        break
    fi
    [[ -n "$ROOT_ENV" ]] || break
done

if [[ ${#ROOT_CMD[@]} -eq 0 ]] && command -v sudo >/dev/null 2>&1; then
    echo "[..] Probando root por socket con sudo (puede pedir tu contrasena de usuario)..."
    if sudo "$CLI" -u root --connect-timeout=5 -e "SELECT 1" >/dev/null 2>&1; then
        ROOT_CMD=(sudo "$CLI" -u root)
    fi
fi

if [[ ${#ROOT_CMD[@]} -eq 0 ]]; then
    aviso "root no acepta contrasena vacia, la del .env ni el socket."
    read -r -s -p "Escribe la contrasena de root de MySQL: " plano
    echo
    if probar_root "$plano"; then
        ROOT_CMD=("$CLI" -u root)
        ROOT_PASS="$plano"
    fi
fi

[[ ${#ROOT_CMD[@]} -gt 0 ]] || fallo "No pude entrar como root. Si no recuerdas la contrasena, reseteala con --init-file y vuelve a correr el script."
echo "[OK] root autenticado."

# Corre el cliente como root con la contrasena encontrada.
root_sql() {
    if [[ "${ROOT_CMD[0]}" == "sudo" ]]; then
        "${ROOT_CMD[@]}" "$@"
    else
        MYSQL_PWD="$ROOT_PASS" "${ROOT_CMD[@]}" "$@"
    fi
}

# ---------------------------------------------------------------
# 4. Reinstalar (opcional)
# ---------------------------------------------------------------
if [[ $REINSTALAR -eq 1 ]]; then
    echo "[..] Borrando la base $DB_NAME..."
    root_sql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`;" || fallo "No pude borrar la base."
fi

# ---------------------------------------------------------------
# 5. Usuario del proyecto
# ---------------------------------------------------------------
echo "[..] Creando usuario $DB_USER..."
root_sql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
             ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
             GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
             FLUSH PRIVILEGES;" || fallo "No pude crear el usuario $DB_USER."
echo "[OK] Usuario listo."

# ---------------------------------------------------------------
# 6. Esquema (solo si no existe)
# ---------------------------------------------------------------
existe="$(root_sql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='tbpersona';")"
if [[ "$existe" == "1" ]]; then
    echo "[OK] El esquema ya existe; no lo reinstalo (usa --reinstalar para empezar de cero)."
else
    echo "[..] Instalando esquema..."
    SQL_INST="$PROYECTO/Database/SqlScripts/000instalacioncompleta.sql"
    [[ -f "$SQL_INST" ]] || fallo "No existe $SQL_INST"
    root_sql --default-character-set=utf8mb4 < "$SQL_INST" \
        || fallo "Fallo el instalador. Revisa el mensaje de arriba (linea del error)."
    echo "[OK] Esquema instalado."
    root_sql -e "FLUSH PRIVILEGES;"
fi

# ---------------------------------------------------------------
# 7. Seeds (solo si las tablas estan vacias)
# ---------------------------------------------------------------
metodos="$(root_sql -N -B "$DB_NAME" -e "SELECT COUNT(*) FROM tbpagometodo;")"
if [[ "$metodos" == "0" ]]; then
    echo "[..] Cargando seeds..."
    for s in 101initialpagometodo 102administrador 104catalogosanimal; do
        archivo="$PROYECTO/Database/SeedData/$s.sql"
        [[ -f "$archivo" ]] || fallo "No existe $archivo"
        root_sql --default-character-set=utf8mb4 "$DB_NAME" < "$archivo" || fallo "Fallo el seed $s."
    done
    echo "[OK] Seeds cargados."
else
    echo "[OK] Los seeds ya estaban cargados."
fi

# ---------------------------------------------------------------
# 8. Verificar entrando con el usuario del proyecto
# ---------------------------------------------------------------
# -h 127.0.0.1 fuerza TCP, que es como se conecta la app.
tablas="$(MYSQL_PWD="$DB_PASS" "$CLI" -h 127.0.0.1 -u "$DB_USER" -N -B "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null \
    || MYSQL_PWD="$DB_PASS" "$CLI" -u "$DB_USER" -N -B "$DB_NAME" -e "SHOW TABLES;")" \
    || fallo "El usuario $DB_USER no puede entrar a $DB_NAME."
admin="$(MYSQL_PWD="$DB_PASS" "$CLI" -u "$DB_USER" -N -B "$DB_NAME" -e "SELECT tbadministradorcorreoelectronico FROM tbadministrador;")"

echo
echo "${VERDE}=========== RESULTADO ===========${NORMAL}"
echo "Tablas:         $(grep -c . <<< "$tablas")"
echo "Administrador:  $admin"
ADMIN_EMAILS="${CFG[SUPABASE_ADMIN_EMAILS]:-}"
if [[ -n "$ADMIN_EMAILS" && -n "$admin" && "$ADMIN_EMAILS" != *"$admin"* ]]; then
    aviso "El administrador de la base no coincide con SUPABASE_ADMIN_EMAILS del .env."
fi
echo "${VERDE}Listo. Enciende el servidor web y prueba la app.${NORMAL}"
