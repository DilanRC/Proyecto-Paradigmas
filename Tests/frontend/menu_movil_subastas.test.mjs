import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (ruta) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');

/** Nodo mínimo para gruposMenuMovil: solo lo que la función consulta. */
function nodo({ href = null, hidden = false, hijos = [] } = {}) {
    return {
        hidden, children: hijos,
        getAttribute: (n) => (n === 'href' ? href : null),
        matches: (sel) => sel === 'a[href]' && href !== null,
        classList: { contains: () => false },
    };
}
function header({ nav = [], cuenta = null }) {
    return {
        querySelector(sel) {
            if (sel === '.public-nav--primary') return { querySelectorAll: () => nav };
            if (sel === '[data-public-account] .public-account-menu__panel') return cuenta;
            return null;
        },
    };
}

test('el menú móvil toma la navegación y la cuenta ya filtradas, sin elementos ocultos', async () => {
    const { gruposMenuMovil, MENU_MOVIL_MAX } = await import('../../Public/js/public-ui.js');
    assert.equal(MENU_MOVIL_MAX, 850);
    const nav = [nodo({ href: 'explorar' }), nodo({ href: 'subastas' })];
    const admin = nodo({ href: 'admin/dashboard', hidden: true });
    const panel = { children: [nodo(), nodo({ href: 'mi-actividad' }), admin, nodo({ href: 'ajustes' })] };
    const g = gruposMenuMovil(header({ nav, cuenta: panel }));
    assert.equal(g.enlaces.length, 2);
    assert.equal(g.cuenta.length, 3, 'el Panel admin oculto (sin permiso confirmado) no se copia');
    assert.ok(!g.cuenta.includes(admin));
    // Visitante: sin enlaces ni cuenta → no hay menú que abrir.
    const vacio = gruposMenuMovil(header({}));
    assert.deepEqual([vacio.enlaces.length, vacio.cuenta.length], [0, 0]);
});

test('el menú móvil es accesible y no deja escuchadores duplicados', () => {
    const js = read('Public/js/public-ui.js');
    for (const patron of [/aria-controls', 'public-menu'/, /aria-expanded', 'false'/, /aria-label', 'Abrir menú'/, /event\.key === 'Escape'/,
        /devolverFoco: true/, /matchMedia\?\.\(`\(max-width:\$\{MENU_MOVIL_MAX\}px\)`\)/, /querySelector\('\[data-public-menu-toggle\]'\)\) return/,
        /\[data-public-account\] \[data-admin-link\]/]) {
        assert.match(js, patron);
    }
    const css = read('Public/css/public-product.css');
    assert.match(css, /@media \(max-width:850px\) \{\n    \/\* Desde el primer pintado[^]*?\.public-header--product \.public-nav--primary,/, 'el nav horizontal no se pinta en móvil (sin CLS)');
    assert.match(css, /\[data-menu-movil\] \[data-public-account\] \{ display:none; \}/, 'la campana (también .public-account-menu) sigue visible');
    assert.match(css, /prefers-reduced-motion:no-preference[^]*public-menu-entrada/);
    assert.match(css, /\.public-menu__grupo a:focus-visible/);
});

test('cada vista con encabezado público carga el menú móvil versionado', () => {
    for (const vista of ['home', 'explorar', 'fletes', 'mi-actividad', 'publicar', 'ajustes', 'me-interesa', 'subastas', 'mi-dashboard']) {
        const html = read(`Application/View/${vista}/index.php`);
        assert.match(html, /css\/public-product\.css\?v=product-15/, vista);
        assert.match(html, /js\/public-ui\.js\?v=public-25/, vista);
    }
});

test('la banda de subastas nunca comprime tarjetas y fija su alto', () => {
    const css = read('Public/css/subastas.css');
    const banda = css.slice(css.indexOf('/* Banda "Subastas en vivo"'));
    assert.doesNotMatch(banda, /grid-auto-columns:\s*minmax\(0/, 'una pista con mínimo 0 comprime 8 tarjetas a ~30 px (defecto reportado)');
    assert.match(banda, /grid-auto-columns:minmax\(250px, 300px\)/);
    assert.match(banda, /grid-auto-columns:min\(82%, 300px\)/, 'móvil: una tarjeta y el borde de la siguiente');
    assert.match(banda, /scroll-snap-type:x mandatory/);
    assert.match(banda, /overscroll-behavior-x:contain/);
    assert.match(banda, /\.subastas-banda__precio strong \{[^}]*white-space:nowrap/, 'un monto grande no se parte en líneas');
    assert.match(banda, /-webkit-line-clamp:2/);
});

test('tiempo restante legible y tarjeta con su etiqueta de precio', async () => {
    const { restanteLegible } = await import('../../Public/js/subastas-vivo.js');
    const ahora = Date.parse('2026-11-18T12:00:00Z');
    const en = (s) => new Date(ahora + s * 1000).toISOString();
    assert.equal(restanteLegible(en(2 * 86400 + 3 * 3600), ahora), 'Quedan 2 d 3 h');
    assert.equal(restanteLegible(en(2 * 3600 + 5 * 60), ahora), 'Quedan 2 h 05 min');
    assert.equal(restanteLegible(en(12 * 60 + 7), ahora), 'Quedan 12 min 07 s');
    assert.equal(restanteLegible(en(45), ahora), 'Quedan 45 s');
    assert.equal(restanteLegible(en(0), ahora), 'Cerrando la subasta…', 'una subasta vencida no se muestra abierta');
    assert.equal(restanteLegible('no-fecha', ahora), 'Cierre sin hora');
    const js = read('Public/js/subastas-vivo.js');
    assert.match(js, /`Puja actual · /);
    assert.match(js, /'Precio base · sin pujas'/);
    assert.match(js, /subasta\.zona\?\.canton/, 'la zona viene de `zona` (antes leía `finca`, que el API no envía)');
    assert.match(js, /safeImageUrl\(subasta\.imagenUrl\)/, 'solo fotos https propias de la subasta');
    assert.match(js, /titulo\.title = subasta\.titulo/, 'el título completo sigue accesible');
});
