import {
    CENTRO_COSTA_RICA,
    cargarLimitesCostaRica,
    crearMapa,
    normalizarCoordenadas,
    puntoDentroDeLimitesCostaRica,
} from './mapa.js?v=map-core-5';
import {
    capturarUbicacionAutomatica,
    leerUbicacionUsuario,
    UBICACION_USUARIO_KEY,
} from './ubicacion-sesion.js';

let selectorSequence = 0;

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

export async function buscarLugaresPorNombre(nombre, {
    fetchImpl = globalThis.fetch,
    signal,
} = {}) {
    const consulta = String(nombre ?? '').trim();
    if (consulta.length < 3) return [];
    const params = new URLSearchParams({
        q: `${consulta}, Costa Rica`,
        format: 'jsonv2',
        addressdetails: '1',
        countrycodes: 'cr',
        limit: '5',
        viewbox: '-90,12.8,-81.5,3.3',
        bounded: '1',
        'accept-language': 'es',
    });
    const response = await fetchImpl(`https://nominatim.openstreetmap.org/search?${params}`, {
        headers: { Accept: 'application/json' },
        signal,
    });
    if (!response.ok) throw new Error('No fue posible buscar ese lugar.');
    const payload = await response.json();
    return (Array.isArray(payload) ? payload : []).filter((item) => item?.address?.country_code === 'cr')
        .map((item) => ({
            nombre: String(item.display_name ?? '').trim(),
            latitud: Number(item.lat),
            longitud: Number(item.lon),
        }))
        .filter((item) => Number.isFinite(item.latitud) && Number.isFinite(item.longitud));
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
        <div class="farm-map-picker__header">
            <strong>Punto exacto de la finca <span class="label">OPCIONAL</span></strong>
            <div class="farm-map-picker__header-actions">
                <button type="button" class="button button--secondary" data-farm-map-open>Abrir mapa</button>
                <button type="button" class="button button--secondary" data-farm-map-close hidden>Cerrar mapa</button>
            </div>
        </div>
        <p class="screen-reader-only" data-farm-map-status role="status" aria-live="polite"></p>
        <div class="map-shell" data-farm-map-shell hidden>
            <div class="map-shell__actions" aria-label="Controles del mapa">
                <button type="button" class="map-shell__action" data-farm-map-location hidden aria-label="Usar mi ubicación" title="Usar mi ubicación"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i></button>
                <button type="button" class="map-shell__action" data-farm-map-clear hidden aria-label="Quitar punto exacto" title="Quitar punto exacto"><i class="fa-solid fa-location-dot-slash" aria-hidden="true"></i></button>
            </div>
            <div class="map-shell__search" data-farm-map-search-form role="search">
                <label for="" data-farm-map-search-label>Buscar lugar</label>
                <div class="map-shell__search-row">
                    <input data-farm-map-search type="search" autocomplete="off" placeholder="Nombre de finca, pueblo o lugar">
                    <button type="button" data-farm-map-search-submit class="button button--secondary">Buscar</button>
                </div>
                <div class="map-shell__results" data-farm-map-results role="listbox" aria-label="Resultados de búsqueda" hidden></div>
            </div>
            <div class="map-shell__layers" aria-label="Capas del mapa">
                <label><input type="checkbox" data-farm-map-details> Mostrar detalles oficiales</label>
                <label><input type="checkbox" data-farm-map-satellite> Vista satelital</label>
            </div>
            <div class="map-shell__canvas" data-farm-map-canvas role="region" aria-label="Mapa para ubicar la finca"></div>
            <div class="map-shell__fallback" data-farm-map-fallback hidden>
                <button type="button" class="button button--secondary" data-farm-map-retry>Reintentar mapa</button>
            </div>
        </div>`;

    const openButton = mount.querySelector('[data-farm-map-open]');
    const closeButton = mount.querySelector('[data-farm-map-close]');
    const locationButton = mount.querySelector('[data-farm-map-location]');
    const clearButton = mount.querySelector('[data-farm-map-clear]');
    const status = mount.querySelector('[data-farm-map-status]');
    const shell = mount.querySelector('[data-farm-map-shell]');
    const canvas = mount.querySelector('[data-farm-map-canvas]');
    const fallback = mount.querySelector('[data-farm-map-fallback]');
    const retry = mount.querySelector('[data-farm-map-retry]');
    const searchForm = mount.querySelector('[data-farm-map-search-form]');
    const searchInput = mount.querySelector('[data-farm-map-search]');
    const searchSubmit = mount.querySelector('[data-farm-map-search-submit]');
    const searchLabel = mount.querySelector('[data-farm-map-search-label]');
    const searchResults = mount.querySelector('[data-farm-map-results]');
    const detailsToggle = mount.querySelector('[data-farm-map-details]');
    const satelliteToggle = mount.querySelector('[data-farm-map-satellite]');
    const searchId = `farm-map-search-${++selectorSequence}`;
    searchInput.id = searchId;
    searchLabel.htmlFor = searchId;
    const searchController = { current: null };

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
    let listenersDeCapasInstalados = false;
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

    const notificarCambio = () => {
        mount.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = () => {
        clearButton.hidden = punto === null || !puntoValidado;
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

    const mostrarResultados = (resultados) => {
        searchResults.replaceChildren(...resultados.map((resultado) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'map-shell__result';
            button.role = 'option';
            button.textContent = resultado.nombre;
            button.addEventListener('click', () => {
                if (!estaEnZonaCostaRica(resultado)) {
                    status.textContent = 'Ese lugar está fuera del área permitida.';
                    return;
                }
                establecer(resultado);
                mapa?.centrar?.(resultado, 16);
                searchResults.hidden = true;
                status.textContent = 'Lugar encontrado. Puedes ajustar el punto en el mapa.';
            });
            return button;
        }));
        searchResults.hidden = resultados.length === 0;
    };

    const cerrarMapa = () => {
        token += 1;
        searchController.current?.abort();
        searchController.current = null;
        mapa?.destruir?.();
        mapa = null;
        shell.hidden = true;
        fallback.hidden = true;
        canvas.replaceChildren();
        openButton.disabled = false;
        openButton.hidden = false;
        closeButton.hidden = true;
        locationButton.disabled = false;
        solicitudUbicacion += 1;
    };

    const abrirMapaInterno = async () => {
        if (destruido || mapa) return;
        const operacion = ++token;
        shell.hidden = false;
        fallback.hidden = true;
        openButton.disabled = true;
        openButton.hidden = true;
        closeButton.hidden = false;
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
                fullscreenContainer: mount,
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
            if (!listenersDeCapasInstalados) {
                detailsToggle.addEventListener('change', () => {
                    const activo = mapa?.activarDetallesOficiales?.(detailsToggle.checked);
                    if (detailsToggle.checked && !activo) {
                        detailsToggle.checked = false;
                        status.textContent = 'No pudimos cargar los detalles oficiales. Puedes continuar con el mapa base.';
                    }
                });
                satelliteToggle.addEventListener('change', () => {
                    const activo = mapa?.activarCapaRaster?.('satellite', satelliteToggle.checked);
                    if (satelliteToggle.checked && !activo) {
                        satelliteToggle.checked = false;
                        status.textContent = 'No pudimos cargar la vista satelital. Puedes continuar con el mapa base.';
                    }
                });
                listenersDeCapasInstalados = true;
            }
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
    closeButton.addEventListener('click', cerrarMapa);
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
    const buscar = async () => {
        const consulta = searchInput.value.trim();
        if (consulta.length < 3) {
            status.textContent = 'Escribe al menos tres caracteres para buscar.';
            searchResults.hidden = true;
            return;
        }
        searchController.current?.abort();
        const controller = new AbortController();
        searchController.current = controller;
        searchResults.hidden = true;
        status.textContent = 'Buscando lugar…';
        try {
            const resultados = await buscarLugaresPorNombre(consulta, { signal: controller.signal });
            if (controller.signal.aborted || destruido) return;
            mostrarResultados(resultados);
            status.textContent = resultados.length ? 'Elige un resultado para marcarlo en el mapa.' : 'No encontramos ese lugar en Costa Rica.';
        } catch (error) {
            if (error?.name === 'AbortError' || controller.signal.aborted || destruido) return;
            status.textContent = 'No pudimos buscar ese lugar. Puedes marcarlo directamente en el mapa.';
        } finally {
            if (searchController.current === controller) searchController.current = null;
        }
    };
    searchSubmit.addEventListener('click', buscar);
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscar();
        }
    });
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
            searchController.current?.abort();
            locationButton.disabled = false;
            establecer(null);
        },
        cerrarMapa,
        destruir() {
            destruido = true;
            solicitudUbicacion += 1;
            cerrarMapa();
            mount.replaceChildren();
        },
    });
}
