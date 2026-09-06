// Orquestador "una captura por inicio de sesión".
//
// Dispara la captura GPS y su persistencia en tbproductorubicacion
// una sola vez por sesión (sessionStorage marca la captura). Requiere
// que auth-gate.js ya haya revelado la UI privada y el email de sesión
// sea válido.
//
// Nunca lanza: absorbe todos los fallos para no bloquear la UI.

import { esSoportado as esSoportadoDefault, capturar as capturarDefault } from './geo.js';

const UBICACION_SESSION_KEY = 'tindercows:ubicacion-sesion';

function marcar(storage, estado, startedAt) {
    storage.setItem(UBICACION_SESSION_KEY, JSON.stringify({
        estado,
        startedAt,
        en: new Date().toISOString(),
    }));
}

function leido(storage, startedAt) {
    try {
        const raw = storage.getItem(UBICACION_SESSION_KEY);
        if (!raw) return false;
        const dato = JSON.parse(raw);
        return dato?.startedAt === startedAt && typeof dato?.estado === 'string';
    } catch {
        return false;
    }
}

function toast(mensaje) {
    if (typeof document === 'undefined') return;
    const region = document.querySelector('[aria-live="polite"]')
        ?? document.querySelector('[aria-live="assertive"]');
    if (region) region.textContent = mensaje;
}

/**
 * Captura la ubicación del navegador y la persiste una vez por sesión.
 *
 * @param {object} [opts]
 * @param {string} [opts.apiUrl] Endpoint POST de ubicaciones.
 * @param {Storage} [opts.storage] sessionStorage por defecto.
 * @param {(email:string)=>Promise<{identificacionNumero:string}|null>} [opts.resolverIdentificacion]
 *   Función que resuelve la identificación del productor a partir del email.
 *   Se inyecta para que las pruebas no dependan del backend.
 * @param {() => number} [opts.ahora] Generador de timestamp (inyectable).
 * @returns {Promise<void>} Nunca lanza.
 */
export async function capturarEnInicioDeSesion({
    apiUrl = 'api/productores-ubicacion.php',
    storage = typeof sessionStorage !== 'undefined' ? sessionStorage : null,
    resolverIdentificacion = null,
    requestFn = null,
    esSoportadoFn = esSoportadoDefault,
    capturarFn = capturarDefault,
    ahora = () => Date.now(),
} = {}) {
    if (!storage) return;

    // 1. Marcador de sesión: clave separada para no mezclar con auth-gate.
    const startedAt = storage.getItem('tindercows:login')
        ? (() => { try { return JSON.parse(storage.getItem('tindercows:login'))?.startedAt; } catch { return null; } })()
        : null;
    if (!startedAt) return;

    if (leido(storage, startedAt)) return;

    // 2. Soporte del navegador.
    if (!esSoportadoFn()) {
        marcar(storage, 'omitida', startedAt);
        return;
    }

    // 3. Resolver identificación del productor.
    const email = (() => { try { return JSON.parse(storage.getItem('tindercows:login'))?.email; } catch { return null; } })();
    if (!email || typeof resolverIdentificacion !== 'function') {
        marcar(storage, 'omitida', startedAt);
        return;
    }

    let identidad;
    try {
        identidad = await resolverIdentificacion(email);
    } catch {
        // Fallo de red/compatibilidad: sin marcar (reintenta en próxima carga).
        return;
    }
    if (!identidad?.identificacionNumero) {
        marcar(storage, 'omitida', startedAt);
        return;
    }

    // 4. Capturar coordenadas.
    let coordenadas;
    try {
        coordenadas = await capturarFn();
    } catch (error) {
        if (error?.kind === 'unavailable' || error?.kind === 'timeout' || error?.kind === 'network') {
            // Sin marcar: reintenta en la próxima carga de la página.
            return;
        }
        // denied / unsupported: marcar para no reintentar esta sesión.
        marcar(storage, 'omitida', startedAt);
        toast(error?.message ?? 'No se pudo obtener la ubicación.');
        return;
    }

    // 5. POST al endpoint.
    let respuesta;
    try {
        respuesta = await requestFn(apiUrl, {
            method: 'POST',
            body: JSON.stringify({
                productorId: identidad.productorId,
                latitud: coordenadas.latitud,
                longitud: coordenadas.longitud,
                precisionMetros: coordenadas.precisionMetros,
                origen: coordenadas.origen,
            }),
        });
    } catch (error) {
        const retryable = error?.retryable === true;
        if (retryable) {
            // Sin marcar: reintenta en la próxima carga.
            return;
        }
        // 422 / 404 / otros definitivos: marcar.
        marcar(storage, 'omitida', startedAt);
        return;
    }

    // 6. Éxito: 201.
    if (respuesta?.success) {
        marcar(storage, 'capturada', startedAt);
    }
}
