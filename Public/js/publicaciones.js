// api.js se versiona: un api.js viejo en caché carga el auth-gate anterior, que no conoce esta ruta
// y deja el panel oculto (ver base.css, body.rural-panel).
import { request } from './shared/api.js?v=auth-gate-5';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/v1/admin/publicaciones';
const ETIQUETAS = { singular: 'publicación', plural: 'publicaciones' };
const ESTADOS = {
    ACTIVO: ['Activa', 'active'], PAUSADO: ['Pausada', 'inactive'],
    VENDIDO: ['Vendida', 'inactive'], RETIRADO: ['Retirada', 'inactive'],
};
const ACCIONES = {
    PAUSADO: { titulo: 'Pausar publicación', mensaje: 'dejará de verse en Explorar hasta que se reactive.' },
    RETIRADO: { titulo: 'Retirar publicación', mensaje: 'se cerrará y ya no podrá reactivarse.' },
};

/** Cuerpo enviado a la API. Exportado para pruebas. */
export function buildModeracionPayload({ publicacionId, estado, motivo = '' }) {
    const data = { publicacionId: Number(publicacionId), estado };
    if (motivo.trim() !== '') data.motivo = motivo.trim();
    return data;
}

export function formatPrecio(precio) {
    if (typeof precio !== 'number' || !Number.isFinite(precio)) return 'A convenir';
    return `₡${String(Math.round(precio)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-publicaciones'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-publicaciones'), total: $('#total-publicaciones'), search: $('#busqueda-publicacion'),
        status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'),
        next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        modal: $('#modal-moderar'), form: $('#formulario-moderar'), modalTitle: $('#titulo-moderar'),
        modalMessage: $('#mensaje-moderar'), close: $('#cerrar-moderar'), cancel: $('#cancelar-moderar'),
        confirm: $('#confirmar-moderar'), reason: $('#motivo'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const publicaciones = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy || statusChange.busy });

    let state = createListState({ pageSize: 25 });
    let listController = null;
    let pendiente = null;
    let searchTimer = 0;

    async function list({ page = state.page } = {}) {
        listController?.abort();
        listController = new AbortController();
        const started = nextRequest(state, { page });
        state = started.state;
        render();

        const consulta = { pagina: state.page, tamanoPagina: state.pageSize };
        if (elements.search.value.trim()) consulta.q = elements.search.value.trim();
        if (elements.status.value !== 'TODOS') consulta.estado = elements.status.value;

        try {
            const response = await request(API_URL, { method: 'POST', body: JSON.stringify({ consulta }), signal: listController.signal });
            const items = Array.isArray(response.data?.publicaciones) ? response.data.publicaciones : [];
            publicaciones.clear();
            items.forEach((item) => publicaciones.set(String(item.publicacionId), item));
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
        const view = deriveListView(state, ETIQUETAS);
        elements.loading.hidden = !view.showSkeleton;
        elements.empty.hidden = !view.showEmpty;
        elements.error.hidden = !view.showError;
        elements.errorMessage.textContent = view.errorMessage;
        elements.retry.hidden = !view.canRetry;
        elements.panel.setAttribute('aria-busy', String(view.showSkeleton));
        elements.total.textContent = view.totalLabel;
        elements.page.textContent = view.pageLabel;
        elements.previous.disabled = view.previousDisabled;
        elements.next.disabled = view.nextDisabled;
        elements.refresh.disabled = view.refreshDisabled;

        elements.body.replaceChildren();
        if (!view.showList) return;
        const fragment = document.createDocumentFragment();
        state.items.forEach((item) => fragment.appendChild(createRow(item)));
        elements.body.appendChild(fragment);
    }

    function createCell(label, text = '') {
        const cell = document.createElement('td');
        cell.dataset.label = label;
        cell.textContent = text;
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

    function createRow(item) {
        const row = document.createElement('tr');
        row.dataset.id = String(item.publicacionId);
        const [etiqueta, clase] = ESTADOS[item.estado] ?? [String(item.estado ?? '—'), 'inactive'];
        const statusCell = createCell('Estado');
        const badge = document.createElement('span');
        badge.className = `badge badge--${clase}`;
        badge.textContent = etiqueta;
        statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        if (item.estado === 'ACTIVO') {
            actionsCell.append(createActionButton('desactivar', 'Pausar', item.publicacionId), createActionButton('retirar', 'Retirar', item.publicacionId));
        } else if (item.estado === 'PAUSADO') {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', item.publicacionId), createActionButton('retirar', 'Retirar', item.publicacionId));
        }
        row.append(
            createCell('Publicación', item.titulo || 'Sin título'),
            createCell('Vendedor', item.vendedor?.nombre || '—'),
            createCell('Finca', item.finca?.nombre || '—'),
            createCell('Precio', formatPrecio(item.precio)),
            statusCell,
            actionsCell,
        );
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const item = publicaciones.get(button.dataset.id);
        if (!item) { toast.error('No se encontró la publicación seleccionada.'); return; }
        if (button.dataset.action === 'desactivar') openModeration(item, 'PAUSADO');
        if (button.dataset.action === 'retirar') openModeration(item, 'RETIRADO');
        if (button.dataset.action === 'reactivar') reactivate(item);
    }

    function openModeration(item, estado) {
        pendiente = { item, estado };
        errores.clearErrors();
        elements.form.reset();
        elements.modalTitle.textContent = ACCIONES[estado].titulo;
        elements.modalMessage.textContent = `“${item.titulo || 'Publicación'}” ${ACCIONES[estado].mensaje}`;
        elements.confirm.textContent = estado === 'PAUSADO' ? 'Pausar' : 'Retirar';
        dialogs.open(elements.modal, { focus: elements.reason });
    }

    function closeModeration() {
        if (submit.busy) return;
        dialogs.close(elements.modal);
        errores.clearErrors();
        pendiente = null;
    }

    async function patch(item, estado, motivo = '') {
        return request(API_URL, { method: 'PATCH', body: JSON.stringify(buildModeracionPayload({ publicacionId: item.publicacionId, estado, motivo })) });
    }

    function confirmModeration(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!pendiente) return;
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            setSaving(elements.form, true, { submitButton: elements.confirm });
            try {
                const response = await patch(pendiente.item, pendiente.estado, elements.reason.value);
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
                const response = await patch(item, 'ACTIVO');
                toast.success(response.message);
                await list();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => list({ page: 1 }), 300);
    }

    elements.refresh.addEventListener('click', () => list());
    elements.retry.addEventListener('click', () => list());
    elements.previous.addEventListener('click', () => { if (state.page > 1) list({ page: state.page - 1 }); });
    elements.next.addEventListener('click', () => list({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => list({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', confirmModeration);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.close.addEventListener('click', closeModeration);
    elements.cancel.addEventListener('click', closeModeration);
    elements.body.addEventListener('click', handleTableAction);
    elements.modal.addEventListener('click', dialogs.handleBackdropClick);
    elements.modal.addEventListener('close', dialogs.restoreFocus);

    list();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
