// Puerta del frontend administrativo.
//
// IMPORTANTE: una sesión pública Supabase identifica a una Persona, pero NO
// demuestra que esa Persona sea administradora. Calidad pidió separar sesión
// pública y administrativa; mientras la política admin no esté aprobada, este
// gate usa denegación por defecto en vez de reutilizar el viejo booleano local.

import { authSessionIdentity, readAuthSession, SESSION_KEY as AUTH_SESSION_KEY, signOut } from './supabase-auth.js?v=session-4';
import { PROFILE_KEY } from './public-profile.js?v=profile-2';

export const SESSION_KEY = 'tindercows:admin-session';

function availableBrowserStorage(target = globalThis) {
    try {
        const persistent = target.localStorage;
        if (persistent) return persistent;
    } catch { /* se usa el almacenamiento temporal si el navegador bloquea el persistente */ }
    try { return target.sessionStorage ?? null; } catch { return null; }
}

function availableTabStorage(target = globalThis) {
    try { return target.sessionStorage ?? null; } catch { return null; }
}

/**
 * El marcador solo habilita la navegación del shell. La autorización real se
 * vuelve a comprobar en cada API mediante el Bearer y la allowlist del
 * servidor; por eso nunca contiene credenciales ni sustituye al JWT.
 */
export function writeAdminBrowserSession(email, storage = availableBrowserStorage()) {
    if (!storage?.setItem) return;
    const session = JSON.stringify({
        authenticated: true,
        version: 1,
        adminAuthorized: true,
        email: String(email ?? '').trim().toLowerCase(),
        startedAt: new Date().toISOString(),
        mode: 'admin-server-session',
    });
    try { storage.setItem(SESSION_KEY, session); } catch {
        const fallback = availableTabStorage();
        if (fallback && fallback !== storage) {
            try { fallback.setItem(SESSION_KEY, session); } catch { /* el marcador no debe bloquear el sitio público */ }
        }
    }
}

export function clearAdminBrowserSession(storage = availableBrowserStorage()) {
    try { storage?.removeItem?.(SESSION_KEY); } catch { /* la salida de sesión debe continuar */ }
}

/** Recarga las otras pestañas cuando cambia la cuenta o se cierra sesión. */
export function watchAuthSessionAcrossTabs(targetWindow = globalThis.window, tabStorage = null) {
    if (!targetWindow?.addEventListener) return () => {};
    let sharedStorage;
    try { sharedStorage = targetWindow.localStorage; } catch { return () => {}; }
    if (!sharedStorage) return () => {};
    if (tabStorage === null) {
        tabStorage = availableTabStorage(targetWindow);
    }

    const handleStorage = (event) => {
        if (event.key !== AUTH_SESSION_KEY || event.storageArea !== sharedStorage) return;
        if (authSessionIdentity(event.oldValue) === authSessionIdentity(event.newValue)) return;

        try { tabStorage?.removeItem?.(PROFILE_KEY); } catch { /* el perfil en caché es opcional */ }
        clearAdminBrowserSession(sharedStorage);
        targetWindow.location?.reload?.();
    };
    targetWindow.addEventListener('storage', handleStorage);
    return () => targetWindow.removeEventListener?.('storage', handleStorage);
}

/**
 * Cierre de sesión completo, igual desde el sitio público y desde el panel
 * admin: revoca la sesión Supabase, borra el permiso admin del navegador y
 * vuelve a Inicio. Si solo se borrara el permiso admin, el JWT seguiría vivo y
 * el menú de cuenta volvería a ofrecer el panel sin pedir contraseña.
 */
export async function signOutEverywhere(storage = availableBrowserStorage()) {
    try { await signOut(storage ? { storage } : {}); } finally {
        clearAdminBrowserSession(storage);
        globalThis.location?.assign('./');
    }
}

const PRIVATE_ROUTES = new Set([
    'admin/dashboard',
    'admin/productores',
    'admin/compradores',
    'admin/transportistas',
    'admin/vehiculos',
    'admin/metodos-pago',
    'admin/publicaciones',
    'admin/administradores',
    'admin/bitacora',
    'admin/fletes',
    'admin/documentos',
    'admin/catalogos',
    'admin/subastas',
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
    return parts[0] === 'admin' ? parts.slice(0, 2).join('/') : parts[parts.length - 1] || '';
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

    // Todas las vistas admin traen el enlace de salida (algunas como "entrar",
    // otras como "admin/entrar"): todas cierran la sesión completa.
    const logoutLink = document.querySelector('.rural-panel__admin-link[href$="entrar"]');
    if (!logoutLink) return;
    logoutLink.textContent = 'Cerrar sesión';
    logoutLink.setAttribute('aria-label', 'Cerrar sesión y volver al inicio');
    logoutLink.addEventListener('click', (event) => {
        event.preventDefault();
        void signOutEverywhere(storage);
    });
}

function revealPrivateUi() {
    if (typeof document !== 'undefined') document.documentElement.dataset.tcAuth = 'ready';
}

if (typeof window !== 'undefined') {
    watchAuthSessionAcrossTabs();
    const pathname = window.location.pathname;
    // Las rutas públicas, incluido /entrar, no deben inicializar el shell
    // privado ni ejecutar efectos que puedan provocar navegación o recargas.
    if (isPrivateRoute(pathname)) {
        const storage = availableBrowserStorage(window);
        const allowed = enforceBrowserSession({ location: window.location, storage });
        const hasVerifiedAuthSession = readAuthSession() !== null;
        if (allowed && !hasVerifiedAuthSession) {
            clearAdminBrowserSession(storage);
            window.location.replace(loginTarget(pathname));
        } else if (allowed && typeof document !== 'undefined') {
            revealPrivateUi();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    wirePrivateShell(storage);
                }, { once: true });
            } else {
                wirePrivateShell(storage);
            }
        }
    }
}
