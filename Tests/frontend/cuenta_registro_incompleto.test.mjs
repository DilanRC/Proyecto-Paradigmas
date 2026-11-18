// Cuenta de Supabase sin Persona: el servidor decide, el encabezado no finge una
// cuenta completa, el usuario puede terminar el registro y un fallo temporal no
// se confunde con "no tiene Persona".
//
// Ejecutar: node --test Tests/frontend/cuenta_registro_incompleto.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

import {
    CODIGO_REGISTRO_INCOMPLETO,
    ESTADO_CUENTA,
    PAGINAS_CON_PERSONA,
    VIGENCIA_PERFIL_MS,
    consultarCuenta,
    enlaceCompletarRegistro,
    esRegistroIncompleto,
    estadoPorError,
    perfilConfirmado,
    mostrarRegistroIncompleto,
} from '../../Public/js/shared/cuenta.js';
import { PROFILE_KEY } from '../../Public/js/shared/public-profile.js';
import { safeNext } from '../../Public/js/shared/next.js';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

function memoria(inicial = {}) {
    const datos = new Map(Object.entries(inicial));
    return {
        datos,
        getItem: (k) => datos.get(k) ?? null,
        setItem: (k, v) => datos.set(k, String(v)),
        removeItem: (k) => datos.delete(k),
    };
}

const SESION = { authenticated: true, email: 'ana@example.test' };
const ACTIVIDAD = {
    persona: { personaId: 7, nombre: 'Ana Rojas', correoElectronico: 'ana@example.test' },
    capacidades: { COMPRADOR: { estado: 'ACTIVO' }, PRODUCTOR: { estado: 'NO_CONFIGURADO' } },
};
const falla = (status, data = null, message = 'x') => Object.assign(new Error(message), { status, data, type: status ? 'http' : 'network' });
const responde = (fn) => {
    const llamadas = [];
    const requestImpl = async (url, opciones) => { llamadas.push({ url, opciones }); return fn(); };
    return { llamadas, requestImpl };
};

test('completar registro conserva el detalle de subasta y rechaza identificadores inválidos', () => {
    const enlace = enlaceCompletarRegistro({ href: 'https://staging.example/subastas?id=4' });
    assert.equal(safeNext(enlace.slice(enlace.indexOf('?'))), 'subastas?id=4');
    for (const id of ['-1', 'abc', '1.5', '1234567890']) {
        assert.equal(safeNext(`?next=${encodeURIComponent(`subastas?id=${id}`)}`), 'subastas');
    }
    assert.equal(safeNext('?next=ajustes%3Fid%3D4'), 'ajustes');
});

test('el aviso de registro conserva el DOM para respuestas pendientes sin mostrar el contenido privado', () => {
    class Nodo {
        constructor(tag) { this.tagName = tag; this.childNodes = []; this.dataset = {}; this.attributes = {}; }
        append(...nodes) {
            for (const node of nodes) {
                if (node.parentNode) node.parentNode.childNodes.splice(node.parentNode.childNodes.indexOf(node), 1);
                if (typeof node !== 'string') node.parentNode = this;
                this.childNodes.push(node);
            }
        }
        replaceChildren(...nodes) { this.childNodes.forEach((n) => { n.parentNode = null; }); this.childNodes = []; this.append(...nodes); }
        setAttribute(key, value) { this.attributes[key] = value; }
        removeAttribute(key) { delete this.attributes[key]; }
        focus() {}
        querySelector() { return this.childNodes.find((n) => n.dataset?.cuentaPendiente) ?? null; }
    }
    const original = globalThis.document;
    globalThis.document = { createElement: (tag) => new Nodo(tag) };
    try {
        const main = new Nodo('main');
        const loading = new Nodo('section');
        main.append(loading);
        mostrarRegistroIncompleto({ contenedor: main, correo: 'ana@example.test' });
        assert.equal(loading.parentNode?.parentNode, main, 'los IDs siguen dentro del documento');
        assert.equal(loading.parentNode.hidden, true);
        assert.equal(loading.parentNode.inert, true);
        loading.hidden = false; // respuesta tardía de la pantalla original
        assert.equal(loading.parentNode.hidden, true, 'el callback no vuelve visible la pantalla privada');
        assert.equal(mostrarRegistroIncompleto({ contenedor: main }), null, 'el aviso es idempotente');
    } finally { globalThis.document = original; }
});

test('solo el 409 con data.codigo REGISTRO_INCOMPLETO es "registro incompleto"', () => {
    assert.equal(CODIGO_REGISTRO_INCOMPLETO, 'REGISTRO_INCOMPLETO');
    assert.equal(esRegistroIncompleto(falla(409, { codigo: 'REGISTRO_INCOMPLETO' })), true);
    // Otros conflictos (correo en dos Personas, Persona no disponible) no se arreglan registrándose otra vez.
    assert.equal(esRegistroIncompleto(falla(409, null, 'El correo autenticado está vinculado a más de una Persona.')), false);
    assert.equal(esRegistroIncompleto(falla(409, { roles: {} })), false);
    for (const status of [401, 403, 422, 500, 503, null]) {
        assert.equal(esRegistroIncompleto(falla(status, { codigo: 'REGISTRO_INCOMPLETO' })), false, `status ${status}`);
    }
});

test('el código del navegador es el mismo que envía PHP', () => {
    const resolver = read('Application/Auth/SupabaseActorResolver.php');
    assert.match(resolver, /public const CODIGO_REGISTRO_INCOMPLETO = 'REGISTRO_INCOMPLETO';/);
    assert.match(resolver, /if \(\$exigirPersona && \$personaId === null\) \{\s*throw new HttpException\([\s\S]*?409,\s*\['codigo' => self::CODIGO_REGISTRO_INCOMPLETO\],/);
});

test('cuenta completa: el servidor confirma la Persona y se guarda el perfil de esta pestaña', async () => {
    const storage = memoria();
    const { llamadas, requestImpl } = responde(() => ({ success: true, data: ACTIVIDAD }));
    const cuenta = await consultarCuenta({ session: SESION, requestImpl, storage });
    assert.equal(cuenta.estado, ESTADO_CUENTA.COMPLETA);
    assert.equal(cuenta.perfil.persona.nombre, 'Ana Rojas');
    assert.equal(llamadas.length, 1);
    assert.equal(llamadas[0].url, 'api/v1/actividad');
    assert.ok(perfilConfirmado(SESION, { storage }), 'el perfil recién confirmado vale para el encabezado');
});

test('sesión sin Persona: REGISTRO_INCOMPLETO y se borra el perfil viejo de la caché', async () => {
    const storage = memoria({ [PROFILE_KEY]: JSON.stringify({ persona: { correoElectronico: 'ana@example.test', nombre: 'Viejo' }, updatedAt: new Date().toISOString() }) });
    const { requestImpl } = responde(() => { throw falla(409, { codigo: 'REGISTRO_INCOMPLETO' }); });
    const cuenta = await consultarCuenta({ session: SESION, requestImpl, storage });
    assert.equal(cuenta.estado, ESTADO_CUENTA.REGISTRO_INCOMPLETO);
    assert.equal(storage.datos.has(PROFILE_KEY), false, 'una caché vieja no puede seguir mostrando una cuenta completa');
});

test('un fallo temporal no se convierte en "no tiene Persona" ni borra la caché', async () => {
    const perfil = JSON.stringify({ persona: { correoElectronico: 'ana@example.test' }, updatedAt: new Date().toISOString() });
    for (const error of [falla(500), falla(503), falla(null), falla(400)]) {
        const storage = memoria({ [PROFILE_KEY]: perfil });
        const { requestImpl } = responde(() => { throw error; });
        const cuenta = await consultarCuenta({ session: SESION, requestImpl, storage });
        assert.equal(cuenta.estado, ESTADO_CUENTA.ERROR, `status ${error.status}`);
        assert.equal(storage.datos.get(PROFILE_KEY), perfil, `status ${error.status} no toca la caché`);
    }
});

test('403 u otro 409: conflicto de la cuenta, sin "Reintentar" ni alta nueva', async () => {
    const { accountPanelHtml } = await import('../../Public/js/public-ui.js');
    for (const error of [falla(409, null, 'El correo autenticado está vinculado a más de una Persona.'), falla(409, { codigo: 'OTRO' }), falla(403)]) {
        const storage = memoria({ [PROFILE_KEY]: JSON.stringify({ persona: { correoElectronico: 'ana@example.test' } }) });
        const { requestImpl } = responde(() => { throw error; });
        const cuenta = await consultarCuenta({ session: SESION, requestImpl, storage });
        assert.equal(cuenta.estado, ESTADO_CUENTA.CONFLICTO, `status ${error.status}`);
        assert.equal(storage.datos.has(PROFILE_KEY), false, 'la caché no sigue presentando una cuenta utilizable');
    }
    const panel = accountPanelHtml(ESTADO_CUENTA.CONFLICTO, 'ana@example.test');
    assert.ok(!panel.includes('data-cuenta-reintentar'), 'reintentar no arregla un conflicto');
    assert.ok(!panel.includes('href="mi-actividad"') && !panel.includes('Completar registro'));
    assert.ok(panel.includes('data-public-logout') && panel.includes('data-admin-link'));
});

test('login, encabezado y registro clasifican igual el mismo fallo', () => {
    const casos = [[falla(409, { codigo: 'REGISTRO_INCOMPLETO' }), ESTADO_CUENTA.REGISTRO_INCOMPLETO], [falla(409), ESTADO_CUENTA.CONFLICTO],
        [falla(403), ESTADO_CUENTA.CONFLICTO], [falla(401), ESTADO_CUENTA.ANONIMA], [falla(500), ESTADO_CUENTA.ERROR], [falla(null), ESTADO_CUENTA.ERROR]];
    for (const [error, estado] of casos) assert.equal(estadoPorError(error), estado, `status ${error.status}`);
    // Una sola definición: nadie más declara su propia tabla de estados.
    assert.match(read('Public/js/login.js'), /const fallo = estadoPorError\(error\);/);
    assert.doesNotMatch(read('Public/js/public-ui.js'), /export const ESTADO\b/);
    assert.match(read('Public/js/registro.js'), /cuenta\.estado === ESTADO_CUENTA\.CONFLICTO\)/);
});

test('401: la sesión ya no vale (no es registro incompleto) y sin sesión no se consulta nada', async () => {
    const { requestImpl } = responde(() => { throw falla(401); });
    const cuenta = await consultarCuenta({ session: SESION, requestImpl, storage: memoria() });
    assert.equal(cuenta.estado, ESTADO_CUENTA.ANONIMA);
    assert.equal(cuenta.error.status, 401);

    const anonimo = responde(() => ({ success: true, data: ACTIVIDAD }));
    const sinSesion = await consultarCuenta({ session: null, requestImpl: anonimo.requestImpl, storage: memoria() });
    assert.equal(sinSesion.estado, ESTADO_CUENTA.ANONIMA);
    assert.equal(anonimo.llamadas.length, 0);
});

test('la caché de perfil solo evita la consulta mientras es reciente y del mismo correo', () => {
    const ahora = Date.parse('2026-11-17T12:00:00Z');
    const guardado = (edadMs, correo = 'ana@example.test') => memoria({ [PROFILE_KEY]: JSON.stringify({
        persona: { correoElectronico: correo }, updatedAt: new Date(ahora - edadMs).toISOString(),
    }) });
    assert.ok(perfilConfirmado(SESION, { storage: guardado(5000), ahora }));
    assert.equal(perfilConfirmado(SESION, { storage: guardado(VIGENCIA_PERFIL_MS + 1), ahora }), null, 'vencido: se vuelve a preguntar');
    assert.equal(perfilConfirmado(SESION, { storage: guardado(5000, 'otra@example.test'), ahora }), null, 'otro correo: no cuenta');
    assert.equal(perfilConfirmado(SESION, { storage: guardado(-60000), ahora }), null, 'fecha futura: no cuenta');
    assert.equal(perfilConfirmado(SESION, { storage: memoria(), ahora }), null);
});

test('"Completar registro" vuelve a la página de origen solo si es un destino permitido', () => {
    const en = (href) => ({ href });
    assert.equal(enlaceCompletarRegistro(en('http://localhost/mi-actividad')), 'registro?next=mi-actividad');
    assert.equal(enlaceCompletarRegistro(en('http://localhost/sub/mi-dashboard')), 'registro?next=mi-dashboard');
    assert.equal(enlaceCompletarRegistro(en('http://localhost/subastas?id=4')), 'registro?next=subastas%3Fid%3D4');
    assert.equal(enlaceCompletarRegistro(en('http://localhost/admin/dashboard')), 'registro');
    assert.equal(enlaceCompletarRegistro(en('http://localhost/registro')), 'registro', 'sin bucle registro → registro');
    // El registro valida el next con safeNext: cada página privada es un destino válido.
    for (const pagina of PAGINAS_CON_PERSONA) assert.equal(safeNext(`?next=${pagina}`), pagina, pagina);
    assert.equal(safeNext('?next=https%3A%2F%2Fevil.test'), null);
    assert.equal(safeNext('?next=registro'), null, 'registro no es destino: no hay bucle');
});

test('el encabezado no presenta una cuenta completa sin Persona', async () => {
    const { accountPanelHtml, registroFor } = await import('../../Public/js/public-ui.js');
    const privados = ['href="mi-actividad"', 'href="mi-dashboard"', 'href="ajustes"', 'href="participar"', 'href="me-interesa"'];

    const incompleta = accountPanelHtml(ESTADO_CUENTA.REGISTRO_INCOMPLETO, 'ana@example.test', null, 'registro?next=mi-actividad');
    for (const enlace of privados) assert.ok(!incompleta.includes(enlace), `sin ${enlace}`);
    assert.ok(incompleta.includes('Cuenta verificada'));
    assert.ok(!incompleta.includes('Cuenta activa'));
    assert.ok(incompleta.includes('href="registro?next=mi-actividad"'));
    assert.ok(incompleta.includes('Completar registro'));
    assert.ok(incompleta.includes('data-public-logout'), 'puede cerrar sesión');
    assert.ok(incompleta.includes('data-admin-link'), 'un admin sin Persona conserva su acceso (lo decide admin/status)');

    const verificando = accountPanelHtml(ESTADO_CUENTA.VERIFICANDO, 'ana@example.test');
    for (const enlace of privados) assert.ok(!verificando.includes(enlace), `verificando sin ${enlace}`);
    assert.ok(verificando.includes('data-public-logout'));

    const completa = accountPanelHtml(ESTADO_CUENTA.COMPLETA, 'ana@example.test', { persona: { nombre: 'Ana <b>' } });
    for (const enlace of privados) assert.ok(completa.includes(enlace), `completa con ${enlace}`);
    assert.ok(completa.includes('Ana &lt;b&gt;'), 'el nombre se escapa');

    const error = accountPanelHtml(ESTADO_CUENTA.ERROR, 'ana@example.test');
    assert.ok(error.includes('data-cuenta-reintentar'), 'un error de verificación ofrece reintentar');
    assert.ok(error.includes('href="mi-actividad"'), 'un error temporal no esconde la cuenta');

    assert.equal(registroFor('http://localhost/me-interesa'), 'registro?next=me-interesa');
    assert.equal(registroFor('explorar?publicacion=9', 'http://localhost/'), 'registro?next=explorar%3Fpublicacion%3D9');
});

test('el encabezado confirma con el servidor y reacciona a cada estado', () => {
    const ui = read('Public/js/public-ui.js');
    // Con sesión el estado lo decide la consulta al servidor; la caché solo pinta mientras tanto.
    assert.match(ui, /pintar\(enCache \? \{ estado: ESTADO_CUENTA\.COMPLETA, perfil: enCache \} : \{ estado: ESTADO_CUENTA\.VERIFICANDO \}\);/);
    assert.match(ui, /if \(!modulo\.perfilConfirmado\(session\)\) void verificar\(\);/);
    assert.match(ui, /resultado\.error\?\.status === 401\) \{\s*void endExpiredSession\(resultado\.error\.authSessionVersion\);/);
    // Sin Persona: fuera Publicar/Fletes/Subastas, los enlaces privados llevan al registro y la página privada muestra el paso.
    assert.match(ui, /estado === ESTADO_CUENTA\.CONFLICTO\) \{\n\s+NAV_PERSONA\.forEach\(\(\[href\]\) => nav\.querySelectorAll/);
    assert.match(ui, /initializeIncompleteGate\(cuenta\.PAGINAS_CON_PERSONA\)/);
    assert.match(ui, /cuenta\.mostrarRegistroIncompleto\(\)/);
    // Repintar no acumula listeners ni intervalos de la campana.
    assert.match(ui, /controlador\?\.abort\(\);\n\s+controlador = new AbortController\(\);/);
    assert.match(ui, /signal\.addEventListener\('abort', \(\) => clearInterval\(intervalo\), \{ once: true \}\);/);
    // Las acciones de Explorar (Me interesa, Contactar) también piden terminar el registro.
    assert.match(ui, /estadoPublicado\(\) === ESTADO_CUENTA\.REGISTRO_INCOMPLETO\) \{\n\s+\/\/[^\n]*\n\s+destination = registroFor\(origen, document\.baseURI\);/);
    // cuenta.js (y api.js) solo se descargan con sesión: un visitante anónimo no paga ese JS.
    assert.doesNotMatch(ui, /^import .*\/cuenta\.js/m);
    assert.match(ui, /import\('\.\/shared\/cuenta\.js\?v=cuenta-2'\)/);
});

test('las páginas privadas tratan solo el 409 REGISTRO_INCOMPLETO como registro pendiente', () => {
    const dashboard = read('Public/js/mi-dashboard.js');
    const incompleto = dashboard.indexOf('if (esRegistroIncompleto(error)) { mostrarRegistroIncompleto(); return; }');
    assert.ok(incompleto > -1 && incompleto < dashboard.indexOf('if (error?.status === 409) {'),
        'antes de "sin rol": una cuenta sin Persona no es "todavía no tienes un dashboard"');
    const panel = read('Public/js/mi-actividad.js');
    assert.match(panel, /else if \(esRegistroIncompleto\(error\)\) mostrarRegistroIncompleto\(\);/);
    assert.match(panel, /loadActivity\(\{ compartida: true \}\)/, 'Mi panel reutiliza la consulta del encabezado');
    const subastas = read('Public/js/subastas.js');
    assert.match(subastas, /if \(esRegistroIncompleto\(error\)\) \{ mostrarRegistroIncompleto\(\); return true; \}/);
    assert.match(subastas, /async function cargarLista\(\{ silencioso = false, conservar = false \} = \{\}\) \{\s*if \(cuentaIncompleta\(\)\) return;/, 'deja de consultar cada 30 s');
});

test('login: con la Persona ya guardada no se vuelve a crear', () => {
    // Con la Persona ya guardada, un fallo al leer el perfil no vuelve a crearla: se sigue al destino.
    const login = read('Public/js/login.js');
    assert.match(login, /if \(!await completarRegistroPendiente\(email\)\) return false;[\s\S]*?try \{ await loadBusinessProfile\(\); \} catch \{[^}]*\}\n\s+window\.location\.assign\(resolveNext\(window\.location\.search\)\);\n\s+return true;/);
});

test('registro: sesión sin Persona completa el alta con su correo, sin contraseña ni datos ajenos', async () => {
    const { borradorDeLaCuenta } = await import('../../Public/js/registro.js');
    const propio = { persona: { correoElectronico: 'Ana@Example.test', identificacionNumero: '112340817' } };
    const ajeno = { persona: { correoElectronico: 'otra@example.test', identificacionNumero: '999999999' } };
    assert.equal(borradorDeLaCuenta(propio, 'ana@example.test'), propio);
    assert.equal(borradorDeLaCuenta(ajeno, 'ana@example.test'), null, 'el borrador de otra cuenta no rellena cédula ni teléfono');
    assert.equal(borradorDeLaCuenta(ajeno, ''), ajeno, 'sin sesión el borrador es de quien está llenando');
    assert.equal(borradorDeLaCuenta(ajeno, '', true), null, 'sesión sin correo utilizable: no hereda datos personales');
    assert.equal(borradorDeLaCuenta(null, 'ana@example.test'), null);

    const registro = read('Public/js/registro.js');
    // Un error del servidor no habilita un alta: se ofrece reintentar.
    assert.match(registro, /if \(cuenta\.estado === ESTADO_CUENTA\.ERROR \|\| cuenta\.estado === ESTADO_CUENTA\.CONFLICTO\) \{[\s\S]*?mostrarErrorVerificacion\(form, cuenta\.error, cuenta\.estado === ESTADO_CUENTA\.CONFLICTO\);\n\s+return;/);
    assert.doesNotMatch(registro, /catch \{\n\s+\/\/ Un usuario autenticado sin Persona aún puede completar la primera alta\./);
    // Con sesión: correo fijo y sin contraseña; la cuenta Auth no se vuelve a crear.
    assert.match(registro, /email\.value = authSession\.email;\n\s+email\.readOnly = true;/);
    assert.match(registro, /if \(!readAuthSession\(\)\) \{\n\s+setStatus\(status, 'Creando tu cuenta…'\);\n\s+const auth = await signUpWithPassword/);
    assert.match(registro, /presentarCuentaVerificada\(authSession\.email\)/);
});
