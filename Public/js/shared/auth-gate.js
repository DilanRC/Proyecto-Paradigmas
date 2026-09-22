// Puerta del frontend administrativo.
//
// IMPORTANTE: una sesión pública Supabase identifica a una Persona, pero NO
// demuestra que esa Persona sea administradora. Calidad pidió separar sesión
// pública y administrativa; mientras la política admin no esté aprobada, este
// gate usa denegación por defecto en vez de reutilizar el viejo booleano local.

import { inicializarUbicacionAutomatica } from './ubicacion-sesion.js';
import { readAuthSession } from './supabase-auth.js';

export const SESSION_KEY = 'tindercows:admin-session';

/**
 * El marcador solo habilita la navegación del shell. La autorización real se
 * vuelve a comprobar en cada API mediante el Bearer y la allowlist del
 * servidor; por eso nunca contiene credenciales ni sustituye al JWT.
 */
export function writeAdminBrowserSession(email, storage = globalThis.sessionStorage) {
    if (!storage?.setItem) return;
    storage.setItem(SESSION_KEY, JSON.stringify({
        authenticated: true,
        version: 1,
        adminAuthorized: true,
        email: String(email ?? '').trim().toLowerCase(),
        startedAt: new Date().toISOString(),
        mode: 'admin-server-session',
    }));
}

export function clearAdminBrowserSession(storage = globalThis.sessionStorage) {
    storage?.removeItem?.(SESSION_KEY);
}

const PRIVATE_ROUTES = new Set([
    'admin/dashboard',
    'admin/productores',
    'admin/compradores',
    'admin/transportistas',
    'admin/vehiculos',
    'admin/metodos-pago',
]);

/**
 * Contrato reservado para una futura sesión administrativa emitida/verificada
 * por servidor. Ningún flujo público actual escribe esta clave.
 */
export function readBrowserSession(storage) {
    try {
        const raw = storage?.getItem(SESSION_KEY);
        if (!raw) return null;
        const session = JSON.parse(raw);
        if (
            session?.authenticated !== true
            || session?.adminAuthorized !== true
            || session?.version !== 1
            || session?.mode !== 'admin-server-session'
            || typeof session?.startedAt !== 'string'
            || Number.isNaN(Date.parse(session.startedAt))
        ) return null;
        return session;
    } catch {
        return null;
    }
}

export function routeName(pathname = '') {
    const parts = String(pathname).split('/').filter(Boolean);
    return parts[0] === 'admin' ? parts.slice(0, 2).join('/') : parts.at(-1) || '';
}

export function isPrivateRoute(pathname = '') {
    return PRIVATE_ROUTES.has(routeName(pathname));
}

export function loginTarget(pathname = '') {
    const route = routeName(pathname);
    return PRIVATE_ROUTES.has(route)
        ? `admin/entrar?next=${encodeURIComponent(route)}`
        : 'entrar';
}

export function enforceBrowserSession({ location, storage } = {}) {
    if (!location || !isPrivateRoute(location.pathname)) return true;
    if (readBrowserSession(storage)) return true;
    location.replace(loginTarget(location.pathname));
    return false;
}

function wirePrivateShell(storage) {
    const publicLink = document.querySelector('.rural-panel__admin-link[href="./"]');
    if (publicLink) {
        publicLink.textContent = 'Sitio público';
        publicLink.setAttribute('aria-label', 'Volver al sitio público de TinderCows');
        publicLink.title = 'Inicio público de TinderCows';
    }

    const logoutLink = document.querySelector('.rural-panel__admin-link[href="admin/entrar"]');
    if (!logoutLink) return;
    logoutLink.textContent = 'Cerrar sesión administrativa';
    logoutLink.setAttribute('aria-label', 'Cerrar sesión administrativa');
    logoutLink.addEventListener('click', (event) => {
        event.preventDefault();
        try { clearAdminBrowserSession(storage); }
        finally { window.location.assign('admin/entrar'); }
    });
}

function revealPrivateUi() {
    if (typeof document !== 'undefined') document.documentElement.dataset.tcAuth = 'ready';
}

if (typeof window !== 'undefined') {
    const allowed = enforceBrowserSession({ location: window.location, storage: window.sessionStorage });
    const hasVerifiedAuthSession = readAuthSession(window.sessionStorage) !== null;
    if (allowed && isPrivateRoute(window.location.pathname) && !hasVerifiedAuthSession) {
        clearAdminBrowserSession(window.sessionStorage);
        window.location.replace(loginTarget(window.location.pathname));
    } else if (allowed && typeof document !== 'undefined') {
        revealPrivateUi();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                wirePrivateShell(window.sessionStorage);
                inicializarUbicacionAutomatica();
            }, { once: true });
        } else {
            wirePrivateShell(window.sessionStorage);
            inicializarUbicacionAutomatica();
        }
    }
}
