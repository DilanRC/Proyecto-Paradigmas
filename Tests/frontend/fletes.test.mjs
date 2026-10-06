import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8').replace(/\r\n/g, '\n');
const js = read('Public/js/fletes.js');
const vista = read('Application/View/fletes/index.php');

test('todo id que fletes.js busca existe en la vista', () => {
    const ids = [...js.matchAll(/\$\('#([\w-]+)'\)/g)].map((m) => m[1]);
    assert.ok(ids.length > 20, 'debe encontrar los selectores del script');
    for (const id of new Set(ids)) assert.match(vista, new RegExp(`id="${id}"`), `falta #${id} en la vista`);
});

test('el formulario de oferta tiene los campos que la API exige', () => {
    for (const campo of ['vehiculoId', 'radioKm', 'capacidad', 'precio', 'descripcion']) {
        assert.match(vista, new RegExp(`name="${campo}"`));
        assert.match(vista, new RegExp(`data-oferta-error="${campo}"`));
    }
    // La zona usa los data-farm-* que lee montarEditorDireccion.
    for (const marca of ['province', 'canton', 'district', 'town', 'town-list', 'directions', 'map']) {
        assert.match(vista, new RegExp(`data-farm-${marca}\\b`));
    }
});

test('la pantalla usa las API de ofertas y las versiona donde importa', () => {
    assert.match(js, /api\/v1\/fletes/);
    assert.match(js, /api\/v1\/mi-ofertas/);
    assert.match(js, /editor-direccion\.js\?v=oferta-1/);
    assert.match(read('Public/js/shared/editor-direccion.js'), /finca-mapa\.js\?v=oferta-1/);
    assert.match(vista, /js\/fletes\.js\?v=fletes-1/);
});

test('solo manda fletes cercanos con ubicación y no pinta HTML sin escapar', () => {
    assert.match(js, /if \(!ubicacion\) \{/);
    for (const campo of ['oferta.transportista', 'oferta.descripcion', 'oferta.vehiculo?.modelo', 'oferta.vehiculo?.placa']) {
        assert.ok(js.includes(`escapeHtml(${campo}`), `${campo} debe escaparse`);
    }
});

test('el selector de mapa conserva sus textos de finca por defecto', () => {
    const mapa = read('Public/js/shared/finca-mapa.js');
    assert.match(mapa, /titulo = 'Punto exacto de la finca'/);
    assert.match(mapa, /lugar = 'la finca'/);
});
