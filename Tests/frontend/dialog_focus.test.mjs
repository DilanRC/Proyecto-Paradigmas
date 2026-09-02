// Retorno del foco de los <dialog>, con un sustituto minimo de DOM.
//
// El caso delicado es encadenar detalle -> editar: el evento 'close' de
// <dialog> es asincrono, asi que el segundo dialogo se abre antes de que el
// primero notifique su cierre. Se comprobo en navegador real que el
// comportamiento es correcto; estas pruebas lo fijan para que un refactor
// futuro no lo rompa en silencio.

import assert from 'node:assert/strict';
import test, { beforeEach } from 'node:test';

import { createDialogController } from '../../Public/js/shared/dialog.js';

class Elemento {
    constructor(nombre, padre = null) {
        this.nombre = nombre;
        this.padre = padre;
        this.isConnected = true;
        this.enfocado = 0;
    }

    focus() { this.enfocado += 1; document.activeElement = this; }

    closest(selector) {
        let nodo = this;
        while (nodo) {
            if (selector === 'dialog' && nodo.esDialogo) return nodo;
            nodo = nodo.padre;
        }
        return null;
    }
}

class Dialogo extends Elemento {
    constructor(nombre) {
        super(nombre);
        this.esDialogo = true;
        this.open = false;
        this.oyentes = [];
    }

    showModal() { this.open = true; }

    /** close() encola el evento, igual que el navegador: no es sincrono. */
    close() {
        this.open = false;
        queueMicrotask(() => this.oyentes.forEach((fn) => fn({ currentTarget: this })));
    }

    addEventListener(_tipo, fn) { this.oyentes.push(fn); }
    querySelector() { return null; }
}

let document;

beforeEach(() => {
    document = { activeElement: null };
    globalThis.document = document;
    globalThis.HTMLElement = Elemento;
});

test('el foco vuelve al elemento que abrio el dialogo', async () => {
    const dialogs = createDialogController();
    const boton = new Elemento('crear');
    const modal = new Dialogo('modal');
    modal.addEventListener('close', dialogs.restoreFocus);

    boton.focus();
    dialogs.open(modal);
    assert.equal(modal.open, true);

    dialogs.close(modal);
    await Promise.resolve();

    assert.equal(document.activeElement, boton);
});

test('detalle -> editar devuelve el foco a la fila que abrio el recorrido', async () => {
    const dialogs = createDialogController();
    const filaVer = new Elemento('boton-ver');
    const detalle = new Dialogo('detalle');
    const edicion = new Dialogo('edicion');
    const botonEditar = new Elemento('editar-desde-detalle', detalle);
    detalle.addEventListener('close', dialogs.restoreFocus);
    edicion.addEventListener('close', dialogs.restoreFocus);

    filaVer.focus();
    dialogs.open(detalle);
    botonEditar.focus();            // el foco esta dentro del detalle

    // editFromDetail: cierra el detalle y abre edicion en el mismo turno.
    dialogs.close(detalle);
    dialogs.open(edicion);          // el push ocurre ANTES del close encolado
    await Promise.resolve();

    dialogs.close(edicion);
    await Promise.resolve();

    assert.equal(document.activeElement, filaVer,
        'al terminar el recorrido el foco vuelve a la fila, no al body');
});

test('no se enfoca un elemento que ya salio del documento', async () => {
    const dialogs = createDialogController();
    const boton = new Elemento('fila-borrada');
    const modal = new Dialogo('modal');
    modal.addEventListener('close', dialogs.restoreFocus);

    boton.focus();
    dialogs.open(modal);
    boton.isConnected = false;      // la lista se recargo y la fila desaparecio
    const focosPrevios = boton.enfocado;

    dialogs.close(modal);
    await Promise.resolve();

    assert.equal(boton.enfocado, focosPrevios, 'no debe enfocarse un nodo desconectado');
});

test('el clic en el fondo no cierra mientras hay una operacion en curso', () => {
    let ocupado = true;
    const dialogs = createDialogController({ isBusy: () => ocupado });
    const modal = new Dialogo('modal');
    dialogs.open(modal);

    dialogs.handleBackdropClick({ target: modal, currentTarget: modal });
    assert.equal(modal.open, true, 'no debe cerrarse mientras se guarda');

    ocupado = false;
    dialogs.handleBackdropClick({ target: modal, currentTarget: modal });
    assert.equal(modal.open, false);
});

test('un clic dentro del dialogo nunca lo cierra', () => {
    const dialogs = createDialogController();
    const modal = new Dialogo('modal');
    const dentro = new Elemento('campo', modal);
    dialogs.open(modal);

    dialogs.handleBackdropClick({ target: dentro, currentTarget: modal });
    assert.equal(modal.open, true);
});
