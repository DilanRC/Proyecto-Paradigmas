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
const ajustesView = readFileSync('Application/View/ajustes/index.php', 'utf8');
const ajustesJs = readFileSync('Public/js/ajustes.js', 'utf8');
const htaccess = readFileSync('Public/.htaccess', 'utf8');

test('Mi panel usa el shell público y muestra solo lo operativo', () => {
    for (const value of ['public-header--product', 'Inicio', 'Explorar', 'Nosotros', 'Cómo funciona', 'public-ui.js', 'panel-hello', 'panel-roles', 'panel-actions', 'publications-list', 'farms-list', 'vehicles-list', 'vehicle-modal', 'href="ajustes"']) {
        assert.ok(view.includes(value), `falta ${value}`);
    }
    assert.equal(view.includes('auth-header'), false);
    // Resumen de 4 tarjetas, "Cómo participas" e identidad completa salen del panel.
    for (const removed of ['activity-summary', 'summary-card', 'activity-list', 'profile-list']) {
        assert.equal(view.includes(removed), false, `${removed} ya no vive en el panel`);
    }
});

test('Ajustes de cuenta concentra perfil y actividades con la lógica existente', () => {
    for (const value of ['activity-list', 'profile-list', 'id="perfil"', 'id="participacion"', 'js/ajustes.js', 'Si desactivas una actividad, tus otros datos no se borran.']) {
        assert.ok(ajustesView.includes(value), `falta ${value}`);
    }
    // Mismo endpoint y mismo cuerpo que antes; solo cambia el lugar.
    assert.ok(ajustesJs.includes("const ACTIVITY_API = 'api/v1/actividad'"));
    assert.ok(ajustesJs.includes("method: 'PATCH', body: JSON.stringify({ contexto: id, activo: nextActive })"));
    assert.ok(ajustesJs.includes('window.confirm'), 'desactivar pide confirmación');
    assert.ok(ajustesJs.includes('role="switch"'));
    assert.match(htaccess, /RewriteRule \^ajustes\/\?\$ ajustes\.php \[END\]/);
    assert.equal(js.includes('data-toggle-capability'), false, 'el panel ya no cambia actividades');
});

test('Ajustes enmascara identificación y teléfono', async () => {
    const { maskTail } = await import('../../Public/js/ajustes.js');
    assert.equal(maskTail('112340817'), '•••• 0817');
    assert.equal(maskTail('+506 8823 8236'), '•••• 8236');
    assert.equal(maskTail(''), 'Sin completar');
    assert.equal(maskTail(null), 'Sin completar');
});

test('el panel muestra solo las acciones de actividades activas y una sola principal', async () => {
    const { panelActions } = await import('../../Public/js/mi-actividad.js');
    const activo = (destinoActivo) => ({ estado: 'ACTIVO', destinoActivo });
    const todas = panelActions({ COMPRADOR: activo('explorar'), PRODUCTOR: activo('publicar'), TRANSPORTISTA: activo('fletes') });
    assert.deepEqual(todas.map((a) => a.label), ['Explorar ganado', 'Ver fletes', 'Publicar ganado']);
    assert.equal(todas.filter((a) => a.primary).length, 1);
    assert.equal(todas.find((a) => a.primary).label, 'Publicar ganado');
    assert.deepEqual(panelActions({ COMPRADOR: activo('explorar'), PRODUCTOR: { estado: 'INACTIVO' } }).map((a) => a.label), ['Explorar ganado']);

    // Mis publicaciones las filtra el servidor (mias); ya no se cruzan nombres de finca en el navegador.
    assert.ok(js.includes('mias: true'));
    assert.ok(!js.includes('ownPublications'));
    for (const accion of ['editar', 'PAUSADO', 'VENDIDO']) assert.ok(js.includes(`'${accion}'`), `falta la acción ${accion}`);
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

test('el menú de la cuenta ya no ofrece completar actividades: eso vive en Ajustes', () => {
    assert.equal(publicUi.includes('data-profile-register'), false);
    assert.equal(publicUi.includes('resolveProfileRegister'), false);
    assert.ok(publicUi.includes('href="ajustes"'));
});

test('Ajustes activa Transportista ahí mismo y Vendedor abre solo su formulario', async () => {
    const { necesitaFormulario, mensajeActivacion } = await import('../../Public/js/ajustes.js');
    assert.equal(necesitaFormulario('PRODUCTOR'), true);
    assert.equal(necesitaFormulario('TRANSPORTISTA'), false);
    assert.equal(necesitaFormulario('COMPRADOR'), false);
    assert.match(mensajeActivacion('TRANSPORTISTA', true), /ya ofreces fletes/);
    assert.ok(ajustesJs.includes('href="registro/productor?next=ajustes">Configurar</a>'));
    // Misma ampliación de cuenta que hacía el formulario, sin datos extra.
    assert.ok(ajustesJs.includes("request(REGISTRO_API, { method: 'POST', body: JSON.stringify({ capacidades: [id], fincas: [] }) })"));
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
