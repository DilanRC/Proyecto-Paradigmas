// Comportamiento de la maquina de estados de los listados.
// Ejecutar: node --test Tests/frontend/

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from '../../Public/js/shared/list-state.js';

const cargar = (state) => nextRequest(state);

test('una lista vacia solo se afirma cuando el servidor respondio bien', () => {
    const { state, sequence } = cargar(createListState({ pageSize: 25 }));
    const listo = applyResult(state, { sequence, items: [], total: 0 });
    const vista = deriveListView(listo);

    assert.equal(vista.showEmpty, true);
    assert.equal(vista.showError, false);
});

test('REGRESION: un fallo del servidor da estado de error, nunca el vacio', () => {
    // Este es el defecto original: el catch llamaba a render([], 0, size) y la
    // pantalla decia "No se encontraron X" ante un 500.
    const { state, sequence } = cargar(createListState());
    const error = { type: 'http', status: 500, message: 'El servidor fallo.', retryable: true };
    const fallido = applyFailure(state, { sequence, error });
    const vista = deriveListView(fallido);

    assert.equal(vista.showError, true);
    assert.equal(vista.showEmpty, false, 'un fallo jamas puede presentarse como lista vacia');
    assert.equal(vista.showList, false);
    assert.equal(vista.canRetry, true);
    assert.equal(vista.errorMessage, 'El servidor fallo.');
});

test('REGRESION: un fallo de red tampoco se presenta como vacio', () => {
    const { state, sequence } = cargar(createListState());
    const error = { type: 'network', status: null, message: 'Sin conexion.', retryable: true };
    const vista = deriveListView(applyFailure(state, { sequence, error }));

    assert.equal(vista.showError, true);
    assert.equal(vista.showEmpty, false);
    assert.equal(vista.canRetry, true);
});

test('un 422 se muestra sin ofrecer reintento: repetir no cambia nada', () => {
    const { state, sequence } = cargar(createListState());
    const error = { type: 'http', status: 422, message: 'Revise los campos.', retryable: false };
    const vista = deriveListView(applyFailure(state, { sequence, error }));

    assert.equal(vista.showError, true);
    assert.equal(vista.canRetry, false);
});

test('mientras carga se muestra el esqueleto y no el vacio', () => {
    const { state } = cargar(createListState());
    const vista = deriveListView(state);

    assert.equal(vista.showSkeleton, true);
    assert.equal(vista.showEmpty, false);
    assert.equal(vista.showError, false);
    assert.equal(vista.refreshDisabled, true);
});

test('una respuesta obsoleta no puede pisar a la vigente', () => {
    const inicial = createListState();
    const primera = nextRequest(inicial);
    const segunda = nextRequest(primera.state);

    const conSegunda = applyResult(segunda.state, { sequence: segunda.sequence, items: [{ id: 2 }], total: 1 });
    const conPrimeraTardia = applyResult(conSegunda, { sequence: primera.sequence, items: [{ id: 1 }], total: 9 });

    assert.deepEqual(conPrimeraTardia.items, [{ id: 2 }], 'la respuesta vieja se descarta');
    assert.equal(conPrimeraTardia.total, 1);
});

test('un fallo obsoleto no puede pintar error sobre una lista ya cargada', () => {
    const primera = nextRequest(createListState());
    const segunda = nextRequest(primera.state);
    const cargada = applyResult(segunda.state, { sequence: segunda.sequence, items: [{ id: 1 }], total: 1 });

    const conFalloViejo = applyFailure(cargada, {
        sequence: primera.sequence,
        error: { message: 'tarde' },
    });

    assert.equal(deriveListView(conFalloViejo).showList, true);
    assert.equal(deriveListView(conFalloViejo).showError, false);
});

test('cancelar no es un fallo: escribir en el buscador no pinta errores', () => {
    const { state } = cargar(createListState());
    const trasCancelar = applyAbort(state);

    assert.equal(deriveListView(trasCancelar).showError, false);
    assert.equal(trasCancelar.phase, 'loading');
});

test('la paginacion se deriva del total y bloquea los extremos', () => {
    const { state, sequence } = nextRequest(createListState({ pageSize: 25 }), { page: 1 });
    const vista = deriveListView(applyResult(state, { sequence, items: new Array(25).fill({}), total: 60 }));

    assert.equal(vista.pageLabel, 'Pagina 1 de 3');
    assert.equal(vista.previousDisabled, true);
    assert.equal(vista.nextDisabled, false);
});

test('el total se redacta en singular y plural', () => {
    const uno = nextRequest(createListState());
    const vistaUno = deriveListView(
        applyResult(uno.state, { sequence: uno.sequence, items: [{}], total: 1 }),
        { singular: 'vehiculo', plural: 'vehiculos' },
    );
    assert.equal(vistaUno.totalLabel, '1 vehiculo encontrado');

    const varios = nextRequest(createListState());
    const vistaVarios = deriveListView(
        applyResult(varios.state, { sequence: varios.sequence, items: [{}, {}], total: 2 }),
        { singular: 'vehiculo', plural: 'vehiculos' },
    );
    assert.equal(vistaVarios.totalLabel, '2 vehiculos encontrados');
});
