import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const view = read('Application/View/documentos/index.php');
const js = read('Public/js/documentos.js');

test('/admin/documentos tiene ruta, API admin y la marca activa en su menú', () => {
    const htaccess = read('Public/.htaccess');
    assert.match(htaccess, /RewriteRule \^admin\/documentos\/\?\$ documentos\.php \[END\]/);
    assert.match(htaccess, /RewriteRule \^api\/v1\/admin\/documentos\/\?\$ api\/admin-documentos\.php \[END\]/);
    const endpoint = read('Public/api/admin-documentos.php');
    assert.ok(endpoint.includes('AdminAuthorization::require'));
    assert.ok(endpoint.includes("header('Cache-Control: no-store, private')"), 'documentos nunca en caché');
    assert.ok(endpoint.indexOf('AdminAuthorization::require') < endpoint.indexOf("array_key_exists('consulta'"), 'la autorización va primero');
    assert.match(view, /rural-panel__nav-item--active" href="admin\/documentos"/);
    assert.equal((view.match(/rural-panel__nav-item--active/g) ?? []).length, 1);
});

test('rechazar pide motivo y verificar pide confirmación', () => {
    for (const id of ['cuerpo-documentos', 'filtro-estado', 'formulario-rechazar', 'motivo', 'error-motivo', 'modal-verificar']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
    assert.match(view, /id="motivo" name="motivo" maxlength="250"[^>]*required/);
    assert.match(view, /<option value="PENDIENTE">Pendientes<\/option>/, 'por defecto se ven las pendientes');
});

test('solo un pendiente se decide; el resto solo se puede ver', async () => {
    const { accionesDocumento, formatIdentificacion } = await import('../../Public/js/documentos.js');
    assert.deepEqual(accionesDocumento({ estado: 'PENDIENTE' }), ['ver', 'verificar', 'rechazar']);
    assert.deepEqual(accionesDocumento({ estado: 'VERIFICADO' }), ['ver']);
    assert.deepEqual(accionesDocumento({ estado: 'RECHAZADO' }), ['ver']);
    assert.equal(formatIdentificacion({ identificacionTipo: 'CEDULA_FISICA', identificacionNumero: '111111111' }), 'Cédula física · 111111111');
});

test('el documento se abre en una pestaña sin acceso al panel y nada se pinta como HTML', () => {
    assert.match(js, /const pestaña = window\.open\('', '_blank'\);\n\s*if \(pestaña\) pestaña\.opener = null;/);
    assert.match(js, /pestaña\.location\.replace\(response\.data\.url\)/);
    assert.doesNotMatch(js, /innerHTML/);
    // La ruta del archivo nunca viaja desde el navegador: solo el personaId.
    assert.match(js, /JSON\.stringify\(\{ personaId \}\)/);
});
