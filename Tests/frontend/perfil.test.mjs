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
