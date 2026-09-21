// Superficie del navegador (Tramo A): identidad.php distingue autenticado de
// público sin inventar login propio (DEC-30/DEC-33).

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ACTOR_KEY, BEARER_KEY, flujoLogin, guardarActor, limpiarActor,
    leerActor, leerBearer, resolverSuperficie,
} from '../../Public/js/shared/sesion.js';

const personaPublica = {
    data: {
        esProductor: false, esComprador: false, esTransportista: false,
        productorId: null, compradorId: null, transportistaId: null,
        identificacionNumero: null, persona: null,
    },
};

const personaAutenticada = {
    data: {
        esProductor: true, esComprador: false, esTransportista: false,
        productorId: 7, compradorId: null, transportistaId: null,
        identificacionNumero: '1-1111-1111',
        persona: { tbpersonaid: 1 },
    },
};

const falloUnauthorized = Object.assign(new Error('Debe iniciar sesión'), { status: 401 });

test('sin bearer el modo es público: no se fabrica una sesión', async () => {
    const resuelto = await resolverSuperficie({ requestImpl: async () => personaPublica });
    assert.equal(resuelto.autenticado, false);
    assert.equal(resuelto.motivo, 'PUBLICO');
    assert.equal(resuelto.actor, null);
});

test('un 401 SIN_SESION no se confunde con un fallo de red', async () => {
    const sinSesion = await resolverSuperficie({ requestImpl: async () => { throw falloUnauthorized; } });
    assert.equal(sinSesion.autenticado, false);
    assert.equal(sinSesion.motivo, 'SIN_SESION');

    const red = await resolverSuperficie({ requestImpl: async () => { throw new TypeError('Failed to fetch'); } });
    assert.equal(red.autenticado, false);
    assert.equal(red.motivo, 'NO_DISPONIBLE');
});

test('con persona el navegador queda en superficie autenticada', async () => {
    let autorizacionEnviada = null;
    const resuelto = await resolverSuperficie({
        bearer: 'token-supabase-1',
        requestImpl: async (url, options) => {
            assert.equal(url, 'api/identidad.php');
            autorizacionEnviada = options?.headers?.Authorization ?? null;
            return personaAutenticada;
        },
    });
    assert.equal(autorizacionEnviada, 'Bearer token-supabase-1');
    assert.equal(resuelto.autenticado, true);
    assert.equal(resuelto.actor.esProductor, true);
    assert.equal(resuelto.actor.identificacionNumero, '1-1111-1111');
});

test('flujoLogin conserva actor y bearer solo cuando el proveedor devolvió persona', async () => {
    const storage = new Map();
    const fake = { getItem: (k) => (storage.has(k) ? storage.get(k) : null), setItem: (k, v) => storage.set(k, v), removeItem: (k) => storage.delete(k) };

    await flujoLogin({ email: 'a@b.test', storage: fake, requestImpl: async () => personaPublica });
    assert.equal(leerActor(fake), null, 'el modo público no deja actor');

    storage.set(BEARER_KEY, 'token-supabase-2');
    await flujoLogin({ email: 'a@b.test', storage: fake, requestImpl: async () => personaAutenticada });
    assert.equal(leerActor(fake)?.identificacionNumero, '1-1111-1111');
    assert.equal(leerBearer(fake), 'token-supabase-2');
});

test('guardarActor / limpiarActor y lecturas toleran storage ausente', () => {
    assert.equal(leerActor(null), null);
    assert.equal(leerBearer(null), null);
    guardarActor(null, personaAutenticada.data, 'x');
    limpiarActor(null);
    assert.equal(ACTOR_KEY.length > 0, true);
    assert.equal(BEARER_KEY.length > 0, true);
});