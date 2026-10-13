// Subastas en tiempo real (DEC-SUBASTA-001): cliente de Supabase Realtime, contador, vista y enlaces.
//
// Ejecutar: node --test Tests/frontend/subastas.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8').replace(/\r\n/g, '\n');

test('el cliente de tiempo real habla el protocolo de canales de Supabase y solo atiende avisos de Broadcast', async () => {
    const { mensajeUnion, topicoDeAviso, esperaReintento } = await import('../../Public/js/shared/tiempo-real.js');
    const union = mensajeUnion('subasta-7', 3);
    assert.equal(union.topic, 'realtime:subasta-7');
    assert.equal(union.event, 'phx_join');
    assert.equal(union.ref, '3');
    assert.equal(union.payload.config.private, false);
    assert.equal(topicoDeAviso({ event: 'broadcast', topic: 'realtime:subastas' }), 'subastas');
    assert.equal(topicoDeAviso({ event: 'phx_reply', topic: 'realtime:subastas' }), null, 'las respuestas del servidor no son avisos');
    assert.equal(topicoDeAviso({ event: 'broadcast', topic: 'otro' }), null);
    assert.deepEqual([0, 1, 2, 10].map(esperaReintento), [1000, 2000, 4000, 30000], 'reintentos con espera creciente hasta 30 s');
    assert.doesNotMatch(read('Public/js/shared/tiempo-real.js'), /secret|sb_secret/i, 'el navegador nunca usa la clave secreta');
});

test('el contador y la descripción del animal', async () => {
    const { tiempoRestante, describirAnimal } = await import('../../Public/js/subastas.js');
    const ahora = Date.parse('2026-10-11T12:00:00Z');
    assert.equal(tiempoRestante('2026-10-11T12:05:09Z', ahora), '05:09');
    assert.equal(tiempoRestante('2026-10-11T15:00:00Z', ahora), '03:00:00');
    assert.equal(tiempoRestante('2026-10-13T13:00:01Z', ahora), '2 d 01:00:01');
    assert.equal(tiempoRestante('2026-10-11T11:59:59Z', ahora), 'Terminada');
    assert.equal(tiempoRestante('no es fecha', ahora), '—');
    assert.equal(describirAnimal({ tipo: 'Vaca', raza: 'Jersey' }), 'Vaca · Jersey');
    assert.equal(describirAnimal({ especie: 'Bovino' }), 'Bovino');
    assert.equal(describirAnimal({}), 'Animal');
});

test('cada id que usa subastas.js existe en la vista y nada se pinta como HTML', () => {
    const js = read('Public/js/subastas.js');
    const vista = read('Application/View/subastas/index.php');
    const ids = [...new Set([...js.matchAll(/\$\('#([a-z0-9-]+)'\)/g)].map((m) => m[1]))];
    assert.ok(ids.length > 20);
    for (const id of ids) assert.match(vista, new RegExp(`id="${id}"`), `falta #${id} en la vista`);
    assert.doesNotMatch(js, /\.innerHTML\s*=/, 'los datos se pintan con textContent');
    assert.match(js, /escuchar\('subastas'/);
    assert.match(js, /escuchar\(`subasta-\$\{id\}`/);
    assert.match(js, /api\/v1\/fletes\?subastaId=/, 'el ganador ve los fletes de la finca');
    for (const accion of ['PUJAR', 'COMENTAR', 'CANCELAR']) assert.ok(js.includes(`accion: '${accion}'`), `envía ${accion}`);
});

test('la página, Explorar, el encabezado y Mis animales llevan a las subastas', () => {
    const htaccess = read('Public/.htaccess');
    for (const regla of ['^subastas/?$ subastas.php', '^api/v1/subastas/?$ api/subastas.php', '^api/v1/notificaciones/?$ api/notificaciones.php',
        '^admin/subastas/?$ adminsubastas.php', '^api/v1/admin/subastas/?$ api/admin-subastas.php']) {
        assert.ok(htaccess.includes(regla), `falta la ruta ${regla}`);
    }
    const explorar = read('Application/View/explorar/index.php');
    assert.match(explorar, /data-subastas-banda/);
    assert.match(explorar, /js\/subastas-vivo\.js\?v=subastas-vivo-1/);
    assert.match(explorar, /css\/subastas\.css\?v=subastas-1/);
    const ui = read('Public/js/public-ui.js');
    assert.match(ui, /PRIVATE_PAGES = Object\.freeze\(\[[^\]]*'subastas'/);
    assert.match(ui, /addNavLink\(nav, 'subastas', 'fa-gavel', 'Subastas'\)/);
    assert.match(ui, /api\/v1\/notificaciones/);
    assert.match(ui, /notificaciones-\$\{personaId\}/, 'la campana escucha su tópico');
    assert.match(read('Public/js/mis-animales.js'), /subastas\?nueva=\$\{id\}/, 'Mis animales ofrece Subastar');
    assert.match(read('Application/View/subastas/index.php'), /js\/subastas\.js\?v=subastas-4/);
});

test('los estilos usan solo variables del sitio', () => {
    const css = read('Public/css/subastas.css');
    assert.doesNotMatch(css, /#[0-9a-f]{3,6}\b/i, 'sin colores nuevos');
    assert.doesNotMatch(css, /var\(--tc-primary\)\s*;/, 'el coral queda para los botones principales');
});
