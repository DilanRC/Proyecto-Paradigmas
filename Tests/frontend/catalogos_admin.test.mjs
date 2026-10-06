// Catálogos del animal en el panel admin (P2-6).
//
// Ejecutar: node --test Tests/frontend/catalogos_admin.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('el filtro separa catálogos y estados; solo tipos y razas llevan especie', async () => {
    const { filtrarCatalogo, camposCatalogo } = await import('../../Public/js/catalogos.js');
    const datos = { vacunas: [{ id: 1, activo: true }, { id: 2, activo: false }], tipos: [{ id: 9, activo: true }] };
    assert.deepEqual(filtrarCatalogo(datos, 'VACUNA').map((x) => x.id), [1, 2]);
    assert.deepEqual(filtrarCatalogo(datos, 'VACUNA', 'ACTIVO').map((x) => x.id), [1]);
    assert.deepEqual(filtrarCatalogo(datos, 'VACUNA', 'INACTIVO').map((x) => x.id), [2]);
    assert.deepEqual(filtrarCatalogo(datos, 'RAZA'), []);
    assert.deepEqual(camposCatalogo('TIPO'), { especie: true, sexo: true });
    assert.deepEqual(camposCatalogo('RAZA'), { especie: true, sexo: false });
    assert.deepEqual(camposCatalogo('VACUNA'), { especie: false, sexo: false });
});

test('la API admin exige administrador y no permite borrar', () => {
    assert.match(read('Public/.htaccess'), /RewriteRule \^api\/v1\/admin\/catalogos\/\?\$ api\/admin-catalogos\.php \[END\]/);
    assert.match(read('Public/.htaccess'), /RewriteRule \^admin\/catalogos\/\?\$ catalogos\.php \[END\]/);
    const endpoint = read('Public/api/admin-catalogos.php');
    assert.match(endpoint, /AdminAuthorization::require\(\$actor, \$conexion\)/);
    assert.match(endpoint, /\['GET', 'POST', 'PATCH'\]/);
    assert.doesNotMatch(read('Public/js/catalogos.js'), /\.innerHTML\s*=/, 'los nombres se pintan como texto');
});
