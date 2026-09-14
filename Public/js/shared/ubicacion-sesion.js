// Operaciones explicitas sobre la ubicacion observada del productor.
// El nombre del archivo se conserva por compatibilidad historica, pero ya NO
// existe captura automatica al iniciar sesion. El navegador solo pide permiso
// despues de una accion consciente del usuario.

import { capturar, esSoportado } from './geo.js';

export const PRECISION_AVISO_METROS = 100;

export function validarCoordenadasManual({ latitud, longitud } = {}) {
    const errors = {};
    const lat = Number(latitud);
    const lon = Number(longitud);
    if (!Number.isFinite(lat) || lat < -90 || lat > 90) {
        errors.latitud = 'La latitud debe estar entre -90 y 90.';
    }
    if (!Number.isFinite(lon) || lon < -180 || lon > 180) {
        errors.longitud = 'La longitud debe estar entre -180 y 180.';
    }
    if (Object.keys(errors).length) return { ok: false, errors };
    return {
        ok: true,
        data: {
            latitud: lat.toFixed(7),
            longitud: lon.toFixed(7),
            precisionMetros: null,
            origen: 'MANUAL',
        },
    };
}

export function evaluarPrecision(precisionMetros, umbralAviso = PRECISION_AVISO_METROS) {
    if (precisionMetros === null || precisionMetros === undefined || !Number.isFinite(Number(precisionMetros))) {
        return { nivel: 'desconocida', mensaje: 'La precision no fue informada por el origen.' };
    }
    const precision = Number(precisionMetros);
    if (precision > umbralAviso) {
        return {
            nivel: 'baja',
            mensaje: `Precision aproximada: ${precision.toFixed(0)} m. Puede repetir la captura antes de registrar esta observacion.`,
        };
    }
    return { nivel: 'normal', mensaje: `Precision aproximada: ${precision.toFixed(0)} m.` };
}

export async function solicitarUbicacionNavegador({
    capturarFn = capturar,
    esSoportadoFn = esSoportado,
    timeoutMs = 10000,
    altaPrecision = false,
} = {}) {
    if (!esSoportadoFn()) {
        const error = new Error('Este navegador no ofrece geolocalizacion.');
        error.kind = 'unsupported';
        throw error;
    }
    return capturarFn({ timeoutMs, altaPrecision });
}

export async function registrarUbicacionObservada({ productorId, ubicacion, requestFn }) {
    if (!Number.isInteger(Number(productorId)) || Number(productorId) <= 0) {
        throw new TypeError('Se requiere un productorId valido.');
    }
    if (typeof requestFn !== 'function') throw new TypeError('Se requiere requestFn.');
    const payload = {
        productorId: Number(productorId),
        latitud: ubicacion?.latitud,
        longitud: ubicacion?.longitud,
        precisionMetros: ubicacion?.precisionMetros ?? null,
        origen: ubicacion?.origen,
    };
    return requestFn('api/productores-ubicacion.php', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}
