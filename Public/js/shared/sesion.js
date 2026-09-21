// Superficie del navegador: autenticado (escritura) frente a público (solo lectura).
//
// El backend no implementa login propio (DEC-30): resuelve el actor desde el
// encabezado Authorization Bearer y la superficie de identidad
// (GET api/identidad.php) devuelve los contextos de la persona. Este módulo:
//
//   1. Resuelve la superficie consultando identidad.php con el bearer del
//      navegador (si lo hay) — nunca inventa credenciales.
//   2. Conserva actor + bearer en sessionStorage únicamente cuando el
//      proveedor realmente devolvió una persona (flujo autenticado).
//   3. Deja el demo local sin bearer en modo público de solo lectura, tal como
//      documentan DEC-30 y DEC-33.
//
// La identidad vive en tbpersona: Productor, Comprador y Transportista son
// contextos de la misma persona (DEC-28/29), nunca roles administrativos.

import { request } from './api.js';

export const IDENTIDAD_URL = 'api/identidad.php';
export const SESSION_KEY = 'tindercows:login';
export const ACTOR_KEY = 'tindercows:actor';
export const BEARER_KEY = 'tindercows:bearer';

/** Bearer conservado por el proveedor de identidad (Supabase/OAuth). */
export function leerBearer(storage) {
    try {
        const token = storage?.getItem(BEARER_KEY);
        return typeof token === 'string' && token.trim() !== '' ? token.trim() : null;
    } catch {
        return null;
    }
}

export function guardarBearer(storage, token) {
    if (!storage) return;
    if (typeof token === 'string' && token.trim() !== '') {
        storage.setItem(BEARER_KEY, token.trim());
    } else {
        storage.removeItem(BEARER_KEY);
    }
}

/** Actor resuelto por la API (contextos de la persona autenticada). */
export function leerActor(storage) {
    try {
        const raw = storage?.getItem(ACTOR_KEY);
        if (!raw) return null;
        const actor = JSON.parse(raw);
        if (!actor || typeof actor !== 'object' || typeof actor.identificacionNumero !== 'string') {
            return null;
        }
        return actor;
    } catch {
        return null;
    }
}

export function guardarActor(storage, actor, bearer = null) {
    if (!storage) return;
    if (actor && typeof actor === 'object') {
        storage.setItem(ACTOR_KEY, JSON.stringify(actor));
    }
    guardarBearer(storage, bearer);
}

export function limpiarActor(storage) {
    if (!storage) return;
    storage.removeItem(ACTOR_KEY);
    storage.removeItem(BEARER_KEY);
}

/**
 * Consulta la superficie pública de identidad y decide si el navegador porta
 * una sesión verificable (actor con persona) o navega en modo público.
 *
 * @returns {{autenticado: boolean, motivo: string, actor: object|null,
 *            persona: object|null, data: object|null, error: Error|null}}
 */
export async function resolverSuperficie({
    requestImpl = request,
    storage = null,
    bearer = null,
} = {}) {
    const token = bearer ?? leerBearer(storage);
    let respuesta;
    try {
        respuesta = await requestImpl(IDENTIDAD_URL, {
            ...(token ? { headers: { Authorization: `Bearer ${token}` } } : {}),
        });
    } catch (error) {
        // 401 SIN_SESION o fallo de transporte: no hay superficie autenticada.
        return {
            autenticado: false,
            motivo: error?.status === 401 || error?.status === 403 ? 'SIN_SESION' : 'NO_DISPONIBLE',
            actor: null,
            persona: null,
            data: null,
            error: error ?? null,
        };
    }

    const datos = respuesta?.data ?? {};
    const persona = datos.persona ?? null;
    if (persona === null) {
        return { autenticado: false, motivo: 'PUBLICO', actor: null, persona: null, data: datos, error: null };
    }

    const actor = {
        identificacionNumero: datos.identificacionNumero ?? null,
        esProductor: datos.esProductor === true,
        esComprador: datos.esComprador === true,
        esTransportista: datos.esTransportista === true,
        persona,
    };
    return { autenticado: true, motivo: 'AUTENTICADO', actor, persona, data: datos, error: null };
}

/**
 * Flujo de inicio de sesión: resuelve la superficie y, si el proveedor
 * devolvió una persona, conserva el actor + bearer para las peticiones admin.
 * Sin bearer el resultado queda en modo público (no se fabrica una sesión).
 */
export async function flujoLogin({
    email = null,
    storage = null,
    requestImpl = request,
} = {}) {
    const resuelto = await resolverSuperficie({ requestImpl, storage });
    if (resuelto.autenticado && storage) {
        guardarActor(storage, resuelto.actor, leerBearer(storage));
    }
    return { ...resuelto, email: typeof email === 'string' ? email : null };
}