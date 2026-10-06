import { inicializarUbicacionAutomatica } from './shared/ubicacion-sesion.js';
import { readAuthSession, getAccessToken } from './shared/supabase-auth.js';
import { readPublicProfile } from './shared/public-profile.js';
import { signOutEverywhere, writeAdminBrowserSession } from './shared/auth-gate.js?v=auth-gate-5';

const SESSION_KEY = 'tindercows:login';
const PROFILE_KEY = 'tindercows:profile';

function ensureProductStyles() {
    if (document.querySelector('link[data-tc-public-product]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/public-product.css?v=product-7';
    link.dataset.tcPublicProduct = 'true';
    document.head.appendChild(link);
}

function readStorage(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function readSession() { return readAuthSession(); }

function setSearchOpen(root, open) {
    if (!root) return;
    const safeOpen = open === true;
    root.dataset.open = String(safeOpen);
    const toggle = root.querySelector('[data-public-search-toggle]');
    const input = root.querySelector('input[type="search"]');
    const label = safeOpen ? 'Cerrar búsqueda' : 'Buscar';
    toggle?.setAttribute('aria-expanded', String(safeOpen));
    toggle?.setAttribute('aria-label', label);
    const visibleLabel = toggle?.querySelector('span');
    if (visibleLabel) visibleLabel.textContent = label;
    if (safeOpen) requestAnimationFrame(() => input?.focus());
}

function initializePublicSearch() {
    for (const root of document.querySelectorAll('[data-public-search]')) {
        if (root.dataset.ready === 'true') continue;
        root.dataset.ready = 'true';
        const toggle = root.querySelector('[data-public-search-toggle]');
        const input = root.querySelector('input[type="search"]');
        if (!toggle || !input) continue;

        toggle.addEventListener('click', () => setSearchOpen(root, root.dataset.open !== 'true'));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                input.value = '';
                setSearchOpen(root, false);
                toggle.focus();
            }
        });
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target) && input.value.trim() === '') setSearchOpen(root, false);
        });
    }
}

function addNavLink(nav, href, icon, label) {
    if (!nav || nav.querySelector(`a[href="${href}"]`)) return;
    const link = document.createElement('a');
    link.href = href;
    link.innerHTML = `<i class="fa-solid ${icon}" aria-hidden="true"></i><span>${label}</span>`;
    if (href === 'publicar') {
        const fletes = nav.querySelector('a[href="fletes"]');
        if (fletes) {
            fletes.before(link);
            return;
        }
    }
    nav.append(link);
}

function createAccountMenu(actions, session, profile) {
    const login = actions.querySelector('.public-header__login');
    if (!login || actions.querySelector('[data-public-account]')) return null;

    const menu = document.createElement('div');
    menu.className = 'public-account-menu';
    menu.dataset.publicAccount = 'true';
    const name = profile?.persona?.nombre || session.email || 'Mi cuenta';
    const initial = String(name).trim().charAt(0).toUpperCase() || 'U';
    const photo = safeHttpsUrl(profile?.persona?.fotoUrl);
    menu.innerHTML = `
        <button class="public-account-menu__trigger" type="button" aria-expanded="false" aria-controls="public-account-panel">
            <span class="public-account-menu__avatar" aria-hidden="true">${photo ? `<img src="${escapeHtml(photo)}" alt="" referrerpolicy="no-referrer">` : initial}</span>
            <span class="public-account-menu__label">Mi perfil</span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="public-account-menu__panel" id="public-account-panel" hidden>
            <div class="public-account-menu__identity"><strong>${escapeHtml(profile?.persona?.nombre || 'Cuenta activa')}</strong><small>${escapeHtml(session.email)}</small></div>
            <a href="mi-actividad"><i class="fa-solid fa-table-columns" aria-hidden="true"></i><span>Mi panel</span></a>
            <a href="me-interesa"><i class="fa-solid fa-heart" aria-hidden="true"></i><span>Me interesa</span></a>
            <a href="ajustes"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Ajustes de cuenta</span></a>
            <a href="admin/dashboard" data-admin-link hidden><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Panel admin</span></a>
            <button type="button" data-public-logout><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Cerrar sesión</span></button>
        </div>`;

    login.replaceWith(menu);
    // Si la foto ya no existe, vuelve la inicial.
    const avatarImg = menu.querySelector('.public-account-menu__avatar img');
    avatarImg?.addEventListener('error', () => { avatarImg.parentElement.textContent = initial; }, { once: true });
    const trigger = menu.querySelector('.public-account-menu__trigger');
    const panel = menu.querySelector('.public-account-menu__panel');
    trigger.addEventListener('click', () => {
        const open = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', String(!open));
        panel.hidden = open;
    });
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            trigger.setAttribute('aria-expanded', 'false');
            panel.hidden = true;
        }
    });
    menu.querySelector('[data-public-logout]')?.addEventListener('click', () => signOutEverywhere());
    return menu;
}

/** Solo https: la foto de perfil llega del servidor, pero nunca se confía en el esquema. */
function safeHttpsUrl(value) {
    try {
        const url = new URL(String(value ?? ''));
        return url.protocol === 'https:' ? url.href : '';
    } catch {
        return '';
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

async function resolveAdminLink(menu) {
    const adminLink = menu?.querySelector('[data-admin-link]');
    if (!adminLink) return;
    try {
        const token = await getAccessToken();
        if (!token) return;
        const response = await fetch('api/v1/admin/status', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            cache: 'no-store',
        });
        if (response.ok) {
            adminLink.hidden = false;
            adminLink.addEventListener('click', async (event) => {
                event.preventDefault();
                const session = readSession();
                writeAdminBrowserSession(session?.email || '');
                window.location.assign(adminLink.href);
            });
        }
    } catch {
        // El menú de perfil sigue disponible; el acceso admin falla cerrado.
    }
}

/** Páginas que solo tienen sentido con sesión iniciada. */
export const PRIVATE_PAGES = Object.freeze(['explorar', 'publicar', 'fletes']);

/** Nombre de página de un href o pathname: "/explorar?q=x" → "explorar". */
export function pageName(href, base = 'http://localhost/') {
    try {
        const url = new URL(href, base);
        return url.pathname.replace(/\/+$/, '').split('/').pop().replace(/\.php$/, '');
    } catch {
        return '';
    }
}

/** Sin sesión, ir a una página privada pasa por Entrar y vuelve al mismo destino. */
export function loginFor(href, base = 'http://localhost/') {
    const url = new URL(href, base);
    const destino = `${pageName(url.href)}${url.search}`;
    return `entrar?next=${encodeURIComponent(destino)}`;
}

function hideAnonymousLinks() {
    const selector = PRIVATE_PAGES.map((page) => `a[href="${page}"]`).join(', ');
    document.querySelectorAll('.public-nav--primary, .public-footer').forEach((zona) => {
        zona.querySelectorAll(selector).forEach((link) => link.remove());
    });
    // Sin sesión el header queda en logo, tema, Entrar y Crear cuenta: el logo
    // ya lleva a Inicio y buscar exige cuenta.
    const nav = document.querySelector('.public-nav--primary');
    nav?.querySelectorAll('a[href="./"], a[href="#inicio"]').forEach((link) => link.remove());
    if (nav && !nav.querySelector('a')) nav.hidden = true;
    document.querySelectorAll('.public-header [data-public-search]').forEach((buscar) => buscar.remove());
}

/** Enlaces y búsquedas hacia páginas privadas: sin sesión, primero Entrar. */
function initializeAnonymousGate() {
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || !PRIVATE_PAGES.includes(pageName(link.href, document.baseURI))) return;
        event.preventDefault();
        window.location.assign(loginFor(link.href, document.baseURI));
    }, true);
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !PRIVATE_PAGES.includes(pageName(form.action, document.baseURI))) return;
        event.preventDefault();
        const url = new URL(form.action, document.baseURI);
        for (const [clave, valor] of new FormData(form)) {
            if (String(valor).trim() !== '') url.searchParams.set(clave, String(valor).trim());
        }
        window.location.assign(loginFor(url.href, document.baseURI));
    }, true);
}

function enhancePublicNavigation() {
    const nav = document.querySelector('.public-nav--primary');
    const session = readSession();
    if (session?.authenticated === true) {
        addNavLink(nav, 'publicar', 'fa-circle-plus', 'Publicar');
        addNavLink(nav, 'fletes', 'fa-truck', 'Fletes');
        // Con sesión no hay portada: Inicio es Explorar.
        document.querySelectorAll('.public-nav--primary a[href="./"], .public-nav--primary a[href="#inicio"], .public-footer a[href="./"], .public-footer a[href^="./#"]')
            .forEach((link) => link.remove());
        document.querySelectorAll('.public-brand').forEach((logo) => { logo.href = 'explorar'; });
    } else {
        hideAnonymousLinks();
        initializeAnonymousGate();
    }

    const actions = document.querySelector('.public-header__actions');
    const login = actions?.querySelector('.public-header__login');
    if (!actions || !login) return;

    if (session?.authenticated === true) {
        const menu = createAccountMenu(actions, session, readPublicProfile());
        void resolveAdminLink(menu);
        return;
    }

    // Un solo botón principal: "Crear cuenta" relleno, "Entrar" con contorno.
    login.classList.add('public-header__login--secondary');
    if (!actions.querySelector('[data-register-link]')) {
        const register = document.createElement('a');
        register.className = 'public-header__login';
        register.href = 'registro';
        register.dataset.registerLink = 'true';
        register.innerHTML = '<i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Crear cuenta</span>';
        login.after(register);
    }
}

function initializeBusinessActionGate() {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-explore-action]') : null;
        if (!(button instanceof HTMLButtonElement)) return;
        const action = button.dataset.exploreAction;
        if (!['Me interesa', 'Contactar'].includes(action)) return;

        const session = readStorage(SESSION_KEY);
        const profile = readStorage(PROFILE_KEY);
        let destination = null;

        if (!session?.authenticated) {
            const id = Number(button.closest('.explore-card')?.dataset.publicacionId);
            destination = `entrar?next=${encodeURIComponent(Number.isInteger(id) && id > 0 ? `explorar?publicacion=${id}` : 'explorar')}`;
        } else if (!profile?.persona) {
            destination = 'registro?next=explorar';
        }

        if (!destination) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(destination);
    }, true);
}

function initialize() {
    initializePublicSearch();
    enhancePublicNavigation();
    initializeBusinessActionGate();
    // Explorar coordina su propia recarga al recibir la ubicación. El resto del
    // sitio público inicia la captura aquí para que la sesión ya conozca la zona.
    if (!document.body.classList.contains('explore-page')) inicializarUbicacionAutomatica();
}

if (typeof document !== 'undefined'
    && ['explorar', 'fletes'].includes(pageName(window.location.href))
    && !readSession()) {
    window.location.replace('./');
}

// La portada es para quien aún no tiene cuenta: con sesión se va directo a Explorar.
if (typeof document !== 'undefined'
    && document.body?.hasAttribute('data-portada')
    && readSession()) {
    window.location.replace('explorar');
}

if (typeof document !== 'undefined') {
    ensureProductStyles();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
