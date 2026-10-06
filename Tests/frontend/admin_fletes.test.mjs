import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const htaccess = read('Public/.htaccess');
const view = read('Application/View/adminfletes/index.php');

test('/admin/fletes tiene ruta, API admin y guarda de sesión', () => {
    assert.match(htaccess, /RewriteRule \^admin\/fletes\/\?\$ adminfletes\.php \[END\]/);
    assert.match(htaccess, /RewriteRule \^api\/v1\/admin\/fletes\/\?\$ api\/admin-fletes\.php \[END\]/);
    assert.ok(read('Public/js/shared/auth-gate.js').includes("'admin/fletes'"));
    assert.ok(read('Public/js/login.js').includes("'admin/fletes'"));
    assert.ok(read('Public/js/shared/admin-ui.js').includes("'admin/fletes'"));
    assert.ok(read('Public/api/admin-fletes.php').includes('AdminAuthorization::require'));
    // La cadena de versiones (api.js, auth-gate, admin-ui) la comprueba admin_cache_chain.test.mjs.
});

test('todas las vistas admin enlazan a Fletes', () => {
    for (const vista of ['dashboard', 'productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos', 'publicaciones', 'administradores', 'bitacora', 'adminfletes']) {
        assert.ok(read(`Application/View/${vista}/index.php`).includes('href="admin/fletes"'), `falta el enlace en ${vista}`);
    }
});

test('la pantalla tiene las dos vistas, el filtro de estado y pide motivo para retirar', () => {
    for (const id of ['cuerpo-ofertas', 'cuerpo-solicitudes', 'busqueda-flete', 'filtro-vista', 'filtro-estado', 'formulario-moderar', 'motivo', 'error-motivo']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
    assert.ok(view.includes('name="motivo"') && view.includes('required'));
    assert.ok(view.includes('value="OFERTAS"') && view.includes('value="SOLICITUDES"'));
    // Solicitudes es de solo lectura: su tabla no tiene columna de acciones.
    const tablaSolicitudes = view.split('id="tabla-solicitudes"')[1].split('</table>')[0];
    assert.equal(tablaSolicitudes.includes('Acciones'), false);
});

test('la consulta, la moderación y los formatos se arman como espera el API', async () => {
    const { buildConsulta, buildModeracionPayload, formatPrecio, formatZona } = await import('../../Public/js/adminfletes.js');
    assert.deepEqual(buildConsulta({ vista: 'OFERTAS' }), { vista: 'OFERTAS', pagina: 1, tamanoPagina: 25 });
    assert.deepEqual(buildConsulta({ vista: 'SOLICITUDES', q: ' toro ', estado: 'ACEPTADA', pagina: 2 }),
        { vista: 'SOLICITUDES', pagina: 2, tamanoPagina: 25, q: 'toro', estado: 'ACEPTADA' });
    assert.deepEqual(buildModeracionPayload({ ofertaId: '7', estado: 'RETIRADA', motivo: '  Precio engañoso ' }),
        { ofertaId: 7, estado: 'RETIRADA', motivo: 'Precio engañoso' });
    assert.deepEqual(buildModeracionPayload({ ofertaId: 7, estado: 'ACTIVA' }), { ofertaId: 7, estado: 'ACTIVA' });
    assert.equal(formatPrecio(150000), '₡150.000');
    assert.equal(formatPrecio(null), 'A convenir');
    assert.equal(formatZona({ provincia: 'San José', canton: 'Central', distrito: null, pueblo: 'Centro' }), 'San José, Central, Centro');
    assert.equal(formatZona({}), '—');
});

test('el transportista ve la oferta retirada como retirada por un administrador y la API no la deja tocar', () => {
    const fletes = read('Public/js/fletes.js');
    assert.ok(fletes.includes('Retirada por un administrador'));
    assert.ok(read('Application/Controller/MiOfertasController.php').includes('ESTADO_RETIRADA'));
});
