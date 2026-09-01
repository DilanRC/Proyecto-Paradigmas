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

// --- mensajes de validacion del cliente --------------------------------------
// Como el envio se cancela con preventDefault(), el globo nativo del navegador
// nunca aparece. Sin estos mensajes el usuario veria los campos en rojo y ningun
// texto que explique por que.

import { describeValidity } from '../../Public/js/shared/field-errors.js';

const control = (validity, extra = {}) => ({ validity: { valid: false, ...validity }, ...extra });

test('un control valido no produce mensaje', () => {
    assert.equal(describeValidity({ validity: { valid: true } }), '');
    assert.equal(describeValidity(null), '');
    assert.equal(describeValidity(undefined), '');
});

test('campo obligatorio vacio', () => {
    assert.equal(describeValidity(control({ valueMissing: true })), 'Este campo es obligatorio.');
});

test('correo con formato invalido se nombra como correo', () => {
    assert.equal(
        describeValidity(control({ typeMismatch: true }, { type: 'email' })),
        'Ingrese un correo electrónico válido.',
    );
    assert.equal(
        describeValidity(control({ typeMismatch: true }, { type: 'url' })),
        'El formato del valor no es válido.',
    );
});

test('longitud minima y maxima citan el limite real del control', () => {
    assert.equal(
        describeValidity(control({ tooShort: true }, { minLength: 3 })),
        'Debe contener al menos 3 caracteres.',
    );
    assert.equal(
        describeValidity(control({ tooLong: true }, { maxLength: 150 })),
        'No puede superar 150 caracteres.',
    );
});

test('los mensajes son propios y no dependen del idioma del navegador', () => {
    // validationMessage seria "Completa este campo" o "Please fill out this
    // field" segun la configuracion; el texto mostrado no debe variar por eso.
    const nativo = control({ valueMissing: true }, { validationMessage: 'Please fill out this field' });
    assert.equal(describeValidity(nativo), 'Este campo es obligatorio.');
});

test('ante un motivo desconocido se prefiere el mensaje del navegador a no decir nada', () => {
    const raro = control({}, { validationMessage: 'Motivo del navegador' });
    assert.equal(describeValidity(raro), 'Motivo del navegador');
    assert.equal(describeValidity(control({})), 'Revise este campo.');
});

// --- restricciones del numero de identificacion ------------------------------
import { TIPOS_NUMERICOS, reglaIdentificacion } from '../../Public/js/shared/identificacion.js';

test('REGRESION: los patrones compilan bajo la bandera v del atributo pattern', () => {
    // El navegador compila `pattern` con la bandera `v` y, si el patron no
    // compila, DESCARTA el atributo sin avisar: el campo deja de validar del
    // todo. Escrito como '[0-9][0-9 -]*' el guion literal es un error de
    // sintaxis y una cedula aceptaba letras en silencio.
    for (const tipo of [...TIPOS_NUMERICOS, 'NITE', 'PASAPORTE']) {
        const { pattern } = reglaIdentificacion(tipo);
        assert.doesNotThrow(
            () => new RegExp(`^(?:${pattern})$`, 'v'),
            `el patron de ${tipo} no compila y el navegador lo ignoraria`,
        );
    }
});

test('los patrones significan lo mismo que los del backend', () => {
    // Application/Controller/*Controller.php::validarIdentificacion()
    for (const tipo of TIPOS_NUMERICOS) {
        assert.equal(reglaIdentificacion(tipo).pattern, '[0-9][0-9 \\-]*', tipo);
    }
    for (const tipo of ['NITE', 'PASAPORTE']) {
        assert.equal(reglaIdentificacion(tipo).pattern, '[A-Za-z0-9][A-Za-z0-9 \\-]*', tipo);
    }
});

test('el patron acepta y rechaza lo mismo que el servidor', () => {
    const acepta = (tipo, valor) => new RegExp(`^(?:${reglaIdentificacion(tipo).pattern})$`).test(valor);

    assert.equal(acepta('CEDULA_FISICA', '1-1111-1111'), true);
    assert.equal(acepta('CEDULA_FISICA', '1 1111 1111'), true);
    assert.equal(acepta('CEDULA_FISICA', 'AB123'), false, 'una cedula no admite letras');
    assert.equal(acepta('CEDULA_FISICA', '-1111'), false, 'no puede empezar por guion');

    assert.equal(acepta('PASAPORTE', 'AB123456'), true);
    assert.equal(acepta('PASAPORTE', 'AB-123 456'), true);
    assert.equal(acepta('PASAPORTE', '¡mal!'), false, 'no admite simbolos');
});

test('sin tipo elegido no se impone patron y se pide elegir primero', () => {
    const regla = reglaIdentificacion('');
    assert.equal(regla.pattern, null);
    assert.match(regla.ayuda, /Elija primero el tipo/);
});

test('el modo de teclado acompana al tipo', () => {
    assert.equal(reglaIdentificacion('CEDULA_JURIDICA').inputMode, 'numeric');
    assert.equal(reglaIdentificacion('PASAPORTE').inputMode, 'text');
});

test('la ayuda advierte que los separadores no se conservan', () => {
    // El backend normaliza con preg_replace('/[ -]+/u', '') antes de guardar.
    assert.match(reglaIdentificacion('CEDULA_FISICA').ayuda, /sin espacios ni guiones/);
});

test('no se inventan longitudes que el backend no exige', () => {
    // El servidor solo pide entre 1 y 250 caracteres; fijar aqui "nueve digitos"
    // rechazaria valores que el backend acepta.
    for (const tipo of [...TIPOS_NUMERICOS, 'NITE', 'PASAPORTE']) {
        const regla = reglaIdentificacion(tipo);
        assert.equal(/\{\d+\}/.test(regla.pattern), false, `${tipo} no debe fijar longitud`);
    }
});
