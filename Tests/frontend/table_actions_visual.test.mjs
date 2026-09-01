// Contrato de presentacion de las tablas CRUD rurales.
//
// Los botones conservan su texto real en el DOM para el nombre accesible; CSS
// oculta solo el glifo textual y pinta un icono. Asi no dependemos de Font
// Awesome ni convertimos acciones semanticas en botones sin nombre.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync(new URL('../../Public/css/panel.css', import.meta.url), 'utf8');

test('el contenido rural deja de limitar la tabla a 1030 px', () => {
    assert.ok(css.includes('width:min(100%,1380px)'), 'el panel debe aprovechar el ancho disponible');
    assert.ok(!css.includes('max-width:1030px'), 'no debe sobrevivir el limite estrecho anterior');
    assert.ok(css.includes('overflow-x:auto'), 'una tabla ancha debe degradar con scroll horizontal');
});

test('las acciones de fila son botones iconicos compactos como en CF4', () => {
    assert.ok(css.includes('.rural-panel .row-actions .action'));
    assert.ok(css.includes('width:34px'));
    assert.ok(css.includes('height:34px'));
    assert.ok(css.includes('font-size:0'), 'el texto visible se sustituye por icono sin quitarlo del DOM');

    for (const action of ['ver', 'editar', 'desactivar', 'reactivar']) {
        assert.ok(css.includes(`.action--${action}::before`), `falta icono para ${action}`);
    }
    assert.ok(css.includes('mask-image:url("data:image/svg+xml'), 'los iconos deben ser locales y sin dependencia externa');
});

test('las acciones conservan ayuda visual y foco de teclado', () => {
    for (const label of ['Ver detalle', 'Editar', 'Desactivar', 'Reactivar']) {
        assert.ok(css.includes(`content:"${label}"`), `falta tooltip ${label}`);
    }
    assert.ok(css.includes('.row-actions .action:focus-visible'));
});
