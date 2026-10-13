import './auth-gate.js?v=auth-gate-12';
import './admin-ui.js?v=admin-14';
import { getAccessToken, readAuthSession } from './supabase-auth.js';

// Token Bearer vigente en esta pestaña (Supabase/proveedor de identidad).
// Se adjunta a las peticiones para que la API resuelva al actor y las
// superficies privadas dejen de responder 401 SIN_SESION (DEC-30). Sin token,
// el modo local navega en público de solo lectura (DEC-33).
let bearerVigente = null;

export function setBearer(token) {
    bearerVigente = typeof token === 'string' && token.trim() !== '' ? token.trim() : null;
}

export function getBearer() {
    return bearerVigente;
}

// Acceso HTTP y taxonomia de fallos.
//
// Separa estructuralmente dos familias que antes se confundian:
//
//   type: 'http'      hubo respuesta del servidor -> status es un numero real
//   type: 'network'   no hubo respuesta          -> status es null
//
// Cuando existe una sesión Supabase real, esta capa adjunta su JWT como Bearer.
// Los endpoints PHP siguen verificando identidad por SupabaseActorResolver; el
// navegador nunca convierte el correo/cédula guardados localmente en autoridad.

/** Mensaje por defecto cuando el servidor no envia uno propio. */
const MENSAJE_HTTP = {
    400: 'La solicitud no se pudo interpretar.',
    401: 'Debe iniciar sesión para completar esta operación.',
    403: 'Su cuenta no tiene permiso para esta operación.',
    404: 'El registro no existe o fue retirado.',
    405: 'La operación no está permitida sobre este recurso.',
    409: 'El registro entra en conflicto con uno existente.',
    413: 'La solicitud es demasiado grande. Use un texto más corto o una imagen más liviana.',
    415: 'El formato de la solicitud no es admitido.',
    422: 'Revise los campos indicados.',
    429: 'Hizo demasiadas solicitudes seguidas. Espere un momento e intente de nuevo.',
};

/** Clasificacion estable por codigo, para que la UI decida sin mirar numeros. */
export function httpKind(status) {
    if (status >= 500) return 'server';
    return {
        400: 'bad-request', 401: 'authentication', 404: 'not-found', 405: 'method',
        409: 'conflict', 415: 'unsupported-media', 422: 'validation',
    }[status] ?? 'unknown';
}

/** Fallo con respuesta del servidor. Conserva errors y data del contrato PHP. */
export function describeHttpFailure(status, payload = {}) {
    const kind = httpKind(status);
    return {
        ok: false,
        type: 'http',
        status,
        kind,
        message: payload.message || MENSAJE_HTTP[status] || (status >= 500
            ? 'El servidor tuvo un problema. Intente de nuevo en unos minutos.'
            : 'No fue posible completar la operación.'),
        errors: payload.errors ?? null,
        data: payload.data ?? null,
        retryable: kind === 'server',
    };
}

/** Fallo sin respuesta: DNS, offline, conexion rechazada, CORS. */
export function describeNetworkFailure(error) {
    return {
        ok: false,
        type: 'network',
        status: null,
        kind: 'network',
        message: 'No fue posible comunicarse con el servidor. Revise su conexión.',
        errors: null,
        data: null,
        retryable: true,
        cause: error?.message ?? null,
    };
}

/** Hubo respuesta, pero no era JSON interpretable. */
export function describeInvalidResponse(status) {
    return {
        ok: false,
        type: 'http',
        status,
        kind: 'invalid-response',
        message: MENSAJE_HTTP[status] || 'El servidor no devolvió una respuesta válida. Intente de nuevo en unos minutos.',
        errors: null,
        data: null,
        retryable: true,
    };
}

/** Convierte una descripcion en Error conservando sus campos para el llamador. */
export function toError(failure) {
    const error = new Error(failure.message);
    return Object.assign(error, failure);
}

function hasAuthorizationHeader(headers = {}) {
    return Object.keys(headers).some((name) => name.toLowerCase() === 'authorization');
}

async function bearerForRequest(options) {
    if (hasAuthorizationHeader(options.headers ?? {})) return null;
    try {
        return await getAccessToken();
    } catch {
        // Una sesión expirada no debe romper las rutas públicas. El helper de
        // Auth elimina la sesión únicamente cuando Supabase la rechaza; la API
        // PHP decidirá si el recurso concreto admite acceso anónimo.
        return null;
    }
}

async function executeJsonRequest(url, options, fetchImpl, bearer) {
    let response;
    try {
        response = await fetchImpl(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(bearer ? { Authorization: `Bearer ${bearer}` } : {}),
                ...(options.headers ?? {}),
                // El bearer del proveedor viaja adjunto cuando existe; los
                // headers explícitos del llamador tienen la última palabra.
                ...(bearerVigente && !(options.headers && 'Authorization' in options.headers)
                    ? { Authorization: `Bearer ${bearerVigente}` }
                    : {}),
            },
        });
    } catch (error) {
        if (error?.name === 'AbortError') throw error;
        throw toError(describeNetworkFailure(error));
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw toError(describeInvalidResponse(response.status));
    }

    return { response, payload };
}

/**
 * Ejecuta la peticion y devuelve el cuerpo JSON ya validado.
 *
 * Si un PHP responde 401 y había una sesión Supabase real, renueva el JWT una
 * sola vez y repite la misma solicitud. Nunca repite otros estados ni entra en
 * bucle; una autorización de negocio 409/422 se devuelve tal como la decidió PHP.
 */
export async function request(url, options = {}, { fetchImpl = globalThis.fetch } = {}) {
    const timeoutMs = Number(options.timeoutMs ?? 20000);
    const controller = options.signal ? null : new AbortController();
    const requestOptions = controller ? { ...options, signal: controller.signal } : options;
    const timeout = controller && Number.isFinite(timeoutMs) && timeoutMs > 0
        ? setTimeout(() => controller.abort(), timeoutMs) : null;
    const bearer = await bearerForRequest(options);
    try {
        let { response, payload } = await executeJsonRequest(url, requestOptions, fetchImpl, bearer);

    if (
        response.status === 401
        && bearer
        && readAuthSession()
        && !hasAuthorizationHeader(options.headers ?? {})
    ) {
        try {
            const refreshedBearer = await getAccessToken({ forceRefresh: true });
            if (refreshedBearer) {
                ({ response, payload } = await executeJsonRequest(url, requestOptions, fetchImpl, refreshedBearer));
            }
        } catch {
            // Conservamos la respuesta 401 original. La UI puede redirigir al
            // login; no disfrazamos una sesión inválida como error de red.
        }
    }

        if (!response.ok || payload.success !== true) {
            throw toError(describeHttpFailure(response.status, payload));
        }
        return payload;
    } catch (error) {
        if (error?.name === 'AbortError' && controller?.signal.aborted) {
            throw toError({ ...describeNetworkFailure(error), message: 'El servidor tardó demasiado en responder. Intenta nuevamente.' });
        }
        throw error;
    } finally {
        if (timeout) clearTimeout(timeout);
    }
}
