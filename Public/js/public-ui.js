import { endExpiredSession, readAuthSession, getAccessToken } from './shared/supabase-auth.js?v=session-4';
import { publicProfileForSession } from './shared/public-profile.js?v=profile-2';
import { signOutEverywhere, writeAdminBrowserSession } from './shared/auth-gate.js?v=auth-gate-15';
import { ESTADO_CUENTA, estadoPublicado } from './shared/estado-cuenta.js?v=estado-cuenta-1';

function ensureProductStyles() {
    if (document.querySelector('link[data-tc-public-product]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/public-product.css?v=product-12';
    link.dataset.tcPublicProduct = 'true';
    document.head.appendChild(link);
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

// Carrito = contador de solicitudes de compra aprobadas (como Comprador); enlaza a Mi panel → Mis solicitudes.
const CART_KEY = 'tindercows:carrito';
const CART_TTL_MS = 15000;

function createCartLink() {
    const link = document.createElement('a');
    link.className = 'public-cart';
    link.href = 'mi-actividad#mis-solicitudes';
    link.dataset.publicCart = 'true';
    link.innerHTML = '<i class="fa-solid fa-cart-shopping" aria-hidden="true"></i><span class="public-cart__badge" aria-hidden="true" hidden>0</span>';
    paintCart(link, 0);
    return link;
}

function paintCart(link, count) {
    const total = Number.isInteger(count) && count > 0 ? count : 0;
    const badge = link.querySelector('.public-cart__badge');
    badge.textContent = total > 99 ? '99+' : String(total);
    badge.hidden = total === 0;
    link.setAttribute('aria-label', `Mis solicitudes aprobadas: ${total}`);
}

/** Una consulta por carga de página (con caché de segundos en la pestaña); si falla, el carrito queda en 0 y la navegación no se rompe. */
async function loadCartCount(link, email) {
    try {
        try {
            const cached = JSON.parse(globalThis.sessionStorage?.getItem(CART_KEY) ?? 'null');
            if (cached?.email === email && Date.now() - cached.t < CART_TTL_MS) { paintCart(link, cached.n); return; }
        } catch { /* sin caché */ }
        const token = await getAccessToken();
        if (!token) return;
        const response = await fetch('api/v1/solicitudes-compra?resumen=1', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            cache: 'no-store',
        });
        if (!response.ok) return;
        const aprobadas = (await response.json())?.data?.aprobadas;
        paintCart(link, aprobadas);
        try { globalThis.sessionStorage?.setItem(CART_KEY, JSON.stringify({ email, n: aprobadas, t: Date.now() })); } catch { /* sin caché */ }
    } catch {
        // El contador es un adorno: sin red o sin sesión válida se queda en 0.
    }
}

// Campana: avisos de subastas (ganada, terminada, te superaron). Se actualiza en vivo por Supabase Realtime.
async function fetchNotificaciones(method = 'GET') {
    const token = await getAccessToken();
    if (!token) return null;
    const response = await fetch('api/v1/notificaciones', {
        method,
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(method === 'PATCH' && { 'Content-Type': 'application/json' }) },
        body: method === 'PATCH' ? JSON.stringify({ accion: 'LEER' }) : undefined,
        cache: 'no-store',
    });
    return response.ok ? (await response.json())?.data ?? null : null;
}

function paintBell(bell, datos) {
    const total = Number.isInteger(datos?.sinLeer) && datos.sinLeer > 0 ? datos.sinLeer : 0;
    const badge = bell.querySelector('.public-cart__badge');
    badge.textContent = total > 99 ? '99+' : String(total);
    badge.hidden = total === 0;
    bell.querySelector('button').setAttribute('aria-label', total ? `Avisos: ${total} sin leer` : 'Avisos');
    const panel = bell.querySelector('.public-bell__panel');
    const lista = Array.isArray(datos?.notificaciones) ? datos.notificaciones : [];
    panel.replaceChildren(...(lista.length ? lista.map((aviso) => {
        const item = document.createElement(aviso.enlace ? 'a' : 'div');
        item.className = `public-bell__item${aviso.leida ? '' : ' is-nueva'}`;
        if (aviso.enlace && /^[a-z0-9-]+(\?[a-zA-Z0-9=&-]*)?$/.test(aviso.enlace)) item.href = aviso.enlace;
        const titulo = document.createElement('strong');
        titulo.textContent = aviso.titulo;
        const mensaje = document.createElement('span');
        mensaje.textContent = aviso.mensaje;
        const fecha = document.createElement('small');
        fecha.textContent = new Date(aviso.fecha).toLocaleString('es-CR', { dateStyle: 'short', timeStyle: 'short' });
        item.append(titulo, mensaje, fecha);
        return item;
    }) : [Object.assign(document.createElement('div'), { className: 'public-account-menu__identity', textContent: 'No tienes avisos todavía.' })]));
}

function createBell(profile, signal) {
    const bell = document.createElement('div');
    bell.className = 'public-account-menu public-bell';
    bell.dataset.publicBell = 'true';
    bell.innerHTML = '<button class="public-cart" type="button" aria-expanded="false" aria-controls="public-bell-panel" aria-label="Avisos"><i class="fa-solid fa-bell" aria-hidden="true"></i><span class="public-cart__badge" aria-hidden="true" hidden>0</span></button><div class="public-account-menu__panel public-bell__panel" id="public-bell-panel" hidden></div>';
    const button = bell.querySelector('button');
    const panel = bell.querySelector('.public-bell__panel');
    let datos = null;
    const cargar = async () => {
        try { datos = await fetchNotificaciones(); if (datos) paintBell(bell, datos); } catch { /* la campana es un adorno: sin red queda igual */ }
    };
    button.addEventListener('click', async () => {
        const open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!open));
        panel.hidden = open;
        // Al abrir se marcan leídas; la lista sigue mostrando cuáles eran nuevas hasta la próxima carga.
        if (!open && datos?.sinLeer > 0) {
            try {
                const leidas = await fetchNotificaciones('PATCH');
                if (leidas) {
                    datos = leidas;
                    bell.querySelector('.public-cart__badge').hidden = true;
                    button.setAttribute('aria-label', 'Avisos');
                }
            } catch { /* se marcarán la próxima vez */ }
        }
    });
    document.addEventListener('click', (event) => {
        if (!bell.contains(event.target)) { button.setAttribute('aria-expanded', 'false'); panel.hidden = true; }
    }, { signal });
    void cargar();
    const personaId = Number(profile?.persona?.personaId);
    if (Number.isInteger(personaId) && personaId > 0) {
        import('./shared/tiempo-real.js?v=tiempo-real-1')
            .then(({ escuchar, agrupar }) => {
                if (signal.aborted) return;
                const dejar = escuchar(`notificaciones-${personaId}`, agrupar(cargar));
                signal.addEventListener('abort', dejar, { once: true });
            })
            .catch(() => {});
    }
    const intervalo = setInterval(cargar, 60000);
    signal.addEventListener('abort', () => clearInterval(intervalo), { once: true });
    return bell;
}

const ENLACES_PERSONA = `
            <a href="mi-actividad"><i class="fa-solid fa-table-columns" aria-hidden="true"></i><span>Mi panel</span></a>
            <a href="me-interesa"><i class="fa-solid fa-heart" aria-hidden="true"></i><span>Me interesa</span></a>
            <a href="mi-dashboard"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><span>Mi dashboard</span></a>
            <a href="participar"><i class="fa-solid fa-route" aria-hidden="true"></i><span>Más formas de participar</span></a>
            <a href="ajustes"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Ajustes de cuenta</span></a>`;
const ENLACE_ADMIN = '<a href="admin/dashboard" data-admin-link hidden><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Panel admin</span></a>';
const BOTON_SALIR = '<button type="button" data-public-logout><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Cerrar sesión</span></button>';

/** Contenido del panel de cuenta: solo ofrece lo que el estado verificado permite. */
export function accountPanelHtml(estado, email, profile = null, completarHref = 'registro') {
    const correo = `<small>${escapeHtml(email)}</small>`;
    if (estado === ESTADO_CUENTA.COMPLETA) {
        return `<div class="public-account-menu__identity"><strong>${escapeHtml(profile?.persona?.nombre || 'Mi cuenta')}</strong>${correo}</div>${ENLACES_PERSONA}${ENLACE_ADMIN}${BOTON_SALIR}`;
    }
    if (estado === ESTADO_CUENTA.REGISTRO_INCOMPLETO) {
        return `<div class="public-account-menu__identity"><strong>Cuenta verificada</strong>${correo}<p class="public-account-menu__note">Falta completar tu registro para usar Mi panel, publicar, pedir fletes o pujar.</p></div>
            <a href="${escapeHtml(completarHref)}"><i class="fa-solid fa-user-pen" aria-hidden="true"></i><span>Completar registro</span></a>
            <a href="explorar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Explorar ganado</span></a>${ENLACE_ADMIN}${BOTON_SALIR}`;
    }
    if (estado === ESTADO_CUENTA.CONFLICTO) {
        // Inactiva o con el correo en dos Personas: reintentar no lo arregla y no hay funciones privadas que ofrecer.
        return `<div class="public-account-menu__identity"><strong>No podemos abrir tu cuenta</strong>${correo}<p class="public-account-menu__note">Tu sesión es válida, pero la cuenta necesita revisión del equipo de Ganado Cerca.</p></div>
            <a href="explorar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Explorar ganado</span></a>${ENLACE_ADMIN}${BOTON_SALIR}`;
    }
    if (estado === ESTADO_CUENTA.ERROR) {
        return `<div class="public-account-menu__identity"><strong>No pudimos confirmar tu cuenta</strong>${correo}<p class="public-account-menu__note">Puede ser la conexión. Tus datos no se perdieron.</p></div>
            <button type="button" data-cuenta-reintentar><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Reintentar</span></button>${ENLACES_PERSONA}${ENLACE_ADMIN}${BOTON_SALIR}`;
    }
    return `<div class="public-account-menu__identity"><strong>Verificando tu cuenta…</strong>${correo}</div>${BOTON_SALIR}`;
}

/**
 * Pinta (o vuelve a pintar) la zona de cuenta del encabezado. Cada pintado
 * aborta los listeners, intervalos y suscripciones del anterior.
 */
function paintAccount(actions, session, { estado, perfil = null }, { signal, completarHref, reintentar }) {
    const login = actions.querySelector('.public-header__login:not([data-completar-registro])');
    if (!login) return null;
    login.hidden = true;
    actions.querySelectorAll('[data-public-account], [data-public-cart], [data-public-bell], [data-completar-registro]')
        .forEach((nodo) => nodo.remove());

    const completa = estado === ESTADO_CUENTA.COMPLETA;
    const name = perfil?.persona?.nombre || session.email || 'Mi cuenta';
    const initial = String(name).trim().charAt(0).toUpperCase() || 'U';
    const photo = completa ? safeHttpsUrl(perfil?.persona?.fotoUrl) : '';
    const menu = document.createElement('div');
    menu.className = 'public-account-menu';
    menu.dataset.publicAccount = 'true';
    menu.dataset.estadoCuenta = estado;
    menu.innerHTML = `
        <button class="public-account-menu__trigger" type="button" aria-expanded="false" aria-controls="public-account-panel"${estado === ESTADO_CUENTA.VERIFICANDO ? ' aria-busy="true"' : ''}>
            <span class="public-account-menu__avatar" aria-hidden="true">${photo ? `<img src="${escapeHtml(photo)}" alt="" referrerpolicy="no-referrer">` : initial}</span>
            <span class="public-account-menu__label">${completa ? 'Mi perfil' : 'Mi cuenta'}</span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="public-account-menu__panel" id="public-account-panel" hidden>${accountPanelHtml(estado, session.email, perfil, completarHref)}</div>`;
    login.after(menu);

    if (estado === ESTADO_CUENTA.REGISTRO_INCOMPLETO) {
        // La acción principal del encabezado pasa a ser terminar el registro.
        const completar = document.createElement('a');
        completar.className = 'public-header__login';
        completar.href = completarHref;
        completar.dataset.completarRegistro = 'true';
        completar.innerHTML = '<i class="fa-solid fa-user-pen" aria-hidden="true"></i><span>Completar registro</span>';
        menu.before(completar);
    }
    if (completa) {
        const cart = createCartLink();
        menu.before(cart);
        cart.before(createBell(perfil, signal));
        void loadCartCount(cart, session.email);
    }

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
    }, { signal });
    menu.querySelector('[data-public-logout]')?.addEventListener('click', () => signOutEverywhere());
    menu.querySelector('[data-cuenta-reintentar]')?.addEventListener('click', () => reintentar?.());
    if (estado !== ESTADO_CUENTA.VERIFICANDO) void resolveAdminLink(menu);
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
export const PRIVATE_PAGES = Object.freeze(['explorar', 'publicar', 'fletes', 'subastas']);

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
    // La barra vacía se queda en la rejilla (hidden la sacaría y el bloque de
    // acciones saltaría de columna: CLS ~0,07); solo deja de anunciarse.
    if (nav && !nav.querySelector('a')) nav.setAttribute('aria-hidden', 'true');
    document.querySelectorAll('.public-header [data-public-search]').forEach((buscar) => buscar.remove());
}

/** Sesión sin Persona: ir a una página que la necesita pasa por el registro y vuelve al mismo destino. */
export function registroFor(href, base = 'http://localhost/') {
    const url = new URL(href, base);
    return `registro?next=${encodeURIComponent(`${pageName(url.href)}${url.search}`)}`;
}

/** El pie también debe reflejar la cuenta actual, igual que el menú del encabezado. */
function synchronizeFooterAccountLink(estado, completarHref) {
    const incompleta = estado === ESTADO_CUENTA.REGISTRO_INCOMPLETO;
    document.querySelectorAll('.public-footer a[href="entrar"], .public-footer a[data-footer-cuenta]').forEach((link) => {
        link.dataset.footerCuenta = 'true';
        link.href = incompleta ? completarHref : 'mi-actividad';
        link.textContent = incompleta ? 'Completar registro' : 'Mi panel';
    });
}

const NAV_PERSONA = Object.freeze([['publicar', 'fa-circle-plus', 'Publicar'], ['fletes', 'fa-truck', 'Fletes'], ['subastas', 'fa-gavel', 'Subastas']]);

/** Publicar, Fletes y Subastas solo aparecen cuando la cuenta puede usarlos (o si no se pudo confirmar). */
function synchronizeBusinessNav(nav, estado) {
    if (!nav) return;
    if (estado === ESTADO_CUENTA.COMPLETA || estado === ESTADO_CUENTA.ERROR) {
        NAV_PERSONA.forEach(([href, icono, etiqueta]) => addNavLink(nav, href, icono, etiqueta));
    } else if (estado === ESTADO_CUENTA.REGISTRO_INCOMPLETO || estado === ESTADO_CUENTA.CONFLICTO) {
        NAV_PERSONA.forEach(([href]) => nav.querySelectorAll(`a[href="${href}"]`).forEach((link) => link.remove()));
    }
}

let incompleteGateReady = false;

/** Con la cuenta sin Persona, los enlaces a páginas privadas llevan a terminar el registro. */
function initializeIncompleteGate(paginas) {
    if (incompleteGateReady) return;
    incompleteGateReady = true;
    document.addEventListener('click', (event) => {
        if (estadoPublicado() !== ESTADO_CUENTA.REGISTRO_INCOMPLETO) return;
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || !paginas.includes(pageName(link.href, document.baseURI))) return;
        event.preventDefault();
        window.location.assign(registroFor(link.href, document.baseURI));
    }, true);
}

/**
 * Encabezado con sesión. Se pinta al instante con el perfil en caché (o en
 * "Verificando") y el servidor confirma el estado real con GET api/v1/actividad.
 */
function initializeAccountState(nav, actions, session) {
    let controlador = null;
    let cuenta = null;
    const pintar = (resultado) => {
        controlador?.abort();
        controlador = new AbortController();
        const completarHref = cuenta?.enlaceCompletarRegistro() ?? 'registro';
        document.documentElement.dataset.tcCuenta = resultado.estado;
        synchronizeBusinessNav(nav, resultado.estado);
        synchronizeFooterAccountLink(resultado.estado, completarHref);
        if (actions) paintAccount(actions, session, resultado, { signal: controlador.signal, completarHref, reintentar: () => verificar(true) });
        if (resultado.estado === ESTADO_CUENTA.REGISTRO_INCOMPLETO && cuenta) {
            initializeIncompleteGate(cuenta.PAGINAS_CON_PERSONA);
            if (cuenta.PAGINAS_CON_PERSONA.includes(pageName(window.location.href))) cuenta.mostrarRegistroIncompleto();
        }
    };
    const verificar = async (forzar = false) => {
        if (forzar) pintar({ estado: ESTADO_CUENTA.VERIFICANDO });
        try {
            cuenta ??= await import('./shared/cuenta.js?v=cuenta-2');
            const resultado = await cuenta.consultarCuenta({ forzar });
            // Supabase ya no acepta la sesión: se cierra solo la que hizo la consulta.
            if (resultado.error?.status === 401) {
                void endExpiredSession(resultado.error.authSessionVersion);
                return;
            }
            pintar(resultado);
        } catch {
            pintar({ estado: ESTADO_CUENTA.ERROR });
        }
    };

    const enCache = publicProfileForSession(session);
    pintar(enCache ? { estado: ESTADO_CUENTA.COMPLETA, perfil: enCache } : { estado: ESTADO_CUENTA.VERIFICANDO });
    import('./shared/cuenta.js?v=cuenta-2')
        .then((modulo) => {
            cuenta = modulo;
            // Confirmado hace menos de VIGENCIA_PERFIL_MS en esta pestaña: no hace falta otra consulta.
            if (!modulo.perfilConfirmado(session)) void verificar();
        })
        .catch(() => pintar({ estado: ESTADO_CUENTA.ERROR }));
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
    const actions = document.querySelector('.public-header__actions');
    if (session?.authenticated === true) {
        // Con sesión no hay portada: Inicio es Explorar, que es público aunque falte el registro.
        document.querySelectorAll('.public-nav--primary a[href="./"], .public-nav--primary a[href="#inicio"], .public-footer a[href="./"], .public-footer a[href^="./#"]')
            .forEach((link) => link.remove());
        document.querySelectorAll('.public-brand').forEach((logo) => { logo.href = 'explorar'; });
        initializeAccountState(nav, actions, session);
        return;
    }
    hideAnonymousLinks();
    initializeAnonymousGate();

    const login = actions?.querySelector('.public-header__login');
    if (!actions || !login) return;

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

        const session = readSession();
        const id = Number(button.closest('.explore-card')?.dataset.publicacionId);
        const origen = Number.isInteger(id) && id > 0 ? `explorar?publicacion=${id}` : 'explorar';
        let destination = null;

        if (!session?.authenticated) {
            destination = `entrar?next=${encodeURIComponent(origen)}`;
        } else if (estadoPublicado() === ESTADO_CUENTA.REGISTRO_INCOMPLETO) {
            // Marcar o contactar necesita la Persona: primero se termina el registro.
            destination = registroFor(origen, document.baseURI);
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
}

if (typeof document !== 'undefined'
    && ['explorar', 'fletes', 'subastas'].includes(pageName(window.location.href))
    && !readSession()) {
    window.location.replace('./');
}

// La portada es para quien aún no tiene cuenta: con sesión se va directo a Explorar.
if (typeof document !== 'undefined'
    && document.body?.hasAttribute('data-portada')
    && readSession()) {
    window.location.replace('explorar');
}

/**
 * La rueda del ratón sobre un campo numérico enfocado cambia su valor sin que la persona lo note (un precio o un
 * peso distinto al escrito). Se quita el foco: la rueda vuelve a desplazar la página y el valor no cambia.
 */
export function soltarNumeroConRueda(event) {
    const campo = event.target;
    if (campo?.type === 'number' && campo === campo.ownerDocument?.activeElement) campo.blur();
}

if (typeof document !== 'undefined') {
    document.addEventListener('wheel', soltarNumeroConRueda, { passive: true });
    ensureProductStyles();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
