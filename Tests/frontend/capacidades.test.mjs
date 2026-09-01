// Lecturas de una persona: Productor, clasificación Comprador y Transportista.
//
// La propiedad crítica es distinguir ausencia comprobada de fallo de consulta y,
// además, distinguir una capacidad registrada de una clasificación derivada.
// Comprador no puede volver a presentarse como un alta administrativa.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    CAPACIDADES, consultarCapacidades, describirCapacidad, interpretarCapacidad,
} from '../../Public/js/shared/capacidades.js';

const fallo = (status) => Object.assign(new Error('fallo'), { status });

test('un 404 significa ausencia comprobada', () => {
    assert.deepEqual(
        interpretarCapacidad({ ok: false, error: fallo(404) }),
        { situacion: 'no-registrado', estado: null },
    );
});

test('un fallo de red no permite concluir ausencia', () => {
    const red = interpretarCapacidad({ ok: false, error: fallo(null) });
    assert.equal(red.situacion, 'desconocido');
    assert.notEqual(red.situacion, 'no-registrado');
});

test('un 500 tampoco permite concluir ausencia', () => {
    assert.equal(interpretarCapacidad({ ok: false, error: fallo(500) }).situacion, 'desconocido');
});

test('un 200 distingue estado efectivo activo e inactivo', () => {
    assert.deepEqual(
        interpretarCapacidad({ ok: true, data: { estado: 'ACTIVO' } }),
        { situacion: 'registrado', estado: 'ACTIVO' },
    );
    assert.deepEqual(
        interpretarCapacidad({ ok: true, data: { estado: 'INACTIVO' } }),
        { situacion: 'registrado', estado: 'INACTIVO' },
    );
});

test('una respuesta sin estado nunca concede estado activo', () => {
    assert.equal(interpretarCapacidad({ ok: true, data: {} }).estado, 'INACTIVO');
    assert.equal(interpretarCapacidad({ ok: true, data: undefined }).estado, 'INACTIVO');
});

test('capacidades registradas conservan etiquetas de registro', () => {
    assert.equal(
        describirCapacidad({ situacion: 'registrado', estado: 'ACTIVO', derivada: false }),
        'Registrado y activo',
    );
    assert.equal(
        describirCapacidad({ situacion: 'registrado', estado: 'INACTIVO', derivada: false }),
        'Registrado, inactivo',
    );
    assert.equal(
        describirCapacidad({ situacion: 'no-registrado', estado: null, derivada: false }),
        'No registrado',
    );
});

test('Comprador se describe como clasificación y nunca como registro', () => {
    assert.equal(
        describirCapacidad({ situacion: 'registrado', estado: 'ACTIVO', derivada: true }),
        'Clasificado actualmente',
    );
    assert.equal(
        describirCapacidad({ situacion: 'registrado', estado: 'INACTIVO', derivada: true }),
        'Clasificado, persona inactiva',
    );
    assert.equal(
        describirCapacidad({ situacion: 'no-registrado', estado: null, derivada: true }),
        'Sin clasificación vigente',
    );
    assert.doesNotMatch(
        describirCapacidad({ situacion: 'registrado', estado: 'ACTIVO', derivada: true }),
        /registrad/i,
    );
});

test('un fallo conserva una etiqueta de incertidumbre para ambos tipos', () => {
    assert.match(describirCapacidad({ situacion: 'desconocido', estado: null }), /no se pudo/i);
    assert.match(describirCapacidad({ situacion: 'desconocido', estado: null, derivada: true }), /no se pudo/i);
});

test('el catálogo contiene dos capacidades y una clasificación derivada', () => {
    assert.deepEqual(CAPACIDADES.map((c) => c.clave), ['productor', 'comprador', 'transportista']);
    const comprador = CAPACIDADES.find((c) => c.clave === 'comprador');
    const productor = CAPACIDADES.find((c) => c.clave === 'productor');
    const transportista = CAPACIDADES.find((c) => c.clave === 'transportista');

    assert.equal(comprador.derivada, true, 'Comprador debe quedar marcado como clasificación derivada');
    assert.equal(productor.derivada, false);
    assert.equal(transportista.derivada, false);
});

test('Productor no es alias de Vendedor', () => {
    const productor = CAPACIDADES.find((c) => c.clave === 'productor');
    assert.equal(productor.alias, null);
    assert.equal(CAPACIDADES.some((c) => c.alias === 'vendedor'), false,
        'VENDEDOR es clasificación del Productor, no alias ni entidad propia');
});

test('cada lectura apunta a su API y a su panel', () => {
    for (const capacidad of CAPACIDADES) {
        assert.match(capacidad.api, /^api\/[a-z]+\.php$/, `${capacidad.clave}: API mal formada`);
        assert.match(capacidad.panel, /^[a-z]+\.php$/, `${capacidad.clave}: panel mal formado`);
    }
});

test('consulta las tres lecturas y conserva la semántica derivada de comprador', async () => {
    const urls = [];
    const resultado = await consultarCapacidades('1-1111-1111', {
        requestImpl: async (url) => {
            urls.push(url);
            if (url.includes('productores')) return { data: { estado: 'ACTIVO' } };
            if (url.includes('compradores')) throw fallo(404);
            return { data: { estado: 'INACTIVO' } };
        },
    });

    assert.equal(urls.length, 3, 'cada lectura se consulta una sola vez');
    assert.deepEqual(
        resultado.map((c) => [c.clave, c.situacion, c.estado, c.derivada]),
        [
            ['productor', 'registrado', 'ACTIVO', false],
            ['comprador', 'no-registrado', null, true],
            ['transportista', 'registrado', 'INACTIVO', false],
        ],
    );
    assert.equal(describirCapacidad(resultado[1]), 'Sin clasificación vigente');
});

test('una consulta que falla no arrastra a las demás', async () => {
    const resultado = await consultarCapacidades('1-1111-1111', {
        requestImpl: async (url) => {
            if (url.includes('transportistas')) throw fallo(null);
            return { data: { estado: 'ACTIVO' } };
        },
    });

    assert.equal(resultado.length, 3);
    assert.equal(resultado.filter((c) => c.situacion === 'registrado').length, 2);
    assert.equal(resultado.find((c) => c.clave === 'transportista').situacion, 'desconocido');
    assert.equal(describirCapacidad(resultado.find((c) => c.clave === 'comprador')), 'Clasificado actualmente');
});

test('la identificación viaja escapada en la URL', async () => {
    const urls = [];
    await consultarCapacidades('AB 123/45&x=1', {
        requestImpl: async (url) => { urls.push(url); return { data: { estado: 'ACTIVO' } }; },
    });

    for (const url of urls) {
        assert.ok(!url.includes('&x=1'), `parametro sin escapar: ${url}`);
        assert.ok(url.includes('AB%20123%2F45%26x%3D1'), `identificacion mal escapada: ${url}`);
    }
});

test('cada resultado conserva la identificación consultada', async () => {
    const resultado = await consultarCapacidades('7-0777-0777', {
        requestImpl: async () => { throw fallo(404); },
    });

    for (const capacidad of resultado) {
        assert.equal(capacidad.identificacionNumero, '7-0777-0777');
    }
});
