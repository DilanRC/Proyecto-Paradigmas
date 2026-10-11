// Color de la línea de estado de los formularios (Public/js/shared/estado.js).
//
// Ejecutar: node --test Tests/frontend/estado_formulario.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { mostrarEstado } from '../../Public/js/shared/estado.js';

test('cada mensaje lleva su tipo; vacío lo quita', () => {
    const nodo = { textContent: '', dataset: {} };
    mostrarEstado(nodo, 'Revise los campos indicados.', 'error');
    assert.deepEqual([nodo.textContent, nodo.dataset.status], ['Revise los campos indicados.', 'error']);
    mostrarEstado(nodo, 'Guardando…');
    assert.equal(nodo.dataset.status, 'info');
    mostrarEstado(nodo, '');
    assert.equal(nodo.dataset.status, '');
});

test('los errores de los formularios del cliente se muestran como error (rojo), no con el verde por defecto', () => {
    for (const modulo of ['fletes', 'mi-actividad', 'mis-animales', 'ajustes']) {
        const js = readFileSync(new URL(`../../Public/js/${modulo}.js`, import.meta.url), 'utf8');
        assert.ok(js.includes("from './shared/estado.js?v=estado-1'"), `${modulo} usa mostrarEstado`);
        assert.doesNotMatch(js, /(status|estado)\.textContent = \[error\?\.message/, `${modulo}: un error escrito sin tipo saldría verde`);
    }
});
