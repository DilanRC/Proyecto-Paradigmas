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

test('cambiosPerfil compara nombre, identificación y correo como el servidor', async () => {
    const { cambiosPerfil } = await import('../../Public/js/ajustes.js');
    const persona = { nombre: 'Ana Pérez', identificacionTipo: 'CEDULA_FISICA', identificacionNumero: '111111111', correoElectronico: 'ana@ejemplo.cr' };
    const iguales = { nombre: ' Ana  Pérez ', identificacionTipo: 'CEDULA_FISICA', identificacionNumero: '1-1111-1111', correoElectronico: 'ANA@ejemplo.cr' };
    assert.deepEqual(cambiosPerfil(iguales, persona), {}, 'guiones, espacios y mayúsculas no son cambios');
    assert.deepEqual(cambiosPerfil({ ...iguales, identificacionTipo: 'PASAPORTE', identificacionNumero: '111111111' }, persona),
        { identificacion: { tipoCodigo: 'PASAPORTE', numero: '111111111' } }, 'cambiar solo el tipo también cuenta');
    assert.deepEqual(cambiosPerfil({ ...iguales, correoElectronico: ' Nuevo@Ejemplo.cr ' }, persona), { correoElectronico: 'nuevo@ejemplo.cr' });
    assert.deepEqual(cambiosPerfil({ ...iguales, nombre: 'Ana María Pérez' }, persona), { nombre: 'Ana María Pérez' });
});

test('Ajustes → Perfil edita nombre, identificación y correo con las reglas compartidas', () => {
    for (const nombre of ['nombre', 'identificacionTipo', 'identificacionNumero', 'correoElectronico']) {
        assert.ok(view.includes(`name="${nombre}"`), `falta el campo ${nombre}`);
    }
    assert.ok(view.includes('data-profile-error="identificacion.numero"'), 'el error del servidor tiene su lugar');
    assert.match(js, /errorIdentificacion/, 'la identificación usa las reglas del servidor');
    assert.match(js, /telefonoValido/, 'el teléfono usa telefono.js');
    assert.match(js, /refreshAuthSession\(\)/, 'con el correo nuevo se renueva la sesión');
    assert.match(view, /js\/ajustes\.js\?v=ajustes-14/);
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
