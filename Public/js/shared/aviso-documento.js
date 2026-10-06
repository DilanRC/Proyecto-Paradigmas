// Aviso de un solo uso después de crear la cuenta (P2-5): invita a subir el
// documento de identidad en Ajustes → Perfil.
//
// El registro termina en registro.js (con sesión) o en login.js (al confirmar
// el correo); los dos marcan la pestaña y Explorar muestra el aviso una vez.
// Funciona igual con o sin confirmación de correo, porque el aviso aparece
// cuando ya hay sesión. Si el destino no es Explorar, la marca espera ahí.

export const AVISO_DOCUMENTO_KEY = 'tindercows:aviso-documento';

/** Marca la pestaña para mostrar el aviso. Sin almacenamiento no hace nada. */
export function marcarAvisoDocumento(storage = globalThis.sessionStorage) {
    try { storage?.setItem(AVISO_DOCUMENTO_KEY, '1'); } catch { /* sin almacenamiento, sin aviso */ }
}

/** Devuelve si hay que mostrar el aviso y borra la marca (un solo uso). */
export function tomarAvisoDocumento(storage = globalThis.sessionStorage) {
    try {
        const pendiente = storage?.getItem(AVISO_DOCUMENTO_KEY) === '1';
        storage?.removeItem(AVISO_DOCUMENTO_KEY);
        return pendiente;
    } catch {
        return false;
    }
}

function iniciar() {
    // Solo la página que tiene el aviso consume la marca.
    const aviso = document.querySelector('[data-aviso-documento]');
    if (!aviso || !tomarAvisoDocumento()) return;
    aviso.hidden = false;
    aviso.querySelector('[data-aviso-cerrar]')?.addEventListener('click', () => { aviso.hidden = true; });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', iniciar);
