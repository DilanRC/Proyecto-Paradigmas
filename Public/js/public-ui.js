import { inicializarUbicacionAutomatica } from './shared/ubicacion-sesion.js';

const SESSION_KEY = 'tindercows:login';
const PROFILE_KEY = 'tindercows:profile';

function ensureProductStyles() {
    if (document.querySelector('link[data-tc-public-product]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/public-product.css?v=product-1';
    link.dataset.tcPublicProduct = 'true';
    document.head.appendChild(link);
}

function readStorage(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function readSession() {
    return readStorage(SESSION_KEY);
}

function setSearchOpen(root, open) {
    if (!root) return;
    const safeOpen = open === true;
    root.dataset.open = String(safeOpen);
    const toggle = root.querySelector('[data-public-search-toggle]');
    const input = root.querySelector('input[type="search"]');
    toggle?.setAttribute('aria-expanded', String(safeOpen));
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
    nav.append(link);
}

function enhancePublicNavigation() {
    const nav = document.querySelector('.public-nav--primary');
    addNavLink(nav, 'publicar.php', 'fa-circle-plus', 'Publicar');
    addNavLink(nav, 'fletes.php', 'fa-truck', 'Fletes');

    const actions = document.querySelector('.public-header__actions');
    const login = actions?.querySelector('.public-header__login');
    if (!actions || !login) return;

    const session = readSession();
    if (session?.authenticated === true) {
        login.href = 'mi-actividad.php';
        login.innerHTML = '<i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>Mi actividad</span>';
        return;
    }

    if (!actions.querySelector('[data-register-link]')) {
        const register = document.createElement('a');
        register.className = 'public-header__login';
        register.href = 'registro.php';
        register.dataset.registerLink = 'true';
        register.innerHTML = '<i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Crear cuenta</span>';
        actions.insertBefore(register, login);
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
        const buyerState = profile?.capacidadesEstado?.COMPRADOR ?? 'NO_CONFIGURADO';
        let destination = null;

        if (!session?.authenticated) {
            destination = profile ? 'login.php?next=explorar.php' : 'registro.php?capacidad=COMPRADOR&next=explorar.php';
        } else if (!profile) {
            destination = 'registro.php?capacidad=COMPRADOR&next=explorar.php';
        } else if (buyerState === 'NO_CONFIGURADO') {
            destination = 'registro.php?capacidad=COMPRADOR&next=explorar.php';
        } else if (buyerState === 'INACTIVO') {
            destination = 'mi-actividad.php';
        }

        if (!destination) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(destination);
    }, true);
}

function initializeExploreCommerce() {
    if (!document.body.classList.contains('explore-page')) return;
    import('./explore-actions.js').catch((error) => {
        console.error('No se pudo inicializar el flujo Comprar / pujar.', error);
    });
}

function initialize() {
    initializePublicSearch();
    enhancePublicNavigation();
    initializeBusinessActionGate();
    initializeExploreCommerce();
    // Explorar coordina su propia recarga al recibir la ubicación. El resto del
    // sitio público inicia la captura aquí para que la sesión ya conozca la zona.
    if (!document.body.classList.contains('explore-page')) inicializarUbicacionAutomatica();
}

if (typeof document !== 'undefined') {
    ensureProductStyles();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
