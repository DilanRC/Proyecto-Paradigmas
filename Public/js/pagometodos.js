import { request } from './shared/api.js';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/pagometodos.php';
const ETIQUETAS = { singular: 'método de pago', plural: 'métodos de pago' };

/** Cuerpo enviado a la API. Exportado para la prueba de paridad de contrato. */
export function buildPagoMetodoPayload({ nombre, descripcion, id = '' }) {
    const data = {
        nombre: nombre.trim(),
        descripcion: descripcion.trim(),
        activo: true,
    };
    if (String(id) !== '') data.id = Number(id);
    return data;
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-pagometodos'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-pagometodos'), total: $('#total-pagometodos'), search: $('#busqueda-pagometodo'),
        status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'),
        next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-pagometodo'),
        modal: $('#modal-pagometodo'), form: $('#formulario-pagometodo'), modalTitle: $('#titulo-modal'),
        modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'),
        save: $('#guardar-pagometodo'), deactivateModal: $('#modal-desactivar'),
        deactivateMessage: $('#mensaje-desactivar'), cancelDeactivate: $('#cancelar-desactivacion'),
        confirmDeactivate: $('#confirmar-desactivacion'), detailModal: $('#modal-detalle'),
        detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'),
        closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'),
        editFromDetail: $('#editar-desde-detalle'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const pagoMetodos = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy || statusChange.busy });

    let state = createListState({ pageSize: 25 });
    let listController = null;
    let pagoMetodoPendiente = null;
    let pagoMetodoDetalle = null;
    let searchTimer = 0;

    async function listPagoMetodos({ page = state.page } = {}) {
        listController?.abort();
        listController = new AbortController();
        const started = nextRequest(state, { page });
        state = started.state;
        render();

        const parameters = new URLSearchParams({
            pagina: String(state.page), tamanoPagina: String(state.pageSize),
        });
        if (elements.search.value.trim()) parameters.set('q', elements.search.value.trim());
        if (elements.status.value !== 'TODOS') parameters.set('estado', elements.status.value);

        try {
            const response = await request(`${API_URL}?${parameters}`, { signal: listController.signal });
            const list = Array.isArray(response.data?.pagoMetodos) ? response.data.pagoMetodos : [];
            pagoMetodos.clear();
            list.forEach((pagoMetodo) => pagoMetodos.set(String(pagoMetodo.pagoMetodoId), pagoMetodo));
            state = applyResult(state, {
                sequence: started.sequence,
                items: list,
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
        state.items.forEach((pagoMetodo) => fragment.appendChild(createRow(pagoMetodo)));
        elements.body.appendChild(fragment);
    }

    function createCell(label) {
        const cell = document.createElement('td');
        cell.dataset.label = label;
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

    function createRow(pagoMetodo) {
        const row = document.createElement('tr');
        row.dataset.id = String(pagoMetodo.pagoMetodoId);
        const nameCell = createCell('Nombre');
        nameCell.textContent = pagoMetodo.nombre || 'Sin nombre';
        const descriptionCell = createCell('Descripción');
        descriptionCell.textContent = pagoMetodo.descripcion || 'Sin descripción';
        const statusCell = createCell('Estado');
        const active = pagoMetodo.estado === 'ACTIVO';
        const badge = document.createElement('span');
        badge.className = `badge badge--${active ? 'active' : 'inactive'}`;
        badge.textContent = active ? 'Activo' : 'Inactivo';
        statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', pagoMetodo.pagoMetodoId));
        if (active) {
            actionsCell.append(
                createActionButton('editar', 'Editar', pagoMetodo.pagoMetodoId),
                createActionButton('desactivar', 'Desactivar', pagoMetodo.pagoMetodoId),
            );
        } else {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', pagoMetodo.pagoMetodoId));
        }
        row.append(nameCell, descriptionCell, statusCell, actionsCell);
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const pagoMetodo = pagoMetodos.get(button.dataset.id);
        if (!pagoMetodo) return toast.error('No se encontró el método de pago seleccionado.');
        if (button.dataset.action === 'ver') openDetail(pagoMetodo);
        if (button.dataset.action === 'editar') openEditForm(pagoMetodo);
        if (button.dataset.action === 'desactivar') openDeactivation(pagoMetodo);
        if (button.dataset.action === 'reactivar') reactivatePagoMetodo(pagoMetodo);
        return undefined;
    }

    function openDetail(pagoMetodo) {
        pagoMetodoDetalle = pagoMetodo;
        elements.detailTitle.textContent = pagoMetodo.nombre || 'Método de pago';
        const fragment = document.createDocumentFragment();
        [
            ['Nombre', pagoMetodo.nombre || '—', false],
            ['Descripción', pagoMetodo.descripcion || '—', true],
            ['Estado', pagoMetodo.estado === 'ACTIVO' ? 'Activo' : 'Inactivo', false],
        ].forEach(([etiqueta, valor, completa]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor;
            if (completa) dd.className = 'detail--full';
            fragment.append(dt, dd);
        });
        elements.detailContent.replaceChildren(fragment);
        dialogs.open(elements.detailModal, { focus: elements.closeDetail });
    }

    function closeDetail() { dialogs.close(elements.detailModal); pagoMetodoDetalle = null; }

    function editFromDetail() {
        if (!pagoMetodoDetalle) return;
        const pagoMetodo = pagoMetodoDetalle;
        closeDetail();
        openEditForm(pagoMetodo);
    }

    function resetForm() {
        elements.form.reset();
        errores.clearErrors();
        $('#pagometodo-id').value = '';
    }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear método de pago';
        elements.modalSubtitle.textContent = 'Nuevo registro';
        elements.save.textContent = 'Guardar método de pago';
        dialogs.open(elements.modal, { focus: $('#nombre') });
    }

    function openEditForm(pagoMetodo) {
        resetForm();
        $('#pagometodo-id').value = String(pagoMetodo.pagoMetodoId);
        $('#nombre').value = pagoMetodo.nombre ?? '';
        $('#descripcion').value = pagoMetodo.descripcion ?? '';
        elements.modalTitle.textContent = 'Editar método de pago';
        elements.modalSubtitle.textContent = 'Actualizar registro';
        elements.save.textContent = 'Guardar cambios';
        dialogs.open(elements.modal, { focus: $('#nombre') });
    }

    function savePagoMetodo(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            const id = $('#pagometodo-id').value;
            const editing = id !== '';
            const data = buildPagoMetodoPayload({
                nombre: $('#nombre').value,
                descripcion: $('#descripcion').value,
                id,
            });
            setSaving(elements.form, true, { submitButton: elements.save });
            try {
                const response = await request(API_URL, {
                    method: editing ? 'PUT' : 'POST',
                    body: JSON.stringify(data),
                });
                dialogs.close(elements.modal);
                toast.success(response.message);
                await listPagoMetodos();
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                setSaving(elements.form, false, { submitButton: elements.save });
            }
        });
    }

    function openDeactivation(pagoMetodo) {
        pagoMetodoPendiente = pagoMetodo;
        elements.deactivateMessage.textContent =
            `${pagoMetodo.nombre} dejará de estar disponible para nuevas transacciones.`;
        dialogs.open(elements.deactivateModal, { focus: elements.confirmDeactivate });
    }

    function changeStatus(method, pagoMetodo, afterSuccess = null) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll('[data-action], #confirmar-desactivacion');
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await request(API_URL, {
                    method,
                    body: JSON.stringify({ id: pagoMetodo.pagoMetodoId }),
                });
                afterSuccess?.();
                toast.success(response.message);
                await listPagoMetodos();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function deactivatePagoMetodo() {
        if (!pagoMetodoPendiente) return undefined;
        return changeStatus('DELETE', pagoMetodoPendiente, () => {
            dialogs.close(elements.deactivateModal);
            pagoMetodoPendiente = null;
        });
    }

    function reactivatePagoMetodo(pagoMetodo) { return changeStatus('PATCH', pagoMetodo); }

    function closeForm() { if (!submit.busy) { dialogs.close(elements.modal); errores.clearErrors(); } }
    function closeDeactivation() {
        if (statusChange.busy) return;
        dialogs.close(elements.deactivateModal);
        pagoMetodoPendiente = null;
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => listPagoMetodos({ page: 1 }), 300);
    }

    elements.create.addEventListener('click', openCreateForm);
    elements.refresh.addEventListener('click', () => listPagoMetodos());
    elements.retry.addEventListener('click', () => listPagoMetodos());
    elements.previous.addEventListener('click', () => {
        if (state.page > 1) listPagoMetodos({ page: state.page - 1 });
    });
    elements.next.addEventListener('click', () => listPagoMetodos({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => listPagoMetodos({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', savePagoMetodo);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.form.addEventListener('change', errores.clearControlError);
    elements.close.addEventListener('click', closeForm);
    elements.cancel.addEventListener('click', closeForm);
    elements.cancelDeactivate.addEventListener('click', closeDeactivation);
    elements.confirmDeactivate.addEventListener('click', deactivatePagoMetodo);
    elements.body.addEventListener('click', handleTableAction);
    elements.closeDetail.addEventListener('click', closeDetail);
    elements.closeDetailSecondary.addEventListener('click', closeDetail);
    elements.editFromDetail.addEventListener('click', editFromDetail);
    [elements.modal, elements.deactivateModal, elements.detailModal].forEach((dialog) => {
        dialog.addEventListener('click', dialogs.handleBackdropClick);
        dialog.addEventListener('close', dialogs.restoreFocus);
    });

    listPagoMetodos();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
