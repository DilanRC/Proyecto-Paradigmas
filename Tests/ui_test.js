'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const javascript = fs.readFileSync(path.join(root, 'Public/js/productores.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'Application/View/productores/index.php'), 'utf8');

assert.match(view, /<html lang="es">/, 'La vista debe declarar idioma español.');
assert.match(javascript, /event\.preventDefault\(\)/, 'El formulario debe impedir la navegación nativa.');
assert.doesNotMatch(javascript, /(?:location|window\.location)\.reload|innerHTML\s*=/, 'La UI no debe recargar ni insertar HTML externo.');
for (const method of ['POST', 'PUT', 'DELETE', 'PATCH']) {
    assert.match(javascript, new RegExp(`['\"]${method}['\"]`), `Falta el método AJAX ${method}.`);
}
for (const token of ['AbortController', 'aria-invalid', 'aria-busy', 'textContent', 'pagina-anterior', 'pagina-siguiente', 'tamanoPagina']) {
    assert.ok(javascript.includes(token) || view.includes(token), `Falta el control de interfaz: ${token}.`);
}

const referencedIds = [...javascript.matchAll(/\$\('#([A-Za-z0-9-]+)'\)/g)].map((match) => match[1]);
for (const id of new Set(referencedIds)) {
    assert.match(view, new RegExp(`id=["']${id}["']`), `JavaScript referencia #${id}, pero la vista no lo define.`);
}

console.log('OK ui_test: AJAX sin recarga, IDs, paginación, carreras y ARIA.');
