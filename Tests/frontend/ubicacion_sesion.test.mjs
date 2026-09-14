import assert from 'node:assert/strict';
import test from 'node:test';
import {
    evaluarPrecision,
    registrarUbicacionObservada,
    solicitarUbicacionNavegador,
    validarCoordenadasManual,
} from '../../Public/js/shared/ubicacion-sesion.js';

test('coordenadas manuales validas se normalizan y quedan como MANUAL', () => {
    const result = validarCoordenadasManual({ latitud: '9.9280694', longitud: '-84.0907246' });
    assert.equal(result.ok, true);
    assert.deepEqual(result.data, {
        latitud: '9.9280694', longitud: '-84.0907246', precisionMetros: null, origen: 'MANUAL',
    });
});

test('coordenadas manuales fuera de rango no se aceptan', () => {
    const result = validarCoordenadasManual({ latitud: '91', longitud: '-181' });
    assert.equal(result.ok, false);
    assert.ok(result.errors.latitud);
    assert.ok(result.errors.longitud);
});

test('precision baja genera aviso, no rechazo de negocio', () => {
    assert.equal(evaluarPrecision(250).nivel, 'baja');
    assert.equal(evaluarPrecision(25).nivel, 'normal');
    assert.equal(evaluarPrecision(null).nivel, 'desconocida');
});

test('GPS solo se invoca cuando el consumidor llama solicitarUbicacionNavegador', async () => {
    let calls = 0;
    const result = await solicitarUbicacionNavegador({
        esSoportadoFn: () => true,
        capturarFn: async (options) => { calls++; assert.equal(options.altaPrecision, false); return { latitud: '9', longitud: '-84', precisionMetros: 20, origen: 'NAVEGADOR' }; },
    });
    assert.equal(calls, 1);
    assert.equal(result.origen, 'NAVEGADOR');
});

test('navegador sin soporte produce unsupported antes de capturar', async () => {
    let calls = 0;
    await assert.rejects(() => solicitarUbicacionNavegador({
        esSoportadoFn: () => false,
        capturarFn: async () => { calls++; },
    }), (error) => error.kind === 'unsupported');
    assert.equal(calls, 0);
});

test('registrarUbicacionObservada usa JSON y el endpoint existente', async () => {
    let received;
    await registrarUbicacionObservada({
        productorId: 7,
        ubicacion: { latitud: '9.1', longitud: '-84.2', precisionMetros: 30, origen: 'NAVEGADOR' },
        requestFn: async (url, options) => { received = { url, options }; return { success: true }; },
    });
    assert.equal(received.url, 'api/productores-ubicacion.php');
    assert.equal(received.options.method, 'POST');
    assert.deepEqual(JSON.parse(received.options.body), {
        productorId: 7, latitud: '9.1', longitud: '-84.2', precisionMetros: 30, origen: 'NAVEGADOR',
    });
});
