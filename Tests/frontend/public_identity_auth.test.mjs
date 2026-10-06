import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import test from 'node:test';

import { isAdminLogin, resolveAdminNext, resolveNext } from '../../Public/js/login.js';
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
const publicV3 = read('../../Public/css/public-v3.css');
const registroJs = read('../../Public/js/registro.js');
const supabaseAuth = read('../../Public/js/shared/supabase-auth.js');
const fletes = read('../../Application/View/fletes/index.php');
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

test('la portada usa identidad Ganado Cerca y habla como producto', () => {
    assert.ok(home.includes('assets/logo_dark.png'));
    assert.ok(home.includes('assets/logo_light.png'));
    assert.ok(home.includes('rel="icon" href="favicon.svg"'));
    assert.ok(home.includes('Compra y vende ganado cerca de ti.'));
    assert.ok(home.includes('Ganado<strong>Cerca</strong>'));
    assert.ok(home.includes('assets/hero-ganado-cerca.png'));
    assert.ok(statSync(new URL('../../Public/assets/hero-ganado-cerca.png', import.meta.url)).size > 10000);
    assert.ok(home.includes('href="explorar"'));
    assert.equal(/EIF400|acad[eé]mic/i.test(home), false, 'la experiencia pública no debe hablar del curso ni de evaluación');
    assert.equal(home.includes('Productores</h3>'), false, 'la landing no debe explicar módulos administrativos');
    assert.equal(home.includes('api/productores.php'), false);
});

test('navbar público prioriza Inicio y Explorar; legal queda en footer', () => {
    const nav = home.match(/<nav class="public-nav public-nav--primary"[\s\S]*?<\/nav>/)?.[0] ?? '';
    for (const label of ['Inicio', 'Explorar']) assert.ok(nav.includes(label));
    for (const label of ['Nosotros', 'Cómo funciona']) assert.equal(nav.includes(label), false);
    for (const label of ['Privacidad', 'Términos', 'Legal']) assert.equal(nav.includes(label), false);
    assert.ok(home.includes('public-footer__legal'));
    assert.ok(home.includes('href="privacidad"'));
    assert.ok(home.includes('href="terminos"'));
    assert.ok(home.includes('href="legal"'));
});

test('la búsqueda pública permanece compacta y se expande bajo demanda', () => {
    assert.ok(home.includes('data-public-search'));
    assert.ok(home.includes('data-public-search-toggle'));
    assert.ok(productCss.includes(".public-search[data-open='true'] .public-search__field"));
    assert.ok(publicUi.includes('root.dataset.open = String(safeOpen)'));
    assert.ok(publicUi.includes("safeOpen ? 'Cerrar búsqueda' : 'Buscar'"));
    assert.ok(publicUi.includes('visibleLabel.textContent = label'));
    assert.ok(publicUi.includes("event.key === 'Escape'"));
});

test('la portada sigue el orden hero, destacadas, cómo funciona', () => {
    const orden = ['public-hero--landing', 'data-featured', 'id="como-funciona"', 'public-footer']
        .map((marca) => home.indexOf(marca));
    assert.ok(orden.every((pos) => pos >= 0), 'falta una sección de la portada');
    assert.deepEqual([...orden].sort((a, b) => a - b), orden, 'las secciones no están en orden');
    // El buscador del hero lleva a Explorar con ubicación y tipo etiquetados.
    assert.match(home, /<form class="hero-search" action="explorar"/);
    assert.match(home, /<label for="hero-search-ubicacion">/);
    assert.match(home, /<label for="hero-search-tipo">/);
    assert.match(home, /name="ubicacion"/);
    assert.match(home, /name="tipo"/);
    // Sin publicaciones la portada da la bienvenida; nunca dice "no encontramos".
    assert.ok(home.includes('Pronto verás aquí ganado cerca de ti'));
    assert.ok(home.includes('Crear cuenta para publicar'));
    assert.equal(/No encontramos/i.test(home), false);
    // Carrusel con flechas y puntos; la tarjeta es la misma de Explorar.
    for (const marca of ['data-featured-carousel', 'data-carousel-track', 'data-carousel-prev', 'data-carousel-next', 'data-carousel-dots']) {
        assert.ok(home.includes(marca), `falta ${marca}`);
    }
    const homeJs = read('../../Public/js/home.js');
    assert.match(homeJs, /import \{ buildCard \} from '\.\/explore\.js(\?v=[a-z0-9-]+)?'/);
    assert.match(homeJs, /const FIJAS_HASTA = 4;/);
    for (const destino of ['href="explorar"', 'href="registro"']) assert.ok(home.includes(destino));
    // Las formas de participar viven en Mi actividad, después de iniciar sesión.
    assert.equal(home.includes('id="participar"'), false);
    assert.equal(home.includes('data-public-carousel'), false);
});

test('Fletes comparte el shell público y no expone lenguaje técnico', () => {
    for (const label of ['Inicio', 'Explorar', 'Fletes']) {
        assert.ok(fletes.includes(`<span>${label}</span>`), `Fletes debe conservar ${label} en su navegación`);
    }
    for (const label of ['Nosotros', 'Cómo funciona']) {
        assert.equal(fletes.includes(`<span>${label}</span>`), false, `Fletes no debe mostrar ${label} en su navegación`);
    }
    assert.ok(fletes.includes('js/public-ui.js'));
    assert.ok(fletes.includes('css/public-product.css'));
    for (const copy of ['CRUD', 'Regla aplicada', 'panel administrativo', 'roles administrativos']) {
        assert.equal(fletes.includes(copy), false, `Fletes no debe exponer ${copy}`);
    }
});

test('la navegación accesible usa el mismo texto visible que anuncia', () => {
    for (const view of [home, explore, fletes, info]) {
        assert.equal(view.includes('aria-label="Ganado Cerca, inicio"'), false);
        assert.equal(view.includes('aria-label="Abrir búsqueda"'), false);
    }
    assert.ok(publicUi.includes("safeOpen ? 'Cerrar búsqueda' : 'Buscar'"));
    assert.ok(publicUi.includes('visibleLabel.textContent = label'));
    assert.ok(publicUi.includes('href="admin/dashboard" data-admin-link'));
});

test('Explorar es una vista distinta con tarjetas completas y acciones icono más texto', () => {
    assert.ok(read('../../Public/explorar.php').includes("Application/View/explorar/index.php"));
    assert.ok(explore.includes('data-explore-deck'));
    // Sin flechas: el scroll inferior aparece solo con más de 4 tarjetas y el
    // snap encaja tarjetas enteras, así que ninguna queda cortada.
    assert.equal(explore.includes('data-explore-prev'), false);
    assert.equal(explore.includes('data-explore-next'), false);
    const css = read('../../Public/css/explore.css');
    assert.match(css, /--explore-por-vista:4;/);
    assert.match(css, /scroll-snap-align:start;/);
    assert.match(css, /\.explore-card:first-child \{ margin-inline-start:auto; \}/);
    assert.ok(read('../../Public/js/explore.js').includes('aria-pressed'));
    // Las acciones viajan con la tarjeta, que ahora construye explore.js con lo
    // que devuelve api/publicaciones.php; la escritura requiere sesión y se
    // realiza desde publicar.js con el bearer verificado.
    const moduloExplorar = read('../../Public/js/explore.js');
    for (const action of ['Me interesa', 'Contactar']) {
        assert.ok(moduloExplorar.includes(`'${action}'`), `falta la acción ${action}`);
    }
    assert.equal(moduloExplorar.includes("'Pasar'"), false, 'la tarjeta ya no ofrece Pasar');
    assert.ok(moduloExplorar.includes('api/v1/publicaciones'),
        'el deck debe leer el catálogo real, no contenido de muestra');
    assert.equal(/EIF400|acad[eé]mic/i.test(explore), false);
});

test('publicar persiste mediante el endpoint autenticado y limpia el borrador solo al guardar', () => {
    assert.match(publicarJs, /import\s*\{[^}]*\bgetAccessToken\b[^}]*\}\s*from\s*'\.\/shared\/supabase-auth\.js';/);
    assert.ok(publicarJs.includes("method: 'POST'"));
    assert.ok(publicarJs.includes("fetch('api/v1/publicaciones'"));
    assert.ok(publicarJs.includes("sessionStorage.removeItem(DRAFT_KEY)"));
    assert.ok(publicarApi.includes("['GET', 'POST', 'PATCH']"));
    assert.ok(publicarApi.includes('readJsonBody()'));
});

test('Explorar persiste Me interesa y Contactar con la Persona autenticada', () => {
    for (const type of ['ME_INTERESA', 'CONTACTAR']) {
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

test('registro guiado separa nombres y apellidos y conserva el nombre canónico', () => {
    const registro = read('../../Application/View/registro/index.php');
    assert.ok(registro.includes('name="nombres"'));
    assert.ok(registro.includes('name="apellidos"'));
    assert.ok(registroJs.includes("const nombres = String(data.get('nombres')"));
    assert.ok(registroJs.includes("const apellidos = String(data.get('apellidos')"));
    assert.ok(registroJs.includes("nombre: [nombres, apellidos].filter(Boolean).join(' ')"));
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
    assert.ok(login.includes('Entrar a Ganado Cerca'));
    assert.doesNotMatch(login, /Acceso seguro|credenciales se validan con Supabase Auth/);
    assert.ok(login.includes('name="email"'));
    assert.ok(login.includes('name="password"'));
    assert.equal(/EIF400|acad[eé]mic/i.test(login), false);
    assert.equal(resolveNext(''), 'explorar');
    assert.equal(resolveNext('?next=explorar'), 'explorar');
    assert.equal(resolveNext('?next=vehiculos'), 'explorar');
    assert.equal(resolveNext('?next=https://example.com'), 'explorar');
    assert.equal(resolveNext('?next=//example.com'), 'explorar');
    assert.equal(resolveNext('?next=../entrar'), 'explorar');
    // Vuelve a la publicación o búsqueda de origen, solo con parámetros seguros.
    assert.equal(resolveNext('?next=explorar%3Fpublicacion%3D5'), 'explorar?publicacion=5');
    assert.equal(resolveNext('?next=explorar%3Fpublicacion%3D5%26x%3Djs'), 'explorar?publicacion=5');
    assert.equal(resolveNext('?next=explorar%3Fpublicacion%3Dabc'), 'explorar');
    assert.equal(resolveNext('?next=explorar%3Fq%3Dbrahman'), 'explorar?q=brahman');
    assert.equal(resolveAdminNext('?next=admin/productores'), 'admin/productores');
    assert.equal(resolveAdminNext('?next=https://example.com'), 'admin/dashboard');
    assert.equal(isAdminLogin({ pathname: '/admin/entrar', search: '?next=admin%2Fdashboard' }), true);
    assert.equal(isAdminLogin({ pathname: '/entrar', search: '?area=admin' }), true);
    assert.equal(isAdminLogin({ pathname: '/entrar', search: '' }), false);
});

test('el estado de autenticación tiene contraste y tamaño legibles', () => {
    assert.match(publicCss, /\.auth-status\s*\{[\s\S]*font-size:14px/);
    assert.match(publicCss, /\.auth-status\s*\{[\s\S]*font-weight:700/);
    assert.match(publicCss, /\.auth-status\s*\{[\s\S]*background:var\(--tc-success-bg\)/);
    assert.match(publicCss, /\.auth-status\s*\{[\s\S]*color:var\(--tc-success\)/);
    assert.match(publicCss, /\.auth-status:empty\s*\{[\s\S]*display:none/);
});

test('la cuenta autenticada muestra perfil y no vuelve a ofrecer Entrar', () => {
    assert.match(publicUi, /readAuthSession/);
    assert.match(publicUi, /createAccountMenu/);
    assert.match(publicUi, /href="mi-actividad"><i class="fa-solid fa-table-columns" aria-hidden="true"><\/i><span>Mi panel<\/span>/);
    // Ajustes de cuenta vive en el menú del avatar.
    assert.match(publicUi, /href="ajustes"><i class="fa-solid fa-gear" aria-hidden="true"><\/i><span>Ajustes de cuenta<\/span>/);
    assert.match(publicUi, /api\/v1\/admin\/status/);
    assert.doesNotMatch(publicUi, /sessionStorage\.setItem\(SESSION_KEY/);
});

test('el registro guiado persiste mediante Supabase y la API, no mediante una sesión falsa', () => {
    assert.match(registroJs, /signUpWithPassword/);
    assert.match(registroJs, /api\/v1\/registro/);
    assert.match(registroJs, /syncPublicProfile/);
    assert.doesNotMatch(registroJs, /frontend-prototype/);
});

test('el registro autenticado no reutiliza un perfil cacheado de otro correo', () => {
    assert.match(registroJs, /const existingProfile = authSession[\s\S]*authEmail === profileEmail \? cachedProfile : null/);
    assert.ok(registroJs.indexOf('restoreDraft(form, existingProfile)') < registroJs.indexOf('email.value = authSession.email'));
});

test('el registro verifica la identificación antes de avanzar y de crear la cuenta', () => {
    assert.match(registroJs, /api\/v1\/registro\/identificacion/);
    assert.match(registroJs, /La identificación ya está registrada\./);
    assert.match(registroJs, /const syncNextButton = \(\) => \{[\s\S]*nextButton\.disabled[\s\S]*identityState/);
    assert.match(registroJs, /if \(!extending && !\(await checkIdentity\(\)\)\)/);
    assert.match(read('../../Application/View/registro/index.php'), /data-identificacion-status[^>]*role="status"/);
});

test('el registro verifica el correo en tiempo real (P2-1) y trata el límite por IP', () => {
    assert.match(registroJs, /JSON\.stringify\(\{ correoElectronico: valor \}\)/);
    assert.match(registroJs, /correoElectronico\?\.addEventListener\('input', scheduleEmailCheck\)/);
    assert.match(registroJs, /setTimeout\(\(\) => \{ emailTimer = null; void checkEmail\(\); \}, 400\)/);
    // Solo en el alta sin sesión, y solo bloquea si el correo ya está registrado.
    assert.match(registroJs, /const emailCheckEnabled = !extending && !authSession/);
    assert.match(registroJs, /emailState === 'taken'/);
    assert.match(registroJs, /if \(!\(await checkEmail\(\)\)\) \{/);
    assert.match(registroJs, /error\?\.status === 429/);
    // En el alta de un solo paso el botón es Registrar: también se bloquea, salvo durante el envío.
    assert.match(registroJs, /if \(!submitInProgress\) finishButton\.disabled = bloqueado;/);
    assert.match(read('../../Application/View/registro/index.php'), /data-correo-status[^>]*role="status"/);
});

test('el alta muestra la misma guía clara sin confirmar si el correo está registrado', () => {
    assert.match(supabaseAuth, /code === 'user_already_exists'/);
    assert.match(supabaseAuth, /providerMessage\.includes\('already registered'\)/);
    assert.match(supabaseAuth, /Si el correo puede usarse para crear una cuenta, te enviaremos instrucciones/);
    assert.match(registroJs, /window\.location\.assign\(loginPendiente\(\)\)/);
    assert.doesNotMatch(registroJs, /Ese correo ya tiene una cuenta/);
    assert.match(registroJs, /let submitInProgress = false/);
});

test('el borrador guiado no persiste contraseñas aunque las conserve en memoria para Supabase', () => {
    assert.match(registroJs, /Object\.entries\(draft\.persona\)\.filter/);
    assert.match(registroJs, /!\['password', 'passwordConfirmacion'\]\.includes\(field\)/);
    assert.match(supabaseAuth, /email_confirmed_at/);
});

test('el alta no queda bloqueada si falla la lectura auxiliar del perfil', () => {
    assert.match(registroJs, /const activity = await request\('api\/v1\/actividad', \{ timeoutMs: 10000 \}\)/);
    assert.match(registroJs, /mi-actividad vuelve a consultar el servidor/);
    assert.match(registroJs, /window\.location\.assign\(resolveNext\(fallback\)\)/);
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
    const location = { pathname: '/admin/productores', replace: (target) => { redirected = target; } };
    const storage = { getItem: () => null };
    assert.equal(isPrivateRoute(location.pathname), true);
    assert.equal(loginTarget(location.pathname), 'admin/entrar?next=admin%2Fproductores');
    assert.equal(enforceBrowserSession({ location, storage }), false);
    assert.equal(redirected, 'admin/entrar?next=admin%2Fproductores');
});

test('las rutas públicas no redirigen al login por el gate administrativo', () => {
    assert.match(
        authGate,
        /if \(isPrivateRoute\(pathname\)\)/,
    );
});

test('el shell privado distingue volver al sitio público de cerrar sesión', () => {
    assert.ok(authGate.includes("publicLink.textContent = 'Sitio público'"));
    assert.ok(authGate.includes("logoutLink.textContent = 'Cerrar sesión'"));
    // Salir del panel cierra la sesión completa, no solo el permiso admin.
    assert.ok(authGate.includes('void signOutEverywhere(storage)'));
    assert.ok(authGate.includes('clearAdminBrowserSession(storage)'));
});

test('los paneles privados fallan cerrados y comparten bootstrap de API', () => {
    assert.ok(baseCss.includes('body.rural-panel {\n    visibility:hidden;'));
    assert.ok(baseCss.includes("html[data-tc-auth='ready'] body.rural-panel"));
    assert.ok(api.startsWith("import './auth-gate.js?v=auth-gate-6';\nimport './admin-ui.js?v=admin-7';"));
    for (const path of PRIVATE_MODULES) {
        const module = read(path);
        assert.ok(module.includes("from './shared/api.js?v=auth-gate-6'"), `${path} debe versionar api.js`);
    }
});
