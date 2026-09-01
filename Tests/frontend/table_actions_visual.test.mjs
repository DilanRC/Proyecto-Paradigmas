// Contrato de presentacion de las tablas CRUD rurales y del sistema de iconos.
//
// CF4 usa @fortawesome/fontawesome-free 6.7.2. Paradigmas fija la misma version
// en una capa CSS global; los botones conservan su texto real en el DOM para que
// el icono nunca sustituya el nombre accesible de la accion.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const leer = (ruta) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');
const panel = leer('Public/css/panel.css');
const base = leer('Public/css/base.css');
const icons = leer('Public/css/icons.css');

test('el contenido rural deja de limitar la tabla a 1030 px', () => {
    assert.ok(panel.includes('width:min(100%,1380px)'), 'el panel debe aprovechar el ancho disponible');
    assert.ok(!panel.includes('max-width:1030px'), 'no debe sobrevivir el limite estrecho anterior');
    assert.ok(panel.includes('overflow-x:auto'), 'una tabla ancha debe degradar con scroll horizontal');
});

test('Paradigmas usa la misma biblioteca y version de iconos que CF4', () => {
    assert.ok(base.startsWith("@import url('./icons.css?v=fa-6.7.2');"),
        'base.css debe cargar la capa global de iconos');
    assert.ok(icons.includes('@fortawesome/fontawesome-free@6.7.2/css/all.min.css'),
        'debe fijarse Font Awesome Free 6.7.2, igual que CF4');
    assert.ok(icons.includes("--tc-fa-family:'Font Awesome 6 Free'"));
});

test('las acciones de fila usan glifos Font Awesome del ActionBtn de CF4', () => {
    assert.ok(panel.includes('.rural-panel .row-actions .action'));
    assert.ok(panel.includes('width:34px'));
    assert.ok(panel.includes('height:34px'));

    for (const [action, glyph] of [
        ['ver', '\\f06e'],
        ['editar', '\\f044'],
        ['desactivar', '\\f05e'],
        ['reactivar', '\\f058'],
    ]) {
        assert.ok(icons.includes(`.action--${action}::before`), `falta icono para ${action}`);
        assert.ok(icons.includes(`content:'${glyph}'`), `falta glifo Font Awesome para ${action}`);
    }
    assert.ok(icons.includes('mask-image:none !important'),
        'la capa global debe anular los SVG mask del prototipo anterior');
});

test('la misma biblioteca cubre navegacion, busqueda, dialogos y estados', () => {
    for (const selector of [
        '.search::before',
        '.rural-panel__nav-item::before',
        '.close-button::before',
        '.empty-state__icon::before',
        '.error-state__icon::before',
        '#accion-pasar::before',
        '#accion-guardar::before',
        '.suggestion-combobox__leading-icon::before',
    ]) {
        assert.ok(icons.includes(selector), `falta cobertura global para ${selector}`);
    }
});

test('landing, login y todos los paneles cargan base.css y heredan Font Awesome', () => {
    for (const vista of [
        'home', 'login', 'productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos',
    ]) {
        const html = leer(`Application/View/${vista}/index.php`);
        assert.ok(html.includes('css/base.css'), `${vista}: no carga la capa base global`);
    }
});

test('las acciones conservan ayuda visual y foco de teclado', () => {
    for (const label of ['Ver detalle', 'Editar', 'Desactivar', 'Reactivar']) {
        assert.ok(panel.includes(`content:\"${label}\"`), `falta tooltip ${label}`);
    }
    assert.ok(panel.includes('.row-actions .action:focus-visible'));
});
