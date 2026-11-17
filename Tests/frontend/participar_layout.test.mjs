import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('Más formas de participar usa todo el ancho útil al ocultar el menú lateral', () => {
    const view = read('Application/View/ajustes/index.php');
    const css = read('Public/css/mi-actividad.css');

    assert.match(view, /data-pagina="participar"/);
    assert.match(view, /class="settings-nav"[^>]*hidden/);
    assert.match(css, /body\.settings-page\[data-pagina="participar"\] \.settings-layout\s*\{\s*grid-template-columns:minmax\(0,1fr\);/);
    assert.match(css, /body\.settings-page\[data-pagina="participar"\] \.settings-sections\s*\{\s*width:min\(100%,960px\);\s*margin-inline:auto;/);
    assert.match(view, /mi-actividad\.css\?v=panel-5/);
});
