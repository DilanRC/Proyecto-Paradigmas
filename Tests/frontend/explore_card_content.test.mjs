// Contenido de las tarjetas de Explorar.
//
// La tarjeta dejo de ser solo ubicacion + titulo: ahora expone precio, ficha
// tecnica (raza, edad, peso, proposito), direccion completa y vendedor, que son
// los campos que el modelo (tbanimal, tbanimalproduccionsalud,
// tbanimalpublicacion, tbfinca/tbdireccion) ya soporta. Estas pruebas fijan ese
// contrato para que un rediseño no vuelva a dejar la tarjeta vacia de datos.
//
// Ejecutar: node --test Tests/frontend/explore_card_content.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const explore = read('Application/View/explorar/index.php');
const css = read('Public/css/explore.css');

const cards = explore.split('<article class="explore-card"').slice(1);

test('la muestra conserva las tres tarjetas del deck', () => {
    assert.equal(cards.length, 3);
});

test('cada tarjeta muestra precio, ficha tecnica y vendedor', () => {
    for (const card of cards) {
        assert.match(card, /class="explore-card__price"/, 'falta el precio');
        assert.match(card, /₡/, 'el precio debe venir con simbolo de colones');
        assert.match(card, /class="explore-card__specs"/, 'falta la ficha tecnica');
        assert.match(card, /class="explore-card__seller"/, 'falta el vendedor');

        for (const spec of ['Raza', 'Edad', 'Peso', 'Propósito']) {
            assert.ok(card.includes(`${spec}</dt>`), `falta la especificacion ${spec}`);
        }
    }
});

test('la ubicacion baja hasta distrito o pueblo, no solo canton y provincia', () => {
    // Tres segmentos separados por coma dentro del renglon de ubicacion.
    for (const card of cards) {
        const meta = card.match(/fa-location-dot" aria-hidden="true"><\/i>([^<]+)</);
        assert.ok(meta, 'falta el renglon de ubicacion');
        const segmentos = meta[1].split(',').map((parte) => parte.trim()).filter(Boolean);
        assert.ok(segmentos.length >= 3, `ubicacion demasiado corta: ${meta[1].trim()}`);
    }
});

test('el texto buscable incluye los datos nuevos para que el filtro los alcance', () => {
    for (const card of cards) {
        const searchable = card.match(/data-searchable="([^"]+)"/);
        assert.ok(searchable, 'falta data-searchable');
        assert.match(searchable[1], /finca/, 'la finca debe ser buscable');
        assert.match(searchable[1], /colones/, 'el precio debe ser buscable');
    }
});

test('los estilos nuevos usan tokens del tema, no colores fijos', () => {
    const bloque = css.slice(css.indexOf('.explore-card p.explore-card__price'), css.indexOf('.explore-card__tags {'));
    assert.ok(bloque.length > 0, 'no se encontro el bloque de estilos nuevos');
    assert.ok(!/#[0-9a-f]{3,6}/i.test(bloque), 'los estilos nuevos no deben fijar colores hexadecimales');
    assert.match(bloque, /var\(--tc-/, 'los estilos nuevos deben usar tokens --tc-*');
});

test('precio y vendedor ganan al margin:0 generico de .explore-card p', () => {
    // .explore-card p tiene mayor especificidad que una clase sola; sin el
    // selector compuesto el margen se perderia y el bloque quedaria pegado.
    assert.match(css, /\.explore-card p\.explore-card__price/);
    assert.match(css, /\.explore-card p\.explore-card__seller/);
});
