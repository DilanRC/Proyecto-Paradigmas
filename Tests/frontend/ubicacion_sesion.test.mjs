// Tests de ubicacion-sesion.js: orquestador una captura por sesión.

import assert from 'node:assert/strict';
import test from 'node:test';

import { capturarEnInicioDeSesion } from '../../Public/js/shared/ubicacion-sesion.js';

function crearStorage(data = {}) {
    const mapa = new Map(Object.entries(data));
    return {
        getItem(k) { return mapa.has(k) ? mapa.get(k) : null; },
        setItem(k, v) { mapa.set(k, String(v)); },
        removeItem(k) { mapa.delete(k); },
    };
}

function sesionLogin(startedAt = '2026-01-01T12:00:00.000Z', email = 'test@example.com') {
    return JSON.stringify({ authenticated: true, version: 1, email, startedAt, mode: 'local-browser-session' });
}

const geoExito = async () => ({ latitud: '9.9280694', longitud: '-84.0907246', precisionMetros: 12.5, origen: 'NAVEGADOR' });
const geoDenegado = async () => { const e = new Error('denegado'); e.kind = 'denied'; throw e; };
const geoNoDisponible = async () => { const e = new Error('unavailable'); e.kind = 'unavailable'; throw e; };

test('primera invocación → captura + un POST', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 1);
    const marcador = JSON.parse(storage.getItem('tindercows:ubicacion-sesion'));
    assert.equal(marcador.estado, 'capturada');
});

test('misma sesión (mismo startedAt) → sin segundo POST', async () => {
    const startedAt = '2026-01-01T12:00:00.000Z';
    const storage = crearStorage({
        'tindercows:login': sesionLogin(startedAt),
        'tindercows:ubicacion-sesion': JSON.stringify({ estado: 'capturada', startedAt, en: new Date().toISOString() }),
    });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 0);
});

test('nueva sesión (distinto startedAt) → POST nuevo → suma una fila', async () => {
    const storage = crearStorage({
        'tindercows:login': sesionLogin('2026-02-01T12:00:00.000Z'),
        'tindercows:ubicacion-sesion': JSON.stringify({ estado: 'capturada', startedAt: '2026-01-01T12:00:00.000Z' }),
    });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 1);
    const marcador = JSON.parse(storage.getItem('tindercows:ubicacion-sesion'));
    assert.equal(marcador.startedAt, '2026-02-01T12:00:00.000Z');
    assert.equal(marcador.estado, 'capturada');
});

test('resolución null (sin perfil productor) → cero POSTs, cero filas', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => null,
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 0);
    const marcador = JSON.parse(storage.getItem('tindercows:ubicacion-sesion'));
    assert.equal(marcador.estado, 'omitida');
});

test('permiso denegado → marca, no reintenta esa sesión, cero filas', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoDenegado,
    });

    assert.equal(posts, 0);
    const marcador = JSON.parse(storage.getItem('tindercows:ubicacion-sesion'));
    assert.equal(marcador.estado, 'omitida');
});

test('ubicación no disponible → sin marcar (reintenta en próxima carga)', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoNoDisponible,
    });

    assert.equal(posts, 0);
    assert.equal(storage.getItem('tindercows:ubicacion-sesion'), null);
});

test('fallo de red en resolverIdentificacion → sin marcar (reintenta en próxima carga)', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => { throw new Error('network error'); },
        requestFn: async () => { posts++; return { success: true }; },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 0);
    assert.equal(storage.getItem('tindercows:ubicacion-sesion'), null);
});

test('fallo retryable en POST (500) → sin marcar', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => {
            posts++;
            const err = new Error('Internal error');
            err.retryable = true;
            throw err;
        },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 1);
    assert.equal(storage.getItem('tindercows:ubicacion-sesion'), null);
});

test('fallo definitivo en POST (422) → marca omitida', async () => {
    const storage = crearStorage({ 'tindercows:login': sesionLogin() });
    let posts = 0;

    await capturarEnInicioDeSesion({
        storage,
        resolverIdentificacion: async () => ({ identificacionNumero: '12345', productorId: 1 }),
        requestFn: async () => {
            posts++;
            const err = new Error('Validation error');
            err.retryable = false;
            throw err;
        },
        esSoportadoFn: () => true,
        capturarFn: geoExito,
    });

    assert.equal(posts, 1);
    const marcador = JSON.parse(storage.getItem('tindercows:ubicacion-sesion'));
    assert.equal(marcador.estado, 'omitida');
});

test('sin storage → no hace nada', async () => {
    let posts = 0;
    await capturarEnInicioDeSesion({
        storage: null,
        requestFn: async () => { posts++; return { success: true }; },
    });
    assert.equal(posts, 0);
});

test('sin login en storage → no hace nada', async () => {
    const storage = crearStorage({});
    let posts = 0;
    await capturarEnInicioDeSesion({
        storage,
        requestFn: async () => { posts++; return { success: true }; },
    });
    assert.equal(posts, 0);
    assert.equal(storage.getItem('tindercows:ubicacion-sesion'), null);
});
