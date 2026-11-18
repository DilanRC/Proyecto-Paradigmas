// Las vistas no deben volver a servir los originales pesados (logos PNG de ~1 MB
// mostrados a 48 px, foto de Inicio de 3 MB, fondo JPG de 1 MB).
//
// Ejecutar: node --test Tests/frontend/assets_livianos.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const raiz = new URL('../../', import.meta.url).pathname;
function archivos(directorio, extension) {
    return readdirSync(join(raiz, directorio), { recursive: true })
        .filter((ruta) => String(ruta).endsWith(extension))
        .map((ruta) => join(directorio, String(ruta)));
}

test('ninguna vista, hoja o módulo referencia los originales pesados', () => {
    const pesados = /assets\/(logo_dark\.png|logo_light\.png|hero-ganado-cerca\.png|fondo-para-publicaciones\.jpg)/;
    for (const ruta of [...archivos('Application/View', '.php'), ...archivos('Public/css', '.css'), ...archivos('Public/js', '.js')]) {
        assert.doesNotMatch(readFileSync(join(raiz, ruta), 'utf8'), pesados, ruta);
    }
});

test('cada logo usa la variante del tamaño en que se muestra', () => {
    for (const ruta of archivos('Application/View', '.php')) {
        const html = readFileSync(join(raiz, ruta), 'utf8');
        for (const [, variante, ancho] of html.matchAll(/assets\/logo_(?:dark|light)-(\d+)\.webp"[^>]*width="(\d+)"/g)) {
            assert.ok(Number(variante) >= Number(ancho) * 2, `${ruta}: logo de ${variante} px para ${ancho} px de ancho`);
            assert.ok(Number(variante) <= Number(ancho) * 8, `${ruta}: logo de ${variante} px es demasiado grande para ${ancho} px`);
        }
    }
    for (const archivo of readdirSync(join(raiz, 'Public/assets')).filter((nombre) => nombre.endsWith('.webp'))) {
        assert.ok(statSync(join(raiz, 'Public/assets', archivo)).size < 450000, `${archivo} supera 450 KB`);
    }
});

test('Apache declara el tipo de las imágenes WebP (XAMPP con mime.types viejo)', () => {
    assert.match(readFileSync(join(raiz, 'Public/.htaccess'), 'utf8'), /AddType image\/webp \.webp/);
});
