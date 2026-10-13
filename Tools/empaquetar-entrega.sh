#!/usr/bin/env bash
#
# Genera el ZIP de entrega para XAMPP a partir del commit actual (nunca del árbol sin
# confirmar: así respaldos y archivos locales no pueden colarse) y le agrega el `.env` de la
# entrega, que no se versiona: MySQL local de XAMPP y la configuración pública de Supabase.
# El ZIP es un entregable privado para Calidad; no se sube al repositorio ni al PR.
#
#   bash Tools/empaquetar-entrega.sh [carpeta-de-salida]
#   ENTREGA_ENV_PRIVADO=/ruta/.env bash Tools/empaquetar-entrega.sh [carpeta-de-salida]
#
# Con ENTREGA_ENV_PRIVADO, el `.env` del ZIP lleva además la SUPABASE_SECRET_KEY de ese archivo local
# (decisión expresa del responsable del proyecto para la entrega a Calidad). Esa clave administra el
# proyecto de Supabase: el ZIP resultante solo se entrega en privado y la clave se rota después.
#
# Resultado: <salida>/GanadoCerca.zip con una sola carpeta raíz, GanadoCerca/.

set -Eeuo pipefail

RAIZ="$(git -C "$(dirname -- "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)"
SALIDA="$(mkdir -p "${1:-$RAIZ/outputs}" && cd "${1:-$RAIZ/outputs}" && pwd)"
ZIP="$SALIDA/GanadoCerca.zip"

if [[ -n "$(git -C "$RAIZ" status --porcelain)" ]]; then
    echo "ERROR: hay cambios sin confirmar o archivos sin rastrear; el ZIP sale del commit, no del árbol de trabajo." >&2
    exit 1
fi

# Fuera del paquete: instrucciones y memoria de agentes, configuración de herramientas
# de desarrollo y los respaldos históricos de la base.
EXCLUIDOS=(AGENTS.md CLAUDE.md MEMORIA.md .agents .codebase-memory .mcp.json Database/Backups)
RUTAS=(.)
for ruta in "${EXCLUIDOS[@]}"; do RUTAS+=(":(exclude)$ruta"); done

rm -f "$ZIP"
git -C "$RAIZ" archive --format=zip --prefix=GanadoCerca/ -o "$ZIP" HEAD -- "${RUTAS[@]}"

# `.env` de la entrega. La URL y la clave publicable de Supabase son públicas por diseño (el
# navegador las recibe de api/v1/auth/config) y salen de .env.example del mismo commit. Nunca
# lleva una URL de PostgreSQL: DB_DRIVER=mysql fija la base local. La clave secreta solo entra si
# se pide con ENTREGA_ENV_PRIVADO, se lee de ese archivo sin versionar y no se imprime.
command -v zip >/dev/null || { echo "ERROR: falta el programa zip." >&2; rm -f "$ZIP"; exit 1; }
valor() { git -C "$RAIZ" show "HEAD:.env.example" | sed -n "s/^$1=//p" | head -n 1; }
SUPABASE_URL="$(valor SUPABASE_URL)"
SUPABASE_PUBLISHABLE_KEY="$(valor SUPABASE_PUBLISHABLE_KEY)"
if [[ ! "$SUPABASE_URL" =~ ^https://[a-z0-9]+\.supabase\.co$ || ! "$SUPABASE_PUBLISHABLE_KEY" =~ ^sb_publishable_[A-Za-z0-9_-]{20,}$ ]]; then
    echo "ERROR: .env.example no trae una URL y una clave publicable de Supabase válidas." >&2; rm -f "$ZIP"; exit 1
fi
SUPABASE_SECRET_KEY=""
if [[ -n "${ENTREGA_ENV_PRIVADO:-}" ]]; then
    SUPABASE_SECRET_KEY="$(sed -n 's/^SUPABASE_SECRET_KEY=//p' "$ENTREGA_ENV_PRIVADO" | head -n 1)"
    if [[ ! "$SUPABASE_SECRET_KEY" =~ ^sb_secret_[A-Za-z0-9_-]{20,}$ ]]; then
        echo "ERROR: $ENTREGA_ENV_PRIVADO no trae una SUPABASE_SECRET_KEY válida." >&2; rm -f "$ZIP"; exit 1
    fi
fi
TEMPORAL="$(mktemp -d)"
trap 'rm -rf "$TEMPORAL"' EXIT
mkdir "$TEMPORAL/GanadoCerca"
cat > "$TEMPORAL/GanadoCerca/.env" <<ENV
# Configuración de la entrega local para XAMPP. No hace falta editar este archivo.
# Si su MySQL usa otro puerto o contraseña, escríbalos en el asistente (Public/instalacion).

# Base de datos: MySQL/MariaDB de XAMPP en esta computadora.
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bdmercadoganadero
DB_USER=root
DB_PASS=

# Cuentas: Supabase Auth (requiere Internet). Son los valores públicos que usa el navegador.
SUPABASE_URL=$SUPABASE_URL
SUPABASE_PUBLISHABLE_KEY=$SUPABASE_PUBLISHABLE_KEY
ENV
if [[ -n "$SUPABASE_SECRET_KEY" ]]; then
    printf '\n# Clave del servidor: nunca llega al navegador. Permite ver documentos de identidad en el panel\n# de administración, cambiar el correo de una cuenta y los avisos instantáneos de subastas.\nSUPABASE_SECRET_KEY=%s\n' \
        "$SUPABASE_SECRET_KEY" >> "$TEMPORAL/GanadoCerca/.env"
fi
chmod 644 "$TEMPORAL/GanadoCerca/.env"
(cd "$TEMPORAL" && zip -q -X "$ZIP" GanadoCerca/.env)

# Un secreto confirmado por error no debe salir: se revisa el contenido real del ZIP.
# Las claves reales de Supabase son largas; las pruebas usan marcadores cortos (sb_secret_de_prueba).
# Sin -q: con pipefail, un grep que corta en la primera coincidencia mata a unzip (SIGPIPE)
# y el control daría por limpio un paquete con secretos.
if [[ "$(unzip -Z1 "$ZIP" | grep -cE '(^|/)\.env$')" != 1 ]] || ! unzip -Z1 "$ZIP" | grep -qxF 'GanadoCerca/.env'; then
    echo "ERROR: el paquete debe traer exactamente un .env, en GanadoCerca/.env." >&2; rm -f "$ZIP"; exit 1
fi
if unzip -Z1 "$ZIP" | grep -E '\.pem$|(^|/)id_rsa' >/dev/null; then
    echo "ERROR: el paquete contiene un archivo de credenciales." >&2; rm -f "$ZIP"; exit 1
fi
if unzip -p "$ZIP" 'GanadoCerca/.env' | grep -E '^POSTGRES[A-Z_]*=.' >/dev/null; then
    echo "ERROR: el .env de la entrega no puede apuntar a PostgreSQL." >&2; rm -f "$ZIP"; exit 1
fi
if [[ -z "$SUPABASE_SECRET_KEY" ]] && unzip -p "$ZIP" 'GanadoCerca/.env' | grep -E '^SUPABASE_SECRET_KEY=.' >/dev/null; then
    echo "ERROR: el .env trae una clave secreta que no se pidió." >&2; rm -f "$ZIP"; exit 1
fi
if unzip -p "$ZIP" -x 'GanadoCerca/.env' | grep -aE 'sb_secret_[A-Za-z0-9_-]{20,}|-----BEGIN [A-Z ]*PRIVATE KEY-----|postgres(ql)?://[^:/@[:space:]]+:[^@[:space:]]{6,}@' >/dev/null; then
    echo "ERROR: el paquete contiene algo con forma de secreto." >&2; rm -f "$ZIP"; exit 1
fi

echo "Clave secreta de Supabase en .env: $([[ -n "$SUPABASE_SECRET_KEY" ]] && echo 'SÍ (entrega privada; rotarla después)' || echo no)"
echo "ZIP:     $ZIP"
echo "Commit:  $(git -C "$RAIZ" rev-parse HEAD)"
echo "Archivos: $(unzip -Z1 "$ZIP" | grep -vc '/$')"
echo "Tamaño:  $(du -h "$ZIP" | cut -f1)"
echo "SHA-256: $(sha256sum "$ZIP" | cut -d' ' -f1)"
