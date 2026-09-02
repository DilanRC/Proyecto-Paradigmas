import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const adminCss = read('../../Public/css/admin-v3.css');
const adminJs = read('../../Public/js/shared/admin-ui.js');
const apiJs = read('../../Public/js/shared/api.js');
const publicCss = read('../../Public/css/public-auth.css');

const views = ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos'];

test('el admin comparte la paleta de los logos con el sitio publico', () => {
    for (const token of ['#151a18', '#2f3c2d', '#d24f28', '#eedbca', '#fef7ec', '#394332']) {
        assert.ok(publicCss.includes(token), `el sitio público debe conservar ${token}`);
        assert.ok(adminCss.includes(token), `el admin debe compartir ${token}`);
    }
    assert.ok(adminCss.includes("html[data-theme='light'] body.rural-panel"));
});

test('el shell existente se mejora desde un bootstrap compartido, no con cinco implementaciones', () => {
    assert.ok(apiJs.startsWith("import './auth-gate.js';\nimport './admin-ui.js';"));
    assert.ok(adminJs.includes("const ADMIN_CSS = 'css/admin-v3.css?v=admin-3'"));
    assert.ok(adminJs.includes('enhanceSidebarAccount();'));
    assert.ok(adminJs.includes('enhancePageHeader();'));
    assert.ok(adminJs.includes('enhanceFilters();'));
    assert.ok(adminJs.includes('enhanceTable();'));

    for (const view of views) {
        const html = read(`../../Application/View/${view}/index.php`);
        assert.equal(html.includes('admin-v3.css'), false, `${view} no debe duplicar imports del shell`);
    }
});

test('la navegación y utilidades mantienen icono mas texto visible', () => {
    assert.ok(adminCss.includes('.rural-panel__nav-item::before'));
    assert.ok(adminCss.includes('.admin-account-card__actions .rural-panel__admin-link'));
    assert.ok(adminJs.includes("siteLink.textContent = 'Ir al sitio'"));
    assert.ok(adminJs.includes("logoutLink.textContent = 'Cerrar sesión'"));
    assert.ok(adminJs.includes('theme-toggle__label'));
});

test('los filtros usan solo capacidades reales y ofrecen limpiar sin reload', () => {
    assert.ok(adminJs.includes("'productores.php':"));
    assert.ok(adminJs.includes("search: 'Nombre, identificación, correo'"));
    assert.ok(adminJs.includes("search: 'Placa, VIN o modelo'"));
    assert.ok(adminJs.includes("search: 'Nombre o descripción'"));
    assert.ok(adminJs.includes("className = 'admin-clear-filters'"));
    assert.ok(adminJs.includes("dispatchEvent(new Event('input'"));
    assert.ok(adminJs.includes("dispatchEvent(new Event('change'"));
    assert.equal(adminJs.includes('location.reload'), false, 'limpiar filtros no debe recargar la página');
});

test('acciones de tabla quedan centradas y no vuelven a ser iconos sin texto', () => {
    assert.ok(adminCss.includes('.rural-panel td.row-actions {'));
    assert.ok(adminCss.includes('text-align:center !important;'));
    assert.ok(adminCss.includes('.rural-panel .row-actions .action {'));
    assert.ok(adminCss.includes('display:inline-flex !important;'));
    assert.ok(adminCss.includes('font-size:10px !important;'));
    assert.equal(adminCss.includes('.rural-panel .row-actions .action {\n    font-size:0'), false);
    assert.ok(adminJs.includes("label.className = 'admin-actions-heading'"));
});

test('tabla y panel contemplan desktop y adaptación a tarjetas en móvil', () => {
    assert.ok(adminJs.includes("table.classList.add('admin-table')"));
    assert.ok(adminCss.includes('@media (max-width:900px)'));
    assert.ok(adminCss.includes('content:attr(data-label)'));
    assert.ok(adminCss.includes('overflow-x:auto !important;'));
});
