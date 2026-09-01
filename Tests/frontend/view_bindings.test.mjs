// Cada selector #id que usa un panel debe existir en su vista.
//
// Al reescribir a la vez el marcado y el JavaScript, un identificador mal
// escrito no da error de sintaxis: el panel simplemente queda inerte al primer
// uso. Sin navegador no se veria. Esta prueba lo detecta de forma determinista.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const PANELES = [
    ['productores', 'productores'],
    ['transportistas', 'transportistas'],
    ['vehiculos', 'vehiculos'],
    ['pagometodos', 'pagometodos'],
    ['home', 'home'],
];

const leer = (ruta) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');

for (const [panel, vista] of PANELES) {
    test(`${panel}: todos los selectores #id existen en su vista`, () => {
        const js = leer(`Public/js/${panel}.js`);
        const html = leer(`Application/View/${vista}/index.php`);

        const disponibles = new Set([...html.matchAll(/\bid="([^"]+)"/g)].map((m) => m[1]));
        const usados = [...js.matchAll(/\$\('#([A-Za-z0-9_-]+)'\)/g)].map((m) => m[1]);

        assert.ok(usados.length > 0, `${panel}: no se detecto ningun selector`);

        const faltantes = [...new Set(usados)].filter((id) => !disponibles.has(id));
        assert.deepEqual(faltantes, [], `${panel}: la vista no declara ${faltantes.join(', ')}`);
    });

    test(`${panel}: la vista carga el modulo y sus hojas de estilo`, () => {
        const html = leer(`Application/View/${vista}/index.php`);

        assert.match(html, new RegExp(`<script type="module" src="js/${panel}\\.js">`),
            `${panel}: la vista debe cargar el panel como modulo ES`);
        for (const hoja of ['tokens', 'base', 'components']) {
            assert.ok(html.includes(`css/${hoja}.css`), `${panel}: falta la capa ${hoja}.css`);
        }
        assert.ok(html.includes('rel="icon"'), `${panel}: la vista debe declarar favicon`);
    });
}

test('todas las vistas tienen las dos regiones vivas de notificacion', () => {
    for (const [, vista] of PANELES) {
        const html = leer(`Application/View/${vista}/index.php`);
        assert.ok(html.includes('id="toast-status"') && html.includes('role="status"'),
            `${vista}: falta la region cortes`);
        assert.ok(html.includes('id="toast-alert"') && html.includes('role="alert"'),
            `${vista}: falta la region asertiva`);
        // El defecto original: la region se ocultaba con hidden y salia del
        // arbol de accesibilidad.
        assert.ok(!/id="toast-(status|alert)"[^>]*\shidden/.test(html),
            `${vista}: las regiones vivas no deben ocultarse con hidden`);
    }
});

test('los cuatro paneles distinguen vacio de error y ofrecen reintento', () => {
    for (const [, vista] of PANELES.filter(([p]) => p !== 'home')) {
        const html = leer(`Application/View/${vista}/index.php`);
        assert.ok(html.includes('id="estado-vacio"'), `${vista}: falta el estado vacio`);
        assert.ok(html.includes('id="estado-error"'), `${vista}: falta el estado de error`);
        assert.ok(html.includes('id="reintentar"'), `${vista}: falta el boton Reintentar`);
        assert.ok(html.includes('class="skeleton"'), `${vista}: falta el esqueleto de carga`);
    }
});

test('los dialogos declaran su semantica de forma explicita', () => {
    for (const [, vista] of PANELES.filter(([p]) => p !== 'home')) {
        const html = leer(`Application/View/${vista}/index.php`);
        const dialogos = [...html.matchAll(/<dialog[^>]*>/g)].map((m) => m[0]);
        assert.ok(dialogos.length > 0, `${vista}: no hay dialogos`);
        for (const dialogo of dialogos) {
            assert.match(dialogo, /role="dialog"/, `${vista}: dialogo sin role`);
            assert.match(dialogo, /aria-modal="true"/, `${vista}: dialogo sin aria-modal`);
            assert.match(dialogo, /aria-labelledby=/, `${vista}: dialogo sin nombre accesible`);
        }
    }
});
