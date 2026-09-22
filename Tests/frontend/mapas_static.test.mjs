import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const auth = fs.readFileSync('Public/js/shared/auth-gate.js', 'utf8');
const map = fs.readFileSync('Public/js/shared/mapa.js', 'utf8');
const farmMap = fs.readFileSync('Public/js/shared/finca-mapa.js', 'utf8');
const adapter = fs.readFileSync('Public/js/productores-fincas-ui.js', 'utf8');
const registration = fs.readFileSync('Public/js/registro.js', 'utf8');
const explore = fs.readFileSync('Public/js/explore.js', 'utf8');
const productoresView = fs.readFileSync('Application/View/productores/index.php', 'utf8');

test('la ubicacion del usuario se intenta automaticamente pero no se persiste como productor', () => {
    assert.ok(auth.includes('inicializarUbicacionAutomatica()'));
    assert.equal(auth.includes('api/productores-ubicacion.php'), false);
    assert.equal(auth.includes('REGISTRAR_UBICACION'), false);
    assert.ok(explore.includes('leerUbicacionUsuario()'));
    assert.ok(explore.includes("parametros.set('latitud'"));
    assert.ok(explore.includes("parametros.set('longitud'"));
});

test('MapLibre y OpenFreeMap siguen centralizados en mapa.js', () => {
    assert.match(map, /MAPLIBRE_VERSION = '6\.9\.0'/);
    assert.match(map, /MAP_STYLE_URL = 'https:\/\/tiles\.openfreemap\.org\/styles\/liberty'/);
    assert.equal(farmMap.includes('new maplibre.Map'), false);
    assert.equal(adapter.includes('new maplibre.Map'), false);
    assert.equal(registration.includes('new maplibre.Map'), false);
    assert.ok(map.includes('OpenStreetMap contributors'));
});

test('el mapa se usa como selector opcional de punto exacto de finca', () => {
    assert.ok(farmMap.includes('Punto exacto de la finca'));
    assert.ok(farmMap.includes('data-farm-map-open>Abrir mapa</button>'));
    assert.ok(farmMap.includes('data-farm-map-close hidden>Cerrar mapa</button>'));
    assert.equal(farmMap.includes('límites locales provienen'), false);
    assert.equal(farmMap.includes('OpenFreeMap carga la cartografía'), false);
    assert.ok(farmMap.includes('data-farm-map-location'));
    assert.ok(farmMap.includes('data-farm-map-clear'));
    assert.equal(farmMap.includes('data-farm-map-coords'), false);
    assert.ok(farmMap.includes('onMapClick'));
    assert.ok(farmMap.includes('draggable: true'));
    assert.ok(farmMap.includes('Buscar lugar'));
    assert.ok(farmMap.includes('buscarLugaresPorNombre'));
    assert.ok(farmMap.includes('Mostrar detalles oficiales'));
    assert.ok(farmMap.includes('Vista satelital'));
    assert.ok(map.includes('SNIT_IGN_WMS_URL'));
    assert.ok(map.includes('ESRI_SATELLITE_TILES_URL'));
});

test('el selector limita el mapa a Costa Rica y puede completar dirección por coordenadas', () => {
    assert.ok(map.includes('BOUNDS_COSTA_RICA'));
    assert.ok(map.includes('LIMITES_COSTA_RICA_URL'));
    assert.ok(map.includes('puntoDentroDeLimitesCostaRica'));
    assert.ok(farmMap.includes('cargarLimitesCostaRica'));
    assert.ok(farmMap.includes('buscarDireccionPorCoordenadas'));
    assert.ok(farmMap.includes('country_code !== \'cr\''));
    assert.ok(farmMap.includes('onPuntoChange'));
    assert.ok(farmMap.includes('address.village'));
    assert.ok(farmMap.includes('address.hamlet'));
    assert.ok(map.includes('setMaxBounds'));
    assert.ok(map.includes('setMinZoom'));
});

test('la finca puede centrar el mapa en la ubicación del usuario sin activar el rastreo automáticamente', () => {
    assert.ok(farmMap.includes('data-farm-map-location'));
    assert.ok(farmMap.includes('capturarUbicacionAutomatica'));
    assert.ok(farmMap.includes('if (!mapa)'));
    assert.ok(farmMap.includes("solicitudUbicacion += 1"));
    assert.match(farmMap, /limpiar\(\) \{[\s\S]*?solicitudUbicacion \+= 1/);
    assert.ok(farmMap.includes("'outside-costa-rica'"));
    assert.ok(farmMap.includes('mapa.centrar(ubicacion, 15)'));
    assert.equal(farmMap.includes('bloquearRuedaSobreMapa'), false);
    assert.equal(map.includes('map.scrollZoom?.disable?.()'), false);
    assert.ok(map.includes('FullscreenControl'));
    assert.ok(map.includes('cooperativeGestures: false'));
    assert.ok(farmMap.includes('listenersDeCapasInstalados'));
});

test('usuario y admin reutilizan el mismo selector de finca', () => {
    assert.ok(registration.includes("from './shared/finca-mapa.js'"));
    assert.ok(registration.includes('crearSelectorPuntoFinca'));
    assert.ok(adapter.includes("from './shared/finca-mapa.js'"));
    assert.ok(adapter.includes('crearSelectorPuntoFinca'));
});

test('el modal de productores se divide en datos, dirección y fincas', () => {
    assert.ok(productoresView.includes('class="modal-section-tabs"'));
    assert.ok(productoresView.includes('data-modal-section-target="seccion-datos"'));
    assert.ok(productoresView.includes('id="seccion-direccion"'));
    assert.ok(productoresView.includes('id="seccion-fincas"'));
});

test('la UI equivocada de ubicacion observada del productor ya no existe', () => {
    assert.equal(fs.existsSync('Public/js/shared/productor-ubicacion-ui.js'), false);
});
