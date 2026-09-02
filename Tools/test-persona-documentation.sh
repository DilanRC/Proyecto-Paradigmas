#!/usr/bin/env bash
set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"

require_text() {
    local file="$1"
    local text="$2"
    if ! grep -Fq -- "$text" "$PROJECT_ROOT/$file"; then
        echo "Falta en ${file}: ${text}" >&2
        exit 1
    fi
}

require_text Documentation/DER.md 'El modelo contiene exactamente 30 tablas.'
require_text Documentation/DER.md 'tbpersona ||--o| tbproductor'
require_text Documentation/DER.md 'tbpersona ||--o| tbcomprador'
require_text Documentation/DER.md 'tbpersona ||--o| tbtransportista'
require_text Documentation/DiccionarioDatos.md '`tbpersonaestado`'
require_text Documentation/DiccionarioDatos.md '`tbpersonaid` | `INT NOT NULL`'
require_text Documentation/Decisiones.md '`DELETE` desactiva exclusivamente el perfil'
require_text Documentation/Respaldos.md 'fallo SQL o cualquier salida por stderr'
require_text README.md 'contiene exactamente 30 tablas'
require_text Tools/test-restore.sh 'Respaldo validado sin modificar MANIFEST ni SHA256'
require_text Tools/Test-Restore.ps1 'Respaldo validado sin modificar MANIFEST ni SHA256'

python3 "$SCRIPT_DIR/generate-documentation-pdfs.py" --check

echo 'Gate documentación persona: APROBADO'
echo 'Eval documentación persona: 12/12 (100%)'
