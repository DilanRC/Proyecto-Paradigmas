// Tests de geo.js: captura de ubicación del navegador.

import assert from 'node:assert/strict';
import test from 'node:test';

import { esSoportado, capturar } from '../../Public/js/shared/geo.js';

function mockNavigator(geoMock) {
    Object.defineProperty(globalThis, 'navigator', {
        value: { geolocation: geoMock },
        writable: true,
        configurable: true,
    });
}

function restoreNavigator() {
    Object.defineProperty(globalThis, 'navigator', {
        value: undefined,
        writable: true,
        configurable: true,
    });
}

test('esSoportado() es false en Node (sin navigator)', () => {
    assert.equal(esSoportado(), false);
});

test('capturar() rechaza con kind unsupported cuando no hay geolocation', async () => {
    await assert.rejects(
        () => capturar(),
        (error) => {
            assert.equal(error.kind, 'unsupported');
            assert.ok(error.message.includes('geolocalización'));
            return true;
        },
    );
});

test('capturar() resuelve con payload normalizado cuando getCurrentPosition tiene éxito', async () => {
    mockNavigator({
        getCurrentPosition(success) {
            success({
                coords: {
                    latitude: 9.9280694,
                    longitude: -84.0907246,
                    accuracy: 12.5,
                },
            });
        },
    });

    try {
        const resultado = await capturar();
        assert.equal(resultado.origen, 'NAVEGADOR');
        assert.equal(typeof resultado.latitud, 'string');
        assert.equal(typeof resultado.longitud, 'string');
        assert.equal(typeof resultado.precisionMetros, 'number');
        assert.ok(!resultado.fecha, 'no debe incluir fecha');
    } finally {
        restoreNavigator();
    }
});

test('capturar() redondea latitud y longitud a 7 decimales', async () => {
    mockNavigator({
        getCurrentPosition(success) {
            success({
                coords: {
                    latitude: 9.9280694123456,
                    longitude: -84.090724678901,
                    accuracy: 12.567,
                },
            });
        },
    });

    try {
        const resultado = await capturar();
        assert.equal(resultado.latitud.split('.')[1].length, 7);
        assert.equal(resultado.longitud.split('.')[1].length, 7);
        assert.equal(resultado.precisionMetros, 12.57);
    } finally {
        restoreNavigator();
    }
});

test('capturar() rechaza con kind denied cuando el usuario deniega permiso (código 1)', async () => {
    mockNavigator({
        getCurrentPosition(_, error) {
            error({ code: 1, message: 'User denied Geolocation' });
        },
    });

    try {
        await assert.rejects(
            () => capturar(),
            (error) => {
                assert.equal(error.kind, 'denied');
                assert.ok(error.message.includes('denegado'));
                return true;
            },
        );
    } finally {
        restoreNavigator();
    }
});

test('capturar() rechaza con kind unavailable cuando la posición no está disponible (código 2)', async () => {
    mockNavigator({
        getCurrentPosition(_, error) {
            error({ code: 2, message: 'Position unavailable' });
        },
    });

    try {
        await assert.rejects(
            () => capturar(),
            (error) => {
                assert.equal(error.kind, 'unavailable');
                return true;
            },
        );
    } finally {
        restoreNavigator();
    }
});

test('capturar() rechaza con kind timeout cuando la solicitud expira (código 3)', async () => {
    mockNavigator({
        getCurrentPosition(_, error) {
            error({ code: 3, message: 'Position timeout' });
        },
    });

    try {
        await assert.rejects(
            () => capturar(),
            (error) => {
                assert.equal(error.kind, 'timeout');
                assert.ok(error.message.includes('demasiado'));
                return true;
            },
        );
    } finally {
        restoreNavigator();
    }
});

test('capturar() usa enableHighAccuracy por defecto', async () => {
    let opcionesCapturadas = null;
    mockNavigator({
        getCurrentPosition(success, error, options) {
            opcionesCapturadas = options;
            success({ coords: { latitude: 0, longitude: 0, accuracy: 1 } });
        },
    });

    try {
        await capturar();
        assert.equal(opcionesCapturadas.enableHighAccuracy, true);
        assert.equal(opcionesCapturadas.maximumAge, 0);
    } finally {
        restoreNavigator();
    }
});
