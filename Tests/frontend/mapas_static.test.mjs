import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const auth = fs.readFileSync('Public/js/shared/auth-gate.js', 'utf8');
const map = fs.readFileSync('Public/js/shared/mapa.js', 'utf8');
const location = fs.readFileSync('Public/js/shared/productor-ubicacion-ui.js', 'utf8');
const adapter = fs.readFileSync('Public/js/productores-fincas-ui.js', 'utf8');

test('auth-gate no solicita geolocalizacion automaticamente', () => {
    assert.equal(auth.includes('navigator.geolocation'), false);
    assert.equal(auth.includes('capturarEnInicioDeSesion'), false);
    assert.equal(auth.includes('capturar('), false);
});

test('MapLibre y OpenFreeMap estan centralizados en mapa.js', () => {
    assert.match(map, /MAPLIBRE_VERSION = '6\.9\.0'/);
    assert.match(map, /MAP_STYLE_URL = 'https:\/\/tiles\.openfreemap\.org\/styles\/liberty'/);
    assert.equal(location.includes('new maplibre.Map'), false);
    assert.equal(adapter.includes('new maplibre.Map'), false);
    assert.ok(map.includes('OpenStreetMap contributors'));
});

test('UI del productor ofrece GPS explicito y alternativa manual', () => {
    assert.ok(location.includes('Usar mi ubicacion'));
    assert.ok(location.includes('Ingresar ubicacion manualmente'));
    assert.ok(location.includes('Cancelar solicitud'));
    assert.ok(location.includes('operationId += 1'));
    assert.ok(location.includes('token !== operationId'));
    assert.ok(location.includes('createdMap?.destruir?.()'));
    assert.ok(location.includes('Mapa no disponible'));
    assert.ok(location.includes('No modifica su direccion declarada ni representa la ubicacion de una finca'));
});

test('integracion se carga desde el modulo ya asociado al panel de productores', () => {
    assert.ok(adapter.includes("from './shared/productor-ubicacion-ui.js'"));
    assert.ok(adapter.includes('inicializarUbicacionProductorUI()'));
});
