// Capacidades de una persona: productor (vendedor), comprador, transportista.
//
// La propiedad que importa aqui es la misma que separa "lista vacia" de "fallo
// al cargar": un 404 afirma que la persona no tiene esa capacidad, mientras que
// un fallo de red no afirma nada. Confundirlos haria que un corte de red se
// mostrara como "No registrado", que es una afirmacion falsa sobre los datos.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    CAPACIDADES, consultarCapacidades, describirCapacidad, interpretarCapacidad,
} from '../../Public/js/shared/capacidades.js';

const fallo = (status) => Object.assign(new Error('fallo'), { status });

test('un 404 significa que la persona no tiene esa capacidad', () => {
    assert.deepEqual(
        interpretarCapacidad({ ok: false, error: fallo(404) }),
        { situacion: 'no-registrado', estado: null },
    );
});

test('un fallo de red no permite concluir nada, y no es "no registrado"', () => {
    // Este es el defecto que la prueba impide: sin distinguirlos, un corte de
    // red pintaria "No registrado" sobre una persona que si es comprador.
    const red = interpretarCapacidad({ ok: false, error: fallo(null) });
    assert.equal(red.situacion, 'desconocido');
    assert.notEqual(red.situacion, 'no-registrado');
});

test('un 500 tampoco es "no registrado"', () => {
    assert.equal(interpretarCapacidad({ ok: false, error: fallo(500) }).situacion, 'desconocido');
});

test('un 200 distingue la capacidad activa de la inactiva', () => {
    assert.deepEqual(
        interpretarCapacidad({ ok: true, data: { estado: 'ACTIVO' } }),
        { situacion: 'registrado', estado: 'ACTIVO' },
    );
    assert.deepEqual(
        interpretarCapacidad({ ok: true, data: { estado: 'INACTIVO' } }),
        { situacion: 'registrado', estado: 'INACTIVO' },
    );
});

test('una respuesta sin estado se trata como inactiva, nunca como activa', () => {
    // Ante un dato ausente se elige la lectura que no concede una capacidad
    // que no se pudo confirmar.
    assert.equal(interpretarCapacidad({ ok: true, data: {} }).estado, 'INACTIVO');
    assert.equal(interpretarCapacidad({ ok: true, data: undefined }).estado, 'INACTIVO');
});

test('las tres situaciones se describen con textos distintos', () => {
    const textos = [
        describirCapacidad({ situacion: 'registrado', estado: 'ACTIVO' }),
        describirCapacidad({ situacion: 'registrado', estado: 'INACTIVO' }),
        describirCapacidad({ situacion: 'no-registrado', estado: null }),
        describirCapacidad({ situacion: 'desconocido', estado: null }),
    ];
    assert.equal(new Set(textos).size, 4, 'dos situaciones distintas no pueden leerse igual');
    assert.match(textos[3], /no se pudo/i);
});

test('el catalogo cubre las tres capacidades del modelo y ninguna mas', () => {
    // DEC-PER-001: no hay tabla de roles ni `tbvendedor`.
    assert.deepEqual(CAPACIDADES.map((c) => c.clave), ['productor', 'comprador', 'transportista']);
});

test('productor lleva el alias vendedor porque no existe tbvendedor', () => {
    const productor = CAPACIDADES.find((c) => c.clave === 'productor');
    assert.equal(productor.alias, 'vendedor');
    assert.equal(CAPACIDADES.filter((c) => c.alias === 'vendedor').length, 1);
});

test('cada capacidad apunta a su API y a su panel', () => {
    for (const capacidad of CAPACIDADES) {
        assert.match(capacidad.api, /^api\/[a-z]+\.php$/, `${capacidad.clave}: API mal formada`);
        assert.match(capacidad.panel, /^[a-z]+\.php$/, `${capacidad.clave}: panel mal formado`);
    }
});

test('consulta las tres capacidades y devuelve una situacion por cada una', async () => {
    const urls = [];
    const resultado = await consultarCapacidades('1-1111-1111', {
        requestImpl: async (url) => {
            urls.push(url);
            if (url.includes('productores')) return { data: { estado: 'ACTIVO' } };
            if (url.includes('compradores')) throw fallo(404);
            return { data: { estado: 'INACTIVO' } };
        },
    });

    assert.equal(urls.length, 3, 'debe consultarse cada capacidad una sola vez');
    assert.deepEqual(
        resultado.map((c) => [c.clave, c.situacion, c.estado]),
        [
            ['productor', 'registrado', 'ACTIVO'],
            ['comprador', 'no-registrado', null],
            ['transportista', 'registrado', 'INACTIVO'],
        ],
    );
});

test('una capacidad que falla no arrastra a las demas', async () => {
    // Con Promise.all sobre promesas sin capturar, un rechazo perderia las tres.
    const resultado = await consultarCapacidades('1-1111-1111', {
        requestImpl: async (url) => {
            if (url.includes('transportistas')) throw fallo(null);
            return { data: { estado: 'ACTIVO' } };
        },
    });

    assert.equal(resultado.length, 3);
    assert.equal(resultado.filter((c) => c.situacion === 'registrado').length, 2);
    assert.equal(resultado.find((c) => c.clave === 'transportista').situacion, 'desconocido');
});

test('la identificacion viaja escapada en la URL', async () => {
    const urls = [];
    await consultarCapacidades('AB 123/45&x=1', {
        requestImpl: async (url) => { urls.push(url); return { data: { estado: 'ACTIVO' } }; },
    });

    for (const url of urls) {
        // Sin escapar, "&x=1" se leeria como otro parametro de la consulta.
        assert.ok(!url.includes('&x=1'), `parametro sin escapar: ${url}`);
        assert.ok(url.includes('AB%20123%2F45%26x%3D1'), `identificacion mal escapada: ${url}`);
    }
});

test('cada resultado conserva la identificacion consultada', async () => {
    const resultado = await consultarCapacidades('7-0777-0777', {
        requestImpl: async () => { throw fallo(404); },
    });

    for (const capacidad of resultado) {
        assert.equal(capacidad.identificacionNumero, '7-0777-0777');
    }
});
