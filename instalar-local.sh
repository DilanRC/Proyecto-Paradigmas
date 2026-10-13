#!/usr/bin/env bash
#
# instalar-local.sh
# Instala la base de datos del proyecto en una PC nueva (Linux).
# Equivalente de instalar-local.ps1 para Windows.
#
# Detecta automaticamente:
#   - El cliente mysql o mariadb disponible en el PATH.
#   - Como entrar como root: contrasena vacia, DB_ROOT_PASS del .env,
#     sin ejecutar sudo automaticamente y,
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
        if [[ "$linea" =~ ^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)[[:space:]]*=[[:space:]]*(.*)$ ]]; then
            clave="${BASH_REMATCH[2]}"
            valor="${BASH_REMATCH[3]}"
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

configuracion() {
    local nombre="$1" defecto="$2"
    if [[ -v "$nombre" ]]; then printf '%s' "${!nombre}"; else printf '%s' "${CFG[$nombre]-$defecto}"; fi
}
DB_NAME="$(configuracion DB_NAME bdmercadoganadero)"
DB_USER="$(configuracion DB_USER tinder_cows)"
DB_PASS="$(configuracion DB_PASS tinder_vacas_dev)"
DB_HOST="$(configuracion DB_HOST 127.0.0.1)"
DB_PORT="$(configuracion DB_PORT 3306)"
DB_ACCOUNT_HOST="$(configuracion DB_ACCOUNT_HOST localhost)"
ROOT_ENV="$(configuracion DB_ROOT_PASS '')"
XAMPP_ROOT="$(configuracion XAMPP_ROOT /opt/lampp)"
CLI="$(configuracion MYSQL_CLIENT '')"
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && ${#DB_NAME} -le 64 ]] || fallo "DB_NAME debe ser un identificador SQL simple de hasta 64 caracteres."
[[ "$DB_USER" =~ ^[A-Za-z0-9_]+$ && ${#DB_USER} -le 32 ]] || fallo "DB_USER debe contener solo letras, numeros o guion bajo (hasta 32)."
[[ "$DB_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$ ]] || fallo "DB_ACCOUNT_HOST no es valido."
[[ "$DB_PORT" =~ ^[0-9]{1,5}$ ]] && ((10#$DB_PORT > 0 && 10#$DB_PORT <= 65535)) || fallo "DB_PORT debe estar entre 1 y 65535."
[[ -n "$DB_HOST" && "$DB_HOST" != -* && "$DB_HOST" != *$'\n'* ]] || fallo "DB_HOST no es valido."
[[ "$DB_NAME" == bdmercadoganadero ]] || fallo "El esquema y las semillas usan bdmercadoganadero. Ajusta DB_NAME antes de instalar."
if [[ -z "$CLI" ]]; then
    for candidato in "$XAMPP_ROOT/bin/mysql" "$XAMPP_ROOT/bin/mariadb" mysql mariadb; do
        if command -v "$candidato" >/dev/null 2>&1; then CLI="$(command -v "$candidato")"; break; fi
    done
fi
[[ -n "$CLI" ]] && command -v "$CLI" >/dev/null 2>&1 || fallo "No encontre el cliente. Configura MYSQL_CLIENT o XAMPP_ROOT."
echo "[OK] Cliente: $CLI; destino TCP: $DB_HOST:$DB_PORT / $DB_NAME"
CONEXION=(--no-defaults --binary-mode --protocol=TCP --host="$DB_HOST" --port="$DB_PORT" --connect-timeout=5)
probar_root() { MYSQL_PWD="$1" "$CLI" "${CONEXION[@]}" -u root -e "SELECT 1" >/dev/null 2>&1; }
ROOT_PASS="$ROOT_ENV"
if ! probar_root "$ROOT_PASS"; then
    [[ -t 0 ]] || fallo "No pude autenticar root por TCP. Configura DB_ROOT_PASS; para socket usa phpMyAdmin o un administrador local."
    read -r -s -p "Contrasena de root de MySQL para $DB_HOST:$DB_PORT: " ROOT_PASS
    echo
    probar_root "$ROOT_PASS" || fallo "No pude autenticar root por TCP. No se ejecuta sudo automaticamente."
fi
root_sql() { MYSQL_PWD="$ROOT_PASS" "$CLI" "${CONEXION[@]}" -u root "$@"; }
app_sql() { MYSQL_PWD="$DB_PASS" "$CLI" "${CONEXION[@]}" -u "$DB_USER" "$@"; }
echo "[OK] root autenticado."
# La cuenta existente se comprueba antes de cualquier cambio o borrado.
usuario_existe="$(root_sql -N -B -e "SELECT COUNT(*) FROM mysql.user WHERE User='$DB_USER' AND Host='$DB_ACCOUNT_HOST';")"
if [[ "$usuario_existe" != 0 ]]; then
    app_sql -e 'SELECT 1' >/dev/null 2>&1 || fallo "La cuenta existente no acepta DB_PASS desde este destino. No se cambia su contrasena; corrige la configuracion."
fi

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
# NO_BACKSLASH_ESCAPES y apostrofes duplicados hacen literal la contrasena.
# El SQL viaja por stdin para no exponer credenciales en argumentos de procesos.
PASS_SQL="${DB_PASS//\'/\'\'}"
{
    printf "SET SESSION sql_mode='NO_BACKSLASH_ESCAPES';\n"
    if [[ "$usuario_existe" == 0 ]]; then
        printf "CREATE USER '%s'@'%s' IDENTIFIED BY '%s';\n" "$DB_USER" "$DB_ACCOUNT_HOST" "$PASS_SQL"
    fi
    printf 'GRANT ALL PRIVILEGES ON `%s`.* TO '\''%s'\''@'\''%s'\'';\n' "$DB_NAME" "$DB_USER" "$DB_ACCOUNT_HOST"
} | root_sql 2>/dev/null || fallo "No pude preparar el usuario $DB_USER."
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
tablas="$(app_sql -N -B "$DB_NAME" -e 'SHOW TABLES;')" || fallo "El usuario $DB_USER no puede entrar a $DB_NAME."
admin="$(app_sql -N -B "$DB_NAME" -e 'SELECT tbadministradorcorreoelectronico FROM tbadministrador;')"

echo
echo "${VERDE}=========== RESULTADO ===========${NORMAL}"
echo "Tablas:         $(grep -c . <<< "$tablas")"
echo "Administrador:  $admin"
ADMIN_EMAILS="${CFG[SUPABASE_ADMIN_EMAILS]:-}"
if [[ -n "$ADMIN_EMAILS" && -n "$admin" && "$ADMIN_EMAILS" != *"$admin"* ]]; then
    aviso "El administrador de la base no coincide con SUPABASE_ADMIN_EMAILS del .env."
fi
echo "${VERDE}Listo. Enciende el servidor web y prueba la app.${NORMAL}"
