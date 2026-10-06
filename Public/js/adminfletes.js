// api.js se versiona: un api.js viejo en caché carga el auth-gate anterior, que no conoce esta ruta
// y deja el panel oculto (ver base.css, body.rural-panel).
import { request } from './shared/api.js?v=auth-gate-8';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/v1/admin/fletes';
const VISTAS = {
    OFERTAS: {
        etiquetas: { singular: 'oferta', plural: 'ofertas' }, clave: 'ofertas', cuerpo: 'cuerpo-ofertas', tabla: 'tabla-ofertas',
        placeholder: 'Buscar por transportista, vehículo o zona', vacio: 'No se encontraron ofertas',
        estados: [['TODOS', 'Todas'], ['ACTIVA', 'Activas'], ['PAUSADA', 'Pausadas'], ['RETIRADA', 'Retiradas']],
    },
    SOLICITUDES: {
        etiquetas: { singular: 'solicitud', plural: 'solicitudes' }, clave: 'solicitudes', cuerpo: 'cuerpo-solicitudes', tabla: 'tabla-solicitudes',
        placeholder: 'Buscar por publicación, comprador o vendedor', vacio: 'No se encontraron solicitudes',
        estados: [['TODOS', 'Todas'], ['PENDIENTE', 'Pendientes'], ['ACEPTADA', 'Aceptadas'], ['RECHAZADA', 'Rechazadas'], ['CANCELADA', 'Canceladas']],
    },
};
const ESTADOS_OFERTA = {
    ACTIVA: ['Activa', 'active'], PAUSADA: ['Pausada', 'inactive'], RETIRADA: ['Retirada', 'inactive'],
};
const ESTADOS_SOLICITUD = {
    PENDIENTE: ['Pendiente', 'inactive'], ACEPTADA: ['Aceptada', 'active'],
    RECHAZADA: ['Rechazada', 'inactive'], CANCELADA: ['Cancelada', 'inactive'],
};

/** Cuerpo de la consulta de lectura. Exportado para pruebas. */
export function buildConsulta({ vista, q = '', estado = 'TODOS', pagina = 1, tamanoPagina = 25 }) {
    const consulta = { vista, pagina, tamanoPagina };
    if (q.trim()) consulta.q = q.trim();
    if (estado !== 'TODOS') consulta.estado = estado;
    return consulta;
}

/** Cuerpo del PATCH de moderación (RETIRADA exige motivo; ACTIVA lo acepta opcional). Exportado para pruebas. */
export function buildModeracionPayload({ ofertaId, estado, motivo = '' }) {
    const data = { ofertaId: Number(ofertaId), estado };
    if (motivo.trim() !== '') data.motivo = motivo.trim();
    return data;
}

export function formatPrecio(precio) {
    if (typeof precio !== 'number' || !Number.isFinite(precio)) return 'A convenir';
    return `₡${String(Math.round(precio)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

export function formatZona(zona = {}) {
    return [zona.provincia, zona.canton, zona.distrito, zona.pueblo].filter(Boolean).join(', ') || '—';
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        empty: $('#estado-vacio'), emptyTitle: $('#titulo-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-fletes'), total: $('#total-fletes'), search: $('#busqueda-flete'),
        view: $('#filtro-vista'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'),
        previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        modal: $('#modal-moderar'), form: $('#formulario-moderar'), modalMessage: $('#mensaje-moderar'),
        close: $('#cerrar-moderar'), cancel: $('#cancelar-moderar'), confirm: $('#confirmar-moderar'), reason: $('#motivo'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const ofertas = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy || statusChange.busy });

    let state = createListState({ pageSize: 25 });
    let listController = null;
    let pendiente = null;
    let searchTimer = 0;
    let vistaActual = 'OFERTAS';

    function fillStatusOptions() {
        elements.status.replaceChildren(...VISTAS[vistaActual].estados.map(([valor, texto]) => {
            const option = document.createElement('option');
            option.value = valor;
            option.textContent = texto;
            return option;
        }));
    }

    async function list({ page = state.page } = {}) {
        listController?.abort();
        listController = new AbortController();
        const vista = vistaActual;
        const started = nextRequest(state, { page });
        state = started.state;
        render();

        const consulta = buildConsulta({
            vista, q: elements.search.value, estado: elements.status.value, pagina: state.page, tamanoPagina: state.pageSize,
        });
        try {
            const response = await request(API_URL, { method: 'POST', body: JSON.stringify({ consulta }), signal: listController.signal });
            const items = Array.isArray(response.data?.[VISTAS[vista].clave]) ? response.data[VISTAS[vista].clave] : [];
            ofertas.clear();
            if (vista === 'OFERTAS') items.forEach((item) => ofertas.set(String(item.ofertaId), item));
            state = applyResult(state, {
                sequence: started.sequence,
                items,
                total: Number(response.data?.total) || 0,
                page: Number(response.data?.pagina) || state.page,
                pageSize: Number(response.data?.tamanoPagina) || state.pageSize,
            });
        } catch (error) {
            if (error.name === 'AbortError') { state = applyAbort(state); return; }
            state = applyFailure(state, { sequence: started.sequence, error });
        }
        render();
    }

    function render() {
        const vista = VISTAS[vistaActual];
        const view = deriveListView(state, vista.etiquetas);
        elements.loading.hidden = !view.showSkeleton;
        elements.empty.hidden = !view.showEmpty;
        elements.emptyTitle.textContent = vista.vacio;
        elements.error.hidden = !view.showError;
        elements.errorMessage.textContent = view.errorMessage;
        elements.retry.hidden = !view.canRetry;
        elements.panel.setAttribute('aria-busy', String(view.showSkeleton));
        elements.total.textContent = view.totalLabel;
        elements.page.textContent = view.pageLabel;
        elements.previous.disabled = view.previousDisabled;
        elements.next.disabled = view.nextDisabled;
        elements.refresh.disabled = view.refreshDisabled;

        for (const [clave, datos] of Object.entries(VISTAS)) {
            $(`#${datos.tabla}`).hidden = clave !== vistaActual;
            if (clave !== vistaActual) $(`#${datos.cuerpo}`).replaceChildren();
        }
        const body = $(`#${vista.cuerpo}`);
        body.replaceChildren();
        if (!view.showList) return;
        const fragment = document.createDocumentFragment();
        state.items.forEach((item) => fragment.appendChild(vistaActual === 'OFERTAS' ? createOfertaRow(item) : createSolicitudRow(item)));
        body.appendChild(fragment);
    }

    function createCell(label, text = '') {
        const cell = document.createElement('td');
        cell.dataset.label = label;
        cell.textContent = text;
        return cell;
    }

    function createBadgeCell(mapa, estado) {
        const cell = createCell('Estado');
        const [etiqueta, clase] = mapa[estado] ?? [String(estado ?? '—'), 'inactive'];
        const badge = document.createElement('span');
        badge.className = `badge badge--${clase}`;
        badge.textContent = etiqueta;
        cell.appendChild(badge);
        return cell;
    }

    function createActionButton(action, text, id) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `action action--${action}`;
        button.dataset.action = action;
        button.dataset.id = String(id);
        button.textContent = text;
        return button;
    }

    function createOfertaRow(item) {
        const row = document.createElement('tr');
        row.dataset.id = String(item.ofertaId);
        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        if (item.estado === 'ACTIVA' || item.estado === 'PAUSADA') {
            actionsCell.append(createActionButton('retirar', 'Retirar', item.ofertaId));
        } else if (item.estado === 'RETIRADA') {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', item.ofertaId));
        }
        row.append(
            createCell('Transportista', item.transportista?.nombre || '—'),
            createCell('Vehículo', [item.vehiculo?.modelo, item.vehiculo?.placa].filter(Boolean).join(' · ') || '—'),
            createCell('Zona', formatZona(item.zona)),
            createCell('Radio', `${item.radioKm} km`),
            createCell('Capacidad', `${item.capacidad} cabezas`),
            createCell('Precio', formatPrecio(item.precio)),
            createBadgeCell(ESTADOS_OFERTA, item.estado),
            actionsCell,
        );
        return row;
    }

    function createSolicitudRow(item) {
        const row = document.createElement('tr');
        row.dataset.id = String(item.solicitudId);
        const flete = item.flete
            ? `${item.flete.transportista || 'Transportista'} · ${ESTADOS_SOLICITUD[item.flete.estado]?.[0] ?? item.flete.estado ?? '—'}`
            : 'Sin flete';
        row.append(
            createCell('Publicación', item.publicacion?.titulo || 'Sin título'),
            createCell('Comprador', item.comprador?.nombre || '—'),
            createCell('Vendedor', item.vendedor?.nombre || '—'),
            createCell('Precio', formatPrecio(item.precio)),
            createBadgeCell(ESTADOS_SOLICITUD, item.estado),
            createCell('Flete', flete),
        );
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const item = ofertas.get(button.dataset.id);
        if (!item) { toast.error('No se encontró la oferta seleccionada.'); return; }
        if (button.dataset.action === 'retirar') openModeration(item);
        if (button.dataset.action === 'reactivar') reactivate(item);
    }

    function openModeration(item) {
        pendiente = { item };
        errores.clearErrors();
        elements.form.reset();
        elements.modalMessage.textContent = `La oferta de ${item.transportista?.nombre || 'este transportista'} dejará de verse en Fletes y no podrá modificarla.`;
        dialogs.open(elements.modal, { focus: elements.reason });
    }

    function closeModeration() {
        if (submit.busy) return;
        dialogs.close(elements.modal);
        errores.clearErrors();
        pendiente = null;
    }

    function patch(item, estado, motivo = '') {
        return request(API_URL, { method: 'PATCH', body: JSON.stringify(buildModeracionPayload({ ofertaId: item.ofertaId, estado, motivo })) });
    }

    function confirmModeration(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!pendiente) return;
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            setSaving(elements.form, true, { submitButton: elements.confirm });
            try {
                const response = await patch(pendiente.item, 'RETIRADA', elements.reason.value);
                dialogs.close(elements.modal);
                pendiente = null;
                toast.success(response.message);
                await list();
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                setSaving(elements.form, false, { submitButton: elements.confirm });
            }
        });
    }

    function reactivate(item) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll('[data-action]');
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await patch(item, 'ACTIVA');
                toast.success(response.message);
                await list();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function changeView() {
        vistaActual = elements.view.value in VISTAS ? elements.view.value : 'OFERTAS';
        elements.search.placeholder = VISTAS[vistaActual].placeholder;
        fillStatusOptions();
        state = createListState({ pageSize: 25 }); // las filas de una vista no sirven en la otra
        list({ page: 1 });
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => list({ page: 1 }), 300);
    }

    elements.refresh.addEventListener('click', () => list());
    elements.retry.addEventListener('click', () => list());
    elements.previous.addEventListener('click', () => { if (state.page > 1) list({ page: state.page - 1 }); });
    elements.next.addEventListener('click', () => list({ page: state.page + 1 }));
    elements.view.addEventListener('change', changeView);
    elements.status.addEventListener('change', () => list({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', confirmModeration);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.close.addEventListener('click', closeModeration);
    elements.cancel.addEventListener('click', closeModeration);
    elements.panel.addEventListener('click', handleTableAction);
    elements.modal.addEventListener('click', dialogs.handleBackdropClick);
    elements.modal.addEventListener('close', dialogs.restoreFocus);

    fillStatusOptions();
    list();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
