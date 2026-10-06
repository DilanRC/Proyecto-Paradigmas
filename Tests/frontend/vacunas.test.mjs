// Historial de vacunación (P2-3): lectura pública en la tarjeta y rutas de la API.
//
// Ejecutar: node --test Tests/frontend/vacunas.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('las fechas de vacunación se muestran día/mes/año y lo inválido no se inventa', async () => {
    const { formatDate } = await import('../../Public/js/explore.js');
    assert.equal(formatDate('2026-05-12'), '12/05/2026');
    assert.equal(formatDate('2026-05-12 10:00:00'), '12/05/2026');
    for (const mala of [null, '', 'mañana', '12/05/2026']) assert.equal(formatDate(mala), '—');
});

test('la tarjeta muestra el historial en un bloque desplegable, sin markup crudo', () => {
    const js = read('Public/js/explore.js');
    assert.match(js, /function vacunasBlock\(vacunas\)/);
    assert.match(js, /element\('details', 'explore-card__more explore-card__vacunas'\)/);
    assert.ok(js.includes('`Vacunas (${lista.length})`'));
    assert.ok(js.includes('próxima ${formatDate(item.proximaDosis)}'));
    // Ambas variantes (completa y compacta) lo incluyen, y solo si hay vacunas.
    assert.equal((js.match(/\.\.\.\(vacunas \? \[vacunas\] : \[\]\)/g) ?? []).length, 2);
    assert.doesNotMatch(js, /\.innerHTML\s*=|\.insertAdjacentHTML\(/);
});

test('la ruta del historial existe y está documentada', () => {
    assert.match(read('Public/.htaccess'), /RewriteRule \^api\/v1\/mi-animales\/vacunas\/\?\$ api\/mi-animales-vacunas\.php \[END\]/);
    for (const doc of ['RutasPublicas', 'RutasFrontend']) {
        assert.match(read(`Documentation/${doc}.md`), /\/api\/v1\/mi-animales\/vacunas/, `${doc} documenta la ruta`);
    }
    const endpoint = read('Public/api/mi-animales-vacunas.php');
    assert.match(endpoint, /requerirAutenticado/, 'el historial exige sesión');
    assert.match(endpoint, /'GET', 'POST', 'PATCH'/, 'sin DELETE: un registro se corrige');
});

test('el endpoint público de catálogos incluye las vacunas', () => {
    assert.match(read('Application/Controller/CatalogosController.php'), /'vacunas' => \$this->catalogo->vacunas\(\)/);
});
