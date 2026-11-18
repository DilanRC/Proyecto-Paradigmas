// Spinner de acción (shared/progreso.js): etapas reales, sin doble envío y
// restauración exacta del botón, haya salido bien o mal la operación.
import assert from 'node:assert/strict';
import test from 'node:test';

class Nodo {
    constructor(tag) { this.tag = tag; this.atributos = {}; this.hijos = []; this.textContent = ''; this.className = ''; }
    setAttribute(nombre, valor) { this.atributos[nombre] = String(valor); }
    removeAttribute(nombre) { delete this.atributos[nombre]; }
    getAttribute(nombre) { return this.atributos[nombre] ?? null; }
}

class Boton extends Nodo {
    constructor(html, disabled = false) { super('button'); this._html = html; this.disabled = disabled; }
    get innerHTML() { return this._html; }
    set innerHTML(valor) { this._html = valor; this.hijos = []; }
    replaceChildren(...hijos) { this.hijos = hijos; this._html = hijos.map((h) => h.tag === 'span' ? h.textContent : `<${h.tag}>`).join(''); }
    get texto() { return this.hijos.filter((h) => h.tag === 'span').map((h) => h.textContent).join(''); }
}

globalThis.document = { createElement: (tag) => new Nodo(tag) };
const { iniciarProgreso } = await import('../../Public/js/shared/progreso.js');

test('muestra spinner y la etapa, bloquea el botón y lo marca ocupado', () => {
    const boton = new Boton('<i class="fa-solid fa-check"></i> Publicar');
    const progreso = iniciarProgreso(boton, 'Subiendo imagen…');
    assert.equal(boton.disabled, true);
    assert.equal(boton.getAttribute('aria-busy'), 'true');
    assert.equal(boton.texto, 'Subiendo imagen…');
    const icono = boton.hijos.find((h) => h.tag === 'i');
    assert.match(icono.className, /fa-spin/);
    assert.equal(icono.getAttribute('aria-hidden'), 'true');

    progreso.etapa('Guardando publicación…');
    assert.equal(boton.texto, 'Guardando publicación…');
    assert.doesNotMatch(boton.innerHTML, /%/, 'no inventa porcentajes');
});

test('terminar devuelve el contenido y el estado exactos', () => {
    const boton = new Boton('<i class="fa-solid fa-check"></i> Publicar');
    const progreso = iniciarProgreso(boton, 'Guardando…');
    progreso.terminar();
    assert.equal(boton.innerHTML, '<i class="fa-solid fa-check"></i> Publicar');
    assert.equal(boton.disabled, false);
    assert.equal(boton.getAttribute('aria-busy'), null);
});

test('respeta un botón que ya estaba deshabilitado por una regla de negocio', () => {
    const boton = new Boton('Pujar', true);
    iniciarProgreso(boton, 'Registrando puja…').terminar();
    assert.equal(boton.disabled, true);
});

test('una etapa tardía después de terminar no pisa el botón restaurado', () => {
    const boton = new Boton('Guardar');
    const progreso = iniciarProgreso(boton, 'Guardando…');
    progreso.terminar();
    progreso.etapa('Subiendo foto…');
    assert.equal(boton.innerHTML, 'Guardar');
    progreso.terminar();
    assert.equal(boton.innerHTML, 'Guardar', 'terminar dos veces es inocuo');
});

test('dos inicios seguidos sobre el mismo botón restauran el original, no el spinner', () => {
    const boton = new Boton('Guardar');
    iniciarProgreso(boton, 'Primero…');
    const segundo = iniciarProgreso(boton, 'Segundo…');
    segundo.terminar();
    assert.equal(boton.innerHTML, 'Guardar');
    assert.equal(boton.disabled, false);
});

test('sin botón no falla', () => {
    const progreso = iniciarProgreso(null, 'Guardando…');
    progreso.etapa('Otra');
    progreso.terminar();
});
