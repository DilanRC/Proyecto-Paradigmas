// Formato y armado de la tarjeta de Explorar.
//
// La tarjeta ya no vive en el PHP: la construye explore.js con lo que devuelve
// api/publicaciones.php. Estas pruebas fijan dos cosas que rompen en silencio:
// un campo sin observación registrada NO puede inventarse, y el contenido que
// viene de la base NO puede interpretarse como markup.
//
// Ejecutar: node --test Tests/frontend/explore_card_content.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const exploreSource = read('Public/js/explore.js');

const {
    formatPrice, formatLocation, formatAge, formatWeight, formatText, formatSeller,
    formatPurpose, availablePurposes, filterByPurpose, normalizePurpose,
    filterByLocation, filterByPrice, purposeFromText,
} = await import('../../Public/js/explore.js');

test('el proposito se escribe bien en pantalla aunque la base lo guarde gritado', () => {
    // La base guarda CRIA y DOBLE PROPOSITO: en mayusculas y sin tildes.
    assert.equal(formatPurpose('CRIA'), 'Cría');
    assert.equal(formatPurpose('DOBLE PROPOSITO'), 'Doble propósito');
    assert.equal(formatPurpose('engorde'), 'Engorde');
    // Un valor futuro no mapeado se capitaliza en vez de desaparecer.
    assert.equal(formatPurpose('EXPORTACION'), 'Exportacion');
    assert.equal(formatPurpose(null), '—');
});

test('el precio se muestra en colones enteros y tolera su ausencia', () => {
    // Separador fijo, no el del ICU del entorno: el mismo precio debe leerse
    // igual en Node y en el navegador.
    assert.equal(formatPrice(950000), '₡950.000');
    assert.equal(formatPrice(1200000.4), '₡1.200.000');
    assert.equal(formatPrice(800), '₡800');
    // Sin precio la tarjeta no puede mostrar ₡0: eso seria una oferta falsa.
    assert.equal(formatPrice(null), 'Precio a convenir');
    assert.equal(formatPrice(undefined), 'Precio a convenir');
    assert.equal(formatPrice(Number.NaN), 'Precio a convenir');
});

test('la ubicacion va de lo especifico a lo general y omite lo que falta', () => {
    assert.equal(
        formatLocation({ provincia: 'Alajuela', canton: 'San Carlos', distrito: 'Aguas Zarcas' }),
        'Aguas Zarcas, San Carlos, Alajuela',
    );
    assert.equal(
        formatLocation({ provincia: 'Alajuela', canton: 'Grecia', distrito: 'San Roque', pueblo: 'El Alto' }),
        'El Alto, San Roque, Grecia, Alajuela',
    );
    // Una finca sin direccion enlazada llega con todo en null.
    assert.equal(formatLocation({}), 'Ubicación no registrada');
    assert.equal(formatLocation(null), 'Ubicación no registrada');
});

test('edad y peso salen de observaciones que pueden no existir', () => {
    assert.equal(formatAge(18), '18 meses');
    assert.equal(formatAge(1), '1 mes');
    assert.equal(formatAge(null), '—');
    assert.equal(formatWeight(320), '320 kg');
    assert.equal(formatWeight(320.5), '320.5 kg');
    assert.equal(formatWeight(null), '—');
});

test('los textos vacios no se rellenan con inventos', () => {
    assert.equal(formatText('Brahman'), 'Brahman');
    assert.equal(formatText('  '), '—');
    assert.equal(formatText(null), '—');
});

test('el vendedor combina finca y persona, y aguanta que falte cualquiera', () => {
    assert.equal(
        formatSeller({ finca: { nombre: 'Finca El Alto' }, vendedor: { nombre: 'Carlos Venegas' } }),
        'Finca El Alto · Carlos Venegas',
    );
    assert.equal(formatSeller({ vendedor: { nombre: 'Carlos Venegas' } }), 'Carlos Venegas');
    assert.equal(formatSeller({}), 'Vendedor no registrado');
});

test('los filtros de proposito salen de los datos, no de una lista fija', () => {
    const items = [
        { animal: { proposito: 'ENGORDE' } },
        { animal: { proposito: 'CRIA' } },
        { animal: { proposito: 'engorde' } },
        { animal: { proposito: null } },
    ];
    // Sin duplicados por mayusculas y sin una entrada vacia por el null.
    assert.deepEqual(availablePurposes(items), ['CRIA', 'ENGORDE']);
    assert.deepEqual(availablePurposes([]), []);

    assert.equal(filterByPurpose(items, 'ENGORDE').length, 2);
    assert.equal(filterByPurpose(items, 'todos').length, 4);
    assert.equal(filterByPurpose(items, 'INEXISTENTE').length, 0);
});

test('la tarjeta se arma con el DOM, nunca con innerHTML', () => {
    // Titulo y descripcion vienen de la base: con innerHTML, un titulo con
    // markup se ejecutaria como HTML en la pagina publica.
    const js = read('Public/js/explore.js');
    assert.doesNotMatch(js, /\.innerHTML\s*=|\.insertAdjacentHTML\(/,
        'explore.js no debe escribir markup crudo con contenido de la base');
    assert.match(js, /createElement/);
    assert.match(js, /textContent/);
});

test('la tarjeta conserva los identificadores necesarios para acciones persistentes', () => {
    assert.match(exploreSource, /dataset\.publicacionId/);
    assert.match(exploreSource, /dataset\.animalId/);
});

test('la vista ya no trae tarjetas de muestra escritas a mano', () => {
    const vista = read('Application/View/explorar/index.php');
    assert.doesNotMatch(vista, /<article class="explore-card"/,
        'las tarjetas las construye explore.js desde la API');
    assert.match(vista, /data-explore-deck/);
    assert.match(vista, /data-explore-error/, 'la vista debe poder mostrar un fallo de carga');
    assert.match(vista, /data-explore-loading/, 'la vista debe poder mostrar la carga');
});

test('normalizePurpose vuelve a todos cuando el filtro ya no existe', () => {
    assert.equal(normalizePurpose('ENGORDE', ['CRIA', 'ENGORDE']), 'ENGORDE');
    assert.equal(normalizePurpose('engorde', ['ENGORDE']), 'ENGORDE');
    assert.equal(normalizePurpose('ENGORDE', ['CRIA']), 'todos');
    assert.equal(normalizePurpose('ENGORDE', []), 'todos');
    assert.equal(normalizePurpose('todos', ['CRIA']), 'todos');
    assert.equal(normalizePurpose('', ['CRIA']), 'todos');
});

test('los filtros de ubicación y precio ignoran tildes y respetan el rango', () => {
    const items = [
        { precio: 500000, direccion: { canton: 'San José', provincia: 'San José' } },
        { precio: 900000, direccion: { canton: 'Grecia', provincia: 'Alajuela' } },
        { precio: null, direccion: { provincia: 'Alajuela' } },
    ];
    assert.equal(filterByLocation(items, 'san jose').length, 1);
    assert.equal(filterByLocation(items, 'ALAJUELA').length, 2);
    assert.equal(filterByLocation(items, '  ').length, 3);
    assert.equal(filterByPrice(items, null, null).length, 3);
    assert.equal(filterByPrice(items, 600000, null).length, 1);
    assert.equal(filterByPrice(items, null, 500000).length, 1, 'el máximo es inclusivo');
    assert.equal(filterByPrice(items, 0, null).length, 2, 'sin precio no entra en un rango');
});

test('el tipo escrito en la portada se traduce al propósito del catálogo', () => {
    assert.equal(purposeFromText('engorde'), 'ENGORDE');
    assert.equal(purposeFromText('Doble propósito'), 'DOBLE PROPOSITO');
    assert.equal(purposeFromText('cria'), 'CRIA');
    assert.equal(purposeFromText('Brahman'), null, 'una raza la busca la API');
    assert.equal(purposeFromText(''), null);
});

test('Explorar solo dice "prueba otra búsqueda" si la persona buscó o filtró', () => {
    const vista = read('Application/View/explorar/index.php');
    assert.match(vista, /data-explore-empty-catalog/);
    assert.match(vista, /Sé el primero en publicar ganado en tu zona/);
    assert.doesNotMatch(vista, /data-explore-filter="todos"/, 'el chip "Todo" no se escribe a mano');
    assert.match(exploreSource, /empty\.hidden = !\(sinResultados && hasActiveFilters\(\)\)/);
});
