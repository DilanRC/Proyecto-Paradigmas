// Puerta de navegacion del frontend privado.
//
// Esta capa controla la sesion de demostracion del navegador. La ubicacion
// automatica que inicia aqui es efimera y solo prepara la experiencia por
// cercania/centrado; no escribe datos de Persona, Productor ni historicos.

import { inicializarUbicacionAutomatica } from './ubicacion-sesion.js';

export const SESSION_KEY = 'tindercows:login';

const PRIVATE_ROUTES = new Set([
    'productores.php',
    'compradores.php',
    'transportistas.php',
    'vehiculos.php',
    'pagometodos.php',
]);

export function readBrowserSession(storage) {
    try {
        const raw = storage?.getItem(SESSION_KEY);
        if (!raw) return null;
        const session = JSON.parse(raw);
        if (
            session?.authenticated !== true
            || session?.version !== 1
            || session?.mode !== 'local-browser-session'
            || typeof session?.email !== 'string'
            || session.email.trim() === ''
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
    return parts.at(-1) || 'index.php';
}

export function isPrivateRoute(pathname = '') {
    return PRIVATE_ROUTES.has(routeName(pathname));
}

export function loginTarget(pathname = '') {
    const route = routeName(pathname);
    return PRIVATE_ROUTES.has(route)
        ? `login.php?next=${encodeURIComponent(route)}`
        : 'login.php';
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
        publicLink.textContent = 'Sitio publico';
        publicLink.setAttribute('aria-label', 'Volver al sitio publico de TinderCows');
        publicLink.title = 'Inicio publico de TinderCows';
    }

    const logoutLink = document.querySelector('.rural-panel__admin-link[href="login.php"]');
    if (!logoutLink) return;
    logoutLink.textContent = 'Cerrar sesion';
    logoutLink.setAttribute('aria-label', 'Cerrar sesion de demostracion');
    logoutLink.addEventListener('click', (event) => {
        event.preventDefault();
        try { storage?.removeItem(SESSION_KEY); }
        finally { window.location.assign('login.php'); }
    });
}

function revealPrivateUi() {
    if (typeof document !== 'undefined') document.documentElement.dataset.tcAuth = 'ready';
}

if (typeof window !== 'undefined') {
    const allowed = enforceBrowserSession({ location: window.location, storage: window.sessionStorage });
    if (allowed && typeof document !== 'undefined') {
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
