import assert from 'node:assert/strict';
import test from 'node:test';

import { buildRegistrationSummary } from '../../Public/js/shared/business-rules.js';

test('el resumen de registro conserva las actividades seleccionadas', () => {
    const summary = buildRegistrationSummary({
        persona: {
            identificacionTipo: 'CEDULA_FISICA',
            identificacionNumero: '1-1111-1111',
            nombres: 'Ana María',
            apellidos: 'Solano Vargas',
            telefono: '8888-8888',
            correoElectronico: 'ana@example.test',
        },
        capacidades: ['PRODUCTOR', 'COMPRADOR'],
        fincas: [{ nombre: 'Finca El Roble' }],
    });

    assert.deepEqual(summary.capacidades, ['PRODUCTOR', 'COMPRADOR']);
    assert.equal(summary.persona.nombre, 'Ana María Solano Vargas');
    assert.deepEqual(summary.fincas, [{ nombre: 'Finca El Roble' }]);
});
