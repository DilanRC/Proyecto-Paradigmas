import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const view = read('Application/View/bitacora/index.php');
const js = read('Public/js/bitacora.js');

test('/admin/bitacora tiene ruta, API admin de solo lectura y guarda de sesión', () => {
    const htaccess = read('Public/.htaccess');
    assert.match(htaccess, /RewriteRule \^admin\/bitacora\/\?\$ bitacora\.php \[END\]/);
    assert.match(htaccess, /RewriteRule \^api\/v1\/admin\/bitacora\/\?\$ api\/admin-bitacora\.php \[END\]/);
    const endpoint = read('Public/api/admin-bitacora.php');
    assert.ok(endpoint.includes('AdminAuthorization::require'));
    assert.ok(endpoint.includes("['GET', 'POST']") && !endpoint.includes("'PATCH'") && !endpoint.includes("'DELETE'"), 'solo lectura');
    assert.match(view, /rural-panel__nav-item--active" href="admin\/bitacora"/);
    assert.equal((view.match(/rural-panel__nav-item--active/g) ?? []).length, 1);
});

test('la pantalla filtra por persona, entidad y fechas, y los filtros no van en la URL', () => {
    for (const id of ['busqueda-bitacora', 'filtro-entidad', 'filtro-desde', 'filtro-hasta', 'cuerpo-bitacora', 'modal-detalle']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
    assert.match(view, /id="filtro-desde" type="date"/);
    assert.match(js, /method: 'POST', body: JSON\.stringify\(\{ consulta \}\)/);
});

test('fechas en hora local, actor legible y solo los filtros con valor', async () => {
    const { buildConsulta, describirActor, formatFecha } = await import('../../Public/js/bitacora.js');
    // 18:30 UTC = 12:30 en Costa Rica (UTC-6).
    assert.match(formatFecha('2026-10-05 18:30:00', 'es-CR', 'America/Costa_Rica'), /12:30/);
    assert.equal(formatFecha('no-es-fecha'), 'no-es-fecha');
    assert.equal(describirActor({ actor: { nombre: 'Ana Mora', tipo: 'PERSONA_AUTENTICADA' } }), 'Ana Mora');
    assert.equal(describirActor({ actor: { tipo: 'USUARIO_VERIFICADO' }, datosNuevos: { realizadoPor: 'admin@x.test' } }), 'admin@x.test');
    assert.equal(describirActor({ actor: { tipo: 'NO_AUTENTICADO' } }), 'Sistema');
    assert.deepEqual(buildConsulta({ q: '  ana ', entidad: '', desde: '2026-10-01', hasta: '' }, 2, 25),
        { pagina: 2, tamanoPagina: 25, q: 'ana', desde: '2026-10-01' });
});

test('el detalle muestra los datos con textContent, nunca como HTML', () => {
    assert.match(js, /pre\.textContent = JSON\.stringify\(\{ antes: item\.datosAnteriores \?\? null, despues: item\.datosNuevos \?\? null \}, null, 2\)/);
    assert.doesNotMatch(js, /innerHTML/);
});

test('el detalle de la bitácora se lee sin JSON: acción, área, campos y solo lo que cambió', async () => {
    const { cambiosEvento, nombreAccion, nombreAreaBitacora, nombreCampo, nombreOrigen } = await import('../../Public/js/bitacora.js');
    assert.equal(`${nombreAccion('REGISTRO_PUBLICO')} · ${nombreAreaBitacora('PERSONA')}`, 'Registro público · Persona');
    assert.equal(nombreAccion('ALGO_NUEVO'), 'Algo nuevo');
    assert.equal(nombreOrigen('API_REGISTRO_PUBLICO'), 'Registro público');
    assert.equal(nombreOrigen('API_SUBASTAS'), 'Subastas');
    assert.equal(nombreCampo('capacidadesCreadas'), 'Capacidades creadas');
    assert.equal(nombreCampo('personaId'), 'Persona (id)');
    assert.deepEqual(cambiosEvento(null, { fincasProductor: ['Finca DRC'], capacidadesCreadas: ['COMPRADOR', 'TRANSPORTISTA'], realizadoPor: 'x@y.cr' }), [
        { campo: 'Fincas productor', antes: null, despues: 'Finca DRC' },
        { campo: 'Capacidades creadas', antes: null, despues: 'Comprador, Transportista' },
    ], 'una creación lista sus datos; el correo de quien lo hizo ya está en «Hecho por»');
    assert.deepEqual(cambiosEvento({ estado: 'ACTIVA', titulo: 'Vaca' }, { estado: 'FINALIZADA', titulo: 'Vaca' }),
        [{ campo: 'Estado', antes: 'Activa', despues: 'Finalizada' }], 'un cambio muestra solo los campos que cambiaron');
    const js = read('Public/js/bitacora.js');
    assert.match(js, /Ver datos técnicos/, 'el JSON original sigue disponible, plegado');
    assert.doesNotMatch(js, /\.innerHTML\s*=/);
});
