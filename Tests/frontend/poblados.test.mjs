// Centros poblados y su busqueda tolerante al defecto de la fuente.

import assert from 'node:assert/strict';
import test from 'node:test';

import { POBLADOS, buscarPoblados, normalizar, pobladosDe } from '../../Public/js/shared/poblados.js';
import { cantones, codigoDistrito, distritos, provincias } from '../../Public/js/shared/territorio.js';

const CODIGOS = new Set(provincias()
    .flatMap((p) => cantones(p).flatMap((c) => distritos(p, c).map((d) => codigoDistrito(p, c, d)))));

test('hay una entrada por cada uno de los 494 distritos', () => {
    assert.equal(Object.keys(POBLADOS).length, 494);
});

test('ninguna localidad cuelga de un distrito inexistente', () => {
    const huerfanos = Object.keys(POBLADOS).filter((codigo) => !CODIGOS.has(codigo));
    assert.deepEqual(huerfanos, [], `codigos sin distrito: ${huerfanos.join(', ')}`);
});

test('el total es 13309, contado del archivo oficial 2026', () => {
    const total = Object.values(POBLADOS).reduce((n, lista) => n + lista.length, 0);
    assert.equal(total, 13309);
});

test('solo un distrito se queda sin localidad publicada', () => {
    assert.equal(Object.values(POBLADOS).filter((lista) => lista.length === 0).length, 1);
});

test('no hay localidades repetidas dentro de un distrito', () => {
    for (const [codigo, lista] of Object.entries(POBLADOS)) {
        assert.equal(new Set(lista).size, lista.length, `${codigo} repite localidades`);
    }
});

test('un distrito desconocido devuelve lista vacia y no rompe', () => {
    assert.deepEqual(pobladosDe('99999'), []);
    assert.deepEqual(buscarPoblados('99999', 'algo'), []);
});

// --- normalizacion -----------------------------------------------------------

test('normalizar ignora acentos y mayusculas', () => {
    assert.equal(normalizar('Pérez Zeledón'), normalizar('perez zeledon'));
    assert.equal(normalizar('SAN JOSÉ'), normalizar('san jose'));
});

test('normalizar compensa el "nd" que la fuente oficial perdio', () => {
    // El XLSX del IGN trae "Tamario" por Tamarindo. Aplicar la misma perdida a
    // la consulta es lo que permite encontrarlo escribiendolo bien.
    assert.equal(normalizar('Tamarindo'), normalizar('Tamario'));
    assert.equal(normalizar('Llano Grande'), normalizar('Llano Grae'));
    assert.equal(normalizar('Condominio'), normalizar('Coominio'));
});

test('normalizar no confunde nombres que de verdad son distintos', () => {
    assert.notEqual(normalizar('Guácimo'), normalizar('Guápiles'));
    assert.notEqual(normalizar('San Isidro'), normalizar('San Rafael'));
});

test('normalizar tolera nulos y vacios', () => {
    assert.equal(normalizar(null), '');
    assert.equal(normalizar(undefined), '');
    assert.equal(normalizar('   '), '');
});

// --- busqueda ----------------------------------------------------------------

test('escribir el nombre correcto encuentra el registro que la fuente mutilo', () => {
    // Este es el punto: sin la compensacion, "Tamarindo" no encontraria nada en
    // Santa Cruz porque la fuente guarda "Tamario".
    const resultado = buscarPoblados('50309', 'Tamarindo');
    assert.ok(resultado.length > 0, 'la busqueda con el nombre correcto no debe quedar vacia');
    assert.ok(resultado.some((n) => normalizar(n) === normalizar('Tamarindo')));
});

test('los 70 nombres reparados con la DTA quedan escritos bien', () => {
    // "Mata Redoa" solo puede ser el "Mata Redonda" que la DTA declara para ese
    // mismo distrito, asi que se corrigio. No es conjetura.
    assert.ok(pobladosDe('10108').includes('Mata Redonda'));
    assert.ok(!pobladosDe('10108').includes('Mata Redoa'));
    assert.ok(pobladosDe('30110').includes('Llano Grande'));
});

test('las coincidencias por prefijo van antes que las del interior', () => {
    const resultado = buscarPoblados('10101', 'san', { limite: 25 });
    const primerInterior = resultado.findIndex((n) => !normalizar(n).startsWith(normalizar('san')));
    if (primerInterior !== -1) {
        const posteriores = resultado.slice(primerInterior);
        assert.ok(
            posteriores.every((n) => !normalizar(n).startsWith(normalizar('san'))),
            'un resultado por prefijo aparece despues de uno por interior',
        );
    }
});

test('una consulta vacia sugiere el principio de la lista', () => {
    const resultado = buscarPoblados('10101', '');
    assert.ok(resultado.length > 0);
    assert.deepEqual(resultado, pobladosDe('10101').slice(0, resultado.length));
});

test('el limite se respeta', () => {
    assert.ok(buscarPoblados('10101', '', { limite: 3 }).length <= 3);
    assert.ok(buscarPoblados('10101', 'a', { limite: 5 }).length <= 5);
});

test('la busqueda solo mira el distrito pedido', () => {
    const codigo = '70605';
    for (const nombre of buscarPoblados(codigo, '', { limite: 50 })) {
        assert.ok(pobladosDe(codigo).includes(nombre), `${nombre} no pertenece a ${codigo}`);
    }
});

test('Duacari, el distrito reconciliado, si trae sus localidades', () => {
    assert.equal(pobladosDe('70605').length, 19);
});
