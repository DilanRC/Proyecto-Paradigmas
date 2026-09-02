import { SESSION_KEY } from './auth-gate.js';
import { applyTheme, preferredTheme } from '../public-theme.js';

const ADMIN_CSS = 'css/admin-v3.css?v=admin-3';
const ADMIN_SIDEBAR_CSS = 'css/admin-sidebar-collapse.css?v=sidebar-1';
export const ADMIN_SIDEBAR_KEY = 'tindercows:admin-sidebar-collapsed';

const MODULES = {
    'productores.php': {
        icon: 'fa-cow',
        search: 'Nombre, identificación, correo',
        hint: 'Busca en nombre, correo e identificación.',
    },
    'compradores.php': {
        icon: 'fa-handshake',
        search: 'Nombre o identificación',
        hint: 'Consulta las clasificaciones disponibles sin cambiar su política.',
    },
    'transportistas.php': {
        icon: 'fa-truck-fast',
        search: 'Nombre, identificación o correo',
        hint: 'Busca en nombre, correo e identificación.',
    },
    'vehiculos.php': {
        icon: 'fa-truck-pickup',
        search: 'Placa, VIN o modelo',
        hint: 'Busca en placa, VIN y modelo.',
    },
    'pagometodos.php': {
        icon: 'fa-wallet',
        search: 'Nombre o descripción',
        hint: 'Busca en nombre y descripción.',
    },
};

function routeName(pathname = globalThis.location?.pathname ?? '') {
    const parts = String(pathname).split('/').filter(Boolean);
    return parts.at(-1) || 'index.php';
}

function injectStylesheet(href, marker) {
    if (typeof document === 'undefined' || document.querySelector(`link[${marker}]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.setAttribute(marker, 'true');
    document.head.appendChild(link);
}

function sessionEmail() {
    try {
        const raw = globalThis.sessionStorage?.getItem(SESSION_KEY);
        if (!raw) return 'Sesión local';
        const session = JSON.parse(raw);
        return typeof session?.email === 'string' && session.email.trim()
            ? session.email.trim()
            : 'Sesión local';
    } catch {
        return 'Sesión local';
    }
}

function createIcon(className) {
    const icon = document.createElement('i');
    icon.className = `fa-solid ${className}`;
    icon.setAttribute('aria-hidden', 'true');
    return icon;
}

export function readSidebarCollapsed(storage = globalThis.localStorage) {
    try {
        return storage?.getItem(ADMIN_SIDEBAR_KEY) === 'true';
    } catch {
        return false;
    }
}

function storeSidebarCollapsed(collapsed, storage = globalThis.localStorage) {
    try {
        storage?.setItem(ADMIN_SIDEBAR_KEY, String(collapsed));
    } catch {
        // La preferencia del shell no debe impedir usar el panel si storage falla.
    }
}

function setSidebarRootState(collapsed) {
    if (typeof document === 'undefined') return;
    document.documentElement.dataset.tcSidebar = collapsed ? 'collapsed' : 'expanded';
}

function wrapDirectText(element, className) {
    if (!element || element.querySelector(`:scope > .${className}`)) {
        return element?.querySelector(`:scope > .${className}`) ?? null;
    }
    const textNodes = Array.from(element.childNodes).filter(
        (node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '',
    );
    if (textNodes.length === 0) return null;

    const label = document.createElement('span');
    label.className = className;
    label.textContent = textNodes.map((node) => node.textContent.trim()).join(' ');
    element.insertBefore(label, textNodes[0]);
    textNodes.forEach((node) => node.remove());
    return label;
}

function registerCollapsedLabel(element, label) {
    if (!element || !label) return;
    element.dataset.sidebarLabel = label;
}

function updateCollapsedTitles(collapsed) {
    for (const element of document.querySelectorAll('[data-sidebar-label]')) {
        if (collapsed) element.title = element.dataset.sidebarLabel;
        else element.removeAttribute('title');
    }
}

export function applySidebarState(collapsed, { persist = false, storage = globalThis.localStorage } = {}) {
    if (typeof document === 'undefined') return;
    const safeCollapsed = collapsed === true;
    setSidebarRootState(safeCollapsed);

    const toggle = document.querySelector('.admin-sidebar-toggle');
    if (toggle) {
        const expanded = !safeCollapsed;
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', safeCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral');
        toggle.title = safeCollapsed ? 'Expandir menú lateral' : 'Contraer menú lateral';
        const icon = toggle.querySelector('i');
        const label = toggle.querySelector('.admin-sidebar-toggle__label');
        if (icon) icon.className = `fa-solid ${safeCollapsed ? 'fa-angles-right' : 'fa-angles-left'}`;
        if (label) label.textContent = safeCollapsed ? 'Expandir menú' : 'Contraer menú';
    }

    updateCollapsedTitles(safeCollapsed);
    if (persist) storeSidebarCollapsed(safeCollapsed, storage);
}

function enhanceSidebarCollapse() {
    const sidebar = document.querySelector('.rural-panel__sidebar');
    if (!sidebar || sidebar.querySelector('.admin-sidebar-toggle')) return;
    if (!sidebar.id) sidebar.id = 'tc-admin-sidebar';

    for (const item of sidebar.querySelectorAll('.rural-panel__nav-item')) {
        const text = item.textContent.trim();
        const label = wrapDirectText(item, 'admin-sidebar-nav-label');
        registerCollapsedLabel(item, label?.textContent.trim() || text);
    }

    const accountActions = sidebar.querySelector('.admin-account-card__actions');
    if (accountActions) {
        for (const link of accountActions.querySelectorAll('.rural-panel__admin-link')) {
            const text = link.textContent.trim();
            const label = wrapDirectText(link, 'admin-sidebar-action-label');
            registerCollapsedLabel(link, label?.textContent.trim() || text);
        }
        const theme = accountActions.querySelector('[data-theme-toggle]');
        registerCollapsedLabel(theme, 'Cambiar tema');
    }

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'admin-sidebar-toggle';
    toggle.setAttribute('aria-controls', sidebar.id);
    toggle.innerHTML = '<i class="fa-solid fa-angles-left" aria-hidden="true"></i><span class="admin-sidebar-toggle__label">Contraer menú</span>';
    const nav = sidebar.querySelector('.rural-panel__nav');
    sidebar.insertBefore(toggle, nav ?? sidebar.querySelector('.rural-panel__sidebar-footer'));

    toggle.addEventListener('click', () => {
        const collapsed = document.documentElement.dataset.tcSidebar === 'collapsed';
        applySidebarState(!collapsed, { persist: true });
    });

    applySidebarState(readSidebarCollapsed());
}

function enhancePageHeader() {
    const header = document.querySelector('.rural-panel .page-header');
    if (!header || header.querySelector('.admin-page-header-icon')) return;
    const info = MODULES[routeName()] ?? { icon: 'fa-table-list' };
    const badge = document.createElement('span');
    badge.className = 'admin-page-header-icon';
    badge.setAttribute('aria-hidden', 'true');
    badge.appendChild(createIcon(info.icon));
    header.prepend(badge);
}

function enhanceSidebarAccount() {
    const footer = document.querySelector('.rural-panel__sidebar-footer');
    if (!footer || footer.querySelector('.admin-account-card')) return;

    const currentRow = document.querySelector('.rural-panel__admin-row');
    const siteLink = currentRow?.querySelector('a[href="./"]') ?? null;
    const logoutLink = currentRow?.querySelector('a[href$="login.php"]') ?? null;
    if (siteLink) siteLink.textContent = 'Ir al sitio';
    if (logoutLink && !/cerrar/i.test(logoutLink.textContent)) logoutLink.textContent = 'Cerrar sesión';

    const card = document.createElement('section');
    card.className = 'admin-account-card';
    card.setAttribute('aria-label', 'Cuenta y preferencias');

    const identity = document.createElement('div');
    identity.className = 'admin-account-card__identity';
    const avatar = document.createElement('span');
    avatar.className = 'admin-account-card__avatar';
    avatar.textContent = 'TC';
    avatar.setAttribute('aria-hidden', 'true');
    const copy = document.createElement('span');
    copy.className = 'admin-account-card__copy';
    const title = document.createElement('strong');
    title.textContent = 'Acceso de demostración';
    const email = document.createElement('small');
    email.textContent = sessionEmail();
    copy.append(title, email);
    identity.append(avatar, copy);

    const actions = document.createElement('div');
    actions.className = 'admin-account-card__actions';
    if (siteLink) actions.appendChild(siteLink);

    const theme = document.createElement('button');
    theme.type = 'button';
    theme.className = 'theme-toggle';
    theme.dataset.themeToggle = '';
    theme.innerHTML = '<i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span>';
    actions.appendChild(theme);

    if (logoutLink) actions.appendChild(logoutLink);
    card.append(identity, actions);
    footer.replaceChildren(card);
    applyTheme(preferredTheme());
}

function filterDescription(route) {
    return MODULES[route]?.hint ?? 'Los filtros actualizan el listado sin recargar la página.';
}

function resetFilters(tools) {
    const search = tools.querySelector('input[type="search"]');
    const selects = tools.querySelectorAll('select');
    let changed = false;

    if (search && search.value !== '') {
        search.value = '';
        changed = true;
        search.dispatchEvent(new Event('input', { bubbles: true }));
    }

    for (const select of selects) {
        const target = Array.from(select.options).some((option) => option.value === 'TODOS') ? 'TODOS' : '';
        if (select.value !== target) {
            select.value = target;
            changed = true;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    if (!changed) search?.focus();
}

function enhanceFilters() {
    const tools = document.querySelector('.rural-panel .tools');
    if (!tools || tools.dataset.tcEnhanced === 'true') return;
    tools.dataset.tcEnhanced = 'true';

    const route = routeName();
    const info = MODULES[route] ?? {};
    const heading = document.createElement('div');
    heading.className = 'admin-filter-heading';
    heading.innerHTML = `<span class="admin-filter-heading__title"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>Filtros</span></span><span class="admin-filter-heading__hint">${filterDescription(route)}</span>`;
    tools.prepend(heading);

    const search = tools.querySelector('.search');
    if (search) {
        const hiddenLabel = search.querySelector('.screen-reader-only');
        if (hiddenLabel) {
            hiddenLabel.classList.remove('screen-reader-only');
            hiddenLabel.classList.add('admin-filter-label');
            hiddenLabel.textContent = 'Buscar';
        }
        const input = search.querySelector('input[type="search"]');
        if (input && info.search) {
            input.placeholder = info.search;
            input.title = info.hint ?? info.search;
        }
    }

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'admin-clear-filters';
    clear.innerHTML = '<i class="fa-solid fa-eraser" aria-hidden="true"></i><span>Limpiar</span>';
    clear.addEventListener('click', () => resetFilters(tools));
    tools.appendChild(clear);
}

function enhanceTable() {
    const panel = document.querySelector('.rural-panel .panel');
    const table = panel?.querySelector('table');
    if (!table) return;
    table.classList.add('admin-table');
    table.closest('.table-container')?.classList.add('admin-table-scroll');

    const lastHeader = table.querySelector('thead th:last-child');
    if (lastHeader && !lastHeader.querySelector('.admin-actions-heading')) {
        const label = document.createElement('span');
        label.className = 'admin-actions-heading';
        label.textContent = 'Acciones';
        if (lastHeader.querySelector('.screen-reader-only')) lastHeader.appendChild(label);
    }
}

function enhanceActions(root = document) {
    for (const button of root.querySelectorAll?.('.row-actions .action') ?? []) {
        const label = button.textContent.trim();
        if (!label) continue;
        button.title = label;
        if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', label);
    }
}

function observeActions() {
    const bodies = document.querySelectorAll('.rural-panel tbody');
    for (const body of bodies) {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE) enhanceActions(node);
                }
            }
        });
        observer.observe(body, { childList: true, subtree: true });
    }
}

function enhancePagination() {
    const pagination = document.querySelector('.rural-panel .pagination');
    if (!pagination) return;
    const labels = new Map([
        ['#pagina-anterior', 'Página anterior'],
        ['#pagina-siguiente', 'Página siguiente'],
        ['#actualizar-lista', 'Actualizar resultados'],
    ]);
    for (const [selector, title] of labels) {
        const control = pagination.querySelector(selector);
        if (control) control.title = title;
    }
}

export function initializeAdminUi() {
    if (typeof document === 'undefined' || !document.body?.classList.contains('rural-panel')) return;
    document.documentElement.dataset.tcAdminUx = '3';
    enhanceSidebarAccount();
    enhanceSidebarCollapse();
    enhancePageHeader();
    enhanceFilters();
    enhanceTable();
    enhanceActions();
    observeActions();
    enhancePagination();
}

setSidebarRootState(readSidebarCollapsed());
injectStylesheet(ADMIN_CSS, 'data-tc-admin-v3');
injectStylesheet(ADMIN_SIDEBAR_CSS, 'data-tc-admin-sidebar');

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAdminUi, { once: true });
    } else {
        initializeAdminUi();
    }
}
