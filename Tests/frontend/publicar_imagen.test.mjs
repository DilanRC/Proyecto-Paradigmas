// Publicar con foto: subida a Supabase Storage o URL https, y la foto en las tarjetas.
//
// Ejecutar: node --test Tests/frontend/publicar_imagen.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('solo URLs https llegan a un <img>', async () => {
    const { safeImageUrl } = await import('../../Public/js/explore.js');
    assert.equal(safeImageUrl('https://upload.wikimedia.org/a.jpg'), 'https://upload.wikimedia.org/a.jpg');
    for (const mala of ['http://example.com/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AA', '', null, 'a.jpg']) {
        assert.equal(safeImageUrl(mala), null, `debe rechazar ${mala}`);
    }
});

test('la tarjeta muestra la foto y conserva el ícono si la imagen falla', () => {
    const explore = read('Public/js/explore.js');
    assert.match(explore, /const imagen = safeImageUrl\(publicacion\?\.imagenUrl\);/);
    assert.match(explore, /foto\.addEventListener\('error', \(\) => foto\.remove\(\)/);
    assert.match(read('Public/css/explore.css'), /\.explore-card__photo \{[\s\S]*object-fit:cover;/);
    // Mi panel también muestra la miniatura.
    assert.match(read('Public/js/mi-actividad.js'), /class="panel-thumb" src="\$\{escapeHtml\(url\)\}"/);
});

test('la imagen del dispositivo se valida y va a la carpeta de su dueño', async () => {
    const { validarImagen, usuarioDelToken, extensionDe, MAXIMO_BYTES } = await import('../../Public/js/shared/storage.js');
    assert.equal(validarImagen({ type: 'image/jpeg', size: 1000 }), null);
    assert.match(validarImagen({ type: 'image/gif', size: 1000 }), /JPG, PNG o WebP/);
    assert.match(validarImagen({ type: 'image/png', size: MAXIMO_BYTES + 1 }), /5 MB/);
    assert.match(validarImagen(null), /Elige una imagen/);
    const carga = Buffer.from(JSON.stringify({ sub: 'user-123' })).toString('base64url');
    assert.equal(usuarioDelToken(`h.${carga}.f`), 'user-123');
    assert.equal(usuarioDelToken('no-es-jwt'), null);
    assert.equal(extensionDe('image/webp'), 'webp');
    const storage = read('Public/js/shared/storage.js');
    assert.match(storage, /const ruta = `\$\{usuario\}\/\$\{nuevoUuid\(\)\}/);
    assert.match(storage, /storage\/v1\/object\/public\/\$\{BUCKET_PUBLICACIONES\}/);
});

test('el formulario envía imagenUrl solo cuando hay foto y nunca el archivo', async () => {
    const { cuerpoPublicacion } = await import('../../Public/js/publicar.js');
    const draft = { titulo: 'Novilla', fincaNombre: 'La Esperanza', imagenModo: 'url', imagenUrl: 'https://x.test/a.jpg' };
    assert.deepEqual(cuerpoPublicacion(draft, 'https://x.test/a.jpg'),
        { titulo: 'Novilla', fincaNombre: 'La Esperanza', imagenUrl: 'https://x.test/a.jpg' });
    assert.deepEqual(cuerpoPublicacion(draft, null), { titulo: 'Novilla', fincaNombre: 'La Esperanza' });
    const js = read('Public/js/publicar.js');
    assert.match(js, /\.filter\(\(\[, value\]\) => typeof value === 'string'\)/, 'un File no se serializa');
});

test('Publicar usa el sistema de formulario y los colores del sitio', () => {
    const vista = read('Application/View/publicar/index.php');
    assert.doesNotMatch(vista, /front2-flow\.css/);
    assert.match(vista, /css\/onboarding\.css/);
    assert.match(vista, /type="file" accept="image\/jpeg,image\/png,image\/webp"/);
    assert.match(vista, /name="imagenUrl" type="url"/);
    assert.match(vista, /value="archivo" checked/);
    assert.match(vista, /maxlength="500"/, 'la descripción coincide con el límite del backend');
});

test('el nombre del archivo es un UUID v4 aunque la página no sea segura (celular por http://IP-local)', async () => {
    const { nuevoUuid } = await import('../../Public/js/shared/storage.js');
    const formato = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
    // Sin randomUUID (contexto no seguro): se arma con getRandomValues.
    const sinRandomUuid = { getRandomValues: (a) => globalThis.crypto.getRandomValues(a) };
    for (let i = 0; i < 50; i++) assert.match(nuevoUuid(sinRandomUuid), formato);
    assert.match(nuevoUuid(), formato);
    // Es el formato que exige el servidor (MiPerfilController) para la ruta del documento.
    assert.notEqual(nuevoUuid(sinRandomUuid), nuevoUuid(sinRandomUuid));
});
