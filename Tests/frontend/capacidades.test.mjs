// Relaciones de una persona: Productor, Comprador y Transportista.
// La prueba distingue ausencia comprobada de fallo de consulta y garantiza que
// Comprador se trate como contexto de Persona, no como clasificación de Productor.

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

test('un fallo de red o 500 no permite concluir ausencia', () => {
    assert.equal(interpretarCapacidad({ ok: false, error: fallo(null) }).situacion, 'desconocido');
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

test('los contextos usan etiquetas de registro', () => {
    assert.equal(describirCapacidad({ situacion: 'registrado', estado: 'ACTIVO' }), 'Registrado y activo');
    assert.equal(describirCapacidad({ situacion: 'registrado', estado: 'INACTIVO' }), 'Registrado, inactivo');
    assert.equal(describirCapacidad({ situacion: 'no-registrado', estado: null }), 'No registrado');
    assert.match(describirCapacidad({ situacion: 'desconocido', estado: null }), /no se pudo/i);
});

test('el catálogo contiene tres contextos de Persona y Comprador no es derivado', () => {
    assert.deepEqual(CAPACIDADES.map((c) => c.clave), ['productor', 'comprador', 'transportista']);
    for (const capacidad of CAPACIDADES) {
        assert.equal(capacidad.derivada, false, `${capacidad.clave} no debe presentarse como clasificación derivada`);
    }
});

test('Productor no es alias de Vendedor', () => {
    const productor = CAPACIDADES.find((c) => c.clave === 'productor');
    assert.equal(productor.alias, null);
    assert.equal(CAPACIDADES.some((c) => c.alias === 'vendedor'), false);
});

test('cada lectura apunta a su API y a su panel', () => {
    for (const capacidad of CAPACIDADES) {
        assert.match(capacidad.api, /^api\/[a-z]+\.php$/, `${capacidad.clave}: API mal formada`);
        assert.match(capacidad.panel, /^[a-z]+\.php$/, `${capacidad.clave}: panel mal formado`);
    }
});

test('consulta los tres contextos y conserva ausencia de Comprador', async () => {
    const urls = [];
    const resultado = await consultarCapacidades('1-1111-1111', {
        requestImpl: async (url) => {
            urls.push(url);
            if (url.includes('productores')) return { data: { estado: 'ACTIVO' } };
            if (url.includes('compradores')) throw fallo(404);
            return { data: { estado: 'INACTIVO' } };
        },
    });

    assert.equal(urls.length, 3);
    assert.deepEqual(
        resultado.map((c) => [c.clave, c.situacion, c.estado, c.derivada]),
        [
            ['productor', 'registrado', 'ACTIVO', false],
            ['comprador', 'no-registrado', null, false],
            ['transportista', 'registrado', 'INACTIVO', false],
        ],
    );
    assert.equal(describirCapacidad(resultado[1]), 'No registrado');
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
    assert.equal(describirCapacidad(resultado.find((c) => c.clave === 'comprador')), 'Registrado y activo');
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
