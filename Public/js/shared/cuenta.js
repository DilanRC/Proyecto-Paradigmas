// Estado de la cuenta con sesión iniciada.
//
// Una sesión de Supabase solo prueba que la persona entró con su correo; no que
// exista su Persona en Ganado Cerca. Quien decide eso es el servidor: GET
// api/v1/actividad responde 200 con la Persona o 409 con data.codigo
// REGISTRO_INCOMPLETO. Ningún otro fallo se interpreta como "no tiene Persona"
// (la clasificación está en estado-cuenta.js).
//
// tindercows:profile sigue siendo solo caché de presentación: se usa para pintar
// el encabezado sin esperar, y se vuelve a confirmar con el servidor cuando tiene
// más de VIGENCIA_PERFIL_MS. Las operaciones privadas las protege siempre PHP.

import { request } from './api.js?v=auth-gate-16';
import { readAuthSession } from './supabase-auth.js?v=session-4';
import { clearPublicProfile, publicProfileForSession, syncPublicProfile } from './public-profile.js?v=profile-2';
import { PUBLIC_DESTINATIONS } from './next.js?v=next-4';
import { CODIGO_REGISTRO_INCOMPLETO, ESTADO_CUENTA, esRegistroIncompleto, estadoPorError, estadoPublicado } from './estado-cuenta.js?v=estado-cuenta-1';

export { CODIGO_REGISTRO_INCOMPLETO, ESTADO_CUENTA, esRegistroIncompleto, estadoPorError, estadoPublicado };
export const VIGENCIA_PERFIL_MS = 120000;

/** Páginas que necesitan la Persona: sin ella solo queda terminar el registro. */
export const PAGINAS_CON_PERSONA = Object.freeze([
    'mi-actividad', 'mi-dashboard', 'ajustes', 'participar', 'me-interesa', 'publicar', 'fletes', 'subastas',
]);

/** Perfil de esta pestaña que el servidor confirmó hace menos de VIGENCIA_PERFIL_MS. */
export function perfilConfirmado(session, { storage, ahora = Date.now() } = {}) {
    const perfil = publicProfileForSession(session, storage);
    const confirmado = Date.parse(perfil?.updatedAt ?? '');
    const edad = ahora - confirmado;
    return perfil && Number.isFinite(edad) && edad >= 0 && edad < VIGENCIA_PERFIL_MS ? perfil : null;
}

function resultado(estado, extra = {}) {
    return { estado, perfil: null, datos: null, error: null, ...extra };
}

async function pedirCuenta(session, requestImpl, storage) {
    if (session?.authenticated !== true) return resultado(ESTADO_CUENTA.ANONIMA);
    try {
        const respuesta = await requestImpl('api/v1/actividad', { timeoutMs: 10000 });
        const perfil = syncPublicProfile(respuesta.data, storage);
        return resultado(ESTADO_CUENTA.COMPLETA, { perfil, datos: respuesta.data });
    } catch (error) {
        const estado = estadoPorError(error);
        // Un perfil en caché de antes no puede seguir presentando una cuenta que el servidor no acepta.
        if (estado === ESTADO_CUENTA.REGISTRO_INCOMPLETO || estado === ESTADO_CUENTA.CONFLICTO) clearPublicProfile(storage);
        return resultado(estado, { error });
    }
}

let consultaDeLaPagina = null;
let cuentaDeLaConsulta = '';

/**
 * Pregunta al servidor por la cuenta. El encabezado y la página comparten la
 * misma consulta por carga (una sola petición); `forzar` pide una nueva, para
 * "Reintentar" o después de guardar cambios.
 */
export function consultarCuenta({ session = readAuthSession(), requestImpl = request, storage, forzar = false } = {}) {
    // Solo se comparte la consulta real de la página, y solo para la misma cuenta.
    const compartible = requestImpl === request && storage === undefined;
    const cuenta = session?.authenticated === true ? String(session.email ?? '') : '';
    if (compartible && !forzar && consultaDeLaPagina && cuentaDeLaConsulta === cuenta) return consultaDeLaPagina;
    const consulta = pedirCuenta(session, requestImpl, storage);
    if (compartible) {
        consultaDeLaPagina = consulta;
        cuentaDeLaConsulta = cuenta;
    }
    return consulta;
}

/** "registro?next=…" que vuelve a la página actual si es un destino permitido. */
export function enlaceCompletarRegistro(location = globalThis.location) {
    let ruta = '';
    let consulta = '';
    try {
        const url = new URL(location?.href ?? '', 'http://localhost/');
        ruta = url.pathname.replace(/\/+$/, '').split('/').pop().replace(/\.php$/, '');
        consulta = url.search;
    } catch { /* sin destino: el registro decide */ }
    return PUBLIC_DESTINATIONS.has(ruta) ? `registro?next=${encodeURIComponent(`${ruta}${consulta}`)}` : 'registro';
}

function crear(tag, className, texto) {
    const nodo = document.createElement(tag);
    if (className) nodo.className = className;
    if (texto !== undefined) nodo.textContent = texto;
    return nodo;
}

function icono(clase) {
    const nodo = crear('i', `fa-solid ${clase}`);
    nodo.setAttribute('aria-hidden', 'true');
    return nodo;
}

/**
 * Oculta el contenido de una página privada y muestra el paso que falta. Es
 * idempotente: el encabezado y la página pueden pedirlo a la vez.
 */
export function mostrarRegistroIncompleto({ contenedor = globalThis.document?.querySelector('main'), correo = readAuthSession()?.email ?? '', location = globalThis.location } = {}) {
    if (!contenedor || contenedor.querySelector('[data-cuenta-pendiente]')) return null;
    const seccion = crear('section', 'cuenta-pendiente');
    seccion.dataset.cuentaPendiente = 'true';
    seccion.setAttribute('aria-labelledby', 'cuenta-pendiente-titulo');

    const encabezado = crear('div', 'cuenta-pendiente__icono');
    encabezado.append(icono('fa-user-check'));
    const kicker = crear('p', 'section-kicker', 'Cuenta verificada');
    const titulo = crear('h1', null, 'Tu cuenta está verificada. Termina de configurar tu perfil.');
    titulo.id = 'cuenta-pendiente-titulo';
    titulo.tabIndex = -1;

    const intro = crear('p', 'cuenta-pendiente__texto');
    if (correo) {
        intro.append('Entraste como ', crear('strong', null, correo), '. ');
    }
    intro.append('Para usar esta sección falta registrar en Ganado Cerca:');
    const faltante = crear('ul', 'cuenta-pendiente__lista');
    for (const texto of ['Tu nombre y número de identificación', 'Un teléfono de contacto', 'Cómo quieres participar: comprar, vender o transportar']) {
        faltante.append(crear('li', null, texto));
    }
    const nota = crear('p', 'cuenta-pendiente__nota', 'No tienes que crear otra cuenta ni volver a escribir tu contraseña. Mientras tanto puedes seguir viendo el ganado publicado.');

    const acciones = crear('div', 'cuenta-pendiente__acciones');
    const completar = crear('a', 'public-cta');
    completar.href = enlaceCompletarRegistro(location);
    completar.append(icono('fa-user-pen'), crear('span', null, 'Completar registro'));
    const explorar = crear('a', 'public-secondary');
    explorar.href = 'explorar';
    explorar.append(crear('span', null, 'Seguir explorando'), icono('fa-arrow-right'));
    acciones.append(completar, explorar);

    seccion.append(encabezado, kicker, titulo, intro, faltante, nota, acciones);
    // Conserva los IDs: consultas ya iniciadas pueden terminar después del gate.
    // El contenedor oculto e inerte impide que sus callbacks revelen la pantalla.
    const anterior = crear('div');
    anterior.hidden = true;
    anterior.inert = true;
    anterior.append(...contenedor.childNodes);
    contenedor.append(anterior, seccion);
    contenedor.removeAttribute('aria-busy');
    titulo.focus({ preventScroll: true });
    return seccion;
}
