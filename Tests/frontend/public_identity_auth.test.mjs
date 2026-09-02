import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { resolveNext } from '../../Public/js/login.js';
import {
    enforceBrowserSession,
    isPrivateRoute,
    loginTarget,
    readBrowserSession,
    SESSION_KEY,
} from '../../Public/js/shared/auth-gate.js';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const home = read('../../Application/View/home/index.php');
const login = read('../../Application/View/login/index.php');
const info = read('../../Application/View/public/info.php');
const publicCss = read('../../Public/css/public-auth.css');
const publicV3Css = read('../../Public/css/public-v3.css');
const themeJs = read('../../Public/js/public-theme.js');
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
    '../../Public/sobre-nosotros.php',
    '../../Public/como-usar.php',
    '../../Public/privacidad.php',
    '../../Public/terminos.php',
    '../../Public/legal.php',
];

test('la portada usa identidad oficial TinderCows y no vuelve a exponer un catalogo de modulos', () => {
    assert.ok(home.includes('assets/logo_dark.png'));
    assert.ok(home.includes('assets/logo_light.png'));
    assert.ok(home.includes('rel="icon" href="favicon.svg"'));
    assert.ok(home.includes('Encuentra. Conecta. Gestiona.'));
    assert.equal(home.includes('id="modulos"'), false, 'la landing no debe convertirse en un indice del admin');
    assert.equal(home.includes('login.php?next='), false, 'la landing no debe explicar rutas privadas');
    assert.equal(home.includes('js/home.js'), false, 'la portada pública no debe consultar la red privada');
    assert.equal(home.includes('api/productores.php'), false);
});

test('el navbar reserva la navegacion primaria para tareas publicas y deja lo legal en footer', () => {
    const nav = home.match(/<nav class="public-nav public-nav--primary"[\s\S]*?<\/nav>/)?.[0] ?? '';
    for (const destination of ['#inicio', '#buscar-ganado', '#nosotros', '#como-funciona']) {
        assert.ok(nav.includes(`href="${destination}"`), `falta ${destination} en navegación primaria`);
    }
    for (const legal of ['privacidad.php', 'terminos.php', 'legal.php']) {
        assert.equal(nav.includes(legal), false, `${legal} debe vivir solo en el footer`);
        assert.ok(home.includes(`href="${legal}"`), `${legal} debe seguir accesible desde el footer`);
    }
    assert.ok(nav.includes('fa-house'));
    assert.ok(nav.includes('fa-magnifying-glass'));
    assert.ok(nav.includes('<span>Inicio</span>'));
    assert.ok(nav.includes('<span>Buscar ganado</span>'));
});

test('la búsqueda de ganado es un preview honesto y no inventa resultados sin contrato', () => {
    assert.ok(home.includes('id="buscar-ganado"'));
    assert.ok(home.includes('cattle-search-preview'));
    assert.ok(home.includes('readonly'));
    assert.ok(home.includes('no expone aún una entidad ni un endpoint de ganado'));
    assert.ok(home.includes('no inventa animales, razas ni resultados'));
    assert.ok(publicV3Css.includes('.cattle-search-preview'));
});

test('la paleta pública sale del logo y elimina la referencia cromática de Tinder', () => {
    for (const token of ['#151a18', '#2f3c2d', '#d24f28', '#eedbca', '#fef7ec', '#394332']) {
        assert.ok(publicCss.includes(token), `falta color de identidad ${token}`);
    }
    for (const tinderColor of ['#fd267a', '#ff315f', '#ff6746', '#7c3aed']) {
        assert.equal(publicCss.includes(tinderColor), false, `no debe sobrevivir el color Tinder ${tinderColor}`);
    }
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

test('el acceso declara de forma explícita que es una demostración local', () => {
    assert.ok(login.includes('Acceso de demostración'));
    assert.ok(login.includes('No valida credenciales contra un backend'));
    assert.ok(login.includes('name="email"'));
    assert.ok(login.includes('name="password"'));
    assert.ok(login.includes('id="formulario-login"'));
    assert.ok(login.includes('assets/logo_dark.png'));
    assert.ok(login.includes('rel="icon" href="favicon.svg"'));
    assert.equal(/Google|Facebook|Apple/.test(login), false);
});

test('existen las rutas públicas informativas y usan una plantilla común', () => {
    for (const route of PUBLIC_ROUTES) {
        const wrapper = read(route);
        assert.ok(wrapper.includes("Application/View/public/info.php"), `${route} debe usar la plantilla pública común`);
    }
    for (const key of ['about', 'guide', 'privacy', 'terms', 'legal']) {
        assert.ok(info.includes(`'${key}' => [`), `falta página ${key}`);
    }
});

test('next solo acepta rutas privadas locales y rechaza redirecciones abiertas', () => {
    assert.equal(resolveNext('?next=vehiculos.php'), 'vehiculos.php');
    assert.equal(resolveNext('?next=pagometodos.php'), 'pagometodos.php');
    assert.equal(resolveNext('?next=https://example.com'), 'productores.php');
    assert.equal(resolveNext('?next=//example.com'), 'productores.php');
    assert.equal(resolveNext('?next=../login.php'), 'productores.php');
});

test('la puerta requiere un marcador de sesión estructurado', () => {
    const makeStorage = (value) => ({ getItem: (key) => key === SESSION_KEY ? value : null });
    assert.equal(readBrowserSession(makeStorage(null)), null);
    assert.equal(readBrowserSession(makeStorage('{mal json')), null);
    assert.equal(readBrowserSession(makeStorage(JSON.stringify({ email: 'a@b.test' }))), null);

    const valid = JSON.stringify({
        authenticated: true,
        version: 1,
        email: 'a@b.test',
        startedAt: '2026-09-01T12:00:00.000Z',
        mode: 'local-browser-session',
    });
    assert.equal(readBrowserSession(makeStorage(valid))?.email, 'a@b.test');
});

test('una ruta privada sin sesión vuelve al login conservando destino local', () => {
    let redirected = '';
    const location = {
        pathname: '/productores.php',
        replace: (target) => { redirected = target; },
    };
    const storage = { getItem: () => null };
    assert.equal(isPrivateRoute(location.pathname), true);
    assert.equal(loginTarget(location.pathname), 'login.php?next=productores.php');
    assert.equal(enforceBrowserSession({ location, storage }), false);
    assert.equal(redirected, 'login.php?next=productores.php');
});

test('el shell privado distingue volver al sitio público de cerrar sesión', () => {
    assert.ok(authGate.includes("publicLink.textContent = 'Sitio público'"));
    assert.ok(authGate.includes("logoutLink.textContent = 'Cerrar sesión'"));
    assert.ok(authGate.includes('storage?.removeItem(SESSION_KEY)'));
});

test('los paneles privados fallan cerrados hasta que auth-gate valida sesión', () => {
    assert.ok(baseCss.includes('body.rural-panel {\n    visibility:hidden;'));
    assert.ok(baseCss.includes("html[data-tc-auth='ready'] body.rural-panel"));
});

test('todos los módulos privados pasan por api.js y activan auth + shell UX', () => {
    assert.ok(api.startsWith("import './auth-gate.js';\nimport './admin-ui.js';"));
    for (const path of PRIVATE_MODULES) {
        const module = read(path);
        assert.ok(module.includes("from './shared/api.js'"), `${path} debe pasar por el bootstrap protegido de API`);
    }
});
