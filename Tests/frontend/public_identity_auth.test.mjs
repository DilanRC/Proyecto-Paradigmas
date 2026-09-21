import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { resolveAdminNext, resolveNext } from '../../Public/js/login.js';
import {
    enforceBrowserSession,
    isPrivateRoute,
    loginTarget,
    readBrowserSession,
    writeAdminBrowserSession,
    clearAdminBrowserSession,
    SESSION_KEY,
} from '../../Public/js/shared/auth-gate.js';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const home = read('../../Application/View/home/index.php');
const explore = read('../../Application/View/explorar/index.php');
const publicarJs = read('../../Public/js/publicar.js');
const publicarApi = read('../../Public/api/publicaciones.php');
const interactionJs = read('../../Public/js/explore-interactions.js');
const interactionApi = read('../../Public/api/publicacion-interacciones.php');
const login = read('../../Application/View/login/index.php');
const info = read('../../Application/View/public/info.php');
const publicCss = read('../../Public/css/public-auth.css');
const productCss = read('../../Public/css/public-product.css');
const themeJs = read('../../Public/js/public-theme.js');
const passwordToggleJs = read('../../Public/js/password-toggle.js');
const publicUi = read('../../Public/js/public-ui.js');
const registroJs = read('../../Public/js/registro.js');
const baseCss = read('../../Public/css/base.css');
const api = read('../../Public/js/shared/api.js');
const authGate = read('../../Public/js/shared/auth-gate.js');

const PRIVATE_MODULES = [
    '../../Public/js/productores.js',
    '../../Public/js/compradores.js',
    '../../Public/js/transportistas.js',
    '../../Public/js/vehiculos.js',
    '../../Public/js/pagometodos.js',
];

const PUBLIC_ROUTES = [
    '../../Public/explorar.php',
    '../../Public/sobre-nosotros.php',
    '../../Public/como-usar.php',
    '../../Public/privacidad.php',
    '../../Public/terminos.php',
    '../../Public/legal.php',
];

test('la portada usa identidad TinderCows y habla como producto', () => {
    assert.ok(home.includes('assets/logo_dark.png'));
    assert.ok(home.includes('assets/logo_light.png'));
    assert.ok(home.includes('rel="icon" href="favicon.svg"'));
    assert.ok(home.includes('El ganado que buscas, más cerca de ti.'));
    assert.ok(home.includes('explorar.php'));
    assert.equal(/EIF400|acad[eé]mic/i.test(home), false, 'la experiencia pública no debe hablar del curso ni de evaluación');
    assert.equal(home.includes('Productores</h3>'), false, 'la landing no debe explicar módulos administrativos');
    assert.equal(home.includes('api/productores.php'), false);
});

test('navbar público prioriza Inicio, Explorar, Nosotros y Cómo funciona; legal queda en footer', () => {
    const nav = home.match(/<nav class="public-nav public-nav--primary"[\s\S]*?<\/nav>/)?.[0] ?? '';
    for (const label of ['Inicio', 'Explorar', 'Nosotros', 'Cómo funciona']) assert.ok(nav.includes(label));
    for (const label of ['Privacidad', 'Términos', 'Legal']) assert.equal(nav.includes(label), false);
    assert.ok(home.includes('public-footer__legal'));
    assert.ok(home.includes('privacidad.php'));
    assert.ok(home.includes('terminos.php'));
    assert.ok(home.includes('legal.php'));
});

test('la búsqueda pública permanece compacta y se expande bajo demanda', () => {
    assert.ok(home.includes('data-public-search'));
    assert.ok(home.includes('data-public-search-toggle'));
    assert.ok(productCss.includes(".public-search[data-open='true'] .public-search__field"));
    assert.ok(publicUi.includes('root.dataset.open = String(safeOpen)'));
    assert.ok(publicUi.includes("event.key === 'Escape'"));
});

test('Explorar es una vista distinta con deck deslizable y acciones icono más texto', () => {
    assert.ok(read('../../Public/explorar.php').includes("Application/View/explorar/index.php"));
    assert.ok(explore.includes('data-explore-deck'));
    assert.ok(explore.includes('data-explore-prev'));
    assert.ok(explore.includes('data-explore-next'));
    // Las acciones viajan con la tarjeta, que ahora construye explore.js con lo
    // que devuelve api/publicaciones.php; la escritura requiere sesión y se
    // realiza desde publicar.js con el bearer verificado.
    const moduloExplorar = read('../../Public/js/explore.js');
    for (const action of ['Pasar', 'Me interesa', 'Contactar']) {
        assert.ok(moduloExplorar.includes(`'${action}'`), `falta la acción ${action}`);
    }
    assert.ok(moduloExplorar.includes('api/publicaciones.php'),
        'el deck debe leer el catálogo real, no contenido de muestra');
    assert.equal(/EIF400|acad[eé]mic/i.test(explore), false);
});

test('publicar persiste mediante el endpoint autenticado y limpia el borrador solo al guardar', () => {
    assert.ok(publicarJs.includes("import { getAccessToken } from './shared/supabase-auth.js';"));
    assert.ok(publicarJs.includes("method: 'POST'"));
    assert.ok(publicarJs.includes("fetch('api/publicaciones.php'"));
    assert.ok(publicarJs.includes("sessionStorage.removeItem(DRAFT_KEY)"));
    assert.ok(publicarApi.includes("['GET', 'POST']"));
    assert.ok(publicarApi.includes('readJsonBody()'));
});

test('Explorar persiste Pasar, Me interesa y Contactar con la Persona autenticada', () => {
    for (const type of ['ME_INTERESA', 'PASAR', 'CONTACTAR']) {
        assert.ok(interactionJs.includes(`'${type}'`));
    }
    assert.ok(interactionJs.includes("fetch(API_URL"));
    assert.ok(interactionJs.includes("method: 'POST'"));
    assert.ok(interactionApi.includes('PublicacionInteraccionController'));
    assert.ok(interactionApi.includes('SupabaseActorResolver'));
});

test('la paleta pública sale del logo y elimina la referencia cromática de Tinder', () => {
    for (const token of ['#151a18', '#2f3c2d', '#d24f28', '#eedbca', '#fef7ec', '#394332']) {
        assert.ok(publicCss.includes(token), `falta color de identidad ${token}`);
    }
    for (const tinderColor of ['#fd267a', '#ff315f', '#ff6746', '#7c3aed']) {
        assert.equal(publicCss.includes(tinderColor), false, `no debe sobrevivir el color Tinder ${tinderColor}`);
    }
});

test('registro guiado hereda los tokens públicos y conserva contraste al cambiar tema', () => {
    for (const token of [
        '--background:var(--tc-bg)',
        '--surface:var(--tc-surface)',
        '--text:var(--tc-text)',
        '--border-color:var(--tc-line-strong)',
        '--accent:var(--tc-primary)',
    ]) assert.ok(publicCss.includes(token), `falta alias visual ${token}`);
    assert.ok(publicCss.includes('.auth-field select,'));
    assert.ok(themeJs.includes('wireThemeToggle'));
    assert.ok(themeJs.includes('document.documentElement.dataset.theme === \'dark\''));
    assert.ok(themeJs.includes('storeTheme(next)'));
});

test('login y registro permiten mostrar u ocultar cada contraseña sin enviarla al almacenamiento', () => {
    for (const view of [login, read('../../Application/View/registro/index.php')]) {
        assert.ok(view.includes('data-password-toggle'));
        assert.ok(view.includes('aria-controls='));
    }
    assert.ok(passwordToggleJs.includes("input.type === 'password' ? 'text' : 'password'"));
    assert.ok(passwordToggleJs.includes('aria-pressed'));
    assert.equal(passwordToggleJs.includes('localStorage'), false);
    assert.equal(passwordToggleJs.includes('sessionStorage'), false);
});

test('modo claro y oscuro comparten preferencia persistente e iconos reconocibles', () => {
    assert.ok(publicCss.includes("html[data-theme='light']"));
    assert.ok(publicCss.includes("html[data-theme='dark'] .brand-logo--light"));
    assert.ok(themeJs.includes("export const THEME_KEY = 'tindercows:theme'"));
    assert.ok(themeJs.includes('storeTheme(next)'));
    assert.ok(themeJs.includes('prefers-color-scheme: light'));
    assert.ok(themeJs.includes("'fa-sun'"));
    assert.ok(themeJs.includes("'fa-moon'"));
});

test('el acceso público valida con Supabase y vuelve a Explorar por defecto', () => {
    assert.ok(login.includes('Entrar a TinderCows'));
    assert.match(login, /credenciales se validan con Supabase Auth/);
    assert.match(login, /verifica la autorizaci[oó]n de administrador en el servidor/);
    assert.ok(login.includes('name="email"'));
    assert.ok(login.includes('name="password"'));
    assert.equal(/EIF400|acad[eé]mic/i.test(login), false);
    assert.equal(resolveNext(''), 'explorar.php');
    assert.equal(resolveNext('?next=explorar.php'), 'explorar.php');
    assert.equal(resolveNext('?next=vehiculos.php'), 'explorar.php');
    assert.equal(resolveNext('?next=https://example.com'), 'explorar.php');
    assert.equal(resolveNext('?next=//example.com'), 'explorar.php');
    assert.equal(resolveNext('?next=../login.php'), 'explorar.php');
    assert.equal(resolveAdminNext('?next=productores.php'), 'productores.php');
    assert.equal(resolveAdminNext('?next=https://example.com'), 'productores.php');
    assert.ok(read('../../Public/js/login.js').includes("area') === 'admin"));
});

test('la cuenta autenticada muestra perfil y no vuelve a ofrecer Entrar', () => {
    assert.match(publicUi, /readAuthSession/);
    assert.match(publicUi, /createAccountMenu/);
    assert.match(publicUi, /Mi perfil y actividad/);
    assert.match(publicUi, /api\/admin-status\.php/);
    assert.doesNotMatch(publicUi, /sessionStorage\.setItem\(SESSION_KEY/);
});

test('el registro guiado persiste mediante Supabase y la API, no mediante una sesión falsa', () => {
    assert.match(registroJs, /signUpWithPassword/);
    assert.match(registroJs, /api\/registro\.php/);
    assert.match(registroJs, /syncPublicProfile/);
    assert.doesNotMatch(registroJs, /frontend-prototype/);
});

test('las páginas públicas informativas no exponen rutas administrativas ni lenguaje académico', () => {
    for (const route of PUBLIC_ROUTES.slice(1)) {
        const wrapper = read(route);
        assert.ok(wrapper.includes('Application/View/public/info.php'));
    }
    for (const key of ['about', 'guide', 'privacy', 'terms', 'legal']) assert.ok(info.includes(`'${key}' => [`));
    assert.equal(info.includes('/productores.php'), false);
    assert.equal(info.includes('/pagometodos.php'), false);
    assert.equal(/EIF400|acad[eé]mic/i.test(info), false);
});

test('la puerta requiere un marcador de sesión estructurado', () => {
    const makeStorage = (value) => ({ getItem: (key) => key === SESSION_KEY ? value : null });
    assert.equal(readBrowserSession(makeStorage(null)), null);
    assert.equal(readBrowserSession(makeStorage('{mal json')), null);
    assert.equal(readBrowserSession(makeStorage(JSON.stringify({ email: 'a@b.test' }))), null);

    const valid = JSON.stringify({
        authenticated: true,
        version: 1,
        adminAuthorized: true,
        startedAt: '2026-09-01T12:00:00.000Z',
        mode: 'admin-server-session',
    });
    assert.equal(readBrowserSession(makeStorage(valid))?.adminAuthorized, true);
});

test('la autorización admin verificada crea y limpia solo el marcador visual', () => {
    const values = new Map();
    const storage = {
        setItem: (key, value) => values.set(key, value),
        getItem: (key) => values.get(key) ?? null,
        removeItem: (key) => values.delete(key),
    };
    writeAdminBrowserSession('ADMIN@EXAMPLE.TEST', storage);
    const marker = readBrowserSession(storage);
    assert.equal(marker?.email, 'admin@example.test');
    assert.equal(marker?.adminAuthorized, true);
    clearAdminBrowserSession(storage);
    assert.equal(readBrowserSession(storage), null);
});

test('las acciones fuera del alcance no aparecen como botones falsamente operativos', () => {
    assert.doesNotMatch(publicUi, /explore-actions\.js/);
    assert.doesNotMatch(explore, /Comprar \/ pujar|Pujar/);
});

test('una ruta privada sin sesión vuelve al login conservando destino local', () => {
    let redirected = '';
    const location = { pathname: '/productores.php', replace: (target) => { redirected = target; } };
    const storage = { getItem: () => null };
    assert.equal(isPrivateRoute(location.pathname), true);
    assert.equal(loginTarget(location.pathname), 'login.php?area=admin&next=productores.php');
    assert.equal(enforceBrowserSession({ location, storage }), false);
    assert.equal(redirected, 'login.php?area=admin&next=productores.php');
});

test('el shell privado distingue volver al sitio público de cerrar sesión', () => {
    assert.ok(authGate.includes("publicLink.textContent = 'Sitio público'"));
    assert.ok(authGate.includes("logoutLink.textContent = 'Cerrar sesión administrativa'"));
    assert.ok(authGate.includes('clearAdminBrowserSession(storage)'));
});

test('los paneles privados fallan cerrados y comparten bootstrap de API', () => {
    assert.ok(baseCss.includes('body.rural-panel {\n    visibility:hidden;'));
    assert.ok(baseCss.includes("html[data-tc-auth='ready'] body.rural-panel"));
    assert.ok(api.startsWith("import './auth-gate.js';\nimport './admin-ui.js';"));
    for (const path of PRIVATE_MODULES) {
        const module = read(path);
        assert.ok(module.includes("from './shared/api.js'"));
    }
});
