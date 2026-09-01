import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const PANEL_VIEWS = ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos'];

test('la marca usa los PNG oficiales en landing, login y paneles', () => {
    const home = read('Application/View/home/index.php');
    const login = read('Application/View/login/index.php');

    assert.match(home, /assets\/logo_light\.png/, 'landing debe mostrar logo claro en la barra');
    assert.match(home, /assets\/logo_dark\.png/, 'landing debe mostrar logo oscuro en el hero');
    assert.match(login, /assets\/logo_dark\.png/, 'login debe usar logo oscuro');
    assert.match(login, /assets\/logo_light\.png/, 'login debe usar logo claro en el panel lateral');

    for (const view of PANEL_VIEWS) {
        const html = read(`Application/View/${view}/index.php`);
        assert.match(html, /assets\/logo_light\.png/, `${view}: falta logo claro oficial`);
        assert.doesNotMatch(html, /<svg viewBox="0 0 48 48">/, `${view}: no debe volver el icono SVG viejo`);
    }
});

test('la landing oficial explica el proyecto y conserva la baraja conectada al JS', () => {
    const html = read('Application/View/home/index.php');

    for (const copy of [
        'Qué resuelve',
        'Cómo usar TinderCows',
        'Productores',
        'Compradores',
        'Transportistas',
        'Métodos de pago',
    ]) {
        assert.ok(html.includes(copy), `landing incompleta: falta "${copy}"`);
    }

    for (const id of [
        'contador-guardados',
        'contador-revisados',
        'tarjeta-productor',
        'accion-pasar',
        'accion-guardar',
        'accion-contactar',
    ]) {
        assert.match(html, new RegExp(`id="${id}"`), `home.js necesita #${id}`);
    }
});

test('login existe como entrada navegable y no guarda contraseñas', () => {
    const home = read('Application/View/home/index.php');
    const login = read('Application/View/login/index.php');
    const js = read('Public/js/login.js');

    assert.match(home, /href="login\.php"/, 'la landing debe enlazar al login');
    assert.match(login, /id="formulario-login"/, 'login debe declarar formulario');
    assert.match(login, /type="password"/, 'login debe tener campo de contraseña');
    assert.match(js, /sessionStorage\.setItem\(SESSION_KEY/, 'login debe marcar sesión de navegador');
    assert.doesNotMatch(js, /password[^;]*sessionStorage\.setItem/s, 'login no debe persistir la contraseña');
});

test('las tablas usan todo el ancho sin scroll horizontal por defecto', () => {
    const css = read('Public/css/panel.css');

    assert.match(css, /\.table-container\s*{[^}]*overflow-x:visible;/s,
        'el contenedor de tabla no debe forzar scroll horizontal');
    assert.match(css, /table\s*{[^}]*table-layout:fixed;/s,
        'la tabla debe distribuir columnas dentro del ancho disponible');
    assert.match(css, /th, td\s*{[^}]*overflow-wrap:anywhere;/s,
        'el contenido largo debe quebrarse dentro de su celda');
    assert.match(css, /\.row-actions\s*{[^}]*flex-wrap:wrap;/s,
        'las acciones no deben ensanchar la tabla');
});
