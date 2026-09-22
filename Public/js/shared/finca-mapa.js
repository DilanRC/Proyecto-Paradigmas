import {
    CENTRO_COSTA_RICA,
    cargarLimitesCostaRica,
    crearMapa,
    normalizarCoordenadas,
    puntoDentroDeLimitesCostaRica,
} from './mapa.js';
import {
    capturarUbicacionAutomatica,
    leerUbicacionUsuario,
    UBICACION_USUARIO_KEY,
} from './ubicacion-sesion.js';

function asegurarEstilos() {
    if (typeof document === 'undefined' || document.querySelector('link[data-tc-map-ui]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/mapa.css?v=mapas-4';
    link.dataset.tcMapUi = 'true';
    document.head.append(link);
}

function normalizarPunto(punto) {
    if (!punto || punto.latitud === null || punto.latitud === undefined
        || punto.longitud === null || punto.longitud === undefined
        || String(punto.latitud).trim() === '' || String(punto.longitud).trim() === '') return null;
    const normalizado = normalizarCoordenadas(punto);
    return {
        latitud: normalizado.latitud.toFixed(7),
        longitud: normalizado.longitud.toFixed(7),
    };
}

/** Geocodificación inversa puntual; no se ejecuta mientras el mapa está cerrado. */
export async function buscarDireccionPorCoordenadas(punto, {
    fetchImpl = globalThis.fetch,
    signal,
} = {}) {
    const limites = await cargarLimitesCostaRica();
    if (!puntoDentroDeLimitesCostaRica(punto, limites)) {
        const error = new Error('El punto debe estar dentro de Costa Rica, sus islas o territorio marítimo.');
        error.kind = 'outside-costa-rica';
        throw error;
    }
    const params = new URLSearchParams({
        format: 'jsonv2',
        addressdetails: '1',
        zoom: '18',
        lat: Number(punto.latitud).toFixed(7),
        lon: Number(punto.longitud).toFixed(7),
        'accept-language': 'es',
    });
    const response = await fetchImpl(`https://nominatim.openstreetmap.org/reverse?${params}`, {
        headers: { Accept: 'application/json' },
        signal,
    });
    if (!response.ok) throw new Error('No fue posible identificar la dirección del punto.');
    const payload = await response.json();
    if (payload?.address?.country_code !== 'cr') {
        const error = new Error('El punto seleccionado no pertenece a Costa Rica.');
        error.kind = 'outside-costa-rica';
        throw error;
    }
    const address = payload.address ?? {};
    const distrito = address.city_district
        ?? address.district
        ?? address.village
        ?? address.town
        ?? '';
    const pueblo = address.hamlet
        ?? address.neighbourhood
        ?? address.suburb
        ?? address.city
        ?? '';
    return {
        provincia: address.state ?? '',
        canton: address.county ?? address.municipality ?? '',
        distrito,
        pueblo: pueblo === distrito ? '' : pueblo,
    };
}

export function crearSelectorPuntoFinca({
    mount,
    puntoInicial = null,
    storage = typeof sessionStorage !== 'undefined' ? sessionStorage : null,
    crearMapaFn = crearMapa,
    onPuntoChange = () => {},
} = {}) {
    if (!mount) throw new TypeError('Se requiere un contenedor para el selector de finca.');
    asegurarEstilos();

    mount.classList.add('farm-map-picker');
    mount.innerHTML = `
        <div class="farm-map-picker__copy">
            <strong>Punto exacto de la finca <span class="label">opcional</span></strong>
            <p>Abra el mapa solo si desea marcar la entrada, corral o punto de referencia exacto de esta finca. La dirección escrita sigue siendo válida sin mapa.</p>
        </div>
        <div class="farm-map-picker__actions">
            <button type="button" class="button button--secondary" data-farm-map-open>Abrir mapa para ubicar finca</button>
            <button type="button" class="button button--secondary" data-farm-map-location>Usar mi ubicación</button>
            <button type="button" class="button button--secondary" data-farm-map-clear hidden>Quitar punto exacto</button>
        </div>
        <p class="farm-map-picker__status" data-farm-map-status role="status" aria-live="polite"></p>
        <p class="farm-map-picker__coords" data-farm-map-coords hidden></p>
        <div class="map-shell" data-farm-map-shell hidden>
            <p class="map-shell__notice">Haga clic sobre la finca y luego ajuste el marcador si lo necesita. OpenFreeMap carga la cartografía; los límites locales provienen de IGN/SNIT y Fundación MarViva. El punto solo se guarda al guardar la dirección.</p>
            <div class="map-shell__canvas" data-farm-map-canvas role="region" aria-label="Mapa para ubicar la finca"></div>
            <div class="map-shell__fallback" data-farm-map-fallback hidden>
                <strong>Mapa no disponible.</strong>
                <p>Puede continuar con provincia, cantón, distrito, pueblo y señas.</p>
                <button type="button" class="button button--secondary" data-farm-map-retry>Reintentar mapa</button>
            </div>
        </div>`;

    const openButton = mount.querySelector('[data-farm-map-open]');
    const locationButton = mount.querySelector('[data-farm-map-location]');
    const clearButton = mount.querySelector('[data-farm-map-clear]');
    const status = mount.querySelector('[data-farm-map-status]');
    const coords = mount.querySelector('[data-farm-map-coords]');
    const shell = mount.querySelector('[data-farm-map-shell]');
    const canvas = mount.querySelector('[data-farm-map-canvas]');
    const fallback = mount.querySelector('[data-farm-map-fallback]');
    const retry = mount.querySelector('[data-farm-map-retry]');

    let punto = normalizarPunto(puntoInicial);
    let puntoValidado = !punto;
    let mapa = null;
    let mapaEnCarga = null;
    let solicitudUbicacion = 0;
    let validacionPuntoToken = 0;
    let token = 0;
    let destruido = false;
    let limitesCostaRica = null;
    let limitesListos = null;
    const prepararLimites = () => {
        limitesListos = cargarLimitesCostaRica().then((geojson) => {
        limitesCostaRica = geojson;
        return geojson;
        });
        return limitesListos;
    };
    const estaEnZonaCostaRica = (punto) => puntoDentroDeLimitesCostaRica(punto, limitesCostaRica);
    const invalidarPuntoFueraDeZona = (notificar = true) => {
        if (!punto || !limitesCostaRica) return;
        if (estaEnZonaCostaRica(punto)) {
            puntoValidado = true;
            return;
        }
        puntoValidado = false;
        punto = null;
        render();
        mapa?.quitarMarcador?.();
        if (notificar) {
            notificarCambio();
            onPuntoChange(null);
        }
        status.textContent = 'El punto guardado está fuera del área permitida y fue eliminado.';
    };
    prepararLimites()
        .then(() => {
            invalidarPuntoFueraDeZona();
            render();
        })
        .catch(() => {
            puntoValidado = false;
            if (punto) status.textContent = 'No se pudo validar el punto guardado. Se conservará la dirección, pero el punto no se guardará.';
        });

    const bloquearRuedaSobreMapa = (event) => {
        event.preventDefault();
        event.stopPropagation();
    };
    canvas.addEventListener('wheel', bloquearRuedaSobreMapa, { passive: false });

    const notificarCambio = () => {
        mount.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = () => {
        clearButton.hidden = punto === null || !puntoValidado;
        coords.hidden = punto === null || !puntoValidado;
        coords.textContent = punto
            ? `Punto seleccionado: ${punto.latitud}, ${punto.longitud}`
            : '';
    };

    const establecer = (nuevoPunto, { moverMapa = true } = {}) => {
        validacionPuntoToken += 1;
        punto = normalizarPunto(nuevoPunto);
        puntoValidado = !punto || Boolean(limitesCostaRica && estaEnZonaCostaRica(punto));
        if (punto && puntoValidado && mapa && moverMapa) mapa.establecerMarcador(punto);
        render();
        notificarCambio();
        status.textContent = punto
            ? 'Punto exacto preparado. Se guardará junto con la dirección de la finca.'
            : 'No hay punto exacto seleccionado.';
        onPuntoChange(punto);
        return punto;
    };

    const cerrarMapa = () => {
        token += 1;
        mapa?.destruir?.();
        mapa = null;
        shell.hidden = true;
        fallback.hidden = true;
        canvas.replaceChildren();
        openButton.disabled = false;
        locationButton.disabled = false;
        solicitudUbicacion += 1;
    };

    const abrirMapaInterno = async () => {
        if (destruido || mapa) return;
        const operacion = ++token;
        shell.hidden = false;
        fallback.hidden = true;
        openButton.disabled = true;
        status.textContent = 'Cargando mapa opcional…';
        try {
            await prepararLimites();
            invalidarPuntoFueraDeZona();
            const ubicacionGuardada = leerUbicacionUsuario(storage);
            const ubicacionUsuario = estaEnZonaCostaRica(ubicacionGuardada) ? ubicacionGuardada : null;
            if (ubicacionGuardada && !ubicacionUsuario) storage?.removeItem?.(UBICACION_USUARIO_KEY);
            const centro = punto
                ? [Number(punto.longitud), Number(punto.latitud)]
                : ubicacionUsuario
                    ? [Number(ubicacionUsuario.longitud), Number(ubicacionUsuario.latitud)]
                    : CENTRO_COSTA_RICA;
            const creado = await crearMapaFn({
                contenedor: canvas,
                coordenadas: punto,
                centro,
                zoom: ubicacionUsuario && !punto ? 13 : 7,
                draggable: true,
                interactive: true,
                onMapClick: (coordenadas) => {
                    if (!estaEnZonaCostaRica(coordenadas)) {
                        status.textContent = 'El punto debe estar dentro de Costa Rica, sus islas o el mar territorial.';
                        return;
                    }
                    punto = normalizarPunto(coordenadas);
                    puntoValidado = true;
                    mapa?.establecerMarcador?.(punto, { centrar: false });
                    render();
                    notificarCambio();
                    onPuntoChange(punto);
                    status.textContent = 'Punto seleccionado. Puede arrastrar el marcador para afinarlo.';
                },
                onMarkerChange: (coordenadas) => {
                    if (!estaEnZonaCostaRica(coordenadas)) {
                        if (punto) mapa?.establecerMarcador?.(punto, { centrar: false });
                        else mapa?.quitarMarcador?.();
                        status.textContent = 'El marcador debe permanecer dentro de Costa Rica, sus islas o el mar territorial.';
                        return;
                    }
                    punto = normalizarPunto(coordenadas);
                    puntoValidado = true;
                    render();
                    notificarCambio();
                    onPuntoChange(punto);
                    status.textContent = 'Punto ajustado. Se guardará al guardar la dirección.';
                },
                onError: (error) => {
                    if (error?.kind === 'resource') {
                        status.textContent = 'Parte de la cartografía no cargó. Puede seguir usando la dirección manual.';
                    }
                },
            });
            if (destruido || operacion !== token) {
                creado?.destruir?.();
                return;
            }
            mapa = creado;
            locationButton.hidden = false;
            status.textContent = punto
                ? 'Mapa listo. Puede arrastrar el marcador o elegir otro punto.'
                : 'Mapa listo. Haga clic en la ubicación exacta de la finca.';
        } catch {
            if (destruido || operacion !== token) return;
            fallback.hidden = false;
            canvas.replaceChildren();
            status.textContent = 'El mapa no está disponible. La dirección manual sigue funcionando.';
        } finally {
            if (!destruido && operacion === token) openButton.disabled = false;
        }
    };

    const abrirMapa = () => {
        if (destruido || mapa) return Promise.resolve();
        if (mapaEnCarga) {
            return mapaEnCarga.then(() => {
                if (!mapa && !destruido) return abrirMapa();
                return undefined;
            });
        }
        const carga = abrirMapaInterno();
        mapaEnCarga = carga;
        return carga.finally(() => {
            if (mapaEnCarga === carga) mapaEnCarga = null;
        });
    };

    openButton.addEventListener('click', abrirMapa);
    locationButton.addEventListener('click', async () => {
        if (destruido) return;
        locationButton.disabled = true;
        status.textContent = 'Buscando tu ubicación…';
        const solicitud = ++solicitudUbicacion;
        try {
            await prepararLimites();
            const resultado = await capturarUbicacionAutomatica({ storage });
            if (destruido || solicitud !== solicitudUbicacion) return;
            const ubicacion = resultado.ubicacion;
            if (!estaEnZonaCostaRica(ubicacion)) {
                storage?.removeItem?.(UBICACION_USUARIO_KEY);
                const error = new Error('La ubicación automática está fuera del área permitida de Costa Rica.');
                error.kind = 'outside-costa-rica';
                throw error;
            }
            establecer(ubicacion, { moverMapa: false });
            if (!mapa) await abrirMapa();
            if (destruido || solicitud !== solicitudUbicacion) return;
            if (mapa) {
                mapa.establecerMarcador(ubicacion);
                mapa.centrar(ubicacion, 15);
                status.textContent = resultado.reutilizada
                    ? 'Usamos tu ubicación reciente. Puedes ajustar el punto en el mapa.'
                    : 'Ubicación encontrada. Puedes ajustar el punto en el mapa.';
            } else {
                status.textContent = 'Ubicación encontrada. El mapa no está disponible, pero el punto se guardará con la finca.';
            }
        } catch (error) {
            if (destruido || solicitud !== solicitudUbicacion) return;
            const mensajes = {
                denied: 'Permiso de ubicación denegado. Puedes marcar el punto o escribir la dirección.',
                unsupported: 'Este navegador no ofrece ubicación automática. Puedes marcar el punto o escribir la dirección.',
                timeout: 'La ubicación tardó demasiado. Puedes reintentarlo o marcar el punto manualmente.',
                unavailable: 'No pudimos encontrar tu ubicación. Puedes marcar el punto o escribir la dirección.',
                'outside-costa-rica': 'La ubicación automática está fuera del área permitida de Costa Rica.',
            };
            status.textContent = mensajes[error?.kind] ?? 'No pudimos encontrar tu ubicación. Puedes continuar manualmente.';
        } finally {
            if (!destruido && solicitud === solicitudUbicacion) locationButton.disabled = false;
        }
    });
    clearButton.addEventListener('click', () => {
        solicitudUbicacion += 1;
        validacionPuntoToken += 1;
        locationButton.disabled = false;
        punto = null;
        puntoValidado = true;
        mapa?.quitarMarcador?.();
        render();
        notificarCambio();
        onPuntoChange(null);
        status.textContent = 'Punto exacto eliminado. La dirección escrita se conserva.';
    });
    retry.addEventListener('click', () => { cerrarMapa(); abrirMapa(); });
    render();

    return Object.freeze({
        obtenerPunto: () => punto && puntoValidado ? { ...punto } : null,
        aplicarPunto(nuevoPunto, { notificar = true } = {}) {
            const validacion = ++validacionPuntoToken;
            punto = normalizarPunto(nuevoPunto);
            puntoValidado = !punto ? true : false;
            limitesListos.then(() => {
                if (validacion !== validacionPuntoToken) return;
                const puntoAntesDeValidar = punto;
                invalidarPuntoFueraDeZona(notificar);
                render();
                if (punto === puntoAntesDeValidar && puntoValidado && !notificar) {
                    mount.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (punto === puntoAntesDeValidar && puntoValidado && notificar) onPuntoChange(punto);
            }).catch(() => {
                puntoValidado = false;
            });
            if (mapa) {
                if (punto && puntoValidado) mapa.establecerMarcador(punto);
                else mapa.quitarMarcador?.();
            }
            render();
        },
        limpiar() {
            solicitudUbicacion += 1;
            locationButton.disabled = false;
            establecer(null);
        },
        cerrarMapa,
        destruir() {
            destruido = true;
            solicitudUbicacion += 1;
            cerrarMapa();
            canvas.removeEventListener('wheel', bloquearRuedaSobreMapa);
            mount.replaceChildren();
        },
    });
}
