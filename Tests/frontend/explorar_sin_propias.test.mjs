import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8').replace(/\r\n/g, '\n');

test('Explorar pide al servidor que excluya las publicaciones propias', () => {
    assert.match(read('Public/js/explore.js'), /excluirPropias: 'true'/);
});

test('con sesión no hay portada: se redirige a Explorar y se quita Inicio', () => {
    const ui = read('Public/js/public-ui.js');
    assert.match(ui, /hasAttribute\('data-portada'\)\s*&& readSession\(\)\) \{\s*window\.location\.replace\('explorar'\)/);
    assert.match(ui, /\.public-brand'\)\.forEach\(\(logo\) => \{ logo\.href = 'explorar'/);
    assert.match(read('Application/View/home/index.php'), /<body class="public-home" data-portada>/);
    // La marca no puede estar en Explorar (redirigiría en bucle).
    assert.doesNotMatch(read('Application/View/explorar/index.php'), /data-portada/);
});
