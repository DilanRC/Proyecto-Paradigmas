// Captura puntual de geolocalizacion. Este modulo nunca se ejecuta solo.

export const GEO_MSG = Object.freeze({
    denied: 'El permiso de ubicacion fue denegado. Puede ingresar las coordenadas manualmente.',
    unavailable: 'La ubicacion no esta disponible en este momento. Puede reintentar o continuar manualmente.',
    timeout: 'La ubicacion tardo demasiado en responder. Puede reintentar o continuar manualmente.',
    unsupported: 'Este navegador no ofrece geolocalizacion. Use la alternativa manual.',
});

export function esSoportado() {
    return typeof navigator !== 'undefined' && Boolean(navigator.geolocation?.getCurrentPosition);
}

function geoError(kind, original = null) {
    const error = new Error(GEO_MSG[kind] ?? GEO_MSG.unavailable);
    error.kind = kind;
    if (original) error.cause = original;
    return error;
}

export function capturar({ timeoutMs = 10000, altaPrecision = false } = {}) {
    if (!esSoportado()) return Promise.reject(geoError('unsupported'));

    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const latitud = Number(position?.coords?.latitude);
                const longitud = Number(position?.coords?.longitude);
                const accuracy = Number(position?.coords?.accuracy);
                if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) {
                    reject(geoError('unavailable'));
                    return;
                }
                resolve({
                    latitud: latitud.toFixed(7),
                    longitud: longitud.toFixed(7),
                    precisionMetros: Number.isFinite(accuracy) ? Math.round(accuracy * 100) / 100 : null,
                    origen: 'NAVEGADOR',
                });
            },
            (error) => {
                const kind = error?.code === 1 ? 'denied' : error?.code === 3 ? 'timeout' : 'unavailable';
                reject(geoError(kind, error));
            },
            {
                enableHighAccuracy: Boolean(altaPrecision),
                timeout: Math.max(1, Number(timeoutMs) || 10000),
                maximumAge: 0,
            },
        );
    });
}
