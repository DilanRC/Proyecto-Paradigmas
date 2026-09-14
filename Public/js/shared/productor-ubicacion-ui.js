import { request } from './api.js';
import { crearMapa } from './mapa.js';
import {
    evaluarPrecision,
    registrarUbicacionObservada,
    solicitarUbicacionNavegador,
    validarCoordenadasManual,
} from './ubicacion-sesion.js';

const API_PRODUCTORES = 'api/productores.php';
const API_UBICACIONES = 'api/productores-ubicacion.php';

function asegurarEstilos() {
    if (document.querySelector('link[data-tc-map-ui]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/mapa.css?v=mapas-1';
    link.dataset.tcMapUi = 'true';
    document.head.append(link);
}

function crearSeccion() {
    const section = document.createElement('section');
    section.className = 'location-observation';
    section.id = 'ubicacion-observada-productor';
    section.setAttribute('aria-labelledby', 'ubicacion-observada-title');
    section.innerHTML = `
        <div class="location-observation__header">
            <div>
                <span class="label">Dato espacial independiente</span>
                <h3 id="ubicacion-observada-title">Ubicacion observada del productor</h3>
                <p>Es una lectura puntual del productor. No modifica su direccion declarada ni representa la ubicacion de una finca.</p>
            </div>
        </div>
        <p class="location-observation__status" data-location-status role="status" aria-live="polite"></p>
        <div class="location-observation__actions">
            <button class="button button--primary" type="button" data-location-gps>Usar mi ubicacion</button>
            <button class="button button--secondary" type="button" data-location-request-cancel hidden>Cancelar solicitud</button>
            <button class="button button--secondary" type="button" data-location-manual-toggle>Ingresar ubicacion manualmente</button>
            <button class="button button--secondary" type="button" data-location-map-show hidden>Mostrar en mapa</button>
        </div>
        <p class="fieldset-help">El navegador solo pedira permiso al pulsar “Usar mi ubicacion”. La lectura no se guarda hasta que confirme. El mapa es opcional y carga recursos de OpenFreeMap; puede continuar sin abrirlo.</p>
        <form class="location-manual" data-location-manual hidden novalidate>
            <div class="location-manual__grid">
                <label><span>Latitud</span><input name="latitud" type="number" min="-90" max="90" step="0.0000001" required inputmode="decimal"><small data-manual-error="latitud"></small></label>
                <label><span>Longitud</span><input name="longitud" type="number" min="-180" max="180" step="0.0000001" required inputmode="decimal"><small data-manual-error="longitud"></small></label>
            </div>
            <div class="location-observation__actions">
                <button class="button button--primary" type="submit">Preparar coordenadas</button>
                <button class="button button--secondary" type="button" data-location-manual-cancel>Cancelar</button>
            </div>
        </form>
        <div class="location-candidate" data-location-candidate hidden>
            <strong>Observacion pendiente de confirmar</strong>
            <dl>
                <div><dt>Latitud</dt><dd data-candidate-lat>—</dd></div>
                <div><dt>Longitud</dt><dd data-candidate-lon>—</dd></div>
                <div><dt>Precision</dt><dd data-candidate-precision>—</dd></div>
                <div><dt>Origen</dt><dd data-candidate-origin>—</dd></div>
            </dl>
            <p data-precision-message></p>
            <div class="location-observation__actions">
                <button class="button button--primary" type="button" data-location-save>Registrar observacion</button>
                <button class="button button--secondary" type="button" data-location-cancel>Cancelar</button>
            </div>
        </div>
        <div class="map-shell" data-map-shell hidden>
            <p class="map-shell__notice">El mapa es una ayuda visual. OpenFreeMap recibira las solicitudes de estilo y teselas necesarias para esta vista; no se usa para validar la direccion administrativa.</p>
            <div class="map-shell__canvas" data-map-canvas role="region" aria-label="Mapa de la ubicacion observada"></div>
            <div class="map-shell__fallback" data-map-fallback hidden>
                <strong>Mapa no disponible.</strong>
                <p>Puede continuar usando las coordenadas o la direccion manualmente.</p>
                <button class="button button--secondary" type="button" data-map-retry>Reintentar mapa</button>
            </div>
        </div>`;
    return section;
}

function textoPrecision(value) {
    return value === null || value === undefined ? 'No informada' : `${Number(value).toFixed(0)} m`;
}

export function crearControlUbicacionProductor({
    root,
    requestFn = request,
    solicitarFn = solicitarUbicacionNavegador,
    crearMapaFn = crearMapa,
} = {}) {
    if (!root) throw new TypeError('Se requiere root para el control de ubicacion.');

    const $ = (selector) => root.querySelector(selector);
    const status = $('[data-location-status]');
    const gpsButton = $('[data-location-gps]');
    const manualToggle = $('[data-location-manual-toggle]');
    const requestCancel = $('[data-location-request-cancel]');
    const manualForm = $('[data-location-manual]');
    const candidateBox = $('[data-location-candidate]');
    const mapShow = $('[data-location-map-show]');
    const mapShell = $('[data-map-shell]');
    const mapCanvas = $('[data-map-canvas]');
    const mapFallback = $('[data-map-fallback]');
    const mapRetry = $('[data-map-retry]');
    const saveButton = $('[data-location-save]');

    let productor = null;
    let latest = null;
    let candidate = null;
    let mapController = null;
    let operationId = 0;
    let saving = false;
    let destroyed = false;

    const setStatus = (message = '', kind = '') => {
        status.textContent = message;
        status.dataset.kind = kind;
    };

    const destroyMap = () => {
        mapController?.destruir?.();
        mapController = null;
        mapShell.hidden = true;
        mapFallback.hidden = true;
        mapCanvas.replaceChildren();
    };

    const currentCoordinates = () => candidate ?? latest;

    const renderCandidate = () => {
        candidateBox.hidden = !candidate;
        if (!candidate) return;
        $('[data-candidate-lat]').textContent = candidate.latitud;
        $('[data-candidate-lon]').textContent = candidate.longitud;
        $('[data-candidate-precision]').textContent = textoPrecision(candidate.precisionMetros);
        $('[data-candidate-origin]').textContent = candidate.origen;
        const precision = evaluarPrecision(candidate.precisionMetros);
        $('[data-precision-message]').textContent = precision.mensaje;
        $('[data-precision-message]').dataset.level = precision.nivel;
        mapShow.hidden = false;
    };

    const setCandidate = (value) => {
        candidate = value;
        renderCandidate();
        destroyMap();
        mapShow.hidden = false;
    };

    const cancelPending = () => {
        operationId += 1;
        candidate = null;
        candidateBox.hidden = true;
        manualForm.hidden = true;
        requestCancel.hidden = true;
        gpsButton.disabled = false;
        setStatus('Operacion cancelada. No se registro ninguna ubicacion.');
        destroyMap();
        mapShow.hidden = latest === null;
    };

    const loadLatest = async (token = operationId) => {
        if (!productor?.productorId) return;
        try {
            const response = await requestFn(`${API_UBICACIONES}?${new URLSearchParams({
                productorId: String(productor.productorId), pagina: '1', tamano: '1',
            })}`);
            if (destroyed || token !== operationId) return;
            const payload = response.data ?? {};
            const items = Array.isArray(payload.ubicaciones) ? payload.ubicaciones
                : Array.isArray(payload) ? payload : [];
            latest = items[0] ? {
                latitud: items[0].tbproductorubicacionlatitud ?? items[0].latitud,
                longitud: items[0].tbproductorubicacionlongitud ?? items[0].longitud,
                precisionMetros: items[0].tbproductorubicacionprecision ?? items[0].precisionMetros ?? null,
                origen: items[0].tbproductorubicacionorigen ?? items[0].origen ?? '—',
                fecha: items[0].tbproductorubicacionfecha ?? items[0].fecha ?? null,
            } : null;
            if (latest) {
                setStatus(`Ultima observacion disponible${latest.fecha ? `: ${latest.fecha}` : ''}.`);
                mapShow.hidden = false;
            } else {
                setStatus('No hay ubicaciones observadas registradas. Puede agregar una si el productor lo consiente.');
                mapShow.hidden = true;
            }
        } catch (error) {
            if (destroyed || token !== operationId) return;
            latest = null;
            mapShow.hidden = true;
            setStatus(error.message ?? 'No fue posible consultar el historico de ubicacion.', 'error');
        }
    };

    const selectProducer = async (identificacionNumero) => {
        const token = ++operationId;
        productor = null;
        candidate = null;
        latest = null;
        destroyMap();
        candidateBox.hidden = true;
        manualForm.hidden = true;
        mapShow.hidden = true;
        setStatus('Consultando el contexto del productor...');
        try {
            const response = await requestFn(`${API_PRODUCTORES}?${new URLSearchParams({ identificacionNumero })}`);
            if (destroyed || token !== operationId) return;
            productor = response.data ?? null;
            if (!productor?.productorId) throw new Error('No fue posible resolver el productor seleccionado.');
            await loadLatest(token);
        } catch (error) {
            if (destroyed || token !== operationId) return;
            productor = null;
            setStatus(error.message ?? 'No fue posible resolver el productor seleccionado.', 'error');
        }
    };

    const solicitarGps = async () => {
        if (!productor || destroyed) return;
        const token = ++operationId;
        gpsButton.disabled = true;
        requestCancel.hidden = false;
        setStatus('Esperando la respuesta del navegador...');
        try {
            const location = await solicitarFn({ altaPrecision: false, timeoutMs: 10000 });
            if (destroyed || token !== operationId) return;
            setCandidate(location);
            const precision = evaluarPrecision(location.precisionMetros);
            setStatus(precision.mensaje, precision.nivel === 'baja' ? 'warning' : '');
        } catch (error) {
            if (destroyed || token !== operationId) return;
            setStatus(error.message ?? 'No fue posible obtener la ubicacion.', 'error');
        } finally {
            if (!destroyed && token === operationId) {
                gpsButton.disabled = false;
                requestCancel.hidden = true;
            }
        }
    };

    const prepararManual = (event) => {
        event.preventDefault();
        for (const node of root.querySelectorAll('[data-manual-error]')) node.textContent = '';
        const values = Object.fromEntries(new FormData(manualForm));
        const validation = validarCoordenadasManual(values);
        if (!validation.ok) {
            for (const [field, message] of Object.entries(validation.errors)) {
                root.querySelector(`[data-manual-error="${field}"]`).textContent = message;
            }
            setStatus('Revise las coordenadas manuales.', 'error');
            return;
        }
        setCandidate(validation.data);
        manualForm.hidden = true;
        setStatus('Coordenadas preparadas. Confirme para registrar la observacion.');
    };

    const save = async () => {
        if (!candidate || !productor?.productorId || saving) return;
        saving = true;
        saveButton.disabled = true;
        root.setAttribute('aria-busy', 'true');
        setStatus('Registrando la observacion...');
        try {
            const response = await registrarUbicacionObservada({
                productorId: productor.productorId,
                ubicacion: candidate,
                requestFn,
            });
            latest = {
                latitud: response.data?.tbproductorubicacionlatitud ?? candidate.latitud,
                longitud: response.data?.tbproductorubicacionlongitud ?? candidate.longitud,
                precisionMetros: response.data?.tbproductorubicacionprecision ?? candidate.precisionMetros,
                origen: response.data?.tbproductorubicacionorigen ?? candidate.origen,
                fecha: response.data?.tbproductorubicacionfecha ?? null,
            };
            candidate = null;
            candidateBox.hidden = true;
            mapShow.hidden = false;
            destroyMap();
            mapShow.hidden = false;
            setStatus(response.message ?? 'Ubicacion registrada correctamente.');
        } catch (error) {
            setStatus(error.message ?? 'No fue posible registrar la ubicacion. Puede reintentar sin perder las coordenadas.', 'error');
        } finally {
            saving = false;
            saveButton.disabled = false;
            root.setAttribute('aria-busy', 'false');
        }
    };

    const showMap = async () => {
        const coordinates = currentCoordinates();
        if (!coordinates || mapController) return;
        mapShell.hidden = false;
        mapFallback.hidden = true;
        mapShow.disabled = true;
        setStatus('Cargando mapa opcional...');
        const token = operationId;
        try {
            const createdMap = await crearMapaFn({
                contenedor: mapCanvas,
                coordenadas: coordinates,
                draggable: false,
                interactive: false,
                onError: (error) => {
                    if (error?.kind === 'resource') setStatus('El mapa esta parcialmente disponible. Puede continuar sin depender de el.', 'warning');
                },
            });
            if (destroyed || token !== operationId) {
                createdMap?.destruir?.();
                return;
            }
            mapController = createdMap;
            setStatus('Mapa cargado. La direccion administrativa sigue siendo independiente de este punto.');
        } catch (error) {
            if (destroyed || token !== operationId) return;
            mapController = null;
            mapCanvas.replaceChildren();
            mapFallback.hidden = false;
            setStatus('Mapa no disponible. Puede continuar ingresando o consultando los datos manualmente.', 'error');
        } finally {
            if (!destroyed && token === operationId) mapShow.disabled = false;
        }
    };

    gpsButton.addEventListener('click', solicitarGps);
    requestCancel.addEventListener('click', () => {
        operationId += 1;
        requestCancel.hidden = true;
        gpsButton.disabled = false;
        setStatus('Solicitud cancelada. Si el navegador responde despues, esa lectura sera ignorada.');
    });
    manualToggle.addEventListener('click', () => {
        operationId += 1;
        requestCancel.hidden = true;
        gpsButton.disabled = false;
        manualForm.hidden = false;
        candidate = null;
        candidateBox.hidden = true;
        destroyMap();
        manualForm.querySelector('input')?.focus();
        setStatus('Ingrese coordenadas manuales. Esto registra una observacion del productor, no una finca.');
    });
    manualForm.addEventListener('submit', prepararManual);
    $('[data-location-manual-cancel]').addEventListener('click', cancelPending);
    $('[data-location-cancel]').addEventListener('click', cancelPending);
    saveButton.addEventListener('click', save);
    mapShow.addEventListener('click', showMap);
    mapRetry.addEventListener('click', () => { destroyMap(); mapShow.hidden = false; showMap(); });

    return Object.freeze({
        seleccionarProductor: selectProducer,
        cancelar: cancelPending,
        destruir() {
            destroyed = true;
            operationId += 1;
            destroyMap();
        },
    });
}

export function inicializarUbicacionProductorUI() {
    asegurarEstilos();
    const detailContent = document.querySelector('#detalle-contenido');
    const detailModal = document.querySelector('#modal-detalle');
    const table = document.querySelector('#cuerpo-productores');
    if (!detailContent || !detailModal || !table) return null;

    let section = document.querySelector('#ubicacion-observada-productor');
    if (!section) {
        section = crearSeccion();
        detailContent.insertAdjacentElement('afterend', section);
    }
    const control = crearControlUbicacionProductor({ root: section });

    table.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-action="ver"]') : null;
        if (!button?.dataset.id) return;
        control.seleccionarProductor(button.dataset.id);
    });
    detailModal.addEventListener('close', () => control.cancelar());
    return control;
}
