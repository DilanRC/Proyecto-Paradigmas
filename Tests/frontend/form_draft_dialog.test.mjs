import assert from 'node:assert/strict';
import test from 'node:test';

import { createDialogController } from '../../Public/js/shared/dialog.js';
import { createFormDraft } from '../../Public/js/shared/form-draft.js';

function fakeStorage() {
    const data = new Map();
    return {
        getItem: (key) => data.has(key) ? data.get(key) : null,
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: (key) => data.delete(key),
    };
}

class FakeDialog {
    constructor() {
        this.open = false;
        this.listeners = new Map();
    }

    showModal() { this.open = true; }
    setAttribute() { this.open = true; }
    querySelector() { return null; }
    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) ?? [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }
    removeEventListener(type, listener) {
        const listeners = this.listeners.get(type) ?? [];
        this.listeners.set(type, listeners.filter((item) => item !== listener));
    }
    dispatchEvent(event) {
        for (const listener of this.listeners.get(event.type) ?? []) listener(event);
        return true;
    }
}

function fakeForm(dialog, controls) {
    const listeners = new Map();
    const elements = [...controls];
    elements.namedItem = (name) => elements.find((item) => item.name === name) ?? null;
    return {
        id: 'formulario-productor',
        elements,
        addEventListener(type, listener) { listeners.set(type, listener); },
        removeEventListener(type) { listeners.delete(type); },
        closest(selector) { return selector === 'dialog' ? dialog : null; },
    };
}

function control(name, value = '', type = 'text') {
    return {
        name,
        value,
        type,
        checked: false,
        focusedWithValue: null,
        focus() {
            this.focusedWithValue = this.value;
            globalThis.document.activeElement = this;
        },
        dispatchEvent() { return true; },
    };
}

test('REGRESION: el borrador se restaura antes de enfocar el select, sin MutationObserver', () => {
    const storage = fakeStorage();
    storage.setItem('tindercows:draft:productor:create', JSON.stringify({
        version: 1,
        savedAt: Date.now(),
        values: {
            identificacionNumeroOriginal: { kind: 'value', value: '' },
            'identificacion.tipoCodigo': { kind: 'value', value: 'FISICA' },
        },
    }));

    const dialog = new FakeDialog();
    const original = control('identificacionNumeroOriginal', '', 'hidden');
    const tipo = control('identificacion.tipoCodigo', '', 'select-one');
    const form = fakeForm(dialog, [original, tipo]);

    // Si la implementacion vuelve al observador asincrono que causaba que el
    // menu nativo se cerrara, esta prueba debe fallar inmediatamente.
    globalThis.MutationObserver = class {
        constructor() { throw new Error('MutationObserver no debe participar en la restauracion'); }
    };
    globalThis.document = { activeElement: null };

    const draft = createFormDraft({ form, storage });
    const dialogs = createDialogController();
    dialogs.open(dialog, { focus: tipo });

    assert.equal(tipo.value, 'FISICA');
    assert.equal(tipo.focusedWithValue, 'FISICA',
        'el select debe recibir foco despues de que el borrador ya fue aplicado');

    draft.destroy();
    delete globalThis.MutationObserver;
    delete globalThis.document;
});
