// Mis animales (P2-4): sección de Mi panel, acciones por estado y ruta de la API.
//
// Ejecutar: node --test Tests/frontend/mis_animales.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('las acciones dependen del estado del animal', async () => {
    const { accionesAnimal, nombreAnimal } = await import('../../Public/js/mis-animales.js');
    const activo = accionesAnimal({ animalId: 7, estado: 'ACTIVO' });
    assert.match(activo, /data-animal-accion="vacunas"/);
    assert.match(activo, /data-animal-accion="publicar"/);
    const publicado = accionesAnimal({ animalId: 7, estado: 'PUBLICADO', publicacion: { publicacionId: 31 } });
    assert.doesNotMatch(publicado, /data-animal-accion="publicar"/, 'uno publicado no se publica dos veces');
    assert.match(publicado, /href="explorar\?publicacion=31"/);
    assert.equal(accionesAnimal({ animalId: 7, estado: 'VENDIDO' }), '', 'vendido: sin acciones');
    assert.equal(nombreAnimal({ animalId: 7, arete: '1880010002345' }), 'Arete 188 0 01 0002345');
    assert.equal(nombreAnimal({ animalId: 7, tipo: { nombre: 'Vaca' }, raza: 'Jersey' }), 'Vaca · Jersey');
    assert.equal(nombreAnimal({ animalId: 7 }), 'Animal #7');
});

test('Mi panel carga la sección y sus diálogos', () => {
    const vista = read('Application/View/mi-actividad/index.php');
    for (const id of ['animals-panel', 'animal-add', 'animal-modal', 'animal-publish-modal', 'animal-vaccines-modal', 'animal-publish-foto']) {
        assert.match(vista, new RegExp(`id="${id}"`), `falta #${id}`);
    }
    assert.match(vista, /js\/mi-actividad\.js\?v=panel-11/);
    const panel = read('Public/js/mi-actividad.js');
    assert.match(panel, /from '\.\/mis-animales\.js\?v=animales-1'/);
    assert.match(panel, /#animals-panel'\)\.hidden = !isActive\('PRODUCTOR'\)/);
    assert.doesNotMatch(read('Public/js/mis-animales.js'), /\.insertAdjacentHTML\(/);
});

test('la ruta del inventario existe y exige sesión', () => {
    const htaccess = read('Public/.htaccess');
    assert.match(htaccess, /RewriteRule \^api\/v1\/mi-animales\/\?\$ api\/mi-animales\.php \[END\]/);
    assert.ok(htaccess.indexOf('mi-animales/vacunas') < htaccess.indexOf('api/mi-animales.php'), 'la ruta de vacunas va primero');
    const endpoint = read('Public/api/mi-animales.php');
    assert.match(endpoint, /requerirAutenticado/);
    assert.match(endpoint, /'GET', 'POST', 'PATCH'/, 'sin DELETE');
    for (const doc of ['RutasPublicas', 'RutasFrontend']) {
        assert.match(read(`Documentation/${doc}.md`), /\/api\/v1\/mi-animales[^/]/, `${doc} documenta la ruta`);
    }
});
