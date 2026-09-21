import assert from 'node:assert/strict';
import test from 'node:test';
import { crearMapa, MAP_STYLE_URL, normalizarCoordenadas } from '../../Public/js/shared/mapa.js';

class FakeEmitter {
    constructor() { this.handlers = new Map(); }
    on(name, handler) { if (!this.handlers.has(name)) this.handlers.set(name, []); this.handlers.get(name).push(handler); return this; }
    once(name, handler) { const once = (...args) => { this.off(name, once); handler(...args); }; return this.on(name, once); }
    off(name, handler) { this.handlers.set(name, (this.handlers.get(name) ?? []).filter((h) => h !== handler)); }
    emit(name, payload) { for (const handler of [...(this.handlers.get(name) ?? [])]) handler(payload); }
}

class FakeMap extends FakeEmitter {
    static last = null;
    constructor(options) { super(); this.options = options; this.zoom = options.zoom; this.removed = false; this.controls = []; FakeMap.last = this; queueMicrotask(() => this.emit('load')); }
    addControl(control) { this.controls.push(control); }
    jumpTo(options) { this.lastJump = options; this.zoom = options.zoom ?? this.zoom; }
    fitBounds(bounds, options) { this.lastBounds = { bounds, options }; }
    getZoom() { return this.zoom; }
    resize() { this.resized = true; }
    remove() { this.removed = true; }
}
class FakeMarker extends FakeEmitter {
    constructor(options) { super(); this.options = options; }
    setLngLat(value) { this.value = { lng: Number(value[0]), lat: Number(value[1]) }; return this; }
    getLngLat() { return this.value; }
    addTo(map) { this.map = map; return this; }
    remove() { this.removed = true; }
}
class FakeAttributionControl { constructor(options) { this.options = options; } }
class FakeNavigationControl { constructor(options) { this.options = options; } }
class FakeResizeObserver { constructor(cb) { this.cb = cb; } observe(target) { this.target = target; } disconnect() { this.disconnected = true; } }
const fakeLib = { Map: FakeMap, Marker: FakeMarker, AttributionControl: FakeAttributionControl, NavigationControl: FakeNavigationControl };

test('normalizarCoordenadas valida rangos', () => {
    assert.deepEqual(normalizarCoordenadas({ latitud: 9, longitud: -84 }).lngLat, [-84, 9]);
    assert.throws(() => normalizarCoordenadas({ latitud: 99, longitud: -84 }), RangeError);
});

test('crearMapa centraliza estilo, atribucion, marcador y destruccion', async () => {
    const container = {};
    const controller = await crearMapa({
        contenedor: container,
        coordenadas: { latitud: '9.9', longitud: '-84.1' },
        interactive: false,
        maplibreLoader: async () => fakeLib,
        ResizeObserverImpl: FakeResizeObserver,
        timeoutMs: 100,
    });
    assert.equal(FakeMap.last.options.style, MAP_STYLE_URL);
    assert.equal(FakeMap.last.options.attributionControl, false);
    assert.equal(FakeMap.last.options.interactive, false);
    assert.deepEqual(controller.obtenerCoordenadas(), { latitud: '9.9000000', longitud: '-84.1000000' });
    controller.establecerMarcador({ latitud: 10, longitud: -85 });
    assert.deepEqual(controller.obtenerCoordenadas(), { latitud: '10.0000000', longitud: '-85.0000000' });
    controller.ajustar([{ latitud: 9, longitud: -85 }, { latitud: 11, longitud: -83 }]);
    assert.deepEqual(FakeMap.last.lastBounds.bounds, [[-85, 9], [-83, 11]]);
    controller.destruir();
    assert.equal(FakeMap.last.removed, true);
    assert.equal(controller.destruido, true);
});

test('fallo de estilo antes de load produce fallback controlable', async () => {
    class BrokenMap extends FakeEmitter {
        constructor(options) { super(); this.options = options; this.removed = false; queueMicrotask(() => this.emit('error', { error: new Error('style down') })); }
        remove() { this.removed = true; }
    }
    const errors = [];
    await assert.rejects(() => crearMapa({
        contenedor: {},
        maplibreLoader: async () => ({ ...fakeLib, Map: BrokenMap }),
        timeoutMs: 100,
        ResizeObserverImpl: null,
        onError: (error) => errors.push(error.kind),
    }), (error) => error.kind === 'style');
    assert.ok(errors.includes('style'));
});

test('mapa que nunca carga expira en vez de dejar spinner permanente', async () => {
    class HangingMap extends FakeEmitter { constructor(options) { super(); this.options = options; } remove() { this.removed = true; } }
    await assert.rejects(() => crearMapa({
        contenedor: {},
        maplibreLoader: async () => ({ ...fakeLib, Map: HangingMap }),
        timeoutMs: 5,
        ResizeObserverImpl: null,
    }), (error) => error.kind === 'timeout');
});
