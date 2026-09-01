import { request } from './shared/api.js';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/vehiculos.php';
const ETIQUETAS = { singular: 'vehículo', plural: 'vehículos' };

/**
 * Cuerpo que se envia a la API. Se exporta para que las pruebas comprueben que
 * el refactor no cambio el contrato: la interfaz puede verse bien y estar
 * mandando otra cosa al backend.
 */
export function buildVehiculoPayload({ placa, vin, modelo, vehiculoId = '' }) {
    const data = {
        placa: placa.trim(),
        vin: vin.trim(),
        modelo: modelo.trim(),
    };
    const id = String(vehiculoId);
    if (id !== '') data.vehiculoId = Number(id);
    return data;
}

function initialize() {
    'use strict';

    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-vehiculos'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-vehiculos'), total: $('#total-vehiculos'), search: $('#busqueda-vehiculo'),
        status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'),
        next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-vehiculo'),
        modal: $('#modal-vehiculo'), form: $('#formulario-vehiculo'), modalTitle: $('#titulo-modal'),
        modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'),
        save: $('#guardar-vehiculo'), deactivateModal: $('#modal-desactivar'),
        deactivateMessage: $('#mensaje-desactivar'), cancelDeactivate: $('#cancelar-desactivacion'),
        confirmDeactivate: $('#confirmar-desactivacion'), detailModal: $('#modal-detalle'),
        detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'),
        closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'),
        editFromDetail: $('#editar-desde-detalle'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const vehiculos = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy || statusChange.busy });

    let state = createListState({ pageSize: 25 });
    let listController = null;
    let vehiculoPendiente = null;
    let vehiculoDetalle = null;
    let searchTimer = 0;

    // --- listado ------------------------------------------------------------------
    async function listVehicles({ page = state.page } = {}) {
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
            const list = Array.isArray(response.data?.vehiculos) ? response.data.vehiculos : [];
            vehiculos.clear();
            list.forEach((vehiculo) => vehiculos.set(String(vehiculo.vehiculoId), vehiculo));
            state = applyResult(state, {
                sequence: started.sequence,
                items: list,
                total: Number(response.data?.total) || 0,
                page: Number(response.data?.pagina) || state.page,
                pageSize: Number(response.data?.tamanoPagina) || state.pageSize,
            });
        } catch (error) {
            // Cancelar no es fallar: no debe pintar ningun estado.
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
        state.items.forEach((vehiculo) => fragment.appendChild(createRow(vehiculo)));
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

    function createRow(vehiculo) {
        const row = document.createElement('tr');
        row.dataset.id = String(vehiculo.vehiculoId);
        const placaCell = createCell('Placa'); placaCell.textContent = vehiculo.placa || 'Sin placa';
        const vinCell = createCell('VIN'); vinCell.textContent = vehiculo.vin || 'Sin VIN';
        const modeloCell = createCell('Modelo'); modeloCell.textContent = vehiculo.modelo || 'Sin modelo';
        const statusCell = createCell('Estado');
        const active = vehiculo.estado === 'ACTIVO';
        const badge = document.createElement('span');
        badge.className = `badge badge--${active ? 'active' : 'inactive'}`;
        badge.textContent = active ? 'Activo' : 'Inactivo';
        statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', vehiculo.vehiculoId));
        if (active) {
            actionsCell.append(
                createActionButton('editar', 'Editar', vehiculo.vehiculoId),
                createActionButton('desactivar', 'Desactivar', vehiculo.vehiculoId),
            );
        } else {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', vehiculo.vehiculoId));
        }
        row.append(placaCell, vinCell, modeloCell, statusCell, actionsCell);
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const vehiculo = vehiculos.get(button.dataset.id);
        if (!vehiculo) return toast.error('No se encontró el vehículo seleccionado.');
        if (button.dataset.action === 'ver') openDetail(vehiculo);
        if (button.dataset.action === 'editar') openEditForm(vehiculo);
        if (button.dataset.action === 'desactivar') openDeactivation(vehiculo);
        if (button.dataset.action === 'reactivar') reactivateVehicle(vehiculo);
        return undefined;
    }

    // --- detalle ------------------------------------------------------------------
    function openDetail(vehiculo) {
        vehiculoDetalle = vehiculo;
        elements.detailTitle.textContent = vehiculo.placa || 'Vehículo';
        const fragment = document.createDocumentFragment();
        [
            ['Placa', vehiculo.placa || '—'],
            ['VIN', vehiculo.vin || '—'],
            ['Modelo', vehiculo.modelo || '—'],
            ['Estado', vehiculo.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
        ].forEach(([etiqueta, valor]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor;
            fragment.append(dt, dd);
        });
        elements.detailContent.replaceChildren(fragment);
        dialogs.open(elements.detailModal, { focus: elements.closeDetail });
    }

    function closeDetail() { dialogs.close(elements.detailModal); vehiculoDetalle = null; }

    function editFromDetail() {
        if (!vehiculoDetalle) return;
        const vehiculo = vehiculoDetalle;
        closeDetail();
        openEditForm(vehiculo);
    }

    // --- formulario ---------------------------------------------------------------
    function resetForm() {
        elements.form.reset();
        errores.clearErrors();
        $('#vehiculo-id').value = '';
    }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear vehículo';
        elements.modalSubtitle.textContent = 'Nuevo registro';
        elements.save.textContent = 'Guardar vehículo';
        dialogs.open(elements.modal, { focus: $('#placa') });
    }

    function openEditForm(vehiculo) {
        resetForm();
        $('#vehiculo-id').value = String(vehiculo.vehiculoId);
        $('#placa').value = vehiculo.placa ?? '';
        $('#vin').value = vehiculo.vin ?? '';
        $('#modelo').value = vehiculo.modelo ?? '';
        elements.modalTitle.textContent = 'Editar vehículo';
        elements.modalSubtitle.textContent = 'Actualizar registro';
        elements.save.textContent = 'Guardar cambios';
        dialogs.open(elements.modal, { focus: $('#placa') });
    }

    function saveVehicle(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            const id = $('#vehiculo-id').value;
            const editing = id !== '';
            const data = buildVehiculoPayload({
                placa: $('#placa').value,
                vin: $('#vin').value,
                modelo: $('#modelo').value,
                vehiculoId: id,
            });
            setSaving(elements.form, true, { submitButton: elements.save });
            try {
                const response = await request(API_URL, {
                    method: editing ? 'PUT' : 'POST',
                    body: JSON.stringify(data),
                });
                dialogs.close(elements.modal);
                toast.success(response.message);
                await listVehicles();
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                setSaving(elements.form, false, { submitButton: elements.save });
            }
        });
    }

    // --- estado -------------------------------------------------------------------
    function openDeactivation(vehiculo) {
        vehiculoPendiente = vehiculo;
        elements.deactivateMessage.textContent =
            `El vehículo ${vehiculo.placa} dejará de estar disponible para asignaciones.`;
        dialogs.open(elements.deactivateModal, { focus: elements.confirmDeactivate });
    }

    function changeStatus(method, vehiculo, afterSuccess = null) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll('[data-action], #confirmar-desactivacion');
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await request(API_URL, {
                    method,
                    body: JSON.stringify({ vehiculoId: vehiculo.vehiculoId }),
                });
                afterSuccess?.();
                toast.success(response.message);
                await listVehicles();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function deactivateVehicle() {
        if (!vehiculoPendiente) return undefined;
        return changeStatus('DELETE', vehiculoPendiente, () => {
            dialogs.close(elements.deactivateModal);
            vehiculoPendiente = null;
        });
    }

    function reactivateVehicle(vehiculo) { return changeStatus('PATCH', vehiculo); }

    function closeForm() { if (!submit.busy) { dialogs.close(elements.modal); errores.clearErrors(); } }
    function closeDeactivation() {
        if (statusChange.busy) return;
        dialogs.close(elements.deactivateModal);
        vehiculoPendiente = null;
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => listVehicles({ page: 1 }), 300);
    }

    // --- cableado -----------------------------------------------------------------
    elements.create.addEventListener('click', openCreateForm);
    elements.refresh.addEventListener('click', () => listVehicles());
    elements.retry.addEventListener('click', () => listVehicles());
    elements.previous.addEventListener('click', () => {
        if (state.page > 1) listVehicles({ page: state.page - 1 });
    });
    elements.next.addEventListener('click', () => listVehicles({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => listVehicles({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', saveVehicle);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.form.addEventListener('change', errores.clearControlError);
    elements.close.addEventListener('click', closeForm);
    elements.cancel.addEventListener('click', closeForm);
    elements.cancelDeactivate.addEventListener('click', closeDeactivation);
    elements.confirmDeactivate.addEventListener('click', deactivateVehicle);
    elements.body.addEventListener('click', handleTableAction);
    elements.closeDetail.addEventListener('click', closeDetail);
    elements.closeDetailSecondary.addEventListener('click', closeDetail);
    elements.editFromDetail.addEventListener('click', editFromDetail);
    [elements.modal, elements.deactivateModal, elements.detailModal].forEach((dialog) => {
        dialog.addEventListener('click', dialogs.handleBackdropClick);
        dialog.addEventListener('close', dialogs.restoreFocus);
    });

    listVehicles();
}

// Solo arranca en el navegador: asi las pruebas pueden importar este modulo
// para comprobar el payload sin necesitar un DOM.
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
