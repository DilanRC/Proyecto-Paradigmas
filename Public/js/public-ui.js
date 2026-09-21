import { inicializarUbicacionAutomatica } from './shared/ubicacion-sesion.js';
import { readAuthSession, signOut, getAccessToken } from './shared/supabase-auth.js';
import { readPublicProfile } from './shared/public-profile.js';
import { clearAdminBrowserSession } from './shared/auth-gate.js';

const SESSION_KEY = 'tindercows:login';
const PROFILE_KEY = 'tindercows:profile';

function ensureProductStyles() {
    if (document.querySelector('link[data-tc-public-product]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/public-product.css?v=product-2';
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
    toggle?.setAttribute('aria-expanded', String(safeOpen));
    toggle?.setAttribute('aria-label', safeOpen ? 'Cerrar búsqueda' : 'Abrir búsqueda');
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

function initializePublicCarousel() {
    for (const root of document.querySelectorAll('[data-public-carousel]')) {
        if (root.dataset.ready === 'true') continue;
        const track = root.querySelector('[data-carousel-track]');
        const pages = [...root.querySelectorAll('.public-carousel__page')];
        const dots = root.querySelector('.public-carousel__dots');
        const status = root.querySelector('[data-carousel-status]');
        if (!track || pages.length < 2 || !dots) continue;
        root.dataset.ready = 'true';
        let index = 0;
        const render = () => {
            index = Math.min(index, pages.length - 1);
            track.style.transform = `translateX(-${index * 100}%)`;
            if (status) status.textContent = index === 0 ? 'Escenas 1–3 de 6' : 'Escenas 4–6 de 6';
            dots.querySelectorAll('button').forEach((dot, dotIndex) => dot.setAttribute('aria-selected', String(dotIndex === index)));
        };
        pages.forEach((page, pageIndex) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.role = 'tab';
            dot.ariaLabel = `Ver página ${pageIndex + 1} de escenas`;
            dot.addEventListener('click', () => { index = pageIndex; render(); });
            dots.append(dot);
        });
        root.querySelector('[data-carousel-prev]')?.addEventListener('click', () => { index = Math.max(0, index - 1); render(); });
        root.querySelector('[data-carousel-next]')?.addEventListener('click', () => { index = Math.min(pages.length - 1, index + 1); render(); });
        render();
    }
}

function addNavLink(nav, href, icon, label) {
    if (!nav || nav.querySelector(`a[href="${href}"]`)) return;
    const link = document.createElement('a');
    link.href = href;
    link.innerHTML = `<i class="fa-solid ${icon}" aria-hidden="true"></i><span>${label}</span>`;
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
    menu.innerHTML = `
        <button class="public-account-menu__trigger" type="button" aria-expanded="false" aria-controls="public-account-panel">
            <span class="public-account-menu__avatar" aria-hidden="true">${initial}</span>
            <span class="public-account-menu__label">Mi perfil</span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="public-account-menu__panel" id="public-account-panel" hidden>
            <div class="public-account-menu__identity"><strong>${escapeHtml(profile?.persona?.nombre || 'Cuenta activa')}</strong><small>${escapeHtml(session.email)}</small></div>
            <a href="mi-actividad"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>Mi perfil y actividad</span></a>
            <a href="registro" data-profile-register><i class="fa-solid fa-user-pen" aria-hidden="true"></i><span>Completar actividades</span></a>
            <a href="admin/productores" data-admin-link hidden><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Panel admin</span></a>
            <button type="button" data-public-logout><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Cerrar sesión</span></button>
        </div>`;

    login.replaceWith(menu);
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
    menu.querySelector('[data-public-logout]')?.addEventListener('click', async () => {
        try { await signOut(); } finally {
            clearAdminBrowserSession();
            window.location.assign('explorar');
        }
    });
    return menu;
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
        if (response.ok) adminLink.hidden = false;
    } catch {
        // El menú de perfil sigue disponible; el acceso admin falla cerrado.
    }
}

function enhancePublicNavigation() {
    const nav = document.querySelector('.public-nav--primary');
    addNavLink(nav, 'publicar', 'fa-circle-plus', 'Publicar');
    addNavLink(nav, 'fletes', 'fa-truck', 'Fletes');

    const actions = document.querySelector('.public-header__actions');
    const login = actions?.querySelector('.public-header__login');
    if (!actions || !login) return;

    const session = readSession();
    if (session?.authenticated === true) {
        const menu = createAccountMenu(actions, session, readPublicProfile());
        void resolveAdminLink(menu);
        return;
    }

    if (!actions.querySelector('[data-register-link]')) {
        const register = document.createElement('a');
        register.className = 'public-header__login';
        register.href = 'registro';
        register.dataset.registerLink = 'true';
        register.innerHTML = '<i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Crear cuenta</span>';
        actions.insertBefore(register, login);
    }
}

if (typeof document !== 'undefined') {
    const initialize = () => initializePublicCarousel();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}

function initializeBusinessActionGate() {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-explore-action]') : null;
        if (!(button instanceof HTMLButtonElement)) return;
        const action = button.dataset.exploreAction;
        if (!['Me interesa', 'Contactar', 'Pasar'].includes(action)) return;

        const session = readStorage(SESSION_KEY);
        const profile = readStorage(PROFILE_KEY);
        let destination = null;

        if (!session?.authenticated) {
            destination = 'entrar?next=explorar';
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

if (typeof document !== 'undefined') {
    ensureProductStyles();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
