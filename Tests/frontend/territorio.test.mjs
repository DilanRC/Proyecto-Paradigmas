// Catalogo territorial y cascada provincia -> canton -> distrito.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    TERRITORIO, cantones, codigoDistrito, distritos, provincias,
} from '../../Public/js/shared/territorio.js';

test('estan las siete provincias', () => {
    assert.equal(provincias().length, 7);
    for (const p of ['San José', 'Alajuela', 'Cartago', 'Heredia', 'Guanacaste', 'Puntarenas', 'Limón']) {
        assert.ok(provincias().includes(p), `falta ${p}`);
    }
});

test('el total de cantones es 84', () => {
    // 84 tras la creacion de Monteverde (2021) y Puerto Jimenez (2022).
    const total = provincias().reduce((n, p) => n + cantones(p).length, 0);
    assert.equal(total, 84);
});

test('cada provincia tiene el numero de cantones que le corresponde', () => {
    assert.deepEqual(
        Object.fromEntries(provincias().map((p) => [p, cantones(p).length])),
        {
            'San José': 20, Alajuela: 16, Cartago: 8, Heredia: 10,
            Guanacaste: 11, Puntarenas: 13, Limón: 6,
        },
    );
});

test('el total de distritos es 494, el que declara la DTA 2026', () => {
    const total = provincias()
        .reduce((n, p) => n + cantones(p).reduce((m, c) => m + distritos(p, c).length, 0), 0);
    assert.equal(total, 494);
});

test('esta el distrito 70605 Duacari, el que la tabla de la DTA omite', () => {
    // La tabla del PDF trae 493 filas frente a las 494 que declara su portada.
    assert.ok(distritos('Limón', 'Guácimo').includes('Duacarí'));
    assert.equal(codigoDistrito('Limón', 'Guácimo', 'Duacarí'), '70605');
});

test('los cantones y distritos cuelgan de su padre y no de otro', () => {
    assert.ok(cantones('Guanacaste').includes('Tilarán'));
    assert.ok(!cantones('Alajuela').includes('Tilarán'));
    assert.ok(distritos('San José', 'San José').includes('Mata Redonda'));
    assert.ok(!distritos('San José', 'Escazú').includes('Mata Redonda'));
});

test('una provincia, canton o distrito desconocidos no rompen la consulta', () => {
    assert.deepEqual(cantones('Narnia'), []);
    assert.deepEqual(distritos('Narnia', 'Cualquiera'), []);
    assert.deepEqual(distritos('Limón', 'Inexistente'), []);
    assert.equal(codigoDistrito('Narnia', 'X', 'Y'), null);
    assert.equal(codigoDistrito('Limón', 'Guácimo', 'Inexistente'), null);
});

test('cada distrito tiene un codigo DTA de cinco digitos, y son unicos', () => {
    const codigos = provincias()
        .flatMap((p) => cantones(p).flatMap((c) => distritos(p, c).map((d) => codigoDistrito(p, c, d))));
    assert.equal(codigos.length, 494);
    for (const codigo of codigos) {
        assert.match(codigo, /^\d{5}$/, `codigo mal formado: ${codigo}`);
        assert.equal(typeof codigo, 'string', 'el codigo debe ser texto: 01101 no puede perder el cero');
    }
    assert.equal(new Set(codigos).size, 494, 'hay codigos DTA repetidos');
});

test('el codigo codifica la jerarquia: sus prefijos agrupan canton y provincia', () => {
    const porCanton = new Set();
    for (const p of provincias()) {
        for (const c of cantones(p)) {
            const prefijos = new Set(distritos(p, c).map((d) => codigoDistrito(p, c, d).slice(0, 3)));
            assert.equal(prefijos.size, 1, `${p}/${c}: sus distritos no comparten codigo de canton`);
            porCanton.add([...prefijos][0]);
        }
    }
    assert.equal(porCanton.size, 84, 'dos cantones comparten codigo');
});

test('ningun nombre perdio caracteres en la extraccion', () => {
    // El XLSX oficial de localidades pierde la secuencia "nd" y deja "Mata
    // Redoa". La tabla DTA no tiene ese defecto y estos nombres lo prueban: si
    // alguno desaparece, el catalogo se regenero desde la fuente equivocada.
    const todos = provincias().flatMap((p) => cantones(p).flatMap((c) => distritos(p, c)));
    for (const esperado of ['Mata Redonda', 'Río Segundo', 'San Andrés', 'Rancho Redondo', 'Llano Grande']) {
        assert.ok(todos.includes(esperado), `falta ${esperado}: extraccion corrupta`);
    }
});

test('los acentos y la enie se conservan', () => {
    assert.ok(cantones('Limón').includes('Guácimo'));
    assert.ok(cantones('San José').includes('Pérez Zeledón'));
    assert.ok(cantones('San José').includes('Vázquez de Coronado'));
    assert.ok(distritos('Limón', 'Guácimo').includes('Duacarí'));
});

test('no hay cantones ni distritos repetidos dentro de su padre', () => {
    for (const p of provincias()) {
        assert.equal(new Set(cantones(p)).size, cantones(p).length, `${p} repite cantones`);
        for (const c of cantones(p)) {
            const lista = distritos(p, c);
            assert.equal(new Set(lista).size, lista.length, `${p}/${c} repite distritos`);
        }
    }
});

test('la estructura es provincia -> canton -> distrito -> codigo', () => {
    for (const [provincia, cantonesDe] of Object.entries(TERRITORIO)) {
        for (const [canton, distritosDe] of Object.entries(cantonesDe)) {
            for (const [distrito, codigo] of Object.entries(distritosDe)) {
                assert.equal(typeof codigo, 'string', `${provincia}/${canton}/${distrito}: codigo no textual`);
            }
        }
    }
});
