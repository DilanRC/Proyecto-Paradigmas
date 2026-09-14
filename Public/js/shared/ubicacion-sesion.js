import { capturar, esSoportado } from './geo.js';

export const UBICACION_USUARIO_KEY = 'tindercows:ubicacion-usuario';
export const UBICACION_USUARIO_EVENT = 'tindercows:ubicacion-usuario';
export const UBICACION_USUARIO_ERROR_EVENT = 'tindercows:ubicacion-error';
export const UBICACION_USUARIO_MAX_EDAD_MS = 15 * 60 * 1000;

function storagePredeterminado() {
    return typeof sessionStorage !== 'undefined' ? sessionStorage : null;
}

function ahoraPredeterminado() {
    return Date.now();
}

function numeroEnRango(valor, minimo, maximo) {
    const numero = Number(valor);
    return Number.isFinite(numero) && numero >= minimo && numero <= maximo ? numero : null;
}

function normalizarGuardada(valor) {
    if (!valor || typeof valor !== 'object') return null;
    const latitud = numeroEnRango(valor.latitud, -90, 90);
    const longitud = numeroEnRango(valor.longitud, -180, 180);
    const capturadaEnMs = Date.parse(String(valor.capturadaEn ?? ''));
    if (latitud === null || longitud === null || Number.isNaN(capturadaEnMs)) return null;
    const precision = valor.precisionMetros === null || valor.precisionMetros === undefined
        ? null : Number(valor.precisionMetros);
    return {
        latitud: latitud.toFixed(7),
        longitud: longitud.toFixed(7),
        precisionMetros: Number.isFinite(precision) && precision >= 0 ? precision : null,
        origen: 'NAVEGADOR',
        capturadaEn: new Date(capturadaEnMs).toISOString(),
    };
}

export function leerUbicacionUsuario(
    storage = storagePredeterminado(),
    { ahoraFn = ahoraPredeterminado, maxEdadMs = UBICACION_USUARIO_MAX_EDAD_MS } = {},
) {
    try {
        const normalizada = normalizarGuardada(JSON.parse(storage?.getItem(UBICACION_USUARIO_KEY) ?? 'null'));
        if (!normalizada) return null;
        if (maxEdadMs >= 0 && ahoraFn() - Date.parse(normalizada.capturadaEn) > maxEdadMs) return null;
        return normalizada;
    } catch {
        return null;
    }
}

export async function capturarUbicacionAutomatica({
    storage = storagePredeterminado(),
    capturarFn = capturar,
    esSoportadoFn = esSoportado,
    ahoraFn = ahoraPredeterminado,
    maxEdadMs = UBICACION_USUARIO_MAX_EDAD_MS,
    forzar = false,
} = {}) {
    if (!forzar) {
        const existente = leerUbicacionUsuario(storage, { ahoraFn, maxEdadMs });
        if (existente) return { ubicacion: existente, reutilizada: true };
    }

    if (!esSoportadoFn()) {
        const error = new Error('Este navegador no ofrece geolocalización.');
        error.kind = 'unsupported';
        throw error;
    }

    const capturada = await capturarFn({ altaPrecision: false, timeoutMs: 8000 });
    const ubicacion = normalizarGuardada({
        ...capturada,
        capturadaEn: new Date(ahoraFn()).toISOString(),
    });
    if (!ubicacion) {
        const error = new Error('La ubicación devuelta por el navegador no es válida.');
        error.kind = 'invalid';
        throw error;
    }
    storage?.setItem(UBICACION_USUARIO_KEY, JSON.stringify(ubicacion));
    return { ubicacion, reutilizada: false };
}

function emitir(windowRef, nombre, detail) {
    if (!windowRef?.dispatchEvent) return;
    const EventCtor = windowRef.CustomEvent ?? (typeof CustomEvent !== 'undefined' ? CustomEvent : null);
    if (!EventCtor) return;
    windowRef.dispatchEvent(new EventCtor(nombre, { detail }));
}

export async function inicializarUbicacionAutomatica({
    windowRef = typeof window !== 'undefined' ? window : null,
    ...opciones
} = {}) {
    try {
        const resultado = await capturarUbicacionAutomatica(opciones);
        if (!resultado.reutilizada) emitir(windowRef, UBICACION_USUARIO_EVENT, resultado.ubicacion);
        return resultado;
    } catch (error) {
        emitir(windowRef, UBICACION_USUARIO_ERROR_EVENT, {
            kind: error?.kind ?? 'unknown',
            message: error?.message ?? 'No fue posible obtener la ubicación.',
        });
        return { ubicacion: null, reutilizada: false, error };
    }
}
