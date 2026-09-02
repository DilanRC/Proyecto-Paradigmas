import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const adminJs = read('../../Public/js/shared/admin-ui.js');
const sidebarCss = read('../../Public/css/admin-sidebar-collapse.css');

test('el sidebar administrativo tiene estado expandido y colapsado persistente', () => {
    assert.ok(adminJs.includes("export const ADMIN_SIDEBAR_KEY = 'tindercows:admin-sidebar-collapsed'"));
    assert.ok(adminJs.includes("dataset.tcSidebar = collapsed ? 'collapsed' : 'expanded'"));
    assert.ok(adminJs.includes('storage?.setItem(ADMIN_SIDEBAR_KEY, String(collapsed))'));
    assert.ok(adminJs.includes('readSidebarCollapsed()'));
});

test('el control de colapso es accesible y conserva reconocimiento', () => {
    assert.ok(adminJs.includes("toggle.setAttribute('aria-controls', sidebar.id)"));
    assert.ok(adminJs.includes("toggle.setAttribute('aria-expanded', String(expanded))"));
    assert.ok(adminJs.includes("'Expandir menú lateral'"));
    assert.ok(adminJs.includes("'Contraer menú lateral'"));
    assert.ok(adminJs.includes('data-sidebar-label') || adminJs.includes('dataset.sidebarLabel'));
});

test('el rail compacto reduce ancho y centra iconos sin alterar el móvil', () => {
    assert.ok(sidebarCss.includes('--admin-sidebar-collapsed-width:82px'));
    assert.ok(sidebarCss.includes("html[data-tc-sidebar='collapsed']"));
    assert.ok(sidebarCss.includes('grid-template-columns:var(--admin-sidebar-current-width) minmax(0,1fr)'));
    assert.ok(sidebarCss.includes("html[data-tc-sidebar='collapsed'] .rural-panel__nav-item"));
    assert.ok(sidebarCss.includes('@media (max-width:900px)'));
    assert.ok(sidebarCss.includes('.admin-sidebar-toggle {\n        display:none !important;'));
});

test('la capa del sidebar se carga desde el bootstrap compartido', () => {
    assert.ok(adminJs.includes("const ADMIN_SIDEBAR_CSS = 'css/admin-sidebar-collapse.css?v=sidebar-1'"));
    assert.ok(adminJs.includes("injectStylesheet(ADMIN_SIDEBAR_CSS, 'data-tc-admin-sidebar')"));
    assert.ok(adminJs.includes('enhanceSidebarCollapse();'));
});
