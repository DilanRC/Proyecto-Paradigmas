// Catalogo territorial y cascada provincia -> canton -> distrito.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    TERRITORIO, cantones, distritos, poblados, provincias,
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

test('el total de distritos es 494, el que declara la DTA 2026', () => {
    const total = provincias()
        .reduce((n, p) => n + cantones(p).reduce((m, c) => m + distritos(p, c).length, 0), 0);
    assert.equal(total, 494);
});

test('esta el distrito 70605 Duacari, el que la tabla de la DTA omite', () => {
    // La tabla del PDF trae 493 filas frente a las 494 que declara su portada.
    // El nombre sale del archivo de Centros Poblados 2026 del propio IGN.
    assert.ok(distritos('Limón', 'Guácimo').includes('Duacarí'));
    assert.equal(distritos('Limón', 'Guácimo').length, 5);
});

test('los distritos cuelgan de su canton y no de otro', () => {
    assert.ok(distritos('San José', 'San José').includes('Mata Redonda'));
    assert.ok(!distritos('San José', 'Escazú').includes('Mata Redonda'));
    assert.ok(distritos('Alajuela', 'Alajuela').includes('Río Segundo'));
});

test('ningun nombre de distrito perdio caracteres en la extraccion', () => {
    // La conversion del PDF de localidades pierde la secuencia "nd" y deja
    // "Coominio" por "Condominio". La DTA no tiene ese defecto y estos nombres
    // lo prueban: si alguno desaparece, la fuente se regenero con un extractor
    // roto. Ver DEC-FRONT-11.
    const todos = provincias().flatMap((p) => cantones(p).flatMap((c) => distritos(p, c)));
    for (const esperado of ['Mata Redonda', 'Río Segundo', 'San Andrés', 'Rancho Redondo']) {
        assert.ok(todos.includes(esperado), `falta ${esperado}: extraccion corrupta`);
    }
});

test('no hay distritos repetidos dentro de un canton', () => {
    for (const p of provincias()) {
        for (const c of cantones(p)) {
            const lista = distritos(p, c);
            assert.equal(new Set(lista).size, lista.length, `${p}/${c} repite distritos`);
        }
    }
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
            assert.equal(typeof lista, 'object', `${provincia}/${canton} debe apuntar a sus distritos`);
            assert.equal(Array.isArray(lista), false, 'el nivel de canton mapea distrito -> poblados');
        }
    }
});

test('los poblados quedan pendientes y por eso el campo es texto libre', () => {
    // El archivo de Centros Poblados 2026 no se pudo extraer con fidelidad,
    // asi que no se cargo ninguno. Mientras esten vacios el formulario no puede
    // imponer un desplegable: impediria escribir un poblado real. DEC-FRONT-11.
    const vacios = provincias()
        .flatMap((p) => cantones(p).flatMap((c) => distritos(p, c).map((d) => poblados(p, c, d).length)))
        .every((n) => n === 0);
    assert.equal(vacios, true, 'si ya hay poblados, revise que el campo los ofrezca');
    assert.deepEqual(poblados('Narnia', 'X', 'Y'), []);
});
