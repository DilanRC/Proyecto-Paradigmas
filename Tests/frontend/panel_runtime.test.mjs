import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
const script = () => readFileSync('Public/js/shared/panel-runtime.js', 'utf8');

test('el aviso clásico aparece al fallar módulos y se oculta cuando la guarda se recupera', () => {
    let ready = false;
    let timeout;
    let observe;
    let reloaded = 0;
    const handlers = {};
    const notice = { hidden: true, setAttribute() { this.hidden = true; }, removeAttribute() { this.hidden = false; } };
    const retry = { addEventListener(type, callback) { handlers[type] = callback; } };
    const document = {
        readyState: 'complete',
        documentElement: { getAttribute() { return ready ? 'ready' : null; } },
        querySelector(selector) { return selector === '[data-panel-runtime]' ? notice : retry; },
    };
    vm.runInNewContext(script(), {
        document,
        window: { location: { reload() { reloaded += 1; } }, setTimeout(callback) { timeout = callback; }, setInterval() { return 1; }, clearInterval() {}, MutationObserver: function(callback) { observe = callback; this.observe = () => {}; } },
    });
    assert.equal(notice.hidden, true);
    timeout();
    assert.equal(notice.hidden, false);
    handlers.click();
    assert.equal(reloaded, 1);
    ready = true;
    observe();
    assert.equal(notice.hidden, true);
    assert.doesNotMatch(script(), /\b(?:import|export|const|let)\b|=>|\?\./, 'el aviso puede ejecutarse sin módulos ni sintaxis moderna');
});

test('el aviso conserva la guarda y ofrece alternativas de teclado y sin JavaScript', () => {
    const css = readFileSync('Public/css/base.css', 'utf8');
    assert.match(css, /body\.rural-panel\s*\{\s*visibility:hidden;/);
    assert.match(css, /\.panel-runtime[\s\S]*?visibility:visible;/);
    const partial = readFileSync('Application/View/shared/panel-runtime.php', 'utf8');
    assert.match(partial, /<button[^>]*type="button"[^>]*data-panel-runtime-retry/);
    assert.match(partial, /href="admin\/entrar"/);
    assert.match(partial, /<noscript>/);
    assert.match(partial, /Recargar/);
});


test('todas las vistas privadas incluyen el aviso clásico independiente del módulo', () => {
    let count = 0;
    for (const folder of readdirSync('Application/View', { withFileTypes: true })) {
        if (!folder.isDirectory() || folder.name === 'shared') continue;
        const path = `Application/View/${folder.name}/index.php`;
        let source;
        try { source = readFileSync(path, 'utf8'); } catch { continue; }
        if (!/<body[^>]*class="[^"]*rural-panel/.test(source)) continue;
        count += 1;
        assert.match(source, /<script defer src="js\/shared\/panel-runtime\.js\?v=runtime-1"><\/script>/);
        assert.match(source, /require dirname\(__DIR__\) \. '\/shared\/panel-runtime\.php'/);
        assert.match(source, /css\/base\.css\?v=product-9/);
    }
    assert.equal(count, 13);
});
