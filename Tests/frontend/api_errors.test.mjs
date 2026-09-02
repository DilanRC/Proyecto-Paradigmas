// Taxonomia de fallos: HTTP y transporte deben ser distinguibles por estructura.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    describeHttpFailure, describeNetworkFailure, httpKind, request,
} from '../../Public/js/shared/api.js';

/** fetch de mentira con la forma minima que usa request(). */
const fakeFetch = (status, payload, { ok = status < 400 } = {}) => async () => ({
    ok,
    status,
    json: async () => payload,
});

test('un fallo HTTP y uno de red no se confunden estructuralmente', () => {
    const http = describeHttpFailure(500, { message: 'Falla interna.' });
    const red = describeNetworkFailure(new TypeError('Failed to fetch'));

    assert.equal(http.type, 'http');
    assert.equal(typeof http.status, 'number');

    assert.equal(red.type, 'network');
    assert.equal(red.status, null, 'sin respuesta no puede haber status');

    // El defecto original: "Failed to fetch" llegaba tal cual al usuario.
    assert.ok(!red.message.includes('Failed to fetch'));
    assert.match(red.message, /No fue posible comunicarse/);
});

test('cada codigo se clasifica de forma estable', () => {
    assert.equal(httpKind(400), 'bad-request');
    assert.equal(httpKind(404), 'not-found');
    assert.equal(httpKind(405), 'method');
    assert.equal(httpKind(409), 'conflict');
    assert.equal(httpKind(415), 'unsupported-media');
    assert.equal(httpKind(422), 'validation');
    assert.equal(httpKind(500), 'server');
    assert.equal(httpKind(503), 'server');
});

test('solo se ofrece reintento cuando repetir puede cambiar el resultado', () => {
    assert.equal(describeHttpFailure(500, {}).retryable, true);
    assert.equal(describeNetworkFailure(new Error('x')).retryable, true);
    assert.equal(describeHttpFailure(422, {}).retryable, false);
    assert.equal(describeHttpFailure(404, {}).retryable, false);
    assert.equal(describeHttpFailure(409, {}).retryable, false);
});

test('un 422 conserva los errores por campo', async () => {
    const payload = { success: false, message: 'Revise los campos indicados.', errors: { telefono: 'No es valido' } };
    await assert.rejects(
        () => request('/api/x.php', {}, { fetchImpl: fakeFetch(422, payload) }),
        (error) => {
            assert.equal(error.type, 'http');
            assert.equal(error.status, 422);
            assert.equal(error.kind, 'validation');
            assert.deepEqual(error.errors, { telefono: 'No es valido' });
            return true;
        },
    );
});

test('REGRESION: un 409 conserva data.reactivacion para poder reactivar', async () => {
    // productores y transportistas leen data.reactivacion.identificacionNumero
    // para ofrecer el boton de reactivar. Si la taxonomia perdiera `data`, ese
    // flujo se romperia en silencio.
    const payload = {
        success: false,
        message: 'La identificacion pertenece a un productor inactivo.',
        data: { reactivacion: { identificacionNumero: '111111111' } },
        errors: { 'identificacion.numero': 'Debe reactivarse el productor existente.' },
    };
    await assert.rejects(
        () => request('/api/productores.php', { method: 'POST', body: '{}' }, { fetchImpl: fakeFetch(409, payload) }),
        (error) => {
            assert.equal(error.status, 409);
            assert.equal(error.kind, 'conflict');
            assert.equal(error.data.reactivacion.identificacionNumero, '111111111');
            return true;
        },
    );
});

test('la cancelacion se propaga tal cual y no se convierte en fallo de red', async () => {
    const abortar = async () => { throw Object.assign(new Error('abortada'), { name: 'AbortError' }); };
    await assert.rejects(
        () => request('/api/x.php', {}, { fetchImpl: abortar }),
        (error) => {
            assert.equal(error.name, 'AbortError');
            assert.equal(error.type, undefined, 'un abort no es un fallo clasificado');
            return true;
        },
    );
});

test('una respuesta que no es JSON no se presenta como fallo de red', async () => {
    const noJson = async () => ({ ok: true, status: 200, json: async () => { throw new SyntaxError('x'); } });
    await assert.rejects(
        () => request('/api/x.php', {}, { fetchImpl: noJson }),
        (error) => {
            assert.equal(error.type, 'http');
            assert.equal(error.kind, 'invalid-response');
            return true;
        },
    );
});

test('success:false se trata como fallo aunque el codigo sea 200', async () => {
    const payload = { success: false, message: 'No fue posible completar la solicitud.' };
    await assert.rejects(
        () => request('/api/x.php', {}, { fetchImpl: fakeFetch(200, payload, { ok: true }) }),
        (error) => error.message === 'No fue posible completar la solicitud.',
    );
});

test('una respuesta correcta devuelve el cuerpo entero', async () => {
    const payload = { success: true, message: 'ok', data: { total: 2 } };
    const result = await request('/api/x.php', {}, { fetchImpl: fakeFetch(200, payload) });
    assert.deepEqual(result, payload);
});

test('el cuerpo JSON viaja siempre con su Content-Type', async () => {
    let visto = null;
    const espia = async (_url, options) => { visto = options; return { ok: true, status: 200, json: async () => ({ success: true }) }; };
    await request('/api/x.php', { method: 'POST', body: '{"a":1}' }, { fetchImpl: espia });

    assert.equal(visto.headers['Content-Type'], 'application/json');
    assert.equal(visto.headers.Accept, 'application/json');
});
