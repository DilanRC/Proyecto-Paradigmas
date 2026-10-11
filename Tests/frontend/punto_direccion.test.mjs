// El punto del mapa es opcional, pero si se marca debe caer en la provincia escrita.
//
// Ejecutar: node --test Tests/frontend/punto_direccion.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const { desajusteProvincia, normalizarProvincia, validarPuntoConDireccion } = await import('../../Public/js/shared/punto-direccion.js');
const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('la provincia se compara sin tildes, mayúsculas ni "Provincia de"', () => {
    assert.equal(normalizarProvincia('Provincia de San José'), 'san jose');
    assert.equal(desajusteProvincia('San José', 'san jose'), null);
    assert.equal(desajusteProvincia('Alajuela', ''), null, 'sin provincia escrita no se compara');
    assert.match(desajusteProvincia('Alajuela', 'Limón'), /está en Alajuela, pero la dirección dice Limón/);
});

test('sin punto o sin servicio de mapas no bloquea; fuera de Costa Rica sí', async () => {
    const buscar = async () => ({ provincia: 'Alajuela' });
    assert.equal(await validarPuntoConDireccion({ provincia: 'Limón' }, { buscar }), null, 'sin punto no se valida');
    assert.match(await validarPuntoConDireccion({ provincia: 'Limón', latitud: 10, longitud: -84 }, { buscar }), /Alajuela/);
    assert.equal(await validarPuntoConDireccion({ provincia: 'Alajuela', latitud: 10, longitud: -84 }, { buscar }), null);
    const caido = async () => { throw new Error('sin red'); };
    assert.equal(await validarPuntoConDireccion({ provincia: 'Limón', latitud: 10, longitud: -84 }, { buscar: caido }), null);
    const fuera = async () => { throw Object.assign(new Error('El punto debe estar dentro de Costa Rica.'), { kind: 'outside-costa-rica' }); };
    assert.match(await validarPuntoConDireccion({ provincia: 'Limón', latitud: 0, longitud: 0 }, { buscar: fuera }), /Costa Rica/);
});

test('el flete ya no exige el punto y todos los formularios con mapa validan la provincia', () => {
    assert.match(read('Public/js/fletes.js'), /opcional: true/);
    for (const archivo of ['Public/js/fletes.js', 'Public/js/mi-actividad.js', 'Public/js/registro.js', 'Public/js/productores.js']) {
        assert.match(read(archivo), /validarPuntoConDireccion\(/, `${archivo} valida el punto contra la provincia`);
    }
});
