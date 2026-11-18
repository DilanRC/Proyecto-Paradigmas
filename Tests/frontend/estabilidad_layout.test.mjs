// CLS medido en staging (17/11/2026): el pie de página quedaba a media pantalla
// mientras cargaban Mi panel, Ajustes, Participar, Me interesa y Subastas, y
// saltaba al llegar los datos (0,10-0,20). En Explorar la barra de filtros
// aparecía con los datos y empujaba las tarjetas.
//
// Ejecutar: node --test Tests/frontend/estabilidad_layout.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('el contenido principal de la app reserva casi una pantalla para que el pie no salte', () => {
    const css = read('Public/css/public-product.css');
    assert.match(css, /\.public-shell > :is\(\.activity-main, \.activity-shell, \.explore-main, \.publish-main\) \{ min-height:75vh; \}/);
    for (const vista of ['mi-actividad', 'ajustes', 'me-interesa', 'subastas', 'fletes', 'explorar', 'publicar', 'mi-dashboard']) {
        const html = read(`Application/View/${vista}/index.php`);
        assert.match(html, /css\/public-product\.css\?v=product-14/, `${vista} carga la regla nueva`);
        assert.match(html, /<main class="(activity-main|activity-shell|explore-main|publish-main)[" ]/, `${vista} usa un main cubierto`);
    }
});

test('Explorar pinta la barra de filtros desde el HTML y la mantiene durante la primera carga', () => {
    const html = read('Application/View/explorar/index.php');
    assert.match(html, /<div class="explore-filters" data-explore-filterbar>/);
    const js = read('Public/js/explore.js');
    assert.match(js, /filterBar\.hidden = !state\.cargando && state\.items\.length === 0 && !hasActiveFilters\(\);/);
});
