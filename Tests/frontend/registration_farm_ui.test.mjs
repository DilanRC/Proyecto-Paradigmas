import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const root = new URL('../../', import.meta.url);

async function source(path) {
    return readFile(new URL(path, root), 'utf8');
}

test('la dirección de la finca empieza cerrada y con lenguaje de usuario', async () => {
    const view = await source('Application/View/registro/index.php');
    const script = await source('Public/js/registro.js');

    assert.doesNotMatch(view, /<details class="finca-address" open>/);
    assert.match(view, /Agregar dirección/);
    assert.match(script, /finca-address__title/);
    assert.match(script, /finca-address__meta/);
});

test('los controles de dirección tienen una capa visual propia y el mapa sigue siendo opcional', async () => {
    const css = await source('Public/css/mapa.css');
    const map = await source('Public/js/shared/finca-mapa.js');

    assert.match(css, /farm-address-editor__grid \.field\{/);
    assert.match(css, /farm-address-editor__grid input/);
    assert.match(css, /farm-address-editor__grid textarea/);
    assert.match(map, /data-farm-map-open/);
    assert.match(map, /data-farm-map-shell hidden/);
});
