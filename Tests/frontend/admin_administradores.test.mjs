import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const htaccess = read('Public/.htaccess');
const view = read('Application/View/administradores/index.php');
const VISTAS_ADMIN = ['dashboard', 'productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos', 'publicaciones', 'administradores'];

test('/admin/administradores tiene ruta, API admin y guarda de sesión', () => {
    assert.match(htaccess, /RewriteRule \^admin\/administradores\/\?\$ administradores\.php \[END\]/);
    assert.match(htaccess, /RewriteRule \^api\/v1\/admin\/administradores\/\?\$ api\/admin-administradores\.php \[END\]/);
    assert.ok(read('Public/js/shared/auth-gate.js').includes("'admin/administradores'"));
    assert.ok(read('Public/js/login.js').includes("'admin/administradores'"));
    assert.ok(read('Public/js/shared/admin-ui.js').includes("'admin/administradores'"));
    assert.ok(read('Public/api/admin-administradores.php').includes('AdminAuthorization::require'));
    // auth-gate conoce la ruta nueva: su versión sube en todos los que lo importan.
    assert.ok(read('Public/js/administradores.js').includes("from './shared/api.js?v=auth-gate-6'"));
    assert.ok(read('Public/js/shared/api.js').includes("import './auth-gate.js?v=auth-gate-6'"));
    assert.ok(read('Public/js/shared/admin-ui.js').includes("from './auth-gate.js?v=auth-gate-6'"));
    assert.ok(read('Public/js/login.js').includes("from './shared/auth-gate.js?v=auth-gate-6'"));
});

test('todas las vistas admin enlazan a Administradores y la propia lo marca activo', () => {
    for (const vista of VISTAS_ADMIN) {
        assert.ok(read(`Application/View/${vista}/index.php`).includes('href="admin/administradores"'), `falta el enlace en ${vista}`);
    }
    assert.match(view, /rural-panel__nav-item--active" href="admin\/administradores"/);
    assert.equal((view.match(/rural-panel__nav-item--active/g) ?? []).length, 1, 'un solo ítem activo');
});

test('la pantalla agrega por correo y confirma antes de desactivar', () => {
    for (const id of ['cuerpo-administradores', 'agregar-administrador', 'formulario-administrador', 'correoElectronico', 'error-correoElectronico', 'modal-desactivar']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
    assert.match(view, /name="correoElectronico" type="email" maxlength="150"[^>]*required/);
});

test('no se ofrece quitarse el propio acceso ni dejar el panel sin administradores', async () => {
    const { accionAdministrador, resumenAdministradores } = await import('../../Public/js/administradores.js');
    assert.equal(accionAdministrador({ estado: 'ACTIVO', esUsted: false }, 2), 'desactivar');
    assert.equal(accionAdministrador({ estado: 'ACTIVO', esUsted: true }, 2), null, 'no a sí mismo');
    assert.equal(accionAdministrador({ estado: 'ACTIVO', esUsted: false }, 1), null, 'no al último activo');
    assert.equal(accionAdministrador({ estado: 'INACTIVO', esUsted: false }, 1), 'reactivar');
    assert.equal(resumenAdministradores([{ estado: 'ACTIVO' }, { estado: 'INACTIVO' }]), '1 administrador activo de 2.');
    assert.equal(resumenAdministradores([]), 'No hay administradores registrados.');
});
