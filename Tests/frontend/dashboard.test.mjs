import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('dashboard administrativo tiene ruta limpia y entrada protegida', () => {
    const routes = read('Public/.htaccess');
    const gate = read('Public/js/shared/auth-gate.js');
    const login = read('Public/js/login.js');
    assert.ok(routes.includes('RewriteRule ^admin/dashboard/?$ dashboard.php [END]'));
    assert.match(gate, /'admin\/dashboard'/);
    assert.match(login, /'admin\/dashboard'/);
});

test('dashboard centraliza indicadores de APIs reales sin exponer identificaciones', () => {
    const view = read('Application/View/dashboard/index.php');
    const js = read('Public/js/dashboard.js');
    assert.ok(view.includes('id="dashboard-metrics"'));
    for (const asset of [
        'finca-camino-costa-rica.png',
        'finca-potrero-costa-rica.png',
        'finca-bebedero-costa-rica.png',
        'subasta-pasarela-costa-rica.png',
        'subasta-corrales-costa-rica.png',
        'subasta-rematador-costa-rica.png',
    ]) assert.ok(view.includes(asset), `falta la escena ${asset}`);
    for (const endpoint of ['api/v1/productores', 'api/v1/compradores', 'api/v1/transportistas', 'api/v1/vehiculos']) {
        assert.ok(js.includes(endpoint), `falta el indicador ${endpoint}`);
    }
    assert.match(js, /method: 'POST'/);
    assert.equal(/identificacionNumero|correoElectronico|telefono|password|token/.test(js), false);
});

test('los assets del dashboard son locales y no placeholders vacíos', () => {
    for (const path of [
        'Public/assets/dashboard/finca-camino-costa-rica.png',
        'Public/assets/dashboard/finca-potrero-costa-rica.png',
        'Public/assets/dashboard/finca-bebedero-costa-rica.png',
        'Public/assets/dashboard/subasta-pasarela-costa-rica.png',
        'Public/assets/dashboard/subasta-corrales-costa-rica.png',
        'Public/assets/dashboard/subasta-rematador-costa-rica.png',
    ]) assert.ok(statSync(new URL(`../../${path}`, import.meta.url)).size > 10000, path);
});
