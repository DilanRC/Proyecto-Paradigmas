// Contrato visual de iconografia y tablas CRUD rurales.
//
// La biblioteca es global y la seleccion de iconos es semantica: modulo,
// accion y estado deben ser reconocibles sin convertir toda la interfaz en
// decoracion. Los botones conservan su texto real en el DOM.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const panelCss = readFileSync(new URL('../../Public/css/panel.css', import.meta.url), 'utf8');
const baseCss = readFileSync(new URL('../../Public/css/base.css', import.meta.url), 'utf8');
const iconsCss = readFileSync(new URL('../../Public/css/icons.css', import.meta.url), 'utf8');
const publicCss = readFileSync(new URL('../../Public/css/public-auth.css', import.meta.url), 'utf8');

const views = [
    'home', 'login', 'productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos',
];

test('el contenido rural deja de limitar la tabla a 1030 px', () => {
    assert.ok(panelCss.includes('width:min(100%,1380px)'), 'el panel debe aprovechar el ancho disponible');
    assert.ok(!panelCss.includes('max-width:1030px'), 'no debe sobrevivir el limite estrecho anterior');
    assert.ok(panelCss.includes('overflow-x:auto'), 'una tabla ancha debe degradar con scroll horizontal');
});

test('Paradigmas usa Font Awesome Free 7.3.1 de forma global', () => {
    assert.ok(baseCss.includes("./icons.css?v=fa-7.3.1"), 'base.css debe invalidar cache con la version nueva');
    assert.ok(
        iconsCss.includes('@fortawesome/fontawesome-free@7.3.1/css/all.min.css'),
        'la version de Font Awesome debe quedar fijada a 7.3.1',
    );
    assert.ok(iconsCss.includes("--tc-fa-family:'Font Awesome 7 Free'"));

    for (const view of views) {
        const html = readFileSync(new URL(`../../Application/View/${view}/index.php`, import.meta.url), 'utf8');
        assert.ok(html.includes('css/base.css'), `${view} debe heredar la capa global de iconos`);
    }
});

test('la navegacion usa iconos ganaderos diferenciados por modulo', () => {
    const expected = new Map([
        ['productores.php', '\\f6c8'], // cow
        ['compradores.php', '\\f2b5'], // handshake
        ['transportistas.php', '\\f48b'], // truck-fast
        ['vehiculos.php', '\\f63c'], // truck-pickup
        ['pagometodos.php', '\\f555'], // wallet
    ]);

    for (const [href, glyph] of expected) {
        assert.ok(iconsCss.includes(`href$='${href}']::before { content:'${glyph}'`), `falta icono semantico para ${href}`);
    }
});

test('las acciones CRUD distinguen ver editar pausar y restaurar', () => {
    assert.ok(iconsCss.includes(".action--ver::before { content:'\\f06e'"), 'ver debe usar eye');
    assert.ok(iconsCss.includes(".action--editar::before { content:'\\f044'"), 'editar debe usar pen-to-square');
    assert.ok(iconsCss.includes(".action--desactivar::before { content:'\\f28b'"), 'desactivar debe comunicar pausa reversible');
    assert.ok(iconsCss.includes(".action--reactivar::before { content:'\\f2ea'"), 'reactivar debe comunicar restauracion');
    assert.ok(!iconsCss.includes(".action--desactivar::before { content:'\\f05e'"), 'ban no debe representar un estado reversible');
});

test('pueblo diferencia buscar de una sugerencia de ubicacion', () => {
    assert.ok(iconsCss.includes(".suggestion-combobox__leading-icon::before {\n    content:'\\f002'"));
    assert.ok(iconsCss.includes(".suggestion-combobox__option-icon::before {\n    content:'\\f3c5'"));
});

test('los estados vacios heredan la identidad del modulo', () => {
    for (const panel of ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos']) {
        assert.ok(iconsCss.includes(`#panel-${panel} .empty-state__icon::before`), `falta empty state de ${panel}`);
    }
    assert.ok(iconsCss.includes(".error-state__icon::before {\n    content:'\\f071'"));
    assert.ok(iconsCss.includes(".confirmation__icon::before {\n    content:'\\f28b'"));
});

test('crear y accesos privados usan iconos sin eliminar el texto', () => {
    assert.ok(iconsCss.includes('#crear-productor > span::before'));
    assert.ok(iconsCss.includes('#crear-vehiculo > span::before'));
    assert.ok(iconsCss.includes('#crear-pagometodo > span::before'));
    assert.ok(iconsCss.includes(".rural-panel__admin-link[href='./']::before"));
    assert.ok(iconsCss.includes(".rural-panel__admin-link[href$='login.php']::before"));
});

test('la identidad publica usa los colores de los logos y no replica Tinder', () => {
    for (const token of ['#151a18', '#2f3c2d', '#d24f28', '#eedbca', '#fef7ec', '#394332']) {
        assert.ok(publicCss.includes(token), `falta token de marca ${token}`);
    }
    for (const tinderColor of ['#fd267a', '#ff315f', '#ff6746', '#7c3aed']) {
        assert.equal(publicCss.includes(tinderColor), false, `no debe sobrevivir el color Tinder ${tinderColor}`);
    }
    assert.ok(publicCss.includes("html[data-theme='light']"));
});

test('las acciones conservan ayuda visual y foco de teclado', () => {
    for (const label of ['Ver detalle', 'Editar', 'Desactivar', 'Reactivar']) {
        assert.ok(panelCss.includes(`content:\"${label}\"`), `falta tooltip ${label}`);
    }
    assert.ok(panelCss.includes('.row-actions .action:focus-visible'));
});
