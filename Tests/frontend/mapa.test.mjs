import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import {
    crearMapa,
    MAP_STYLE_URL,
    cargarLimitesCostaRica,
    normalizarCoordenadas,
    puntoDentroDeLimitesCostaRica,
    SNIT_IGN_WMS_URL,
    ESRI_SATELLITE_TILES_URL,
    ANOC_10CM_LAYER,
    ANOC_50CM_LAYER,
    puntoDentroDeCobertura,
    resolverFuenteImagen,
} from '../../Public/js/shared/mapa.js';
import { buscarLugaresPorNombre } from '../../Public/js/shared/finca-mapa.js';

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
    addSource(name, source) { this.sources ??= new Map(); this.sources.set(name, source); }
    addLayer(layer) { this.layers ??= new Map(); this.layers.set(layer.id, layer); }
    setLayoutProperty(id, property, value) { this.layers.get(id).layout[property] = value; }
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
class FakeFullscreenControl {}
class FakeResizeObserver { constructor(cb) { this.cb = cb; } observe(target) { this.target = target; } disconnect() { this.disconnected = true; } }
const fakeLib = { Map: FakeMap, Marker: FakeMarker, AttributionControl: FakeAttributionControl, NavigationControl: FakeNavigationControl, FullscreenControl: FakeFullscreenControl };

test('normalizarCoordenadas valida rangos', () => {
    assert.deepEqual(normalizarCoordenadas({ latitud: 9, longitud: -84 }).lngLat, [-84, 9]);
    assert.throws(() => normalizarCoordenadas({ latitud: 99, longitud: -84 }), RangeError);
});

test('los límites locales aceptan tierra y mar territorial, pero rechazan países vecinos', () => {
    const limites = {
        type: 'FeatureCollection',
        features: [
            { type: 'Feature', geometry: { type: 'Polygon', coordinates: [[[-85, 9], [-84, 9], [-84, 10], [-85, 10], [-85, 9]]] } },
            { type: 'Feature', geometry: { type: 'Polygon', coordinates: [[[-86, 9], [-85, 9], [-85, 10], [-86, 10], [-86, 9]]] } },
        ],
    };
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 9.5, longitud: -84.5 }, limites), true);
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 9.5, longitud: -85.5 }, limites), true);
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 12.1, longitud: -86.25 }, limites), false);
});

test('el asset versionado acepta Costa Rica, Isla del Coco y mar territorial, pero rechaza países vecinos', () => {
    const limites = JSON.parse(fs.readFileSync('Public/assets/geo/costa-rica-limits.geojson', 'utf8'));
    assert.equal(limites.metadata.coordinateReferenceSystem, 'EPSG:4326');
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 9.9281, longitud: -84.0907 }, limites), true);
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 5.524, longitud: -87.065 }, limites), true);
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 11, longitud: -86 }, limites), true);
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 12.1364, longitud: -86.2514 }, limites), false);
    assert.equal(puntoDentroDeLimitesCostaRica({ latitud: 9, longitud: -79.5 }, limites), false);
});

test('la carga de límites permite reintentar después de un fallo', async () => {
    let intentos = 0;
    const fetchImpl = async () => {
        intentos += 1;
        if (intentos === 1) return { ok: false, json: async () => ({}) };
        return { ok: true, json: async () => ({ type: 'FeatureCollection', features: [] }) };
    };
    await assert.rejects(() => cargarLimitesCostaRica({ fetchImpl, url: 'https://example.test/limits.geojson' }));
    await assert.doesNotReject(() => cargarLimitesCostaRica({ fetchImpl, url: 'https://example.test/limits.geojson' }));
    assert.equal(intentos, 2);
});

test('la selección de imagen aérea prioriza ANOC 10 cm, luego 50 cm y finalmente Esri', () => {
    const punto10 = { latitud: 8.92, longitud: -83.51 };
    const punto50 = { latitud: 10.7, longitud: -85.6 };
    const puntoFuera = { latitud: 9.9, longitud: -84.1 };
    assert.equal(puntoDentroDeCobertura(punto10, resolverFuenteImagen(punto10).bounds), true);
    assert.equal(resolverFuenteImagen(punto10).id, 'anoc-10cm');
    assert.equal(resolverFuenteImagen(punto50).id, 'anoc-50cm');
    assert.equal(resolverFuenteImagen(puntoFuera).id, 'esri');
    assert.equal(resolverFuenteImagen(punto10, { disponibilidad: { 'anoc-10cm': false } }).id, 'esri');
    assert.equal(resolverFuenteImagen(punto50, { disponibilidad: { 'anoc-50cm': false } }).id, 'esri');
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
    assert.equal(FakeMap.last.options.maxZoom, 20);
    assert.deepEqual(controller.obtenerCoordenadas(), { latitud: '9.9000000', longitud: '-84.1000000' });
    controller.establecerMarcador({ latitud: 10, longitud: -85 });
    assert.deepEqual(controller.obtenerCoordenadas(), { latitud: '10.0000000', longitud: '-85.0000000' });
    controller.ajustar([{ latitud: 9, longitud: -85 }, { latitud: 11, longitud: -83 }]);
    assert.deepEqual(FakeMap.last.lastBounds.bounds, [[-85, 9], [-83, 11]]);
    assert.equal(controller.activarDetallesOficiales(), true);
    assert.equal(controller.activarCapaRaster('satellite'), true);
    assert.match(FakeMap.last.sources.get('tc-snit-edificaciones').tiles[0], /edificaciones2017_5k/);
    assert.match(FakeMap.last.sources.get('tc-snit-edificaciones').tiles[0], /\{bbox-epsg-3857\}/);
    assert.equal(FakeMap.last.layers.get('tc-satellite-layer').layout.visibility, 'visible');
    assert.match(ESRI_SATELLITE_TILES_URL, /World_Imagery/);
    assert.match(SNIT_IGN_WMS_URL, /snitcr\.go\.cr/);
    controller.destruir();
    assert.equal(FakeMap.last.removed, true);
    assert.equal(controller.destruido, true);
});

test('las ortofotos ANOC usan WMTS KVP y no cambian el zoom global del mapa', async () => {
    const controller = await crearMapa({
        contenedor: {}, coordenadas: { latitud: 8.92, longitud: -83.51 },
        maplibreLoader: async () => fakeLib, ResizeObserverImpl: null, timeoutMs: 100,
    });
    const fuente10 = controller.activarImagenAerea();
    assert.equal(fuente10.id, 'anoc-10cm');
    const origen10 = FakeMap.last.sources.get('tc-image-anoc-10cm');
    assert.match(origen10.tiles[0], new RegExp(ANOC_10CM_LAYER));
    assert.match(origen10.tiles[0], /TILEMATRIX=EPSG:3857:\{z\}/);
    assert.equal(origen10.maxzoom, 20);
    controller.establecerMarcador({ latitud: 10.7, longitud: -85.6 }, { centrar: false });
    const fuente50 = controller.activarImagenAerea();
    assert.equal(fuente50.id, 'anoc-50cm');
    assert.match(FakeMap.last.sources.get('tc-image-anoc-50cm').tiles[0], new RegExp(ANOC_50CM_LAYER));
    assert.equal(FakeMap.last.options.maxZoom, 20);
    controller.destruir();
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

test('la búsqueda de lugares queda acotada a Costa Rica y devuelve coordenadas utilizables', async () => {
    let llamada = '';
    const resultados = await buscarLugaresPorNombre('San José', {
        fetchImpl: async (url) => {
            llamada = String(url);
            return {
                ok: true,
                json: async () => [
                    { display_name: 'San José, Costa Rica', lat: '9.93', lon: '-84.08', address: { country_code: 'cr' } },
                    { display_name: 'San Jose, otro país', lat: '1', lon: '1', address: { country_code: 'us' } },
                ],
            };
        },
    });
    const parametros = new URL(llamada).searchParams;
    assert.equal(parametros.get('countrycodes'), 'cr');
    assert.equal(parametros.get('bounded'), '1');
    assert.deepEqual(resultados, [{ nombre: 'San José, Costa Rica', latitud: 9.93, longitud: -84.08 }]);
});
