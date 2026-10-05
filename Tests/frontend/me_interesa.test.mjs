import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const js = readFileSync('Public/js/me-interesa.js', 'utf8');
const view = readFileSync('Application/View/me-interesa/index.php', 'utf8');
const htaccess = readFileSync('Public/.htaccess', 'utf8');
const menu = readFileSync('Public/js/public-ui.js', 'utf8');
const controller = readFileSync('Application/Controller/PublicacionInteraccionController.php', 'utf8');

test('/me-interesa es una ruta con sesión que reutiliza la tarjeta de Explorar', () => {
    assert.match(htaccess, /RewriteRule \^me-interesa\/\?\$ me-interesa\.php \[END\]/);
    assert.ok(js.includes("import { buildCard } from './explore.js?v=foto-3'"));
    assert.ok(js.includes("entrar?next=me-interesa"));
    assert.ok(js.includes('tipo=ME_INTERESA'));
    for (const id of ['saved-loading', 'saved-error', 'saved-retry', 'saved-empty', 'saved-list']) {
        assert.ok(view.includes(`id="${id}"`), `falta ${id}`);
    }
});

test('quitar usa RETIRAR y lo no disponible se avisa sin desaparecer', () => {
    assert.ok(js.includes("accion: 'RETIRAR'"));
    assert.ok(js.includes('No disponible'));
    assert.ok(controller.includes("'RETIRAR'") && controller.includes('estaMarcada'));
});

test('la tarjeta guardada es la compacta de la portada y Explorar oculta lo marcado', () => {
    const explore = readFileSync('Public/js/explore.js', 'utf8');
    assert.ok(js.includes('compacta: true'));
    assert.ok(explore.includes('item.meInteresa !== true'), 'el deck no muestra lo ya marcado');
    assert.ok(explore.includes("explore:interaction-saved"), 'al marcar, la tarjeta sale del deck');
});

test('el menú de la cuenta enlaza a Me interesa', () => {
    assert.ok(menu.includes('href="me-interesa"'));
});
