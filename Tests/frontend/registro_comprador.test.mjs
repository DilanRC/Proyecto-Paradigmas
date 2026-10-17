// El alta elige un rol (Comprador, Vendedor o Transportista), pide solo sus datos y siempre suma Comprador.
//
// Ejecutar: node --test Tests/frontend/registro_comprador.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('el alta pide el formulario del rol elegido; la ampliación conserva su flujo', async () => {
    const { registrationSteps, registrationCapabilities } = await import('../../Public/js/registro.js');
    assert.deepEqual(registrationSteps(['COMPRADOR'], false), ['persona'], 'Comprador: un solo formulario');
    assert.deepEqual(registrationSteps(['PRODUCTOR'], false), ['persona', 'fincas'], 'Vendedor: sus fincas');
    assert.deepEqual(registrationSteps(['TRANSPORTISTA'], false), ['persona', 'vehiculo'], 'Transportista: su vehículo');
    assert.deepEqual(registrationCapabilities(['PRODUCTOR'], false), ['COMPRADOR', 'PRODUCTOR'],
        'una cuenta nueva siempre es también Comprador');
    assert.deepEqual(registrationCapabilities(['COMPRADOR'], false), ['COMPRADOR']);
    // Activar Vendedor desde Ajustes muestra solo el formulario de fincas.
    assert.deepEqual(registrationSteps(['COMPRADOR', 'TRANSPORTISTA'], true, 'PRODUCTOR'), ['fincas']);
    assert.deepEqual(registrationCapabilities(['COMPRADOR', 'TRANSPORTISTA'], true, 'PRODUCTOR'), ['PRODUCTOR'],
        'solo se envía la actividad elegida, nunca las ya configuradas');
    // Sin actividad elegida, la ampliación conserva su flujo completo.
    assert.deepEqual(registrationSteps(['PRODUCTOR'], true), ['intereses', 'fincas', 'revision']);
    assert.deepEqual(registrationCapabilities(['PRODUCTOR'], true), ['PRODUCTOR']);
});

test('"Configurar" desde Ajustes preselecciona la actividad aun con URL bonita', async () => {
    const { requestedCapability } = await import('../../Public/js/registro.js');
    assert.equal(requestedCapability({ pathname: '/registro/productor', search: '?next=mi-actividad' }), 'productor');
    assert.equal(requestedCapability({ pathname: '/registro', search: '?capacidad=transportista' }), 'transportista');
    assert.equal(requestedCapability({ pathname: '/registro', search: '' }), null);
});

test('después de registrarse va a Explorar; al ampliar vuelve al panel', () => {
    const js = read('Public/js/registro.js');
    assert.match(js, /const fallback = extending \? 'mi-actividad\?actualizado=1' : vehiculoPendiente \? 'mi-actividad' : 'explorar';/);
    // Las fincas solo viajan si se activa Vendedor (en el alta o al ampliar).
    assert.match(js, /fincas: capacidadesEnvio\.includes\('PRODUCTOR'\) \? summary\.fincas : \[\]/);
    // El vehículo del Transportista se crea con la API de Mi panel, después del alta.
    assert.match(js, /request\('api\/v1\/mi-vehiculos', \{ method: 'POST', body: JSON\.stringify\(draft\.vehiculo\) \}\)/);
    const vista = read('Application/View/registro/index.php');
    assert.equal((vista.match(/type="radio" name="rol"/g) || []).length, 3, 'Comprador, Vendedor y Transportista');
    assert.match(vista, /name="rol" value="COMPRADOR" checked/);
    for (const campo of ['placa', 'vin', 'modelo']) assert.match(vista, new RegExp(`name="${campo}"`));
    // Diseño del formulario: tipo con radios, cuadrícula, plegables y "Registrar".
    assert.equal((vista.match(/type="radio" name="identificacionTipo"/g) || []).length, 5);
    assert.match(vista, /value="CEDULA_FISICA" checked/);
    assert.match(vista, /class="signup-grid"/);
    assert.equal((vista.match(/<details class="signup-accordion">/g) || []).length, 2);
    assert.match(vista, /<span data-finish-label>Registrar<\/span>/);
    assert.doesNotMatch(vista, />Terminar registro</);
});

test('el destino después de entrar o registrarse es seguro y conserva la publicación', async () => {
    const { safeNext } = await import('../../Public/js/shared/next.js');
    const { resolveNext } = await import('../../Public/js/login.js');
    assert.equal(safeNext('?next=explorar%3Fpublicacion%3D9'), 'explorar?publicacion=9');
    assert.equal(safeNext('?next=registro%2Fproductor'), null);
    assert.equal(safeNext('?next=https://example.com'), null);
    assert.equal(safeNext('?next=ajustes'), 'ajustes');
    // Sin destino de origen, iniciar sesión lleva a Explorar (no al panel).
    assert.equal(resolveNext(''), 'explorar');
    assert.equal(resolveNext('?next=mi-actividad'), 'mi-actividad');
    assert.equal(read('Public/js/registro.js').includes('SAFE_NEXT'), false, 'registro usa el next compartido');
});

test('con confirmación de correo, el registro cierra el formulario y va a Entrar', async () => {
    const { loginPendiente } = await import('../../Public/js/registro.js');
    assert.equal(loginPendiente(''), 'entrar?registro=confirmar&next=explorar');
    assert.equal(loginPendiente('?next=explorar%3Fpublicacion%3D4'),
        'entrar?registro=confirmar&next=explorar%3Fpublicacion%3D4');
    const login = read('Public/js/login.js');
    assert.match(login, /get\('registro'\) === 'confirmar'/);
    assert.match(login, /completarRegistroPendiente\(email\)/);
});

test('al entrar tras confirmar, el registro se termina con el borrador y sin contraseña', async () => {
    const { completarRegistroPendiente, REGISTRATION_DRAFT_KEY } = await import('../../Public/js/shared/registro-pendiente.js');
    const memoria = new Map();
    const storage = {
        getItem: (k) => memoria.get(k) ?? null,
        setItem: (k, v) => memoria.set(k, v),
        removeItem: (k) => memoria.delete(k),
    };
    memoria.set(REGISTRATION_DRAFT_KEY, JSON.stringify({
        persona: { identificacionTipo: 'CEDULA_FISICA', identificacionNumero: '112340817', nombres: 'Ana', apellidos: 'Rojas',
            telefono: '+506 8888 8888', correoElectronico: 'Ana@Example.test' },
        capacidades: ['PRODUCTOR'],
    }));
    const llamadas = [];
    const requestImpl = async (url, opciones) => { llamadas.push({ url, cuerpo: JSON.parse(opciones.body) }); return { success: true }; };

    assert.equal(await completarRegistroPendiente('otra@example.test', { storage, requestImpl }), false,
        'un borrador de otro correo no se usa');
    assert.equal(llamadas.length, 0);

    assert.equal(await completarRegistroPendiente('ana@example.test', { storage, requestImpl }), true);
    assert.equal(llamadas[0].url, 'api/v1/registro');
    assert.deepEqual(llamadas[0].cuerpo.capacidades, ['COMPRADOR', 'PRODUCTOR'], 'el rol elegido, siempre con Comprador');
    assert.equal('password' in llamadas[0].cuerpo.persona, false);
    assert.equal(memoria.has(REGISTRATION_DRAFT_KEY), false, 'el borrador se borra al terminar');
});

test('un administrador que se registra como usuario termina su registro antes de ir al panel', () => {
    const login = readFileSync(new URL('../../Public/js/login.js', import.meta.url), 'utf8');
    const pendiente = login.indexOf('await completarRegistroPendiente(email)');
    const admin = login.indexOf('error?.status === 409 && await isAdminAccount()');
    assert.ok(pendiente > -1 && admin > -1 && pendiente < admin, 'el borrador pendiente se completa antes del acceso admin');
    // Por /admin/entrar se sigue priorizando el panel.
    assert.match(login, /error\?\.status === 409 && !isAdminLogin\(window\.location\)/);
});
