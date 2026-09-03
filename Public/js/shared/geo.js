// Captura de ubicación GPS del navegador (agnóstico de mapa).
//
// Módulo puro: no toca DOM ni network. Solo extrae coordenadas del
// Geolocation API y las normaliza a los campos que acepta el endpoint
// productores-ubicacion.php. Nunca envía `fecha` (la asigna PHP).

const GEO_MSG = {
    denied: 'Permiso de ubicación denegado. Actívelo en la configuración del navegador.',
    unavailable: 'No se pudo obtener la ubicación; se omitirá esta sesión.',
    timeout: 'La ubicación tardó demasiado; se omitirá esta sesión.',
    unsupported: 'Su navegador no soporta geolocalización.',
};

/**
 * Devuelve true si el navegador expone navigator.geolocation.
 * En Node (tests) siempre devuelve false.
 */
export function esSoportado() {
    return typeof navigator !== 'undefined'
        && typeof navigator.geolocation?.getCurrentPosition === 'function';
}

/**
 * Solicita la posición actual y devuelve el payload normalizado.
 * @param {object} [opciones]
 * @param {number} [opciones.timeoutMs=10000] Tiempo máximo de espera.
 * @param {boolean} [opciones.altaPrecision=true] Si pide GPS de alta precisión.
 * @returns {Promise<{latitud:string,longitud:string,precisionMetros:number,origen:string}>}
 */
export function capturar({ timeoutMs = 10000, altaPrecision = true } = {}) {
    if (!esSoportado()) {
        return Promise.reject(Object.assign(new Error(GEO_MSG.unsupported), { kind: 'unsupported' }));
    }

    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude, longitude, accuracy } = pos.coords;
                const precision = typeof accuracy === 'number' ? Number(redondear(accuracy, 2)) : 0;
                resolve({
                    latitud: redondear(latitude, 7),
                    longitud: redondear(longitude, 7),
                    precisionMetros: precision,
                    origen: 'NAVEGADOR',
                });
            },
            (err) => {
                const kind = {
                    1: 'denied',
                    2: 'unavailable',
                    3: 'timeout',
                }[err.code] ?? 'unavailable';

                reject(Object.assign(new Error(GEO_MSG[kind] ?? GEO_MSG.unavailable), { kind }));
            },
            {
                enableHighAccuracy: altaPrecision,
                timeout: timeoutMs,
                maximumAge: 0,
            },
        );
    });
}

/** Redondea un número a la cantidad de decimales indicada. */
function redondear(valor, decimales) {
    const factor = 10 ** decimales;
    return String(Math.round(valor * factor) / factor);
}
