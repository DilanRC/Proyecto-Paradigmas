#!/usr/bin/env bash
#
# Genera el ZIP de entrega para XAMPP a partir del commit actual (nunca del árbol sin
# confirmar: así .env, respaldos y archivos locales no pueden colarse).
#
#   bash Tools/empaquetar-entrega.sh [carpeta-de-salida]
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

# Un secreto confirmado por error no debe salir: se revisa el contenido real del ZIP.
# Las claves reales de Supabase son largas; las pruebas usan marcadores cortos (sb_secret_de_prueba).
# Sin -q: con pipefail, un grep que corta en la primera coincidencia mata a unzip (SIGPIPE)
# y el control daría por limpio un paquete con secretos.
if unzip -Z1 "$ZIP" | grep -E '(^|/)\.env$|\.pem$|(^|/)id_rsa' >/dev/null; then
    echo "ERROR: el paquete contiene un archivo de credenciales." >&2; rm -f "$ZIP"; exit 1
fi
if unzip -p "$ZIP" | grep -aE 'sb_secret_[A-Za-z0-9_-]{20,}|-----BEGIN [A-Z ]*PRIVATE KEY-----|postgres(ql)?://[^:/@[:space:]]+:[^@[:space:]]{6,}@' >/dev/null; then
    echo "ERROR: el paquete contiene algo con forma de secreto." >&2; rm -f "$ZIP"; exit 1
fi

echo "ZIP:     $ZIP"
echo "Commit:  $(git -C "$RAIZ" rev-parse HEAD)"
echo "Archivos: $(unzip -Z1 "$ZIP" | grep -vc '/$')"
echo "Tamaño:  $(du -h "$ZIP" | cut -f1)"
echo "SHA-256: $(sha256sum "$ZIP" | cut -d' ' -f1)"
