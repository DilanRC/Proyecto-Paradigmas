// Recorrido de la baraja de la vista principal.
//
// El orden lo decide el backend; aqui solo se comprueba el avance, el guardado
// de sesion y los limites. No hay ninguna logica de recomendacion en el
// frontend, y estas pruebas lo dejan explicito.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    advance, createDeck, currentProducer, deckExhausted, formatFarms, formatLocation, initials, save,
} from '../../Public/js/home.js';

const items = [
    { identificacionNumero: '111', nombre: 'Maria Solano' },
    { identificacionNumero: '222', nombre: 'Juan Perez' },
];

test('la baraja empieza en el primer productor que devolvio la API', () => {
    const deck = createDeck();
    assert.equal(currentProducer(deck, items).identificacionNumero, '111');
    assert.equal(deck.guardados.size, 0);
    assert.equal(deck.revisados.size, 0);
});

test('pasar avanza y cuenta el productor como revisado', () => {
    const deck = advance(createDeck(), items);
    assert.equal(currentProducer(deck, items).identificacionNumero, '222');
    assert.deepEqual([...deck.revisados], ['111']);
    assert.equal(deck.guardados.size, 0);
});

test('guardar cuenta el productor y tambien avanza', () => {
    const deck = save(createDeck(), items);
    assert.deepEqual([...deck.guardados], ['111']);
    assert.deepEqual([...deck.revisados], ['111']);
    assert.equal(currentProducer(deck, items).identificacionNumero, '222');
});

test('el indice nunca se sale del final de la lista', () => {
    let deck = createDeck();
    for (let i = 0; i < 5; i += 1) deck = advance(deck, items);

    assert.equal(deck.index, items.length);
    assert.equal(currentProducer(deck, items), null);
    assert.equal(deckExhausted(deck, items), true);
});

test('guardar sobre una baraja agotada no rompe ni inventa registros', () => {
    let deck = createDeck();
    for (let i = 0; i < items.length; i += 1) deck = advance(deck, items);
    const despues = save(deck, items);

    assert.equal(despues.guardados.size, 0);
    assert.equal(despues.index, items.length);
});

test('una lista vacia no se considera agotada: no hay nada que recorrer', () => {
    assert.equal(deckExhausted(createDeck(), []), false);
    assert.equal(currentProducer(createDeck(), []), null);
});

test('las operaciones no mutan la baraja anterior', () => {
    const inicial = createDeck();
    save(inicial, items);
    advance(inicial, items);

    assert.equal(inicial.index, 0, 'el estado previo debe quedar intacto');
    assert.equal(inicial.guardados.size, 0);
});

test('guardar el mismo productor dos veces no lo cuenta dos veces', () => {
    const primero = save(createDeck(), items);
    // Se vuelve atras manualmente para repetir el mismo registro.
    const repetido = save({ ...primero, index: 0 }, items);

    assert.deepEqual([...repetido.guardados], ['111']);
});

test('la ubicacion se arma de lo especifico a lo general', () => {
    assert.equal(
        formatLocation({ provincia: 'Heredia', canton: 'Heredia', distrito: 'Mercedes' }),
        'Mercedes, Heredia, Heredia',
    );
    assert.equal(formatLocation({}), 'Sin dirección');
    assert.equal(formatLocation(null), 'Sin dirección');
});

test('las fincas y las iniciales tienen texto de reserva', () => {
    assert.equal(formatFarms([{ nombre: 'El Roble' }, { nombre: 'Valle Verde' }]), 'El Roble, Valle Verde');
    assert.equal(formatFarms([]), 'Sin fincas registradas');
    assert.equal(initials('Maria Fernanda Solano'), 'MF');
    assert.equal(initials(''), 'P');
});
