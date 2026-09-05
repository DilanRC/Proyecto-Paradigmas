// Ancho de la cabecera publica y encuadre del bloque final de llamada a accion.
//
// Dos defectos reales que estas pruebas dejan cerrados:
//
// 1. La home declaraba la clase public-header--product pero no cargaba la hoja
//    que la define, asi que la cabecera caia al reparto flex angosto y no usaba
//    el ancho de la pagina. La clase sin su hoja no da error: solo se ve mal.
// 2. El bloque .public-section--cta tiene borde y fondo propios, pero heredaba
//    padding lateral cero de .public-section, asi que el texto quedaba pegado
//    al borde y space-between mandaba texto y boton a los extremos opuestos.
//
// Ejecutar: node --test Tests/frontend/public_shell_layout.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const home = read('Application/View/home/index.php');
const explore = read('Application/View/explorar/index.php');
const productCss = read('Public/css/public-product.css');
const v3Css = read('Public/css/public-v3.css');

// Extrae el cuerpo de una regla CSS de nivel superior (sin anidar en @media).
function rule(css, selector) {
    const start = css.indexOf(`\n${selector} {`);
    assert.notEqual(start, -1, `no se encontro la regla ${selector}`);
    return css.slice(start, css.indexOf('}', start));
}

test('toda vista que use public-header--product carga la hoja que la define', () => {
    for (const [nombre, html] of [['home', home], ['explorar', explore]]) {
        if (!html.includes('public-header--product')) continue;
        assert.match(html, /href="css\/public-product\.css/,
            `${nombre}: usa public-header--product sin cargar public-product.css`);
    }
});

test('la hoja de producto es la unica que define el ancho de la cabecera', () => {
    // Si esta regla desaparece, el link de la vista deja de servir de nada.
    assert.match(rule(productCss, '.public-header--product'), /width:min\(/);
    assert.match(rule(productCss, '.public-header--product'), /grid-template-columns:/);
});

test('el bloque de llamada a accion separa su contenido del borde', () => {
    const cta = rule(v3Css, '.public-section--cta');
    const padding = cta.match(/padding:(\d+)px (\d+)px/);
    assert.ok(padding, 'el bloque debe declarar su propio padding');
    assert.ok(Number(padding[2]) > 0,
        'sin padding lateral el texto queda pegado al borde del bloque');
});

test('el par texto + boton se centra como grupo, no en los extremos', () => {
    const cta = rule(v3Css, '.public-section--cta');
    assert.doesNotMatch(cta, /justify-content:space-between/,
        'space-between manda texto y boton a los bordes opuestos');
    assert.match(cta, /justify-content:center/);
    assert.match(rule(v3Css, '.public-section--cta > div'), /max-width:\d+px/,
        'sin max-width el titulo se estira y desbalancea el grupo');
});
