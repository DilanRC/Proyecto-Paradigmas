import { request } from './shared/api.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/productores.php';
const TAMANO_PAGINA = 25;

/**
 * Estado de sesion de la baraja.
 *
 * IMPORTANTE: no persiste. No existe endpoint ni tabla para "guardar" un
 * productor, y el proyecto tampoco define que significa: podria ser una
 * preferencia real del usuario, que no puede vivir unicamente en un navegador.
 * Hasta que exista ese contrato, esto es memoria de presentacion y se rotula
 * como tal en la vista. Ver DEC-FRONT en Documentation/Decisiones.md.
 */
export function createDeck() {
    return { index: 0, guardados: new Set(), revisados: new Set() };
}

export function currentProducer(deck, items) {
    return items[deck.index] ?? null;
}

/** Avanza sin salirse del final; marca el actual como revisado. */
export function advance(deck, items) {
    const actual = currentProducer(deck, items);
    const revisados = new Set(deck.revisados);
    if (actual) revisados.add(actual.identificacionNumero);
    return { ...deck, revisados, index: Math.min(deck.index + 1, items.length) };
}

/** Marca el actual como guardado y avanza. Idempotente por identificacion. */
export function save(deck, items) {
    const actual = currentProducer(deck, items);
    if (!actual) return deck;
    const guardados = new Set(deck.guardados);
    guardados.add(actual.identificacionNumero);
    return advance({ ...deck, guardados }, items);
}

export function deckExhausted(deck, items) {
    return items.length > 0 && deck.index >= items.length;
}

export function initials(name = '') {
    return name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase()).join('') || 'P';
}

export function formatLocation(direccion) {
    const partes = [direccion?.distrito, direccion?.canton, direccion?.provincia].filter(Boolean);
    return partes.length ? partes.join(', ') : 'Sin dirección';
}

export function formatFarms(fincas) {
    return Array.isArray(fincas) && fincas.length
        ? fincas.map((finca) => finca.nombre).join(', ')
        : 'Sin fincas registradas';
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        loading: $('#estado-carga'), card: $('#tarjeta-productor'), empty: $('#estado-vacio'),
        error: $('#estado-error'), errorMessage: $('#mensaje-error'), retry: $('#reintentar'),
        actions: $('#acciones'), pass: $('#accion-pasar'), keep: $('#accion-guardar'),
        nombre: $('#productor-nombre'), iniciales: $('#productor-iniciales'),
        finca: $('#productor-finca'), ubicacion: $('#productor-ubicacion'),
        posicion: $('#productor-posicion'), identificacion: $('#productor-identificacion'),
        telefono: $('#productor-telefono'), correo: $('#productor-correo'),
        fincas: $('#productor-fincas'), estado: $('#productor-estado'),
        guardados: $('#contador-guardados'), revisados: $('#contador-revisados'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    let state = createListState({ pageSize: TAMANO_PAGINA });
    let deck = createDeck();
    let controller = null;

    async function load() {
        controller?.abort();
        controller = new AbortController();
        const started = nextRequest(state, { page: 1 });
        state = started.state;
        render();

        const parameters = new URLSearchParams({
            estado: 'ACTIVO', pagina: '1', tamanoPagina: String(TAMANO_PAGINA),
        });
        try {
            const response = await request(`${API_URL}?${parameters}`, { signal: controller.signal });
            const list = Array.isArray(response.data?.productores) ? response.data.productores : [];
            state = applyResult(state, {
                sequence: started.sequence,
                items: list,
                total: Number(response.data?.total) || list.length,
            });
            deck = createDeck();
        } catch (error) {
            if (error.name === 'AbortError') { state = applyAbort(state); return; }
            state = applyFailure(state, { sequence: started.sequence, error });
        }
        render();
    }

    function render() {
        const view = deriveListView(state, { singular: 'productor', plural: 'productores' });
        const agotada = deckExhausted(deck, state.items);
        const producer = currentProducer(deck, state.items);

        elements.loading.hidden = !view.showSkeleton;
        elements.error.hidden = !view.showError;
        elements.errorMessage.textContent = view.errorMessage;
        elements.retry.hidden = !view.canRetry;
        // Sin productores activos y baraja agotada comparten el mismo vacio.
        elements.empty.hidden = !(view.showEmpty || agotada);
        elements.card.hidden = !producer;
        elements.actions.hidden = !producer;

        elements.guardados.textContent = String(deck.guardados.size);
        elements.revisados.textContent = String(deck.revisados.size);

        if (!producer) return;
        elements.nombre.textContent = producer.nombre || 'Sin nombre';
        elements.iniciales.textContent = initials(producer.nombre);
        elements.finca.textContent = producer.fincas?.[0]?.nombre ?? 'Sin fincas';
        elements.ubicacion.textContent = formatLocation(producer.direccionPrincipal);
        elements.posicion.textContent = `${deck.index + 1} de ${state.items.length}`;
        elements.identificacion.textContent =
            `${producer.identificacion?.tipoCodigo ?? 'Sin tipo'} · ${producer.identificacionNumero}`;
        elements.telefono.textContent = producer.telefono || '—';
        elements.correo.textContent = producer.correoElectronico || '—';
        elements.fincas.textContent = formatFarms(producer.fincas);
        elements.estado.textContent = producer.estado === 'ACTIVO' ? 'Activo' : 'Inactivo';
    }

    elements.retry.addEventListener('click', load);
    elements.pass.addEventListener('click', () => {
        deck = advance(deck, state.items);
        render();
    });
    elements.keep.addEventListener('click', () => {
        const producer = currentProducer(deck, state.items);
        deck = save(deck, state.items);
        render();
        if (producer) toast.info(`${producer.nombre} guardado en esta sesión.`);
    });

    load();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
