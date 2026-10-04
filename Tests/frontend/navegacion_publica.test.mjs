// Navegación sin sesión, cierre de sesión y carrusel de la portada.
//
// Ejecutar: node --test Tests/frontend/navegacion_publica.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const publicUi = read('Public/js/public-ui.js');

test('cerrar sesión y una sesión vencida llevan a Inicio', () => {
    assert.match(publicUi, /data-public-logout\]'\)\?\.addEventListener\('click', \(\) => signOutEverywhere\(\)\)/);
    // El panel admin cierra la sesión completa (no solo el permiso admin).
    const gate = read('Public/js/shared/auth-gate.js');
    assert.match(gate, /export async function signOutEverywhere[\s\S]*?await signOut\(\{ storage \}\)[\s\S]*?clearAdminBrowserSession\(storage\);[\s\S]*?assign\('\.\/'\)/);
    assert.match(gate, /rural-panel__admin-link\[href\$="entrar"\]/);
    assert.match(gate, /void signOutEverywhere\(storage\)/);
    assert.match(read('Public/js/shared/supabase-auth.js'), /export function endExpiredSession[\s\S]*?location\?\.assign\('\.\/'\)/);
    for (const archivo of ['Public/js/mi-actividad.js', 'Public/js/ajustes.js']) {
        const js = read(archivo);
        assert.ok(js.includes('if (error?.status === 401) endExpiredSession();'), `${archivo} no cierra la sesión vencida`);
        // Sin sesión se pide entrar; una sesión vencida (401) ya no vuelve a Entrar.
        assert.equal(/status === 401\) window\.location\.assign\('entrar/.test(js), false, `${archivo} aún manda a Entrar con 401`);
    }
});

test('sin sesión solo queda Inicio y las páginas privadas piden cuenta', () => {
    assert.match(publicUi, /export const PRIVATE_PAGES = Object\.freeze\(\['explorar', 'publicar', 'fletes'\]\)/);
    // Publicar y Fletes solo se agregan al menú con sesión.
    assert.match(publicUi, /if \(session\?\.authenticated === true\) \{\n\s+addNavLink\(nav, 'publicar'/);
    assert.match(publicUi, /hideAnonymousLinks\(\);\n\s+initializeAnonymousGate\(\);/);
    // Entrar por URL a /explorar o /fletes sin sesión vuelve a Inicio.
    assert.match(publicUi, /\['explorar', 'fletes'\]\.includes\(pageName\(window\.location\.href\)\)\n\s+&& !readSession\(\)\) \{\n\s+window\.location\.replace\('\.\/'\);/);
});

test('el destino privado se reconstruye para volver tras iniciar sesión', async () => {
    const { pageName, loginFor } = await import('../../Public/js/public-ui.js');
    assert.equal(pageName('http://localhost/explorar?q=x'), 'explorar');
    assert.equal(pageName('http://localhost/sub/fletes/'), 'fletes');
    assert.equal(pageName('http://localhost/explorar.php'), 'explorar');
    assert.equal(pageName('http://localhost/'), '');
    assert.equal(loginFor('http://localhost/explorar?publicacion=7'), 'entrar?next=explorar%3Fpublicacion%3D7');
    assert.equal(loginFor('http://localhost/explorar?ubicacion=Alajuela&tipo=engorde'),
        'entrar?next=explorar%3Fubicacion%3DAlajuela%26tipo%3Dengorde');
});

test('la portada usa la tarjeta compacta: foto, nombre, precio y "Ver más información"', async () => {
    const homeJs = read('Public/js/home.js');
    assert.match(homeJs, /buildCard\(p, \{ compacta: true \}\)/);
    assert.doesNotMatch(homeJs, /location\.assign/, 'la tarjeta ya no es un botón que lleva a Entrar');
    assert.doesNotMatch(read('Application/View/home/index.php'), /explore-interactions\.js/, 'sin botones no hacen falta las interacciones');
    const explore = read('Public/js/explore.js');
    assert.match(explore, /element\('summary', null, 'Ver más información'\)/);
    // La variante compacta no agrega Pasar / Me interesa / Contactar.
    const inicio = explore.indexOf('if (compacta) {');
    const compacta = explore.slice(inicio, explore.indexOf('return article;', inicio));
    assert.doesNotMatch(compacta, /acciones/);
});

test('sin sesión el header queda en logo, tema, Entrar y Crear cuenta', () => {
    assert.match(publicUi, /nav\?\.querySelectorAll\('a\[href="\.\/"\], a\[href="#inicio"\]'\)\.forEach\(\(link\) => link\.remove\(\)\)/);
    assert.match(publicUi, /querySelectorAll\('\.public-header \[data-public-search\]'\)\.forEach\(\(buscar\) => buscar\.remove\(\)\)/);
});

test('el carrusel hace loop y respeta reduced-motion', async () => {
    const { siguientePosicion } = await import('../../Public/js/shared/carousel.js');
    assert.equal(siguientePosicion(2, 1, 3), 0, 'después de la última vuelve a la primera');
    assert.equal(siguientePosicion(0, -1, 3), 2, 'antes de la primera va a la última');
    assert.equal(siguientePosicion(1, 1, 3), 2);
    assert.equal(siguientePosicion(0, 1, 0), 0);
    const carrusel = read('Public/js/shared/carousel.js');
    assert.match(carrusel, /prefers-reduced-motion: reduce/);
    assert.match(carrusel, /if \(reducido \|\| pausado \|\| temporizador \|\| document\.hidden \|\| leyendo\(\)\) return;/);
    assert.match(carrusel, /details\[open\]/, 'no avanza mientras alguien lee el detalle de una tarjeta');
    for (const evento of ['pointerenter', 'pointerleave', 'focusin', 'focusout']) {
        assert.ok(carrusel.includes(`'${evento}'`), `falta pausa por ${evento}`);
    }
    const css = read('Public/css/public-v3.css');
    assert.match(css, /\.public-carousel__track \{ --carousel-por-vista:4;/);
    assert.match(css, /max-width:1050px\) \{ \.public-carousel__track \{ --carousel-por-vista:2; \} \}/);
    assert.match(css, /--carousel-por-vista:1;/);
    assert.match(css, /scroll-snap-type:x mandatory/);
});
