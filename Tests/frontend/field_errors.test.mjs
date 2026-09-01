// Mapeo de los errores 422 del backend a los campos del formulario.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    firstErrorField, mapFieldErrors, normalizeFieldKey, pickFirstInvalid,
} from '../../Public/js/shared/field-errors.js';

test('las claves indexadas colapsan al campo que existe en el formulario', () => {
    // El textarea se llama "fincas"; el backend responde "fincas.0.nombre".
    assert.equal(normalizeFieldKey('fincas.0.nombre', { collapsePrefixes: ['fincas'] }), 'fincas');
    assert.equal(normalizeFieldKey('fincas', { collapsePrefixes: ['fincas'] }), 'fincas');
});

test('sin prefijos declarados la clave no se toca', () => {
    assert.equal(normalizeFieldKey('fincas.0.nombre'), 'fincas.0.nombre');
    assert.equal(normalizeFieldKey('telefono'), 'telefono');
});

test('los campos anidados que si existen en el formulario se conservan', () => {
    // identificacion.numero y direccionPrincipal.provincia son nombres reales
    // de control; colapsarlos romperia el pintado.
    assert.equal(normalizeFieldKey('identificacion.numero', { collapsePrefixes: ['fincas'] }), 'identificacion.numero');
    assert.equal(
        normalizeFieldKey('direccionPrincipal.provincia', { collapsePrefixes: ['fincas'] }),
        'direccionPrincipal.provincia',
    );
});

test('varias claves que colapsan al mismo campo: gana la primera', () => {
    // El resultado no puede depender del orden en que PHP serializo el objeto.
    const entries = mapFieldErrors(
        { 'fincas.0.nombre': 'primera', 'fincas.2.nombre': 'segunda' },
        { collapsePrefixes: ['fincas'] },
    );
    assert.deepEqual(entries, [{ field: 'fincas', message: 'primera' }]);
});

test('el mapeo conserva el orden y permite enfocar el primer campo con error', () => {
    const entries = mapFieldErrors({
        telefono: 'El telefono no es valido',
        correoElectronico: 'El correo ya existe',
    });
    assert.deepEqual(entries.map((e) => e.field), ['telefono', 'correoElectronico']);
    assert.equal(firstErrorField(entries), 'telefono');
});

test('sin errores el mapeo es una lista vacia y no hay campo que enfocar', () => {
    assert.deepEqual(mapFieldErrors(null), []);
    assert.deepEqual(mapFieldErrors(undefined), []);
    assert.deepEqual(mapFieldErrors({}), []);
    assert.equal(firstErrorField([]), null);
});

test('los mensajes se normalizan a texto', () => {
    const entries = mapFieldErrors({ pagina: 5 });
    assert.equal(entries[0].message, '5');
    assert.equal(typeof entries[0].message, 'string');
});

test('pickFirstInvalid devuelve el primero en orden del documento', () => {
    const controles = [
        { name: 'a', valido: true },
        { name: 'b', valido: false },
        { name: 'c', valido: false },
    ];
    const primero = pickFirstInvalid(controles, (c) => !c.valido);
    assert.equal(primero.name, 'b');
    assert.equal(pickFirstInvalid(controles, () => false), null);
});
