import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const view = readFileSync('Application/View/mi-actividad/index.php', 'utf8');
const js = readFileSync('Public/js/mi-actividad.js', 'utf8');
const css = readFileSync('Public/css/mi-actividad.css', 'utf8');
const controller = readFileSync('Application/Controller/MiVehiculosController.php', 'utf8');
const api = readFileSync('Public/api/mi-vehiculos.php', 'utf8');
const publicUi = readFileSync('Public/js/public-ui.js', 'utf8');
const farmsController = readFileSync('Application/Controller/MiFincasController.php', 'utf8');
const farmsApi = readFileSync('Public/api/mi-fincas.php', 'utf8');

test('Mi actividad usa el shell público y cubre el dashboard completo', () => {
    for (const value of ['public-header--product', 'Inicio', 'Explorar', 'Nosotros', 'Cómo funciona', 'public-ui.js', 'activity-summary', 'activity-list', 'profile-list', 'farms-list', 'vehicles-list', 'vehicle-modal']) {
        assert.ok(view.includes(value), `falta ${value}`);
    }
    assert.equal(view.includes('auth-header'), false);
});

test('Mi actividad conserva estados parciales y reintentos sin bloquear la pantalla', () => {
    for (const value of ['activity-loading', 'activity-error', 'activity-retry', 'vehicles-loading', 'vehicles-error', 'vehicles-retry', 'vehicles-empty', 'setVehicleView', 'loadVehicles']) {
        assert.ok(view.includes(value) || js.includes(value), `falta el estado ${value}`);
    }
    assert.ok(js.includes("request(VEHICLES_API)"));
    assert.ok(js.includes("setVehicleView('error'"));
});

test('vehículos propios deriva ownership del actor y separa administración', () => {
    assert.ok(api.includes('SupabaseActorResolver'));
    assert.ok(api.includes('AuthGuard::requerirAutenticado'));
    assert.ok(controller.includes('buscarPorPersonaId'));
    assert.ok(controller.includes('vehiculoPropioBloqueado'));
    assert.ok(controller.includes('API_MI_VEHICULOS'));
    assert.ok(controller.includes('ejecutarConBloqueoAlta'));
    assert.ok(controller.includes('beginTransaction'));
    assert.ok(controller.includes('rollBack'));
    for (const forbidden of ['identificacionNumero', 'transportistaId', 'personaId']) {
        assert.ok(controller.includes(forbidden), `${forbidden} debe tener cobertura de rechazo o derivación`);
    }
});

test('modal propio tiene foco, errores por campo y evita doble envío', () => {
    for (const value of ['aria-describedby', 'data-vehicle-error', 'aria-invalid', 'save.disabled = true', 'lastVehicleTrigger?.focus']) {
        assert.ok(view.includes(value) || js.includes(value), `falta ${value}`);
    }
    assert.ok(css.includes('min-height:44px'));
    assert.ok(js.includes("event.key === 'Escape'") || js.includes("addEventListener('cancel'"));
});

test('el enlace de completar actividad se decide con el servidor y falla cerrado', () => {
    assert.ok(publicUi.includes("request('api/v1/actividad'"));
    assert.ok(publicUi.includes("register.hidden = true"));
    assert.ok(publicUi.includes("detail?.estado === 'NO_CONFIGURADO'"));
});

test('Mi actividad reutiliza el subflujo de fincas con dirección y mapa', () => {
    for (const value of ['farm-add', 'farms-loading', 'farms-error', 'farms-retry', 'farm-modal', 'farm-form', 'data-farm-map', 'data-farm-province', 'data-farm-town']) {
        assert.ok(view.includes(value), `falta ${value}`);
    }
    for (const value of ["const FARMS_API = 'api/v1/mi-fincas'", 'loadFarms', 'crearSelectorPuntoFinca', 'conectarDireccion', 'saveFarm', 'lastFarmTrigger?.focus']) {
        assert.ok(js.includes(value), `falta ${value}`);
    }
    assert.ok(farmsApi.includes('AuthGuard::requerirAutenticado'));
    assert.ok(farmsController.includes('buscarPorPersonaId'));
    assert.ok(farmsController.includes('API_MI_FINCAS'));
    assert.ok(farmsController.includes('bloquearPropia'));
    assert.ok(farmsController.includes('beginTransaction'));
    assert.ok(farmsController.includes('rollBack'));
});
