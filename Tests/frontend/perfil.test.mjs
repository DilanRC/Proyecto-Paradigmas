import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const view = read('Application/View/ajustes/index.php');
const js = read('Public/js/ajustes.js');

test('cambiosPerfil envía solo lo que cambió y un alias vacío se convierte en null', async () => {
    const { cambiosPerfil } = await import('../../Public/js/ajustes.js');
    const persona = { alias: 'Chepe', telefono: '+50688887777' };
    assert.deepEqual(cambiosPerfil({ alias: 'Chepe', telefono: '+50688887777' }, persona), {});
    assert.deepEqual(cambiosPerfil({ alias: ' Don Chepe ', telefono: '+50688887777' }, persona), { alias: 'Don Chepe' });
    assert.deepEqual(cambiosPerfil({ alias: '', telefono: '+50670001234' }, persona), { alias: null, telefono: '+50670001234' });
    assert.deepEqual(cambiosPerfil({ alias: '', telefono: '' }, { alias: null, telefono: '' }), {});
});

test('Ajustes → Perfil permite cambiar y quitar la foto y editar alias y teléfono', () => {
    for (const id of ['profile-avatar', 'profile-photo-file', 'profile-photo-change', 'profile-photo-remove', 'profile-form', 'profile-edit']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
    assert.ok(view.includes('accept="image/jpeg,image/png,image/webp"'));
    assert.ok(js.includes("const PERFIL_API = 'api/v1/mi-perfil'"));
    assert.ok(js.includes("method: 'PATCH'"));
    assert.ok(js.includes('subirImagenPublicacion') && js.includes('validarImagen'), 'la subida reutiliza storage.js');
    assert.ok(js.includes('fotoUrl: null'), 'quitar la foto manda null');
});

test('la API y el encabezado usan la foto solo si es https', () => {
    assert.match(read('Public/.htaccess'), /RewriteRule \^api\/v1\/mi-perfil\/\?\$ api\/mi-perfil\.php \[END\]/);
    const ui = read('Public/js/public-ui.js');
    assert.ok(ui.includes('safeHttpsUrl(profile?.persona?.fotoUrl)'));
    assert.ok(ui.includes("url.protocol === 'https:'"));
    assert.ok(read('Application/Controller/MiPerfilController.php').includes("imagenUrl($cuerpo['fotoUrl'], 'fotoUrl')"));
});

test('P2-5: el documento va al bucket privado y PHP solo recibe su ruta', async () => {
    const { validarDocumento, extensionDe, BUCKET_DOCUMENTOS, MAXIMO_BYTES } = await import('../../Public/js/shared/storage.js');
    assert.equal(BUCKET_DOCUMENTOS, 'documentos');
    assert.equal(validarDocumento({ type: 'application/pdf', size: 1000 }), null);
    assert.equal(validarDocumento({ type: 'image/jpeg', size: 1000 }), null);
    assert.match(validarDocumento({ type: 'image/gif', size: 1000 }), /PDF/);
    assert.match(validarDocumento({ type: 'application/pdf', size: MAXIMO_BYTES + 1 }), /5 MB/);
    assert.equal(extensionDe('application/pdf'), 'pdf', 'el servidor rechaza una ruta .jpg para un PDF');
    const storage = read('Public/js/shared/storage.js');
    // El documento nunca arma una URL pública.
    assert.doesNotMatch(storage, /object\/public\/\$\{BUCKET_DOCUMENTOS\}/);
    assert.ok(view.includes('accept="image/jpeg,image/png,image/webp,application/pdf"'));
    // La subida (y la lectura del número) pasan por shared/escaner-documento.js.
    assert.ok(js.includes('await prepararEnvioDocumento(archivo, {'));
    assert.match(read('Public/js/shared/escaner-documento.js'), /documentoRuta: await subirDocumentoIdentidad\(archivo\)/);
});

test('P2-5: Ajustes muestra el estado del documento sin exponer la ruta', async () => {
    const { textoDocumento } = await import('../../Public/js/ajustes.js');
    assert.match(textoDocumento(null), /Todavía no/);
    assert.match(textoDocumento({ estado: 'PENDIENTE' }), /revisión/);
    assert.match(textoDocumento({ estado: 'VERIFICADO' }), /Verificado/);
    assert.match(textoDocumento({ estado: 'RECHAZADO' }), /más clara/);
    // P2-6: si el admin dejó motivo, la persona lo ve.
    assert.equal(textoDocumento({ estado: 'RECHAZADO', motivo: 'La foto está borrosa' }), 'No se pudo verificar: La foto está borrosa. Sube otro documento.');
    assert.ok(view.includes('id="profile-document-state"') && view.includes('role="status"'));
    assert.doesNotMatch(js, /persona\??\.documentoRuta|documento\??\.ruta/, 'la pantalla nunca lee ni muestra la ruta guardada');
});
