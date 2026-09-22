import { request } from './shared/api.js';
import { conectarDireccion } from './shared/direccion.js';
import { buscarDireccionPorCoordenadas, crearSelectorPuntoFinca } from './shared/finca-mapa.js';

const FINCAS_DIRECCION_URL = 'api/v1/fincas/direccion';
const modal = document.querySelector('#modal-productor');
const hiddenField = document.querySelector('#fincas-nombres');
const list = document.querySelector('#fincas-cards');
const addButton = document.querySelector('#agregar-finca-admin');
const emptyState = document.querySelector('#fincas-empty');
const editores = new WeakMap();
let secuencia = 0;
let renderToken = 0;

function leerBorradores() {
    const raw = String(hiddenField?.value ?? '').trim();
    if (raw.startsWith('[')) {
        try {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) return parsed.filter((item) => item && typeof item === 'object');
        } catch {}
    }
    return raw.split(/\r?\n/).map((nombre) => nombre.trim()).filter(Boolean).map((nombre) => ({ nombre }));
}

function leerDireccion(card) {
    const punto = editores.get(card)?.mapa?.obtenerPunto?.() ?? null;
    const direccion = {
        provincia: String(card.querySelector('[data-farm-province]')?.value ?? '').trim(),
        canton: String(card.querySelector('[data-farm-canton]')?.value ?? '').trim(),
        distrito: String(card.querySelector('[data-farm-district]')?.value ?? '').trim(),
        pueblo: String(card.querySelector('[data-farm-town]')?.value ?? '').trim() || null,
        senas: String(card.querySelector('[data-farm-directions]')?.value ?? '').trim() || null,
        latitud: punto?.latitud ?? null,
        longitud: punto?.longitud ?? null,
    };
    return Object.values(direccion).some((value) => value !== null && value !== '') ? direccion : null;
}

function leerCard(card) {
    const nombre = String(card.querySelector('[data-farm-name]')?.value ?? '').trim();
    const resultado = { nombre };
    const direccion = leerDireccion(card);
    if (direccion) resultado.direccion = direccion;
    if (card.dataset.addressExists === 'true') resultado.direccionExiste = true;
    return resultado;
}

function syncHidden() {
    if (!hiddenField || !list) return;
    const borradores = [...list.querySelectorAll('[data-farm-card]')].map(leerCard).filter((item) => item.nombre);
    hiddenField.value = JSON.stringify(borradores);
    hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
    renderEmptyState();
}

function renderEmptyState() {
    if (!emptyState || !list) return;
    emptyState.hidden = list.querySelector('[data-farm-card]') !== null;
}

function montarDireccion(card, inicial = null) {
    const details = card.querySelector('[data-farm-address]');
    const listaId = `admin-pueblos-finca-${++secuencia}`;
    details.innerHTML = `
        <summary>Dirección y punto exacto <span class="label">opcional</span></summary>
        <div class="farm-address-editor">
            <p class="fieldset-help">La dirección pertenece a esta finca. El mapa solo se abre si desea marcar el punto exacto.</p>
            <div class="farm-address-editor__grid">
                <label class="field"><span>Provincia</span><select data-farm-province></select></label>
                <label class="field"><span>Cantón</span><select data-farm-canton></select></label>
                <label class="field"><span>Distrito</span><select data-farm-district disabled><option value="">Seleccione un distrito</option></select></label>
                <label class="field"><span>Pueblo</span><input data-farm-town maxlength="150" list="${listaId}" autocomplete="off" disabled><datalist id="${listaId}"></datalist></label>
                <label class="field field--full"><span>Señas</span><textarea data-farm-directions maxlength="500" rows="2"></textarea></label>
            </div>
            <div data-farm-map></div>
            <p class="fieldset-help" data-farm-address-state></p>
        </div>`;
    const direccion = conectarDireccion({
        provincia: details.querySelector('[data-farm-province]'),
        canton: details.querySelector('[data-farm-canton]'),
        distrito: details.querySelector('[data-farm-district]'),
        pueblo: details.querySelector('[data-farm-town]'),
        listaPueblos: details.querySelector(`#${listaId}`),
    });
    direccion.aplicar(inicial ?? {});
    details.querySelector('[data-farm-directions]').value = inicial?.senas ?? '';
    let geocodificacion = 0;
    let geocodificacionAbortController = null;
    async function completarDireccionDesdePunto(punto) {
        geocodificacionAbortController?.abort();
        const turno = ++geocodificacion;
        if (!punto) return;
        const controller = new AbortController();
        geocodificacionAbortController = controller;
        try {
            const encontrada = await buscarDireccionPorCoordenadas(punto, { signal: controller.signal });
            if (turno !== geocodificacion || !card.isConnected) return;
            direccion.aplicar({
                provincia: encontrada.provincia || details.querySelector('[data-farm-province]')?.value,
                canton: encontrada.canton || details.querySelector('[data-farm-canton]')?.value,
                distrito: encontrada.distrito || details.querySelector('[data-farm-district]')?.value,
                pueblo: encontrada.pueblo || details.querySelector('[data-farm-town]')?.value,
            });
            syncHidden();
        } catch {
            // El punto y la dirección manual siguen siendo válidos si Nominatim falla.
        } finally {
            if (geocodificacionAbortController === controller) geocodificacionAbortController = null;
        }
    }
    const mapa = crearSelectorPuntoFinca({
        mount: details.querySelector('[data-farm-map]'),
        puntoInicial: { latitud: inicial?.latitud ?? null, longitud: inicial?.longitud ?? null },
        onPuntoChange: completarDireccionDesdePunto,
    });
    const cancelarGeocodificacion = () => {
        geocodificacion += 1;
        geocodificacionAbortController?.abort();
        geocodificacionAbortController = null;
    };
    editores.set(card, { direccion, mapa, cancelarGeocodificacion });
    const invalidarGeocodificacion = () => {
        cancelarGeocodificacion();
        syncHidden();
    };
    details.addEventListener('input', invalidarGeocodificacion);
    details.addEventListener('change', invalidarGeocodificacion);
}

function buildCard(borrador = {}, persisted = false) {
    const card = document.createElement('article');
    card.className = 'farm-card';
    card.dataset.farmCard = 'true';
    card.dataset.persisted = String(persisted);
    card.dataset.addressExists = String(Boolean(borrador.direccionExiste));

    const top = document.createElement('div');
    top.className = 'farm-card__top';
    const label = document.createElement('label');
    label.className = 'field';
    const title = document.createElement('span');
    title.textContent = 'Nombre de finca';
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 150;
    input.autocomplete = 'off';
    input.value = borrador.nombre ?? '';
    input.placeholder = 'Ej. Finca El Roble';
    input.dataset.farmName = 'true';
    input.setAttribute('aria-label', 'Nombre de finca');
    input.addEventListener('input', syncHidden);
    label.append(title, input);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button button--secondary farm-card__remove';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => {
        const editor = editores.get(card);
        editor?.cancelarGeocodificacion?.();
        editor?.mapa?.destruir?.();
        card.remove();
        syncHidden();
    });
    top.append(label, remove);

    const details = document.createElement('details');
    details.className = 'farm-card__address';
    details.dataset.farmAddress = 'true';
    card.append(top, details);
    montarDireccion(card, borrador.direccion ?? null);
    return card;
}

async function cargarDireccionPersistida(card, identificacionNumero, nombreFinca, token) {
    if (!identificacionNumero || !nombreFinca) return;
    const state = card.querySelector('[data-farm-address-state]');
    if (state) state.textContent = 'Consultando dirección registrada…';
    try {
        const response = await request(FINCAS_DIRECCION_URL, {
            method: 'POST',
            body: JSON.stringify({ consulta: { identificacionNumero, nombreFinca } }),
        });
        if (token !== renderToken || !card.isConnected) return;
        const direccion = response.data?.direccionFinca ?? null;
        if (direccion) {
            editores.get(card)?.direccion?.aplicar?.(direccion);
            const senas = card.querySelector('[data-farm-directions]');
            if (senas) senas.value = direccion.senas ?? '';
            editores.get(card)?.mapa?.aplicarPunto?.(
                { latitud: direccion.latitud, longitud: direccion.longitud },
                { notificar: false },
            );
            card.dataset.addressExists = 'true';
            if (state) state.textContent = 'Dirección registrada cargada.';
            syncHidden();
        }
    } catch (error) {
        if (token !== renderToken || !card.isConnected) return;
        if (error.status === 404) {
            card.dataset.addressExists = 'false';
            if (state) state.textContent = 'Esta finca todavía no tiene dirección registrada.';
            return;
        }
        if (state) state.textContent = 'No se pudo cargar la dirección. Si guarda ahora, la dirección existente no se modificará.';
    }
}

function renderFromHidden() {
    if (!list) return;
    const token = ++renderToken;
    for (const card of list.querySelectorAll('[data-farm-card]')) {
        const editor = editores.get(card);
        editor?.cancelarGeocodificacion?.();
        editor?.mapa?.destruir?.();
    }
    const borradores = leerBorradores();
    const persisted = Boolean(document.querySelector('#identificacion-original')?.value);
    const identificacionNumero = document.querySelector('#identificacion-original')?.value || '';
    const cards = borradores.map((borrador) => buildCard(borrador, persisted));
    list.replaceChildren(...cards);
    renderEmptyState();
    if (persisted) {
        cards.forEach((card, index) => {
            if (!borradores[index]?.direccion) cargarDireccionPersistida(card, identificacionNumero, borradores[index]?.nombre ?? '', token);
        });
    }
}

function initialize() {
    if (!modal || !hiddenField || !list || !addButton) return;
    addButton.addEventListener('click', () => {
        const card = buildCard({}, false);
        list.append(card);
        renderEmptyState();
        syncHidden();
        card.querySelector('[data-farm-name]')?.focus();
    });

    const observer = new MutationObserver(() => {
        if (modal.hasAttribute('open')) queueMicrotask(renderFromHidden);
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['open'] });
    renderEmptyState();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
