// Acceso HTTP y taxonomia de fallos.
//
// Separa estructuralmente dos familias que antes se confundian:
//
//   type: 'http'      hubo respuesta del servidor -> status es un numero real
//   type: 'network'   no hubo respuesta          -> status es null
//
// Antes, al rechazarse fetch() el codigo mostraba el texto crudo del navegador
// ("Failed to fetch") y no habia ningun status, de modo que cualquier
// comprobacion tipo `error.status === 500` se evaluaba sobre un TypeError.

/** Mensaje por defecto cuando el servidor no envia uno propio. */
const MENSAJE_HTTP = {
    400: 'La solicitud no se pudo interpretar.',
    404: 'El registro no existe o fue retirado.',
    405: 'La operacion no esta permitida sobre este recurso.',
    409: 'El registro entra en conflicto con uno existente.',
    415: 'El formato de la solicitud no es admitido.',
    422: 'Revise los campos indicados.',
};

/** Clasificacion estable por codigo, para que la UI decida sin mirar numeros. */
export function httpKind(status) {
    if (status >= 500) return 'server';
    return {
        400: 'bad-request', 404: 'not-found', 405: 'method',
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
        message: payload.message || MENSAJE_HTTP[status] || 'No fue posible completar la operacion.',
        // 422 los usa para pintar error por campo.
        errors: payload.errors ?? null,
        // 409 lo usa para ofrecer la reactivacion: data.reactivacion.identificacionNumero.
        data: payload.data ?? null,
        // Reintentar sirve ante un fallo del servidor; ante un 422 no cambia nada.
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
        message: 'No fue posible comunicarse con el servidor. Revise su conexion.',
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
        message: 'El servidor no devolvio una respuesta valida.',
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

/**
 * Ejecuta la peticion y devuelve el cuerpo JSON ya validado.
 * Lanza un Error enriquecido con la descripcion del fallo.
 * `fetchImpl` se inyecta en las pruebas; en el navegador usa el global.
 */
export async function request(url, options = {}, { fetchImpl = globalThis.fetch } = {}) {
    let response;
    try {
        response = await fetchImpl(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers ?? {}),
            },
        });
    } catch (error) {
        // La cancelacion no es un fallo: se propaga tal cual para que el
        // llamador la distinga por error.name y no pinte un estado de error.
        if (error?.name === 'AbortError') throw error;
        throw toError(describeNetworkFailure(error));
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw toError(describeInvalidResponse(response.status));
    }

    if (!response.ok || payload.success !== true) {
        throw toError(describeHttpFailure(response.status, payload));
    }
    return payload;
}
