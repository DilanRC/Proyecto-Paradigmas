// El carrito del encabezado es un contador de solicitudes de compra aprobadas (decisión del 06/10),
// no usa las tablas tbcarrito*. Pruebas estáticas: no hay navegador en el entorno de pruebas.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8').replace(/\r\n/g, '\n');
const ui = read('Public/js/public-ui.js');
const css = read('Public/css/public-product.css');
const VISTAS_PUBLICAS = ['ajustes', 'explorar', 'fletes', 'home', 'me-interesa', 'mi-actividad', 'publicar'];

test('el carrito solo existe con sesión (dentro del menú de cuenta) y enlaza a Mis solicitudes', () => {
    assert.match(ui, /link\.href = 'mi-actividad#mis-solicitudes'/);
    assert.match(ui, /fa-cart-shopping/);
    assert.match(ui, /menu\.before\(cart\)/);
    assert.ok(ui.indexOf('const cart = createCartLink()') > ui.indexOf('function createAccountMenu'), 'se crea dentro de createAccountMenu, que solo corre con sesión');
});

test('la etiqueta accesible lleva el número y la insignia se oculta en 0', () => {
    assert.match(ui, /`Mis solicitudes aprobadas: \$\{total\}`/);
    assert.match(ui, /badge\.hidden = total === 0/);
    assert.match(ui, /aria-hidden="true" hidden>0/);
});

test('pide el resumen una vez, con caché corta y falla en silencio', () => {
    assert.match(ui, /api\/v1\/solicitudes-compra\?resumen=1/);
    assert.match(ui, /CART_TTL_MS/);
    assert.match(ui, /sessionStorage\?\.setItem\(CART_KEY/);
    const cuerpo = ui.slice(ui.indexOf('async function loadCartCount'), ui.indexOf('function createAccountMenu'));
    assert.match(cuerpo, /catch \{\s*\/\/ El contador es un adorno/);
    assert.ok(!cuerpo.includes('throw'), 'nunca lanza hacia la navegación');
    assert.match(ui, /void loadCartCount\(cart, session\.email\)/);
});

test('la mini-bandeja de Mi panel tiene el ancla y el hash lleva a la vista', () => {
    assert.match(read('Application/View/mi-actividad/index.php'), /id="mis-solicitudes"/);
    const panel = read('Public/js/mi-actividad.js');
    assert.match(panel, /location\?\.hash === '#mis-solicitudes'/);
    assert.match(panel, /querySelector\('#mis-solicitudes'\)\?\.scrollIntoView\(\)/);
});

test('la insignia usa solo tokens del sitio y el CSS y el JS suben de versión en todas las vistas', () => {
    const insignia = css.slice(css.indexOf('.public-cart__badge {'), css.indexOf('.public-cart__badge[hidden]'));
    assert.match(insignia, /background: var\(--tc-primary\)/);
    assert.ok(!/#[0-9a-f]{3,6}/i.test(insignia.replace('#151a18', '')), 'sin colores nuevos');
    assert.match(css, /\.public-cart:focus-visible \{\s*outline:/);
    for (const vista of VISTAS_PUBLICAS) {
        const html = read(`Application/View/${vista}/index.php`);
        assert.match(html, /js\/public-ui\.js\?v=public-14/, `${vista} sin public-14`);
        assert.match(html, /css\/public-product\.css\?v=product-9/, `${vista} sin product-9`);
    }
    assert.match(read('Application/View/public/info.php'), /js\/public-ui\.js\?v=public-14/);
});

test('la API devuelve solo el contador y el cliente no usa tablas de carrito', () => {
    const controlador = read('Application/Controller/SolicitudesCompraController.php');
    assert.match(controlador, /'aprobadas' =>/);
    assert.match(controlador, /\$consulta\['resumen'\]/);
    assert.ok(!/tbcarrito/.test(read('Application/Model/CompraSolicitud.php') + controlador));
    assert.match(read('Public/api/solicitudes-compra.php'), /->procesar\(\$metodo, \$cuerpo, \$_GET\)/);
});
