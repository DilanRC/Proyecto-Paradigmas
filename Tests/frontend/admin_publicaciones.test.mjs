import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const htaccess = read('Public/.htaccess');
const view = read('Application/View/publicaciones/index.php');

test('/admin/publicaciones tiene ruta, API admin y guarda de sesión', () => {
    assert.match(htaccess, /RewriteRule \^admin\/publicaciones\/\?\$ publicaciones\.php \[END\]/);
    assert.match(htaccess, /RewriteRule \^api\/v1\/admin\/publicaciones\/\?\$ api\/admin-publicaciones\.php \[END\]/);
    assert.ok(read('Public/js/shared/auth-gate.js').includes("'admin/publicaciones'"));
    assert.ok(read('Public/js/login.js').includes("'admin/publicaciones'"));
    assert.ok(read('Public/js/shared/admin-ui.js').includes("'admin/publicaciones'"));
    assert.ok(read('Public/api/admin-publicaciones.php').includes('AdminAuthorization::require'));
    // Sin este versionado, un api.js en caché deja el panel oculto (visibility:hidden) en blanco.
    assert.ok(read('Public/js/publicaciones.js').includes("from './shared/api.js?v=auth-gate-5'"));
});

test('todas las vistas admin enlazan a Publicaciones', () => {
    for (const vista of ['dashboard', 'productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos', 'publicaciones']) {
        assert.ok(read(`Application/View/${vista}/index.php`).includes('href="admin/publicaciones"'), `falta el enlace en ${vista}`);
    }
});

test('la pantalla pide motivo para pausar o retirar y no ofrece crear', () => {
    for (const id of ['cuerpo-publicaciones', 'busqueda-publicacion', 'filtro-estado', 'formulario-moderar', 'motivo', 'error-motivo']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
    assert.ok(view.includes('name="motivo"') && view.includes('required'));
    assert.equal(view.includes('crear-'), false);
});

test('el cuerpo de moderación y el precio se arman como espera el API', async () => {
    const { buildModeracionPayload, formatPrecio } = await import('../../Public/js/publicaciones.js');
    assert.deepEqual(buildModeracionPayload({ publicacionId: '7', estado: 'PAUSADO', motivo: '  Foto incorrecta ' }),
        { publicacionId: 7, estado: 'PAUSADO', motivo: 'Foto incorrecta' });
    assert.deepEqual(buildModeracionPayload({ publicacionId: 7, estado: 'ACTIVO' }), { publicacionId: 7, estado: 'ACTIVO' });
    assert.equal(formatPrecio(1250000), '₡1.250.000');
    assert.equal(formatPrecio(null), 'A convenir');
});
