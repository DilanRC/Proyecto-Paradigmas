import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const adminJs = read('../../Public/js/shared/admin-ui.js');
const css = read('../../Public/css/admin-refinements.css');

test('la cuenta del sidebar se presenta como dropdown compacto', () => {
    assert.ok(adminJs.includes("card.className = 'admin-account-card admin-account-menu'"));
    assert.ok(adminJs.includes("trigger.className = 'admin-account-menu__trigger'"));
    assert.ok(adminJs.includes("trigger.setAttribute('aria-haspopup', 'menu')"));
    assert.ok(adminJs.includes("panel.className = 'admin-account-menu__panel'"));
    assert.ok(adminJs.includes("'fa-chevron-up'"));
    assert.ok(css.includes('.admin-account-menu__panel[hidden]'));
});

test('el dropdown contiene sitio, tema y cierre de sesión y no muestra correo permanentemente', () => {
    assert.ok(adminJs.includes("siteLink.textContent = 'Ir al sitio'"));
    assert.ok(adminJs.includes("logoutLink.textContent = 'Cerrar sesión'"));
    assert.ok(adminJs.includes('theme.dataset.themeToggle'));
    assert.ok(adminJs.includes('sessionDisplayName()'));
    assert.equal(adminJs.includes("copy.append(title, email)"), false);
});

test('la paginación se mueve después de table-container', () => {
    assert.ok(adminJs.includes("footer.className = 'admin-table-footer'"));
    assert.ok(adminJs.includes("tableContainer.insertAdjacentElement('afterend', footer)"));
    assert.ok(adminJs.includes('footer.appendChild(pagination)'));
    assert.ok(css.includes('.admin-table-footer .pagination'));
});
