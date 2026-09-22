// Capa cartografica propia de TinderCows.
// Ningun formulario de negocio debe llamar MapLibre directamente.

export const MAPLIBRE_VERSION = '6.9.0';
export const MAPLIBRE_MODULE_URL = `https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.mjs`;
export const MAPLIBRE_CSS_URL = `https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.css`;
export const MAP_STYLE_URL = 'https://tiles.openfreemap.org/styles/liberty';
// El zoom de interacción no debe confundirse con el nivel nativo de una
// fuente. MapLibre puede reutilizar el último nivel disponible (overzoom).
export const MAP_INTERACTION_MAX_ZOOM = 20;
export const MAP_MARKER_ZOOM = 18;
export const SNIT_IGN_WMS_URL = 'https://geos.snitcr.go.cr/be/IGN_5/wms';
export const ESRI_SATELLITE_TILES_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
export const ANOC_10CM_WMTS_URL = 'https://geos1.snitcr.go.cr/ANOC_10cm_202601/wmts';
export const ANOC_50CM_WMTS_URL = 'https://geos1.snitcr.go.cr/ANOC_50cm_202601/wmts';
export const ANOC_10CM_LAYER = 'ortoimagen_10cm_mision_202601';
export const ANOC_50CM_LAYER = 'ortoimagen_50cm_mision_202601';
export const ANOC_10CM_BOUNDS = Object.freeze({
    minLongitud: -83.55581538057955,
    minLatitud: 8.851926979750909,
    maxLongitud: -83.46222066541057,
    maxLatitud: 8.998951994913051,
});
export const ANOC_50CM_BOUNDS = Object.freeze({
    minLongitud: -85.88095481687932,
    minLatitud: 10.197936887155999,
    maxLongitud: -85.37283029642228,
    maxLatitud: 11.23457328241847,
});
export const IMAGEN_AEREA_PRIORIDAD = Object.freeze(['anoc-10cm', 'anoc-50cm', 'esri']);
export const IMAGEN_AEREA = Object.freeze({
    'anoc-10cm': Object.freeze({
        id: 'anoc-10cm', proveedor: 'snit', etiqueta: 'ANOC 10 cm',
        url: ANOC_10CM_WMTS_URL, layer: ANOC_10CM_LAYER, bounds: ANOC_10CM_BOUNDS,
        maxzoom: 20,
    }),
    'anoc-50cm': Object.freeze({
        id: 'anoc-50cm', proveedor: 'snit', etiqueta: 'ANOC 50 cm',
        url: ANOC_50CM_WMTS_URL, layer: ANOC_50CM_LAYER, bounds: ANOC_50CM_BOUNDS,
        maxzoom: 20,
    }),
    esri: Object.freeze({
        id: 'esri', proveedor: 'esri', etiqueta: 'Esri World Imagery',
        url: ESRI_SATELLITE_TILES_URL, bounds: null, maxzoom: 18,
    }),
});
const RASTER_LAYERS = Object.freeze({
    snitEdificaciones: { source: 'tc-snit-edificaciones', layer: 'tc-snit-edificaciones-layer', wmsLayer: 'edificaciones2017_5k', opacity: 0.78 },
    snitVias: { source: 'tc-snit-vias', layer: 'tc-snit-vias-layer', wmsLayer: 'vias_5000', opacity: 0.82 },
    satellite: { source: 'tc-satellite', layer: 'tc-satellite-layer', opacity: 1 },
});
export const CENTRO_COSTA_RICA = Object.freeze([-84.0907, 9.9281]);
// Rectángulo de navegación que contiene Costa Rica continental, sus islas y
// el mar territorial. La validación exacta usa el GeoJSON local de límites.
export const BOUNDS_COSTA_RICA = Object.freeze([[-90, 3.3], [-81.5, 12.8]]);
export const MAP_LOAD_TIMEOUT_MS = 8000;
export const LIMITES_COSTA_RICA_URL = '/assets/geo/costa-rica-limits.geojson?v=20260921';

let limitesCostaRicaPromise = null;
let limitesCostaRicaUrl = null;

export function cargarLimitesCostaRica({ fetchImpl = globalThis.fetch, url = LIMITES_COSTA_RICA_URL } = {}) {
    if (!limitesCostaRicaPromise || limitesCostaRicaUrl !== url) {
        limitesCostaRicaUrl = url;
        const carga = Promise.resolve(fetchImpl(url, { headers: { Accept: 'application/geo+json, application/json' } }))
            .then((response) => {
                if (!response.ok) throw new Error('No fue posible cargar los límites geográficos de Costa Rica.');
                return response.json();
            })
            .then((geojson) => {
                if (geojson?.type !== 'FeatureCollection' || !Array.isArray(geojson.features)) {
                    throw new Error('Los límites geográficos de Costa Rica tienen un formato inválido.');
                }
                return geojson;
            });
        const cargaFinal = carga.catch((error) => {
            if (limitesCostaRicaPromise === cargaFinal) {
                limitesCostaRicaPromise = null;
                limitesCostaRicaUrl = null;
            }
            throw error;
        });
        limitesCostaRicaPromise = cargaFinal;
    }
    return limitesCostaRicaPromise;
}

function puntoEnAnillo(longitud, latitud, anillo) {
    let dentro = false;
    for (let indice = 0, anterior = anillo.length - 1; indice < anillo.length; anterior = indice++) {
        const [xActual, yActual] = anillo[indice];
        const [xAnterior, yAnterior] = anillo[anterior];
        const cruza = (yActual > latitud) !== (yAnterior > latitud)
            && longitud < ((xAnterior - xActual) * (latitud - yActual)) / (yAnterior - yActual) + xActual;
        if (cruza) dentro = !dentro;
    }
    return dentro;
}

function puntoEnGeometria(longitud, latitud, geometry) {
    if (geometry?.type === 'Polygon') {
        return puntoEnAnillo(longitud, latitud, geometry.coordinates[0])
            && !geometry.coordinates.slice(1).some((anillo) => puntoEnAnillo(longitud, latitud, anillo));
    }
    if (geometry?.type === 'MultiPolygon') {
        return geometry.coordinates.some((poligono) => puntoEnGeometria(longitud, latitud, {
            type: 'Polygon',
            coordinates: poligono,
        }));
    }
    return false;
}

export function puntoDentroDeLimitesCostaRica(punto, geojson) {
    const latitud = Number(punto?.latitud);
    const longitud = Number(punto?.longitud);
    if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) return false;
    return (geojson?.features ?? []).some((feature) => puntoEnGeometria(longitud, latitud, feature.geometry));
}

export function puntoDentroDeCobertura(punto, bounds) {
    const latitud = Number(punto?.latitud);
    const longitud = Number(punto?.longitud);
    return Boolean(
        bounds
        && Number.isFinite(latitud)
        && Number.isFinite(longitud)
        && longitud >= bounds.minLongitud
        && longitud <= bounds.maxLongitud
        && latitud >= bounds.minLatitud
        && latitud <= bounds.maxLatitud,
    );
}

export function resolverFuenteImagen(punto, { disponibilidad = {} } = {}) {
    for (const id of IMAGEN_AEREA_PRIORIDAD) {
        const fuente = IMAGEN_AEREA[id];
        if (disponibilidad[id] === false) continue;
        if (!fuente.bounds || puntoDentroDeCobertura(punto, fuente.bounds)) return fuente;
    }
    return null;
}

const PROVIDER_ATTRIBUTION = '<a href="https://openfreemap.org/" target="_blank" rel="noopener noreferrer">OpenFreeMap</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a> · <a href="https://www.snitcr.go.cr/" target="_blank" rel="noopener noreferrer">SNIT/IGN</a> · <a href="https://www.esri.com/en-us/legal/terms/full-master-agreement" target="_blank" rel="noopener noreferrer">Esri</a>';

function resolverDocumento(documentRef) {
    return documentRef ?? (typeof document !== 'undefined' ? document : null);
}

export function normalizarCoordenadas(coordenadas) {
    const latitud = Number(coordenadas?.latitud);
    const longitud = Number(coordenadas?.longitud);
    if (!Number.isFinite(latitud) || latitud < -90 || latitud > 90) throw new RangeError('La latitud debe estar entre -90 y 90.');
    if (!Number.isFinite(longitud) || longitud < -180 || longitud > 180) throw new RangeError('La longitud debe estar entre -180 y 180.');
    return { latitud, longitud, lngLat: [longitud, latitud] };
}

export function asegurarCssMapLibre(documentRef = null, cssUrl = MAPLIBRE_CSS_URL) {
    const doc = resolverDocumento(documentRef);
    if (!doc?.head) return null;
    const existente = doc.querySelector?.('link[data-tc-maplibre-css]');
    if (existente) return existente;
    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = cssUrl;
    link.dataset.tcMaplibreCss = 'true';
    doc.head.append(link);
    return link;
}

export async function cargarMapLibre({ importFn = (url) => import(url), documentRef = null, moduleUrl = MAPLIBRE_MODULE_URL, cssUrl = MAPLIBRE_CSS_URL } = {}) {
    asegurarCssMapLibre(documentRef, cssUrl);
    const modulo = await importFn(moduleUrl);
    return modulo?.default ?? modulo;
}

function crearError(kind, message, cause = null) {
    const error = new Error(message);
    error.kind = kind;
    if (cause) error.cause = cause;
    return error;
}

function formatearCoordenadas(lngLat) {
    return { latitud: Number(lngLat.lat).toFixed(7), longitud: Number(lngLat.lng).toFixed(7) };
}

function crearUrlWms(capa) {
    const params = new URLSearchParams({ service: 'WMS', version: '1.3.0', request: 'GetMap', layers: capa, styles: '', format: 'image/png', transparent: 'true', width: '256', height: '256', crs: 'EPSG:3857', bbox: '{bbox-epsg-3857}' });
    return `${SNIT_IGN_WMS_URL}?${params}`.replace('%7Bbbox-epsg-3857%7D', '{bbox-epsg-3857}');
}

function crearUrlWmts(fuente) {
    return `${fuente.url}?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=${fuente.layer}&STYLE=_empty&TILEMATRIXSET=EPSG:3857&TILEMATRIX=EPSG:3857:{z}&TILEROW={y}&TILECOL={x}&FORMAT=image/png`;
}

export async function crearMapa({
    contenedor,
    coordenadas = null,
    centro = CENTRO_COSTA_RICA,
    zoom = 7,
    zoomMarcador = MAP_MARKER_ZOOM,
    draggable = false,
    interactive = true,
    styleUrl = MAP_STYLE_URL,
    timeoutMs = MAP_LOAD_TIMEOUT_MS,
    maplibreLoader = cargarMapLibre,
    onMarkerChange = () => {},
    onMapClick = () => {},
    onError = () => {},
    fullscreenContainer = null,
    ResizeObserverImpl = typeof ResizeObserver !== 'undefined' ? ResizeObserver : null,
} = {}) {
    if (!contenedor) throw new TypeError('Se requiere un contenedor para el mapa.');

    let maplibre;
    try { maplibre = await maplibreLoader(); }
    catch (cause) {
        const error = crearError('library', 'No fue posible cargar MapLibre GL JS.', cause);
        onError(error);
        throw error;
    }

    const inicial = coordenadas ? normalizarCoordenadas(coordenadas).lngLat : [...centro];
    let map;
    try {
        map = new maplibre.Map({
            container: contenedor,
            style: styleUrl,
            center: inicial,
            zoom: coordenadas ? zoomMarcador : zoom,
            interactive,
            attributionControl: false,
            cooperativeGestures: false,
            maxBounds: BOUNDS_COSTA_RICA,
            maxZoom: MAP_INTERACTION_MAX_ZOOM,
            renderWorldCopies: false,
        });
    } catch (cause) {
        const error = crearError('initialization', 'No fue posible iniciar el mapa.', cause);
        onError(error);
        throw error;
    }

    let destroyed = false;
    let loaded = false;
    let marker = null;
    let resizeObserver = null;
    const capasRaster = new Set();
    const imagenesAereas = new Map();
    const disponibilidadImagenes = {};
    let imagenAereaActiva = null;
    let ultimoPuntoImagen = coordenadas ?? { latitud: centro[1], longitud: centro[0] };

    function capaAntesDeEtiquetas() {
        return map.getStyle?.().layers?.find((layer) => layer.type === 'symbol')?.id;
    }

    map.on?.('error', (event) => {
        if (!loaded || destroyed) return;
        const fuenteFallida = [...imagenesAereas.values()].find((item) => item.source === event?.sourceId)?.fuente;
        if (fuenteFallida && imagenAereaActiva === fuenteFallida.id) {
            disponibilidadImagenes[fuenteFallida.id] = false;
            const siguiente = activarImagenAerea(ultimoPuntoImagen);
            const error = crearError('image-fallback', siguiente
                ? `La fuente ${fuenteFallida.etiqueta} no está disponible. Se muestra ${siguiente.etiqueta}.`
                : 'La imagen aérea no está disponible. El mapa normal continúa operativo.', event?.error ?? null);
            onError(error);
            return;
        }
        onError(crearError('resource', 'Un recurso cartografico no pudo cargarse.', event?.error ?? null));
    });

    try {
        await new Promise((resolve, reject) => {
            let settled = false;
            const timer = setTimeout(() => {
                if (settled) return;
                settled = true;
                reject(crearError('timeout', 'El mapa tardo demasiado en cargar.'));
            }, Math.max(1, timeoutMs));
            const finish = (callback) => {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                callback();
            };
            map.once?.('load', () => finish(resolve));
            map.once?.('error', (event) => {
                if (loaded) return;
                finish(() => reject(crearError('style', 'No fue posible cargar el estilo cartografico.', event?.error ?? null)));
            });
        });
        loaded = true;
        // Reafirma los límites después de cargar el estilo para evitar que
        // ciertos builds permitan desplazar el mapa fuera del ámbito definido.
        map.setMaxBounds?.(BOUNDS_COSTA_RICA);
        map.setMinZoom?.(6.4);
        map.setMaxZoom?.(MAP_INTERACTION_MAX_ZOOM);
    } catch (error) {
        try { map.remove?.(); } catch {}
        onError(error);
        throw error;
    }

    if (maplibre.AttributionControl) {
        map.addControl?.(new maplibre.AttributionControl({ compact: false, customAttribution: PROVIDER_ATTRIBUTION }), 'bottom-right');
    }
    if (interactive && maplibre.NavigationControl) map.addControl?.(new maplibre.NavigationControl({ showCompass: false }), 'top-right');
    if (interactive && maplibre.FullscreenControl) {
        map.addControl?.(
            new maplibre.FullscreenControl({ container: fullscreenContainer ?? contenedor }),
            'top-right',
        );
    }

    function activarCapaRaster(nombre, visible = true) {
        if (destroyed || !map.addSource || !map.addLayer) return false;
        const capa = RASTER_LAYERS[nombre];
        if (!capa) return false;
        try {
            if (!capasRaster.has(nombre)) {
                const source = nombre === 'satellite'
                    ? { type: 'raster', tiles: [ESRI_SATELLITE_TILES_URL], tileSize: 256, maxzoom: 18, bounds: BOUNDS_COSTA_RICA }
                    : { type: 'raster', tiles: [crearUrlWms(capa.wmsLayer)], tileSize: 256 };
                map.addSource(capa.source, source);
                const capaMapa = { id: capa.layer, type: 'raster', source: capa.source, layout: { visibility: 'none' }, paint: { 'raster-opacity': capa.opacity } };
                map.addLayer(capaMapa, capaAntesDeEtiquetas());
                capasRaster.add(nombre);
            }
            map.setLayoutProperty?.(capa.layer, 'visibility', visible ? 'visible' : 'none');
            return true;
        } catch (error) {
            onError(crearError('resource', 'No fue posible cargar una capa adicional del mapa.', error));
            return false;
        }
    }

    function activarDetallesOficiales(visible = true) {
        const edificios = activarCapaRaster('snitEdificaciones', visible);
        const vias = activarCapaRaster('snitVias', visible);
        if (!edificios || !vias) {
            activarCapaRaster('snitEdificaciones', false);
            activarCapaRaster('snitVias', false);
            return false;
        }
        return true;
    }

    function asegurarImagenAerea(fuente) {
        const existente = imagenesAereas.get(fuente.id);
        if (existente) return existente;
        const source = `tc-image-${fuente.id}`;
        const layer = `tc-image-${fuente.id}-layer`;
        map.addSource(source, {
            type: 'raster',
            tiles: [fuente.proveedor === 'esri' ? fuente.url : crearUrlWmts(fuente)],
            tileSize: 256,
            maxzoom: fuente.maxzoom,
            ...(fuente.bounds ? { bounds: [fuente.bounds.minLongitud, fuente.bounds.minLatitud, fuente.bounds.maxLongitud, fuente.bounds.maxLatitud] } : {}),
        });
        map.addLayer({ id: layer, type: 'raster', source, layout: { visibility: 'none' }, paint: { 'raster-opacity': 1 } }, capaAntesDeEtiquetas());
        const creada = { fuente, source, layer };
        imagenesAereas.set(fuente.id, creada);
        return creada;
    }

    function activarImagenAerea(punto = ultimoPuntoImagen) {
        if (destroyed) return null;
        ultimoPuntoImagen = punto ?? ultimoPuntoImagen;
        let fuente = resolverFuenteImagen(ultimoPuntoImagen, { disponibilidad: disponibilidadImagenes });
        while (fuente) {
            try {
                const imagen = asegurarImagenAerea(fuente);
                for (const otra of imagenesAereas.values()) {
                    map.setLayoutProperty?.(otra.layer, 'visibility', otra.fuente.id === fuente.id ? 'visible' : 'none');
                }
                imagenAereaActiva = fuente.id;
                return fuente;
            } catch (error) {
                disponibilidadImagenes[fuente.id] = false;
                onError(crearError('image-fallback', `No fue posible cargar ${fuente.etiqueta}.`, error));
                fuente = resolverFuenteImagen(ultimoPuntoImagen, { disponibilidad: disponibilidadImagenes });
            }
        }
        imagenAereaActiva = null;
        for (const imagen of imagenesAereas.values()) map.setLayoutProperty?.(imagen.layer, 'visibility', 'none');
        return null;
    }

    function desactivarImagenAerea() {
        imagenAereaActiva = null;
        for (const imagen of imagenesAereas.values()) {
            map.setLayoutProperty?.(imagen.layer, 'visibility', 'none');
        }
    }

    function crearMarcador(lngLat) {
        const nuevo = new maplibre.Marker({ draggable }).setLngLat(lngLat).addTo(map);
        if (draggable && nuevo.on) nuevo.on('dragend', () => { if (!destroyed) onMarkerChange(formatearCoordenadas(nuevo.getLngLat())); });
        return nuevo;
    }

    function establecerMarcador(nuevasCoordenadas, { centrar: debeCentrar = true } = {}) {
        if (destroyed) return null;
        const normalizadas = normalizarCoordenadas(nuevasCoordenadas);
        ultimoPuntoImagen = normalizadas;
        if (!marker) marker = crearMarcador(normalizadas.lngLat);
        else marker.setLngLat(normalizadas.lngLat);
        if (debeCentrar) map.jumpTo?.({ center: normalizadas.lngLat, zoom: Math.max(map.getZoom?.() ?? zoomMarcador, zoomMarcador) });
        return formatearCoordenadas(marker.getLngLat());
    }

    function quitarMarcador() {
        if (destroyed || !marker) return;
        try { marker.remove?.(); } catch {}
        marker = null;
    }

    function obtenerCoordenadas() { return marker ? formatearCoordenadas(marker.getLngLat()) : null; }

    function centrar(nuevasCoordenadas, nuevoZoom = zoomMarcador) {
        if (destroyed) return;
        const normalizadas = normalizarCoordenadas(nuevasCoordenadas);
        map.jumpTo?.({ center: normalizadas.lngLat, zoom: Math.min(nuevoZoom, MAP_INTERACTION_MAX_ZOOM) });
    }

    function ajustar(puntos = [], { padding = 40, maxZoom = zoomMarcador } = {}) {
        if (destroyed || !Array.isArray(puntos) || puntos.length === 0) return;
        const normalizados = puntos.map((punto) => normalizarCoordenadas(punto));
        if (normalizados.length === 1) { centrar(normalizados[0], maxZoom); return; }
        const longitudes = normalizados.map((punto) => punto.longitud);
        const latitudes = normalizados.map((punto) => punto.latitud);
        map.fitBounds?.([
            [Math.min(...longitudes), Math.min(...latitudes)],
            [Math.max(...longitudes), Math.max(...latitudes)],
        ], { padding, maxZoom });
    }

    function redimensionar() { if (!destroyed) map.resize?.(); }
    function destruir() {
        if (destroyed) return;
        destroyed = true;
        resizeObserver?.disconnect?.();
        resizeObserver = null;
        try { marker?.remove?.(); } catch {}
        marker = null;
        try { map.remove?.(); } catch {}
    }

    if (interactive) {
        map.on?.('click', (event) => {
            if (destroyed || !event?.lngLat) return;
            onMapClick(formatearCoordenadas(event.lngLat));
        });
    }
    if (ResizeObserverImpl) {
        resizeObserver = new ResizeObserverImpl(redimensionar);
        resizeObserver.observe?.(contenedor);
    }
    if (coordenadas) establecerMarcador(coordenadas, { centrar: false });

    return Object.freeze({
        establecerMarcador,
        quitarMarcador,
        obtenerCoordenadas,
        centrar,
        ajustar,
        activarCapaRaster,
        activarImagenAerea,
        desactivarImagenAerea,
        obtenerImagenAerea: () => imagenAereaActiva ? IMAGEN_AEREA[imagenAereaActiva] : null,
        activarDetallesOficiales,
        redimensionar,
        destruir,
        get destruido() { return destroyed; },
        get cargado() { return loaded && !destroyed; },
    });
}
