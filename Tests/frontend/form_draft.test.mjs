import assert from 'node:assert/strict';
import test from 'node:test';

import {
    applyFormValues,
    captureFormValues,
    clearFormDraftAfterSuccessfulClose,
    createFormDraft,
    draftStorageKey,
    enableFormDraft,
    resolveDraftContext,
} from '../../Public/js/shared/form-draft.js';

function fakeStorage() {
    const data = new Map();
    return {
        data,
        getItem: (key) => data.has(key) ? data.get(key) : null,
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: (key) => data.delete(key),
    };
}

function control(name, value = '', type = 'text') {
    return {
        name, value, type, checked: false,
        dispatchEvent() { return true; },
    };
}

function fakeForm(id, controls, dialog = null) {
    const listeners = new Map();
    const elements = [...controls];
    elements.namedItem = (name) => elements.find((item) => item.name === name) ?? null;
    return {
        id,
        elements,
        listeners,
        addEventListener(type, listener) { listeners.set(type, listener); },
        removeEventListener(type) { listeners.delete(type); },
        closest(selector) { return selector === 'dialog' ? dialog : null; },
    };
}

test('la clave separa crear de editar y no mezcla registros', () => {
    assert.equal(draftStorageKey('productor', 'create'), 'tindercows:draft:productor:create');
    assert.equal(
        draftStorageKey('productor', 'edit', '1-1111-1111'),
        'tindercows:draft:productor:edit:1-1111-1111',
    );
    assert.notEqual(
        draftStorageKey('vehiculo', 'edit', '7'),
        draftStorageKey('vehiculo', 'edit', '8'),
    );
});

test('el contexto usa el identificador oculto y omite formularios sin identidad estable', () => {
    const original = control('identificacionNumeroOriginal', '', 'hidden');
    const form = fakeForm('formulario-productor', [original, control('nombre')]);
    assert.deepEqual(resolveDraftContext(form), {
        scope: 'productor', mode: 'create', identity: '',
        key: 'tindercows:draft:productor:create',
    });

    original.value = '1-2222-3333';
    assert.equal(resolveDraftContext(form).key,
        'tindercows:draft:productor:edit:1-2222-3333');

    assert.equal(resolveDraftContext(fakeForm('formulario-direccion-finca', [control('provincia')])), null,
        'una dirección de finca no puede compartir un borrador sin conocer productor+finca');
});

test('solo captura datos del usuario: nunca password, archivos ni botones', () => {
    const form = fakeForm('formulario-vehiculo', [
        control('vehiculoId', '', 'hidden'),
        control('placa', 'ABC123'),
        control('secreto', 'no guardar', 'password'),
        control('archivo', '/tmp/foto.png', 'file'),
        control('guardar', 'Guardar', 'submit'),
    ]);
    const values = captureFormValues(form);
    assert.equal(values.placa.value, 'ABC123');
    assert.equal(values.vehiculoId.value, '');
    assert.equal('secreto' in values, false);
    assert.equal('archivo' in values, false);
    assert.equal('guardar' in values, false);
});

test('guardar, restaurar y limpiar conserva el formulario ante un fallo', () => {
    const storage = fakeStorage();
    const id = control('vehiculoId', '', 'hidden');
    const placa = control('placa', 'ABC123');
    const modelo = control('modelo', 'Z Rich');
    const form = fakeForm('formulario-vehiculo', [id, placa, modelo]);
    const draft = createFormDraft({ form, storage, restoreOnOpen: false });

    assert.equal(draft.saveNow(), true);
    placa.value = '';
    modelo.value = '';
    assert.equal(draft.restoreNow(), true);
    assert.equal(placa.value, 'ABC123');
    assert.equal(modelo.value, 'Z Rich');

    assert.equal(storage.data.has('tindercows:draft:vehiculo:create'), true);
    assert.equal(draft.clear(), true);
    assert.equal(storage.data.has('tindercows:draft:vehiculo:create'), false);
});

test('un borrador de crear nunca pisa el de editar', () => {
    const storage = fakeStorage();
    const original = control('identificacionNumeroOriginal', '', 'hidden');
    const nombre = control('nombre', 'Borrador crear');
    const form = fakeForm('formulario-transportista', [original, nombre]);
    const draft = createFormDraft({ form, storage, restoreOnOpen: false });

    draft.saveNow();
    original.value = '3-101-999999';
    nombre.value = 'Borrador editar';
    draft.saveNow();

    assert.equal(storage.data.size, 2);
    nombre.value = 'Servidor';
    draft.restoreNow();
    assert.equal(nombre.value, 'Borrador editar');

    original.value = '';
    draft.restoreNow();
    assert.equal(nombre.value, 'Borrador crear');
});

test('input/change guarda con debounce sin depender del submit', async () => {
    const storage = fakeStorage();
    const id = control('id', '', 'hidden');
    const nombre = control('nombre', 'Efectivo');
    const form = fakeForm('formulario-pagometodo', [id, nombre]);
    createFormDraft({ form, storage, debounceMs: 1, restoreOnOpen: false });

    nombre.value = 'Transferencia temporal';
    form.listeners.get('input')?.({ target: nombre });
    await new Promise((resolve) => setTimeout(resolve, 10));

    const saved = JSON.parse(storage.getItem('tindercows:draft:pagometodo:create'));
    assert.equal(saved.values.nombre.value, 'Transferencia temporal');
});

test('un error conserva el borrador y un cierre posterior a 2xx lo elimina', () => {
    const storage = fakeStorage();
    const dialog = { open: true };
    const form = fakeForm(
        'formulario-vehiculo',
        [control('vehiculoId', '', 'hidden'), control('placa', 'ABC123')],
        dialog,
    );
    const draft = enableFormDraft(form, { storage, restoreOnOpen: false });
    draft.saveNow();

    assert.equal(clearFormDraftAfterSuccessfulClose(form), false,
        'si el diálogo sigue abierto el error debe conservar lo escrito');
    assert.equal(storage.data.has('tindercows:draft:vehiculo:create'), true);

    dialog.open = false;
    assert.equal(clearFormDraftAfterSuccessfulClose(form), true,
        'el borrador se elimina cuando el flujo exitoso ya cerró el diálogo');
    assert.equal(storage.data.has('tindercows:draft:vehiculo:create'), false);
});

test('restaurar selects emite change para reconstruir cascadas dependientes', () => {
    let cambios = 0;
    const provincia = control('direccionPrincipal.provincia', '', 'select-one');
    provincia.dispatchEvent = () => { cambios += 1; return true; };
    const canton = control('direccionPrincipal.canton', '', 'select-one');
    canton.dispatchEvent = () => { cambios += 1; return true; };
    const form = fakeForm('formulario-productor', [provincia, canton]);

    applyFormValues(form, {
        'direccionPrincipal.provincia': { kind: 'value', value: 'Limón' },
        'direccionPrincipal.canton': { kind: 'value', value: 'Guácimo' },
    });
    assert.equal(provincia.value, 'Limón');
    assert.equal(canton.value, 'Guácimo');
    assert.equal(cambios, 2);
});

test('storage corrupto o no disponible degrada sin romper el formulario', () => {
    const storage = fakeStorage();
    const form = fakeForm('formulario-vehiculo', [control('vehiculoId', '', 'hidden'), control('placa')]);
    storage.setItem('tindercows:draft:vehiculo:create', '{json roto');
    const draft = createFormDraft({ form, storage, restoreOnOpen: false });
    assert.equal(draft.restoreNow(), false);

    const roto = {
        getItem() { throw new Error('bloqueado'); },
        setItem() { throw new Error('bloqueado'); },
        removeItem() { throw new Error('bloqueado'); },
    };
    const safe = createFormDraft({ form, storage: roto, restoreOnOpen: false });
    assert.equal(safe.saveNow(), false);
    assert.equal(safe.restoreNow(), false);
    assert.equal(safe.clear(), false);
});
