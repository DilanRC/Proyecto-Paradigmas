import assert from 'node:assert/strict';
import test from 'node:test';

import {
    capturarUbicacionAutomatica,
    leerUbicacionUsuario,
    UBICACION_USUARIO_KEY,
} from '../../Public/js/shared/ubicacion-sesion.js';

function crearStorage(data = {}) {
    const mapa = new Map(Object.entries(data));
    return {
        getItem(k) { return mapa.has(k) ? mapa.get(k) : null; },
        setItem(k, v) { mapa.set(k, String(v)); },
        removeItem(k) { mapa.delete(k); },
    };
}

const ubicacion = async () => ({
    latitud: '9.9280694',
    longitud: '-84.0907246',
    precisionMetros: 22.5,
    origen: 'NAVEGADOR',
});

test('captura automaticamente y conserva la ubicacion solo en session storage', async () => {
    const storage = crearStorage();
    const ahora = Date.parse('2026-09-14T17:00:00.000Z');
    let capturas = 0;
    const resultado = await capturarUbicacionAutomatica({
        storage,
        ahoraFn: () => ahora,
        esSoportadoFn: () => true,
        capturarFn: async () => { capturas += 1; return ubicacion(); },
    });
    assert.equal(capturas, 1);
    assert.equal(resultado.reutilizada, false);
    assert.equal(resultado.ubicacion.latitud, '9.9280694');
    assert.equal(resultado.ubicacion.longitud, '-84.0907246');
    const guardada = JSON.parse(storage.getItem(UBICACION_USUARIO_KEY));
    assert.equal(guardada.origen, 'NAVEGADOR');
    assert.equal(guardada.capturadaEn, '2026-09-14T17:00:00.000Z');
});

test('reutiliza una ubicacion fresca sin solicitar GPS otra vez', async () => {
    const ahora = Date.parse('2026-09-14T17:05:00.000Z');
    const storage = crearStorage({
        [UBICACION_USUARIO_KEY]: JSON.stringify({
            latitud: '9.9280694', longitud: '-84.0907246', precisionMetros: 25,
            origen: 'NAVEGADOR', capturadaEn: '2026-09-14T17:00:00.000Z',
        }),
    });
    let capturas = 0;
    const resultado = await capturarUbicacionAutomatica({
        storage,
        ahoraFn: () => ahora,
        esSoportadoFn: () => true,
        capturarFn: async () => { capturas += 1; return ubicacion(); },
    });
    assert.equal(capturas, 0);
    assert.equal(resultado.reutilizada, true);
});

test('una ubicacion vencida se renueva automaticamente', async () => {
    const ahora = Date.parse('2026-09-14T18:00:00.000Z');
    const storage = crearStorage({
        [UBICACION_USUARIO_KEY]: JSON.stringify({
            latitud: '9.9', longitud: '-84.1', precisionMetros: 30,
            origen: 'NAVEGADOR', capturadaEn: '2026-09-14T17:00:00.000Z',
        }),
    });
    let capturas = 0;
    const resultado = await capturarUbicacionAutomatica({
        storage,
        ahoraFn: () => ahora,
        maxEdadMs: 15 * 60 * 1000,
        esSoportadoFn: () => true,
        capturarFn: async () => { capturas += 1; return ubicacion(); },
    });
    assert.equal(capturas, 1);
    assert.equal(resultado.reutilizada, false);
});

test('leerUbicacionUsuario descarta datos invalidos o vencidos', () => {
    const ahora = Date.parse('2026-09-14T18:00:00.000Z');
    assert.equal(leerUbicacionUsuario(crearStorage({
        [UBICACION_USUARIO_KEY]: '{json roto',
    }), { ahoraFn: () => ahora }), null);
    assert.equal(leerUbicacionUsuario(crearStorage({
        [UBICACION_USUARIO_KEY]: JSON.stringify({
            latitud: 200, longitud: -84, capturadaEn: '2026-09-14T17:59:00.000Z',
        }),
    }), { ahoraFn: () => ahora }), null);
});

test('navegador sin geolocalizacion rechaza sin inventar ubicacion', async () => {
    const storage = crearStorage();
    await assert.rejects(
        () => capturarUbicacionAutomatica({ storage, esSoportadoFn: () => false }),
        (error) => error.kind === 'unsupported',
    );
    assert.equal(storage.getItem(UBICACION_USUARIO_KEY), null);
});

test('dos inicializaciones simultaneas comparten una sola captura', async () => {
    const storage = crearStorage();
    let resolver;
    let capturas = 0;
    const promesa = new Promise((resolve) => { resolver = resolve; });
    const opciones = {
        storage,
        esSoportadoFn: () => true,
        capturarFn: async () => { capturas += 1; return promesa; },
    };
    const primera = capturarUbicacionAutomatica(opciones);
    const segunda = capturarUbicacionAutomatica(opciones);
    assert.equal(capturas, 1);
    resolver(await ubicacion());
    const [a, b] = await Promise.all([primera, segunda]);
    assert.equal(a.ubicacion.latitud, b.ubicacion.latitud);
    assert.equal(capturas, 1);
});
