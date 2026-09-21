import assert from 'node:assert/strict';
import test from 'node:test';
import { esSoportado, capturar } from '../../Public/js/shared/geo.js';

function mockNavigator(geoMock) {
    Object.defineProperty(globalThis, 'navigator', { value: { geolocation: geoMock }, writable: true, configurable: true });
}
function restoreNavigator() {
    Object.defineProperty(globalThis, 'navigator', { value: undefined, writable: true, configurable: true });
}

test('esSoportado es false sin navigator', () => assert.equal(esSoportado(), false));

test('capturar rechaza unsupported sin geolocation', async () => {
    await assert.rejects(() => capturar(), (error) => error.kind === 'unsupported');
});

test('capturar normaliza coordenadas y no inventa fecha', async () => {
    mockNavigator({ getCurrentPosition(success) { success({ coords: { latitude: 9.9280694123, longitude: -84.0907246789, accuracy: 12.567 } }); } });
    try {
        const result = await capturar();
        assert.equal(result.latitud, '9.9280694');
        assert.equal(result.longitud, '-84.0907247');
        assert.equal(result.precisionMetros, 12.57);
        assert.equal(result.origen, 'NAVEGADOR');
        assert.equal('fecha' in result, false);
    } finally { restoreNavigator(); }
});

test('capturar no pide alta precision por defecto', async () => {
    let options;
    mockNavigator({ getCurrentPosition(success, error, received) { options = received; success({ coords: { latitude: 9, longitude: -84, accuracy: 20 } }); } });
    try {
        await capturar();
        assert.equal(options.enableHighAccuracy, false);
        assert.equal(options.maximumAge, 0);
    } finally { restoreNavigator(); }
});

test('capturar permite alta precision cuando el flujo la justifica explicitamente', async () => {
    let options;
    mockNavigator({ getCurrentPosition(success, error, received) { options = received; success({ coords: { latitude: 9, longitude: -84, accuracy: 5 } }); } });
    try {
        await capturar({ altaPrecision: true });
        assert.equal(options.enableHighAccuracy, true);
    } finally { restoreNavigator(); }
});

for (const [code, kind] of [[1, 'denied'], [2, 'unavailable'], [3, 'timeout']]) {
    test(`capturar traduce codigo ${code} a ${kind}`, async () => {
        mockNavigator({ getCurrentPosition(success, error) { error({ code }); } });
        try { await assert.rejects(() => capturar(), (err) => err.kind === kind); }
        finally { restoreNavigator(); }
    });
}
