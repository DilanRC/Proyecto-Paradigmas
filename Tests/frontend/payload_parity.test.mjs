// Paridad del contrato con el backend tras el refactor.
//
// El riesgo real de repartir el codigo en modulos no es visual: es que el
// formulario siga viendose bien y empiece a enviar un cuerpo distinto. Estas
// pruebas fijan el cuerpo exacto que cada panel mandaba antes del refactor,
// comparado contra un payload de referencia escrito a mano.

import assert from 'node:assert/strict';
import test from 'node:test';

import { buildVehiculoPayload } from '../../Public/js/vehiculos.js';

test('vehiculo nuevo: exactamente placa, vin y modelo', () => {
    const payload = buildVehiculoPayload({ placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2024' });

    assert.deepEqual(payload, { placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2024' });
    assert.equal('vehiculoId' in payload, false, 'el alta no envia vehiculoId');
});

test('vehiculo editado: agrega vehiculoId numerico', () => {
    const payload = buildVehiculoPayload({
        placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2025', vehiculoId: '7',
    });

    assert.deepEqual(payload, {
        placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2025', vehiculoId: 7,
    });
    assert.equal(typeof payload.vehiculoId, 'number', 'el backend valida entero, no texto');
});

test('los espacios sobrantes se recortan igual que antes', () => {
    const payload = buildVehiculoPayload({ placa: '  ABC-123 ', vin: ' VIN1 ', modelo: ' Hilux  ' });

    assert.deepEqual(payload, { placa: 'ABC-123', vin: 'VIN1', modelo: 'Hilux' });
});

test('el campo vacio no se convierte en vehiculoId 0', () => {
    // Number('') es 0, que el backend aceptaria como entero invalido en vez de
    // tratarlo como alta. El alta debe omitir la clave por completo.
    const payload = buildVehiculoPayload({ placa: 'A', vin: 'B', modelo: 'C', vehiculoId: '' });

    assert.equal('vehiculoId' in payload, false);
});

test('el panel conserva su endpoint', async () => {
    const { readFile } = await import('node:fs/promises');
    const source = await readFile(new URL('../../Public/js/vehiculos.js', import.meta.url), 'utf8');

    assert.match(source, /const API_URL = 'api\/vehiculos\.php';/);
});
