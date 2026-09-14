const SESSION_KEY = 'tindercows:login';

function ensureProductStyles() {
    if (document.querySelector('link[data-tc-public-product]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/public-product.css?v=product-1';
    link.dataset.tcPublicProduct = 'true';
    document.head.appendChild(link);
}

function readSession() {
    try { return JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null'); } catch { return null; }
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

function enhancePublicNavigation() {
    const nav = document.querySelector('.public-nav--primary');
    if (nav && !nav.querySelector('a[href="fletes.php"]')) {
        const link = document.createElement('a');
        link.href = 'fletes.php';
        link.innerHTML = '<i class="fa-solid fa-truck" aria-hidden="true"></i><span>Fletes</span>';
        nav.append(link);
    }

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

function initialize() {
    initializePublicSearch();
    enhancePublicNavigation();
}

if (typeof document !== 'undefined') {
    ensureProductStyles();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
