import { CENTRO_COSTA_RICA, crearMapa, normalizarCoordenadas } from './mapa.js';
import { leerUbicacionUsuario } from './ubicacion-sesion.js';

function asegurarEstilos() {
    if (typeof document === 'undefined' || document.querySelector('link[data-tc-map-ui]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/mapa.css?v=mapas-3';
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

export function crearSelectorPuntoFinca({
    mount,
    puntoInicial = null,
    storage = typeof sessionStorage !== 'undefined' ? sessionStorage : null,
    crearMapaFn = crearMapa,
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
            <button type="button" class="button button--secondary" data-farm-map-clear hidden>Quitar punto exacto</button>
        </div>
        <p class="farm-map-picker__status" data-farm-map-status role="status" aria-live="polite"></p>
        <p class="farm-map-picker__coords" data-farm-map-coords hidden></p>
        <div class="map-shell" data-farm-map-shell hidden>
            <p class="map-shell__notice">Haga clic sobre la finca y luego ajuste el marcador si lo necesita. OpenFreeMap carga la cartografía; el punto solo se guarda al guardar la dirección.</p>
            <div class="map-shell__canvas" data-farm-map-canvas role="region" aria-label="Mapa para ubicar la finca"></div>
            <div class="map-shell__fallback" data-farm-map-fallback hidden>
                <strong>Mapa no disponible.</strong>
                <p>Puede continuar con provincia, cantón, distrito, pueblo y señas.</p>
                <button type="button" class="button button--secondary" data-farm-map-retry>Reintentar mapa</button>
            </div>
        </div>`;

    const openButton = mount.querySelector('[data-farm-map-open]');
    const clearButton = mount.querySelector('[data-farm-map-clear]');
    const status = mount.querySelector('[data-farm-map-status]');
    const coords = mount.querySelector('[data-farm-map-coords]');
    const shell = mount.querySelector('[data-farm-map-shell]');
    const canvas = mount.querySelector('[data-farm-map-canvas]');
    const fallback = mount.querySelector('[data-farm-map-fallback]');
    const retry = mount.querySelector('[data-farm-map-retry]');

    let punto = normalizarPunto(puntoInicial);
    let mapa = null;
    let token = 0;
    let destruido = false;

    const notificarCambio = () => {
        mount.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = () => {
        clearButton.hidden = punto === null;
        coords.hidden = punto === null;
        coords.textContent = punto
            ? `Punto seleccionado: ${punto.latitud}, ${punto.longitud}`
            : '';
    };

    const establecer = (nuevoPunto, { moverMapa = true } = {}) => {
        punto = normalizarPunto(nuevoPunto);
        if (punto && mapa && moverMapa) mapa.establecerMarcador(punto);
        render();
        notificarCambio();
        status.textContent = punto
            ? 'Punto exacto preparado. Se guardará junto con la dirección de la finca.'
            : 'No hay punto exacto seleccionado.';
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
    };

    const abrirMapa = async () => {
        if (destruido || mapa) return;
        const operacion = ++token;
        shell.hidden = false;
        fallback.hidden = true;
        openButton.disabled = true;
        status.textContent = 'Cargando mapa opcional…';
        const ubicacionUsuario = leerUbicacionUsuario(storage);
        const centro = punto
            ? [Number(punto.longitud), Number(punto.latitud)]
            : ubicacionUsuario
                ? [Number(ubicacionUsuario.longitud), Number(ubicacionUsuario.latitud)]
                : CENTRO_COSTA_RICA;
        try {
            const creado = await crearMapaFn({
                contenedor: canvas,
                coordenadas: punto,
                centro,
                zoom: ubicacionUsuario && !punto ? 13 : 7,
                draggable: true,
                interactive: true,
                onMapClick: (coordenadas) => {
                    punto = normalizarPunto(coordenadas);
                    mapa?.establecerMarcador?.(punto, { centrar: false });
                    render();
                    notificarCambio();
                    status.textContent = 'Punto seleccionado. Puede arrastrar el marcador para afinarlo.';
                },
                onMarkerChange: (coordenadas) => {
                    punto = normalizarPunto(coordenadas);
                    render();
                    notificarCambio();
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

    openButton.addEventListener('click', abrirMapa);
    clearButton.addEventListener('click', () => {
        punto = null;
        mapa?.quitarMarcador?.();
        render();
        notificarCambio();
        status.textContent = 'Punto exacto eliminado. La dirección escrita se conserva.';
    });
    retry.addEventListener('click', () => { cerrarMapa(); abrirMapa(); });
    render();

    return Object.freeze({
        obtenerPunto: () => punto ? { ...punto } : null,
        aplicarPunto(nuevoPunto) {
            punto = normalizarPunto(nuevoPunto);
            if (mapa) {
                if (punto) mapa.establecerMarcador(punto);
                else mapa.quitarMarcador?.();
            }
            render();
        },
        limpiar() { establecer(null); },
        cerrarMapa,
        destruir() {
            destruido = true;
            cerrarMapa();
            mount.replaceChildren();
        },
    });
}
