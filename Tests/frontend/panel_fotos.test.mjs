import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8').replace(/\r\n/g, '\n');
const js = read('Public/js/mi-actividad.js');
const vista = read('Application/View/mi-actividad/index.php');

test('los diálogos de vehículo y publicación tienen el campo de foto', () => {
    for (const x of ['vehicle', 'publication']) {
        assert.match(vista, new RegExp(`data-foto-campo="${x}"`));
        assert.match(vista, new RegExp(`id="${x}-foto" type="file" accept="image/jpeg,image/png,image/webp"`));
    }
    assert.match(js, /montarCampoFoto\(document\.querySelector\('\[data-foto-campo="vehicle"\]'\)\)/);
    assert.match(js, /montarCampoFoto\(document\.querySelector\('\[data-foto-campo="publication"\]'\)\)/);
});

test('fotoUrl e imagenUrl solo viajan si la foto cambió (undefined conserva la guardada)', () => {
    assert.match(js, /if \(fotoUrl !== undefined\) datos\.fotoUrl = fotoUrl;/);
    assert.match(js, /\.\.\.\(imagenUrl !== undefined && \{ imagenUrl \}\)/);
});

test('las tarjetas de vehículo muestran su foto con la misma miniatura', () => {
    assert.match(js, /miniatura\(\{ imagenUrl: vehicle\.fotoUrl \}, 'fa-truck'\)/);
});

test('el campo de foto sube con el bucket de publicaciones y valida el archivo', () => {
    const campo = read('Public/js/shared/foto-campo.js');
    assert.match(campo, /validarImagen/);
    assert.match(campo, /subirImagenPublicacion\(archivo\)/);
    assert.match(campo, /return quitar \? null : undefined;/);
});
