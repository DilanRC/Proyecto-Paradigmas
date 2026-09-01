// Restriccion del campo de telefono, contrastada contra el contrato del backend.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { PATRON_TELEFONO, TITULO_TELEFONO, telefonoValido } from '../../Public/js/shared/telefono.js';

const VISTAS = ['productores', 'compradores', 'transportistas'];
const leer = (r) => readFileSync(new URL(`../../${r}`, import.meta.url), 'utf8');

// Tabla unica: la misma que juzgan el patron y la funcion. Los valores esperados
// salen de leer validarTelefono() en los controladores, no de lo que el
// formulario hace hoy.
const CASOS = [
    ['88887777', true, 'ocho digitos, el minimo'],
    ['2222 3333', true, 'con espacio'],
    ['8888-7777', true, 'con guion'],
    ['(506) 8888-7777', true, 'con parentesis'],
    ['+50688887777', true, 'con prefijo'],
    ['+506 8888 7777', true, 'prefijo y espacios'],
    ['123456789012345', true, 'quince digitos, el maximo'],
    ['1234567', false, 'siete digitos, por debajo del minimo'],
    ['1234567890123456', false, 'dieciseis digitos, por encima del maximo'],
    ['abcdefgh', false, 'letras'],
    ['88887777x', false, 'una letra al final'],
    ['+', false, 'solo el prefijo'],
    ['++50688887777', false, 'prefijo repetido'],
    ['8888.7777', false, 'el punto no esta permitido'],
    ['1', false, 'un solo digito'],
    ['12345678901234567890', false, 'veinte digitos'],
];

test('el patron compila bajo la bandera v del atributo pattern', () => {
    // El navegador compila `pattern` con la bandera `v`; si no compila, descarta
    // el atributo en silencio y deja de validar. Bajo `v` el guion y los
    // parentesis son reservados dentro de una clase y hay que escaparlos.
    assert.doesNotThrow(() => new RegExp(`^(?:${PATRON_TELEFONO})$`, 'v'));
});

test('el patron y la funcion coinciden con el backend en todos los casos', () => {
    const re = new RegExp(`^(?:${PATRON_TELEFONO})$`, 'v');
    for (const [valor, esperado, motivo] of CASOS) {
        assert.equal(telefonoValido(valor), esperado, `funcion, ${motivo}: ${valor}`);
        // El atributo `maxlength` cubre el limite de 20 que el patron no expresa.
        const porPatron = valor.length <= 20 && re.test(valor);
        assert.equal(porPatron, esperado, `patron, ${motivo}: ${valor}`);
    }
});

test('el numero de digitos se cuenta ignorando los separadores', () => {
    // Ocho digitos repartidos entre parentesis, espacios y guiones siguen siendo
    // ocho, igual que hace el backend con preg_replace('/\\D+/', '').
    assert.equal(telefonoValido('(88) 88-77 77'), true);
    assert.equal(telefonoValido('(88) 88-77 7'), false, 'siete digitos aunque el texto sea largo');
});

test('un valor vacio no lo acepta la funcion; el campo es required', () => {
    assert.equal(telefonoValido(''), false);
    assert.equal(telefonoValido(null), false);
    assert.equal(telefonoValido(undefined), false);
});

test('las tres vistas llevan exactamente el patron del modulo', () => {
    for (const vista of VISTAS) {
        const html = leer(`Application/View/${vista}/index.php`);
        const campo = html.match(/<input id="telefono"[^>]*>/)[0];
        const patron = campo.match(/pattern="([^"]+)"/);
        assert.ok(patron, `${vista}: el campo de telefono no declara pattern`);
        assert.equal(patron[1], PATRON_TELEFONO, `${vista}: el patron divergio del modulo`);
        assert.ok(campo.includes(`title="${TITULO_TELEFONO}"`), `${vista}: falta el title con la regla`);
        assert.ok(campo.includes('maxlength="20"'), `${vista}: falta el limite de 20 que exige el backend`);
        assert.ok(html.includes('id="ayuda-telefono"'), `${vista}: falta la ayuda del campo`);
    }
});

test('todos los pattern de todas las vistas compilan bajo la bandera v', () => {
    // Gate general: cualquier patron nuevo que alguien escriba mal se detecta
    // aqui en vez de quedarse mudo en el navegador.
    for (const vista of [...VISTAS, 'vehiculos', 'pagometodos']) {
        const html = leer(`Application/View/${vista}/index.php`);
        for (const [, patron] of html.matchAll(/pattern="([^"]+)"/g)) {
            assert.doesNotThrow(
                () => new RegExp(`^(?:${patron})$`, 'v'),
                `${vista}: el patron ${patron} no compila bajo la bandera v`,
            );
        }
    }
});
