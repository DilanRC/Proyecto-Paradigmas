// Flujo de inscripción desde la vista (Tramo B): inscribir / abandonar /
// reactivar contextos vía /api/capacidades.php con el sobre
// {success,message,data,errors}, sin exigir que el usuario entienda el modelo.
// Un 401 SIN_SESION se traduce en "inicie sesión", nunca en un error genérico.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
    accionParaContexto, CAPACIDADES_URL, CONTEXTOS_INSCRIPCION,
    enviarAccionInscripcion, ETIQUETA_ACCION,
} from '../../Public/js/shared/inscripcion.js';

test('el catálogo presenta los tres contextos por su acción de negocio', () => {
    assert.deepEqual(
        CONTEXTOS_INSCRIPCION.map((c) => c.clave),
        ['comprador', 'productor', 'transportista'],
    );
    for (const contexto of CONTEXTOS_INSCRIPCION) {
        assert.ok(contexto.accionNatural.length > 0, `${contexto.clave} sin acción natural`);
        assert.ok(contexto.descripcion.length > 0, `${contexto.clave} sin descripción`);
    }
});

test('la acción de negocio se decide por el estado del contexto', () => {
    assert.equal(accionParaContexto({ situacion: 'registrado', estado: 'ACTIVO' }), 'abandonar');
    assert.equal(accionParaContexto({ situacion: 'registrado', estado: 'INACTIVO' }), 'reactivar');
    assert.equal(accionParaContexto({ situacion: 'no-registrado', estado: null }), 'inscribir');
    assert.equal(accionParaContexto({ situacion: 'desconocido', estado: null }), 'indefinido');
});

test('las etiquetas humanas cubren las tres acciones', () => {
    assert.equal(ETIQUETA_ACCION.inscribir, 'Inscribirme');
    assert.equal(ETIQUETA_ACCION.abandonar, 'Abandonar');
    assert.equal(ETIQUETA_ACCION.reactivar, 'Reactivar');
});

test('envía el pedido exacto del contrato capacidades.php', async () => {
    let cuerpo = null;
    const requestImpl = async (url, options) => {
        assert.equal(url, CAPACIDADES_URL);
        assert.equal(options.method, 'POST');
        cuerpo = JSON.parse(options.body);
        return { success: true, message: 'ok', data: { estado: 'ACTIVO' } };
    };

    const desenlace = await enviarAccionInscripcion({
        contexto: 'comprador',
        accion: 'inscribir',
        identificacionNumero: '1-1111-1111',
        datosPersona: { nombre: 'Ana' },
        motivo: 'INSCRIPCION',
        requestImpl,
    });

    assert.equal(desenlace.ok, true);
    assert.equal(cuerpo.accion, 'inscribir');
    assert.equal(cuerpo.contexto, 'comprador');
    assert.equal(cuerpo.identificacionNumero, '1-1111-1111');
    assert.equal(cuerpo.motivo, 'INSCRIPCION');
    assert.deepEqual(cuerpo.datosPersona, { nombre: 'Ana' });
});

test('un 401 SIN_SESION se marca como requiereSesion (la vista ofrece iniciar sesión)', async () => {
    const falloUnauthorized = Object.assign(new Error('Debe iniciar sesión'), { status: 401 });
    const desenlace = await enviarAccionInscripcion({
        contexto: 'transportista',
        accion: 'reactivar',
        identificacionNumero: '1-1111-1111',
        requestImpl: async () => { throw falloUnauthorized; },
    });

    assert.equal(desenlace.ok, false);
    assert.equal(desenlace.requiereSesion, true);
});

test('un fallo de red no se presenta como sesión vencida', async () => {
    const desenlace = await enviarAccionInscripcion({
        contexto: 'productor',
        accion: 'abandonar',
        identificacionNumero: '1-1111-1111',
        requestImpl: async () => { throw new TypeError('Failed to fetch'); },
    });

    assert.equal(desenlace.ok, false);
    assert.equal(desenlace.requiereSesion, false);
});

test('sin datosPersona ni motivo el cuerpo conserva su forma mínima', async () => {
    let cuerpo = null;
    await enviarAccionInscripcion({
        contexto: 'comprador', accion: 'abandonar', identificacionNumero: '1',
        requestImpl: async (url, options) => { cuerpo = JSON.parse(options.body); return {}; },
    });
    assert.deepEqual(cuerpo, { accion: 'abandonar', contexto: 'comprador', identificacionNumero: '1' });
});