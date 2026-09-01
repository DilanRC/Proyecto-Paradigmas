// Contrato del combobox de sugerencias para pueblo/localidad.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { nextActiveIndex } from '../../Public/js/shared/suggestion-combobox.js';

const leer = (ruta) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');

test('la navegacion por teclado es circular y tolera listas vacias', () => {
    assert.equal(nextActiveIndex(-1, 1, 3), 0);
    assert.equal(nextActiveIndex(0, 1, 3), 1);
    assert.equal(nextActiveIndex(2, 1, 3), 0);
    assert.equal(nextActiveIndex(-1, -1, 3), 2);
    assert.equal(nextActiveIndex(0, -1, 3), 2);
    assert.equal(nextActiveIndex(2, -1, 0), -1);
});

test('el componente declara el patron WAI-ARIA de combobox/listbox', () => {
    const js = leer('Public/js/shared/suggestion-combobox.js');
    for (const fragmento of [
        "setAttribute('role', 'combobox')",
        "setAttribute('aria-autocomplete', 'list')",
        "setAttribute('aria-expanded'",
        "setAttribute('role', 'listbox')",
        "setAttribute('role', 'option')",
        "aria-activedescendant",
    ]) {
        assert.ok(js.includes(fragmento), `falta ${fragmento}`);
    }
});

test('teclado, cierre externo y respuesta asincrona vieja tienen manejo explicito', () => {
    const js = leer('Public/js/shared/suggestion-combobox.js');
    for (const tecla of ['ArrowDown', 'ArrowUp', 'Escape', 'Enter']) {
        assert.ok(js.includes(tecla), `falta ${tecla}`);
    }
    assert.ok(js.includes("document.addEventListener('pointerdown'"));
    assert.ok(js.includes('ticket !== sequence || query !== input.value'),
        'una respuesta vieja podria reemplazar sugerencias nuevas');
});

test('pueblo conserva la politica de texto libre y el catalogo sigue lazy', () => {
    const direccion = leer('Public/js/shared/direccion.js');
    const componente = leer('Public/js/shared/suggestion-combobox.js');

    assert.ok(direccion.includes("import('./poblados.js')"), 'el catalogo de 200 KB debe seguir lazy');
    assert.ok(direccion.includes('buscarPoblados(codigo, consulta, { limite: 12 })'));
    assert.ok(!direccion.includes('llenarDatalist'), 'el navegador no debe controlar el desplegable');
    assert.ok(!componente.includes('restoreLabelIfInvalid'),
        'el combobox de pueblo no debe convertir el campo libre en catalogo cerrado');
    assert.ok(!/onBlur[\s\S]{0,250}input\.value\s*=/.test(componente),
        'salir del campo no debe borrar ni reemplazar un pueblo escrito manualmente');
});

test('la presentacion vive en una hoja CSS separada', () => {
    const js = leer('Public/js/shared/suggestion-combobox.js');
    const css = leer('Public/css/suggestion-combobox.css');
    assert.ok(js.includes("../../css/suggestion-combobox.css"));
    assert.ok(css.includes('.suggestion-combobox__menu'));
    assert.ok(css.includes('.suggestion-combobox__option.is-active'));
});
