// Validación del navegador leída de una vez (Public/js/shared/validacion-nativa.js).
import test from 'node:test';
import assert from 'node:assert/strict';
import { erroresNativos, mensajeCampo, teclaBloqueada } from '../../Public/js/shared/validacion-nativa.js';

const campo = (validity, extra = {}) => ({
    name: 'x', willValidate: true, tagName: 'INPUT', type: 'text',
    validity: { valid: false, ...validity }, validationMessage: 'del navegador', ...extra,
});

test('los campos numéricos dicen qué corregir', () => {
    const num = { type: 'number', min: '0', max: '5000', step: '0.1' };
    assert.equal(mensajeCampo(campo({ badInput: true }, num)), 'Escribe solo números.');
    assert.equal(mensajeCampo(campo({ rangeUnderflow: true }, num)), 'No puede ser negativo.');
    assert.equal(mensajeCampo(campo({ rangeUnderflow: true }, { ...num, min: '2' })), 'No puede ser menor que 2.');
    assert.equal(mensajeCampo(campo({ rangeOverflow: true }, num)), 'No puede ser mayor que 5000.');
    assert.equal(mensajeCampo(campo({ stepMismatch: true }, num)), 'Usa como máximo un decimal.');
    assert.equal(mensajeCampo(campo({ stepMismatch: true }, { ...num, step: '1' })), 'Escribe un número entero, sin decimales.');
});

test('fecha, enlace, lista y obligatorio', () => {
    assert.equal(mensajeCampo(campo({ rangeOverflow: true }, { type: 'date', max: '2026-10-12' })), 'La fecha no puede ser futura.');
    assert.match(mensajeCampo(campo({ typeMismatch: true }, { type: 'url' })), /https:\/\//);
    assert.equal(mensajeCampo(campo({ valueMissing: true }, { tagName: 'SELECT', type: 'select-one' })), 'Elige una opción.');
    assert.equal(mensajeCampo(campo({ valueMissing: true })), 'Este campo es obligatorio.');
    assert.equal(mensajeCampo(campo({ customError: true })), 'del navegador');
    assert.equal(mensajeCampo({ validity: { valid: true } }), '');
});

test('erroresNativos junta todos los inválidos y salta deshabilitados y ocultos', () => {
    const form = { elements: [
        campo({ valueMissing: true }, { name: 'titulo' }),
        campo({ badInput: true }, { name: 'precio', type: 'number' }),
        campo({ valueMissing: true }, { name: 'oculto', hidden: true }),
        campo({ valueMissing: true }, { name: 'deshabilitado', willValidate: false }),
        campo({ valueMissing: true }, { name: 'dentro', closest: (sel) => (sel === '[hidden]' ? {} : null) }),
        { name: 'bien', willValidate: true, validity: { valid: true } },
        campo({ valueMissing: true }, { name: '' }),
    ] };
    assert.deepEqual(erroresNativos(form), { titulo: 'Este campo es obligatorio.', precio: 'Escribe solo números.' });
});

test('un campo numérico no deja escribir e, +, signo menos ni decimales si no los admite', () => {
    const entero = { type: 'number', min: '0', step: '1' };
    for (const tecla of ['e', 'E', '+', '-', '.', ',']) assert.equal(teclaBloqueada(tecla, entero), true, tecla);
    for (const tecla of ['1', '0', '9', 'Backspace', 'Tab', 'ArrowLeft']) assert.equal(teclaBloqueada(tecla, entero), false, tecla);
    const peso = { type: 'number', min: '0', step: '0.1' };
    assert.equal(teclaBloqueada('.', peso), false);
    assert.equal(teclaBloqueada(',', peso), false);
    assert.equal(teclaBloqueada('e', peso), true);
    assert.equal(teclaBloqueada('-', { type: 'number', min: '-5', step: '1' }), false);
    assert.equal(teclaBloqueada('e', { type: 'text' }), false);
});

import { readFileSync } from 'node:fs';
const leer = (ruta) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');

test('los formularios usan la validación junta y no la burbuja de una en una', () => {
    assert.doesNotMatch(leer('Public/js/publicar.js'), /reportValidity\(\)/);
    for (const modulo of ['publicar', 'mis-animales', 'mi-actividad', 'fletes', 'subastas']) {
        assert.match(leer(`Public/js/${modulo}.js`), /validacion-nativa\.js\?v=nativa-1/, modulo);
    }
});

test('el login avisa con un mensaje amable sin decir cuál de los dos datos falló', () => {
    const login = leer('Public/js/login.js');
    assert.match(login, /El correo o la contraseña no coinciden/);
    assert.doesNotMatch(login, /(correo|contraseña) (no existe|es incorrect[oa])/i);
});
