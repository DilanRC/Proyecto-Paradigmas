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
const publicCss = read('../../Public/css/public-auth.css');
const api = read('../../Public/js/shared/api.js');

const PRIVATE_MODULES = [
    '../../Public/js/productores.js',
    '../../Public/js/compradores.js',
    '../../Public/js/transportistas.js',
    '../../Public/js/vehiculos.js',
    '../../Public/js/pagometodos.js',
];

test('la portada pública presenta solo el producto y no consulta datos privados', () => {
    const forbidden = /productor|comprador|transportista|veh[ií]cul|ganader|finca|vaca/i;
    assert.equal(forbidden.test(home), false, 'la identidad pública no debe adelantar módulos o dominio interno');
    assert.ok(home.includes('<h1 id="public-title">TinderCows</h1>'));
    assert.ok(home.includes('href="login.php"'));
    assert.ok(home.includes('public-auth.css'));
    assert.equal(home.includes('js/home.js'), false, 'la portada no debe volver a solicitar la red privada');
    assert.equal(home.includes('api/productores.php'), false);
});

test('portada y login usan placeholders visuales reemplazables sin imágenes del dominio', () => {
    assert.ok(home.includes('Imagen principal · placeholder'));
    assert.ok(home.includes('Imagen 02'));
    assert.ok(login.includes('Imagen principal · placeholder'));
    assert.ok(login.includes('Imagen secundaria · placeholder'));
    assert.equal(home.includes('assets/logo_'), false, 'la portada no debe mostrar el logo bovino previo');
    assert.equal(login.includes('assets/logo_'), false, 'el acceso no debe mostrar el logo bovino previo');
});

test('el acceso conserva el formulario real en vez de inventar proveedores OAuth', () => {
    assert.ok(login.includes('name="email"'));
    assert.ok(login.includes('name="password"'));
    assert.ok(login.includes('id="formulario-login"'));
    assert.equal(/Google|Facebook|Apple/.test(login), false);
    assert.equal(/productor|comprador|transportista|ganader|finca|vaca/i.test(login), false);
});

test('la identidad visual pública tiene paleta propia inspirada, no copia literal de Tinder', () => {
    assert.ok(publicCss.includes('--public-hot:#ff315f'));
    assert.ok(publicCss.includes('--public-coral:#ff6746'));
    assert.ok(publicCss.includes('--public-violet:#7c3aed'));
    assert.ok(publicCss.includes('--public-gradient:'));
    assert.equal(publicCss.includes('#fd267a'), true, 'la referencia observada debe quedar documentada, no usada como token principal');
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

test('todos los módulos privados pasan por api.js y api.js activa auth-gate', () => {
    assert.ok(api.startsWith("import './auth-gate.js';"));
    for (const path of PRIVATE_MODULES) {
        const module = read(path);
        assert.ok(module.includes("from './shared/api.js'"), `${path} debe pasar por el bootstrap protegido de API`);
    }
});
