import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const PANEL_VIEWS = ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos'];

test('la marca usa los PNG oficiales en sitio público, login y paneles', () => {
    const home = read('Application/View/home/index.php');
    const login = read('Application/View/login/index.php');
    const explore = read('Application/View/explorar/index.php');

    for (const html of [home, login, explore]) {
        assert.match(html, /assets\/logo_light\.png/);
        assert.match(html, /assets\/logo_dark\.png/);
        assert.match(html, /favicon\.svg/);
    }

    for (const view of PANEL_VIEWS) {
        const html = read(`Application/View/${view}/index.php`);
        assert.match(html, /assets\/logo_light\.png/, `${view}: falta logo oficial`);
        assert.doesNotMatch(html, /<svg viewBox="0 0 48 48">/, `${view}: no debe volver el icono SVG viejo`);
    }
});

test('la landing se comporta como producto y deriva la exploración a una ruta propia', () => {
    const home = read('Application/View/home/index.php');
    const explore = read('Application/View/explorar/index.php');

    assert.ok(home.includes('El ganado que buscas, más cerca de ti.'));
    assert.ok(home.includes('href="explorar.php"'));
    assert.equal(home.includes('id="modulos"'), false);
    assert.equal(/EIF400|acad[eé]mic/i.test(home), false);
    assert.ok(explore.includes('data-explore-deck'));
    // Las tarjetas y sus acciones las construye explore.js desde
    // api/publicaciones.php: en el PHP solo queda el contenedor del deck.
    assert.ok(explore.includes('js/explore.js'));
});

test('navbar y búsqueda pública conservan icono más texto sin meter legal en navegación primaria', () => {
    const home = read('Application/View/home/index.php');
    const productCss = read('Public/css/public-product.css');
    const nav = home.match(/<nav class="public-nav public-nav--primary"[\s\S]*?<\/nav>/)?.[0] ?? '';

    for (const label of ['Inicio', 'Explorar', 'Nosotros', 'Cómo funciona']) assert.ok(nav.includes(label));
    assert.equal(nav.includes('Privacidad'), false);
    assert.ok(home.includes('data-public-search-toggle'));
    assert.ok(productCss.includes(".public-search[data-open='true'] .public-search__field"));
});

test('login existe como entrada navegable, no guarda contraseña y vuelve a Explorar', () => {
    const home = read('Application/View/home/index.php');
    const login = read('Application/View/login/index.php');
    const js = read('Public/js/login.js');

    assert.match(home, /href="login\.php"/);
    assert.match(login, /id="formulario-login"/);
    assert.match(login, /type="password"/);
    assert.match(js, /sessionStorage\.setItem\(SESSION_KEY/);
    assert.match(js, /: 'explorar\.php';/);
    assert.doesNotMatch(js, /password[^;]*sessionStorage\.setItem/s);
});

test('admin mantiene ancho útil, sidebar colapsable y paginación al pie', () => {
    const adminCss = read('Public/css/admin-v3.css');
    const collapseCss = read('Public/css/admin-sidebar-collapse.css');
    const refinementsCss = read('Public/css/admin-refinements.css');
    const adminJs = read('Public/js/shared/admin-ui.js');

    assert.ok(adminCss.includes('width:min(100%,1500px)'));
    assert.ok(collapseCss.includes('--admin-sidebar-collapsed-width:82px'));
    assert.ok(adminJs.includes("footer.className = 'admin-table-footer'"));
    assert.ok(refinementsCss.includes('.admin-table-footer .pagination'));
    assert.ok(adminJs.includes("trigger.className = 'admin-account-menu__trigger'"));
});
