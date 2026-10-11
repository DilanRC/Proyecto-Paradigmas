// Errores del servidor que el formulario no tiene dónde mostrar (Public/js/shared/field-errors.js).
//
// Ejecutar: node --test Tests/frontend/errores_sin_lugar.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { erroresSinLugar } from '../../Public/js/shared/field-errors.js';

/** Formulario mínimo: controles por nombre y nodos con atributos data-…-error. */
function formulario(nombres, espacios = []) {
    const nodo = ([name, value]) => ({ attributes: [{ name, value }] });
    return {
        elements: { namedItem: (nombre) => (nombres.includes(nombre) ? {} : null) },
        querySelectorAll: () => espacios.map(nodo),
    };
}

test('solo devuelve los errores sin control ni espacio en el formulario', () => {
    const form = formulario(['titulo'], [['data-animal-error', 'precio'], ['data-error-for', 'nombre']]);
    const errores = { titulo: 'A', precio: 'B', nombre: 'C', loteCantidad: 'Registra los animales uno por uno.' };
    assert.deepEqual(erroresSinLugar(form, errores), ['Registra los animales uno por uno.']);
});

test('una clave con prefijo usa el campo final (persona.nombres -> nombres)', () => {
    assert.deepEqual(erroresSinLugar(formulario(['nombres']), { 'persona.nombres': 'X', 'persona.alias': 'Y' }), ['Y']);
});

test('sin errores no devuelve nada', () => {
    assert.deepEqual(erroresSinLugar(formulario([]), null), []);
});
