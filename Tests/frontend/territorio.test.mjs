// Catalogo territorial y cascada provincia -> canton -> distrito.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    TERRITORIO, cantones, distritos, provincias,
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

test('los cantones cuelgan de su provincia y no de otra', () => {
    assert.ok(cantones('Guanacaste').includes('Tilarán'));
    assert.ok(!cantones('Alajuela').includes('Tilarán'));
    assert.ok(cantones('Alajuela').includes('San Carlos'));
    assert.ok(!cantones('Limón').includes('San Carlos'));
});

test('una provincia o canton desconocidos no rompen la consulta', () => {
    assert.deepEqual(cantones('Narnia'), []);
    assert.deepEqual(distritos('Narnia', 'Cualquiera'), []);
    assert.deepEqual(distritos('Limón', 'Inexistente'), []);
});

test('los distritos quedan pendientes y por eso el campo es texto libre', () => {
    // Mientras la lista este vacia el formulario no puede imponer un desplegable:
    // impediria escribir un distrito real. Ver DEC-FRONT-11.
    const vacios = provincias()
        .flatMap((p) => cantones(p).map((c) => distritos(p, c).length))
        .every((n) => n === 0);
    assert.equal(vacios, true, 'si ya hay distritos, revise que el campo los ofrezca');
});

test('no hay cantones repetidos dentro de una provincia', () => {
    for (const p of provincias()) {
        const lista = cantones(p);
        assert.equal(new Set(lista).size, lista.length, `${p} tiene cantones repetidos`);
    }
});

test('la estructura es provincia -> canton -> lista de distritos', () => {
    for (const [provincia, cantonesDeLaProvincia] of Object.entries(TERRITORIO)) {
        assert.equal(typeof provincia, 'string');
        for (const [canton, lista] of Object.entries(cantonesDeLaProvincia)) {
            assert.ok(Array.isArray(lista), `${provincia}/${canton} debe apuntar a un arreglo`);
        }
    }
});
