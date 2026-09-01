// Gate determinista de contraste WCAG AA para los tokens de texto.
//
// No comprueba que exista una clase ni que un token este declarado: recalcula el
// ratio de contraste real de cada token contra el fondo sobre el que se pinta y
// falla si baja de 4.5:1. Si alguien vuelve a atenuar un color, el gate cae.
//
// Ejecutar:  node Tests/frontend_contrast_test.mjs

import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';

const CSS_DIR = new URL('../Public/css/', import.meta.url);
const AA_NORMAL = 4.5;

// --- utilidades de color ----------------------------------------------------------
const channel = (value) => {
    const c = value / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
};
const luminance = ([r, g, b]) => 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
const parseHex = (hex) => {
    const value = hex.replace('#', '');
    const full = value.length === 3 ? [...value].map((c) => c + c).join('') : value;
    return [0, 2, 4].map((i) => Number.parseInt(full.slice(i, i + 2), 16));
};
/** Compone un color con alpha sobre un fondo opaco, como hace el navegador. */
const over = (fg, alpha, bg) => fg.map((c, i) => Math.round(c * alpha + bg[i] * (1 - alpha)));
const contrast = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
};

// --- lectura de los tokens reales -------------------------------------------------
const tokensCss = readFileSync(new URL('tokens.css', CSS_DIR), 'utf8');
const token = (name) => {
    const found = tokensCss.match(new RegExp(`--${name}\\s*:\\s*(#[0-9a-fA-F]{3,6})`));
    assert.ok(found, `El token --${name} no esta declarado en tokens.css`);
    return parseHex(found[1]);
};

const WHEAT = token('rural-wheat');
const RUST = token('rural-rust');
const WHITE = parseHex('#ffffff');
const BLACK = [0, 0, 0];
const PANEL_BG = parseHex('#FBF3E4');     // body.rural-panel --background
const PANEL_SURFACE = parseHex('#fffaf3'); // .rural-panel .panel

// Fondos compuestos, replicando las mismas capas que declara el CSS.
const TABLE_HEAD_BG = over(WHEAT, 0.24, PANEL_SURFACE); // .rural-panel thead
const TABS_BG = over(BLACK, 0.18, RUST);                // .rural__tabs
const THREAD_BG = over(BLACK, 0.12, RUST);              // .rural__thread
const CARD_NAV_BG = over(RUST, 0.05, WHITE);            // .rural__card-nav
const BADGE_BG = parseHex('#ecefed');                   // .badge--inactive

// --- pares token / fondo que el CSS pinta de verdad -------------------------------
const PAIRS = [
    ['wheat-text-muted', RUST, 'nav, pie del sidebar y hora de mensajes sobre rust'],
    ['wheat-text-soft', TABS_BG, 'pestanas del aside sobre su fondo oscurecido'],
    ['rust-text-muted', PANEL_BG, 'nota al pie del panel'],
    ['rust-text-muted', TABLE_HEAD_BG, 'cabecera de la tabla'],
    ['rust-text-muted', CARD_NAV_BG, 'pestanas de la tarjeta del home'],
    ['rust-text-soft', WHITE, 'sub-identidad, rating y meta del productor'],
    ['rust-text-lead', PANEL_BG, 'descripcion de la cabecera de pagina'],
    ['rust-text-body', WHITE, 'cita del home'],
    ['badge-inactive-text', BADGE_BG, 'badge de estado inactivo'],
];

let failures = 0;
console.log('  token                     fondo      ratio  minimo  resultado');
for (const [name, background, label] of PAIRS) {
    const ratio = contrast(token(name), background);
    const ok = ratio >= AA_NORMAL;
    if (!ok) failures += 1;
    const bgHex = '#' + background.map((c) => c.toString(16).padStart(2, '0')).join('');
    console.log(
        `  --${name.padEnd(22)} ${bgHex}  ${ratio.toFixed(2).padStart(5)}   ${AA_NORMAL}    ` +
        `${ok ? 'OK' : 'FALLA'}   ${label}`,
    );
}
// Variantes de notificacion: color y fondo se leen del propio componente, de
// modo que cambiar una de las dos se detecta aqui.
const componentsCss = readFileSync(new URL('components.css', CSS_DIR), 'utf8');
const toastVariants = [...componentsCss.matchAll(
    /\.toast--(\w+)\s*\{[^}]*?color:\s*(#[0-9a-fA-F]{3,6})[^}]*?background:\s*(#[0-9a-fA-F]{3,6})/g,
)];
assert.ok(toastVariants.length >= 4, 'Se esperaban las variantes success, error, warning e info');

for (const [, variant, fg, bg] of toastVariants) {
    const ratio = contrast(parseHex(fg), parseHex(bg));
    const ok = ratio >= AA_NORMAL;
    if (!ok) failures += 1;
    console.log(
        `  .toast--${variant.padEnd(15)} ${bg}  ${ratio.toFixed(2).padStart(5)}   ${AA_NORMAL}    ` +
        `${ok ? 'OK' : 'FALLA'}   notificacion ${variant}`,
    );
}

assert.equal(failures, 0, `${failures} combinaciones de texto no alcanzan ${AA_NORMAL}:1`);

// --- invariantes que impiden reintroducir el problema -----------------------------
const sheets = readdirSync(CSS_DIR).filter((f) => f.endsWith('.css'));
assert.ok(sheets.length >= 5, 'Se esperaban las capas tokens/base/components/panel/red-ganadera');

for (const sheet of sheets) {
    const css = readFileSync(new URL(sheet, CSS_DIR), 'utf8');

    // 1. El texto nunca se atenua con rgba(): el alpha sobre un fondo de color fue
    //    exactamente la causa de los nueve fallos originales.
    const alphaText = css.match(/(?<![a-z-])color:\s*rgba\([^)]*\)/g) ?? [];
    assert.deepEqual(alphaText, [], `${sheet}: usa color:rgba() para texto; use un token verificado`);

    // 2. No se declaran tipografias que nadie carga (DM Sans e Inter no se pedian).
    for (const ghost of ['DM Sans', 'Inter']) {
        assert.ok(
            !css.includes(ghost),
            `${sheet}: declara la tipografia ${ghost}, que no se carga en ninguna vista`,
        );
    }
}

console.log(`\nOK frontend_contrast_test: ${PAIRS.length + toastVariants.length} combinaciones cumplen AA y ` +
    `${sheets.length} hojas sin texto en rgba() ni tipografias fantasma.`);
