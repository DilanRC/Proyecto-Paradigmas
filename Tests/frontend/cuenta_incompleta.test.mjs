// Cuenta autenticada sin Persona y errores al confirmar el perfil.
// Ejecutar: node --test Tests/frontend/cuenta_incompleta.test.mjs

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { accountPanelHtml } from '../../Public/js/public-ui.js';
import { PROFILE_KEY } from '../../Public/js/shared/public-profile.js';
import { consultarCuenta, enlaceCompletarRegistro, esRegistroIncompleto, ESTADO_CUENTA } from '../../Public/js/shared/cuenta.js';
import { borradorDeLaCuenta } from '../../Public/js/registro.js';

function almacenamiento() {
    const datos = new Map();
    return {
        getItem: (clave) => datos.get(clave) ?? null,
        setItem: (clave, valor) => datos.set(clave, String(valor)),
        removeItem: (clave) => datos.delete(clave),
    };
}

const perfilViejo = {
    persona: { personaId: 7, correoElectronico: 'dilan@example.com', nombre: 'Dilan' },
    capacidades: ['COMPRADOR'],
    updatedAt: new Date().toISOString(),
};

test('solo el 409 con código REGISTRO_INCOMPLETO significa que falta crear Persona', () => {
    assert.equal(esRegistroIncompleto({ status: 409, data: { codigo: 'REGISTRO_INCOMPLETO' } }), true);
    assert.equal(esRegistroIncompleto({ status: 409 }), false);
    assert.equal(esRegistroIncompleto({ status: 409, data: { codigo: 'OTRO_CONFLICTO' } }), false);
    assert.equal(esRegistroIncompleto({ status: 503, data: { codigo: 'REGISTRO_INCOMPLETO' } }), false);
});

test('borra el perfil en caché solo ante el código explícito de registro incompleto', async () => {
    const storage = almacenamiento();
    storage.setItem(PROFILE_KEY, JSON.stringify(perfilViejo));
    const session = { authenticated: true, email: 'dilan@example.com' };
    const incompleta = await consultarCuenta({
        session,
        storage,
        requestImpl: async () => { throw Object.assign(new Error('Termina el registro.'), { status: 409, data: { codigo: 'REGISTRO_INCOMPLETO' } }); },
    });
    assert.equal(incompleta.estado, ESTADO_CUENTA.REGISTRO_INCOMPLETO);
    assert.equal(storage.getItem(PROFILE_KEY), null, 'no conserva una Persona vieja al confirmar que falta el vínculo');

    storage.setItem(PROFILE_KEY, JSON.stringify(perfilViejo));
    const conflicto = await consultarCuenta({
        session,
        storage,
        requestImpl: async () => { throw Object.assign(new Error('Conflicto distinto.'), { status: 409, data: { codigo: 'IDENTIFICACION_DUPLICADA' } }); },
    });
    assert.equal(conflicto.estado, ESTADO_CUENTA.CONFLICTO, 'otro 409 es un conflicto de la cuenta, no "registro incompleto" ni un fallo para reintentar');
});

test('la verificación confirmada sincroniza el perfil desde el servidor', async () => {
    const storage = almacenamiento();
    const resultado = await consultarCuenta({
        session: { authenticated: true, email: 'dilan@example.com' },
        storage,
        requestImpl: async () => ({ data: {
            persona: { personaId: 7, correoElectronico: 'dilan@example.com', nombre: 'Dilan' },
            capacidades: { COMPRADOR: { estado: 'ACTIVO' } },
        } }),
    });
    assert.equal(resultado.estado, ESTADO_CUENTA.COMPLETA);
    assert.equal(resultado.perfil.persona.nombre, 'Dilan');
    assert.equal(JSON.parse(storage.getItem(PROFILE_KEY)).persona.personaId, 7);
});

test('las páginas privadas conservan el destino; solo un estado incompleto retira las acciones', () => {
    assert.equal(enlaceCompletarRegistro({ href: 'https://sitio.test/ajustes?tab=privacidad' }), 'registro?next=ajustes%3Ftab%3Dprivacidad');
    assert.equal(enlaceCompletarRegistro({ href: 'https://sitio.test/https://mal.test' }), 'registro');
    const error = accountPanelHtml(ESTADO_CUENTA.ERROR, 'dilan@example.com');
    assert.match(error, /data-cuenta-reintentar/);
    assert.match(error, /href="mi-actividad"/, 'un fallo temporal no borra la cuenta; la API mantiene la autorización real');
    const incompleta = accountPanelHtml(ESTADO_CUENTA.REGISTRO_INCOMPLETO, 'dilan@example.com');
    assert.match(incompleta, /Completar registro/);
    assert.doesNotMatch(incompleta, /href="mi-actividad"|href="fletes"|href="subastas"/);
});

test('el borrador personal no se comparte entre cuentas ni se rellena con sesión sin correo', () => {
    const draft = { persona: { correoElectronico: 'Dilan@example.com', identificacionNumero: '119290367' } };
    assert.equal(borradorDeLaCuenta(draft, ''), draft, 'quien todavía no inició sesión puede retomar su propio formulario');
    assert.equal(borradorDeLaCuenta(draft, ' dilan@EXAMPLE.com '), draft, 'el correo se compara sin distinguir mayúsculas ni espacios');
    assert.equal(borradorDeLaCuenta(draft, 'otra@example.com'), null);
    assert.equal(borradorDeLaCuenta(draft, '', true), null, 'una sesión sin correo no debe heredar datos personales');
});

// Reserva B: el registro se terminó en otro navegador mientras este tenía el
// formulario abierto. El 409 del alta no se queda como error si el servidor
// confirma la cuenta completa; cualquier otra respuesta sigue siendo error.
test('un 409 del alta con la cuenta ya completa en otro lugar lleva al panel sin reenviar el alta', async () => {
    const { registroCompletadoEnOtroLugar } = await import('../../Public/js/registro.js');
    const { ESTADO_CUENTA, CODIGO_REGISTRO_INCOMPLETO } = await import('../../Public/js/shared/cuenta.js');
    const consultas = [];
    const completa = async (opciones) => { consultas.push(opciones); return { estado: ESTADO_CUENTA.COMPLETA }; };
    const conflicto = { status: 409, data: {} };

    assert.equal(await registroCompletadoEnOtroLugar(conflicto, { consultar: completa }), true);
    assert.deepEqual(consultas, [{ forzar: true }], 'pregunta de nuevo al servidor, sin la consulta cacheada de la página');

    // Solo el servidor decide: sin cuenta completa, error de red o un 409 de registro incompleto, sigue el error.
    assert.equal(await registroCompletadoEnOtroLugar(conflicto, { consultar: async () => ({ estado: ESTADO_CUENTA.CONFLICTO }) }), false);
    assert.equal(await registroCompletadoEnOtroLugar(conflicto, { consultar: async () => { throw new Error('red'); } }), false);
    let preguntada = false;
    const espia = async () => { preguntada = true; return { estado: ESTADO_CUENTA.COMPLETA }; };
    assert.equal(await registroCompletadoEnOtroLugar({ status: 409, data: { codigo: CODIGO_REGISTRO_INCOMPLETO } }, { consultar: espia }), false);
    assert.equal(await registroCompletadoEnOtroLugar({ status: 422, errors: {} }, { consultar: espia }), false);
    assert.equal(await registroCompletadoEnOtroLugar({ status: 500 }, { consultar: espia }), false);
    assert.equal(preguntada, false, 'otros errores no consultan ni redirigen');

    const js = readFileSync(new URL('../../Public/js/registro.js', import.meta.url), 'utf8');
    assert.match(js, /if \(!extending && readAuthSession\(\) && await registroCompletadoEnOtroLugar\(error\)\) \{[\s\S]*?window\.location\.assign\(resolveNext\('mi-actividad'\)\);/);
});
