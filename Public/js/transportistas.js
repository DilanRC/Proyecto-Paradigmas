import { request } from './shared/api.js';
import { createDialogController } from './shared/dialog.js';
import { aplicarRestriccionIdentificacion } from './shared/identificacion.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/transportistas.php';
const VEHICULOS_URL = 'api/vehiculos.php';
const ASIGNACION_URL = 'api/transportistas-vehiculos.php';
const ETIQUETAS = { singular: 'transportista', plural: 'transportistas' };

/** Cuerpo enviado a la API. Exportado para la prueba de paridad de contrato. */
export function buildTransportistaPayload({
    tipoCodigo, numero, nombre, telefono, correoElectronico, identificacionNumeroOriginal = '',
}) {
    const data = {
        identificacion: { tipoCodigo, numero: numero.trim() },
        nombre: nombre.trim(),
        telefono: telefono.trim(),
        correoElectronico: correoElectronico.trim().toLowerCase(),
    };
    if (identificacionNumeroOriginal !== '') {
        data.identificacionNumeroOriginal = identificacionNumeroOriginal;
    }
    return data;
}

/** Iniciales del transportista; la letra de reserva es propia de este panel. */
function getInitials(name = '') {
    return name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase()).join('') || 'T';
}

function formatVehicles(vehiculos) {
    return Array.isArray(vehiculos) && vehiculos.length
        ? vehiculos.map((vehiculo) => vehiculo.placa).join(', ')
        : 'Sin vehículos';
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-transportistas'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-transportistas'), total: $('#total-transportistas'),
        search: $('#busqueda-transportista'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'),
        previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        create: $('#crear-transportista'), modal: $('#modal-transportista'),
        form: $('#formulario-transportista'), modalTitle: $('#titulo-modal'),
        modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'),
        save: $('#guardar-transportista'), reactivateExisting: $('#reactivar-existente'),
        types: $('#identificacion-tipo'), idHint: $('#ayuda-identificacion-numero'), deactivateModal: $('#modal-desactivar'),
        deactivateMessage: $('#mensaje-desactivar'), cancelDeactivate: $('#cancelar-desactivacion'),
        confirmDeactivate: $('#confirmar-desactivacion'), detailModal: $('#modal-detalle'),
        detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'),
        closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'),
        editFromDetail: $('#editar-desde-detalle'), openAssign: $('#abrir-asignar-vehiculo'),
        assignModal: $('#modal-asignar-vehiculo'), assignForm: $('#formulario-asignar-vehiculo'),
        assignSelect: $('#asignar-vehiculo-select'), closeAssign: $('#cerrar-asignar'),
        cancelAssign: $('#cancelar-asignacion'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const transportistas = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const assign = createSubmitGuard();
    // D1: este panel vigila ademas la asignacion de vehiculos.
    const dialogs = createDialogController({
        isBusy: () => submit.busy || statusChange.busy || assign.busy,
    });

    let state = createListState({ pageSize: 25 });
    let tiposIdentificacion = [];
    let listController = null;
    let transportistaPendiente = null;
    let transportistaDetalle = null;
    let searchTimer = 0;

    async function listCarriers({ page = state.page } = {}) {
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
            tiposIdentificacion = Array.isArray(response.data?.catalogos?.tiposIdentificacion)
                ? response.data.catalogos.tiposIdentificacion : [];
            const list = Array.isArray(response.data?.transportistas) ? response.data.transportistas : [];
            transportistas.clear();
            list.forEach((carrier) => transportistas.set(carrier.identificacionNumero, carrier));
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
        state.items.forEach((carrier) => fragment.appendChild(createRow(carrier)));
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
        button.dataset.id = id;
        button.textContent = text;
        return button;
    }

    function createRow(carrier) {
        const row = document.createElement('tr');
        row.dataset.id = carrier.identificacionNumero;
        const carrierCell = createCell('Transportista');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span');
        avatar.className = 'avatar';
        avatar.textContent = getInitials(carrier.nombre);
        const details = document.createElement('span');
        const name = document.createElement('strong'); name.textContent = carrier.nombre || 'Sin nombre';
        const email = document.createElement('small');
        email.textContent = carrier.correoElectronico || 'Sin correo';
        details.append(name, email); summary.append(avatar, details); carrierCell.appendChild(summary);

        const identificationCell = createCell('Identificación');
        const type = document.createElement('small');
        type.className = 'secondary-data';
        type.textContent = carrier.identificacion?.tipoCodigo || 'Sin tipo';
        const number = document.createElement('span');
        number.textContent = carrier.identificacionNumero;
        identificationCell.append(type, number);

        const contactCell = createCell('Contacto');
        contactCell.textContent = carrier.telefono || 'Sin teléfono';
        const vehiclesCell = createCell('Vehículos');
        vehiclesCell.textContent = formatVehicles(carrier.vehiculos);
        const statusCell = createCell('Estado');
        const active = carrier.estado === 'ACTIVO';
        const badge = document.createElement('span');
        badge.className = `badge badge--${active ? 'active' : 'inactive'}`;
        badge.textContent = active ? 'Activo' : 'Inactivo';
        statusCell.appendChild(badge);

        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', carrier.identificacionNumero));
        if (active) {
            actionsCell.append(
                createActionButton('editar', 'Editar', carrier.identificacionNumero),
                createActionButton('desactivar', 'Desactivar', carrier.identificacionNumero),
            );
        } else {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', carrier.identificacionNumero));
        }
        row.append(carrierCell, identificationCell, contactCell, vehiclesCell, statusCell, actionsCell);
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const carrier = transportistas.get(button.dataset.id);
        if (!carrier) return toast.error('No se encontró el transportista seleccionado.');
        if (button.dataset.action === 'ver') openDetail(carrier);
        if (button.dataset.action === 'editar') openEditForm(carrier);
        if (button.dataset.action === 'desactivar') openDeactivation(carrier);
        if (button.dataset.action === 'reactivar') reactivateCarrier(carrier);
        return undefined;
    }

    function openDetail(carrier) {
        transportistaDetalle = carrier;
        elements.detailTitle.textContent = carrier.nombre || 'Transportista';
        const fragment = document.createDocumentFragment();
        [
            ['Identificación', `${carrier.identificacion?.tipoCodigo ?? 'Sin tipo'} · ${carrier.identificacionNumero}`],
            ['Estado', carrier.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
            ['Teléfono', carrier.telefono || '—'],
            ['Correo electrónico', carrier.correoElectronico || '—'],
        ].forEach(([etiqueta, valor]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor;
            fragment.append(dt, dd);
        });

        const vehiculos = Array.isArray(carrier.vehiculos) ? carrier.vehiculos : [];
        if (vehiculos.length === 0) {
            const dt = document.createElement('dt'); dt.textContent = 'Vehículos';
            const dd = document.createElement('dd'); dd.textContent = 'Sin vehículos asignados';
            fragment.append(dt, dd);
        } else {
            vehiculos.forEach((vehiculo) => {
                const dt = document.createElement('dt'); dt.textContent = 'Vehículo';
                const dd = document.createElement('dd');
                const label = document.createElement('span');
                label.textContent = `${vehiculo.placa} — ${vehiculo.modelo}`;
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'link-button';
                removeButton.dataset.vehiculoId = String(vehiculo.vehiculoId);
                removeButton.textContent = 'Quitar';
                removeButton.setAttribute('aria-label', `Quitar el vehículo ${vehiculo.placa}`);
                dd.append(label, document.createTextNode(' — '), removeButton);
                fragment.append(dt, dd);
            });
        }
        elements.detailContent.replaceChildren(fragment);
        dialogs.open(elements.detailModal, { focus: elements.closeDetail });
    }

    function closeDetail() { dialogs.close(elements.detailModal); transportistaDetalle = null; }

    function editFromDetail() {
        if (!transportistaDetalle) return;
        const carrier = transportistaDetalle;
        closeDetail();
        openEditForm(carrier);
    }

    function handleUnassignClick(event) {
        const button = event.target.closest('[data-vehiculo-id]');
        if (!button) return undefined;
        const vehiculoId = Number(button.dataset.vehiculoId);
        return assign.run(async () => {
            button.disabled = true;
            try {
                const response = await request(ASIGNACION_URL, {
                    method: 'DELETE',
                    body: JSON.stringify({ vehiculoId }),
                });
                toast.success(response.message);
                closeDetail();
                await listCarriers();
            } catch (error) {
                toast.error(error.message);
                button.disabled = false;
            }
        });
    }

    function openAssignDialog() {
        if (!transportistaDetalle) return;
        elements.assignSelect.replaceChildren(new Option('Cargando vehículos…', ''));
        dialogs.open(elements.assignModal, { focus: elements.assignSelect });
        loadActiveVehicles();
    }

    async function loadActiveVehicles() {
        try {
            const response = await request(
                `${VEHICULOS_URL}?${new URLSearchParams({ estado: 'ACTIVO', tamanoPagina: '100' })}`,
            );
            const list = Array.isArray(response.data?.vehiculos) ? response.data.vehiculos : [];
            const fragment = document.createDocumentFragment();
            const placeholder = document.createElement('option');
            placeholder.value = ''; placeholder.textContent = 'Seleccione un vehículo';
            fragment.appendChild(placeholder);
            list.forEach((vehiculo) => {
                const option = document.createElement('option');
                option.value = String(vehiculo.vehiculoId);
                option.textContent = `${vehiculo.placa} — ${vehiculo.modelo}`;
                fragment.appendChild(option);
            });
            elements.assignSelect.replaceChildren(fragment);
            if (list.length === 0) toast.warning('No hay vehículos activos disponibles para asignar.');
        } catch (error) {
            elements.assignSelect.replaceChildren(new Option('No se pudieron cargar los vehículos', ''));
            toast.error(error.message);
        }
    }

    function closeAssignDialog() { if (!assign.busy) dialogs.close(elements.assignModal); }

    function confirmAssignment(event) {
        event.preventDefault();
        if (!transportistaDetalle) return undefined;
        const vehiculoId = Number(elements.assignSelect.value || 0);
        if (!vehiculoId) { toast.warning('Seleccione un vehículo.'); return undefined; }
        return assign.run(async () => {
            elements.assignForm.setAttribute('aria-busy', 'true');
            try {
                const response = await request(ASIGNACION_URL, {
                    method: 'PUT',
                    body: JSON.stringify({
                        identificacionNumero: transportistaDetalle.identificacionNumero,
                        vehiculoId,
                    }),
                });
                toast.success(response.message);
                dialogs.close(elements.assignModal);
                closeDetail();
                await listCarriers();
            } catch (error) {
                toast.error(error.message);
            } finally {
                elements.assignForm.setAttribute('aria-busy', 'false');
            }
        });
    }

    function renderTypeOptions() {
        const selected = elements.types.value;
        const fragment = document.createDocumentFragment();
        const placeholder = document.createElement('option');
        placeholder.value = ''; placeholder.textContent = 'Seleccione un tipo';
        fragment.appendChild(placeholder);
        tiposIdentificacion.forEach((type) => {
            const option = document.createElement('option');
            option.value = type.codigo; option.textContent = type.nombre;
            fragment.appendChild(option);
        });
        elements.types.replaceChildren(fragment);
        elements.types.value = selected;
        updateIdentificationInputMode();
    }

    /**
     * El numero admite caracteres distintos segun el tipo, con los mismos
     * patrones que valida el backend. Al cambiar de tipo se limpia un error
     * previo: la regla cambio y el mensaje anterior ya no describe nada.
     */
    function updateIdentificationInputMode() {
        aplicarRestriccionIdentificacion(
            $('#identificacion-numero'), elements.types.value, { hint: elements.idHint },
        );
        errores.clearControlError({ target: $('#identificacion-numero') });
    }

    function resetForm() {
        elements.form.reset();
        errores.clearErrors();
        renderTypeOptions();
        $('#identificacion-original').value = '';
        $('#identificacion-numero').readOnly = false;
        elements.reactivateExisting.hidden = true;
        delete elements.reactivateExisting.dataset.id;
    }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear transportista';
        elements.modalSubtitle.textContent = 'Nuevo registro';
        elements.save.textContent = 'Guardar transportista';
        dialogs.open(elements.modal, { focus: elements.types });
    }

    function openEditForm(carrier) {
        resetForm();
        $('#identificacion-original').value = carrier.identificacionNumero;
        elements.types.value = carrier.identificacion?.tipoCodigo ?? '';
        $('#identificacion-numero').value = carrier.identificacionNumero;
        $('#identificacion-numero').readOnly = true;
        $('#nombre').value = carrier.nombre ?? '';
        $('#telefono').value = carrier.telefono ?? '';
        $('#correo-electronico').value = carrier.correoElectronico ?? '';
        elements.modalTitle.textContent = 'Editar transportista';
        elements.modalSubtitle.textContent = 'Actualizar registro';
        elements.save.textContent = 'Guardar cambios';
        dialogs.open(elements.modal, { focus: $('#nombre') });
    }

    function saveCarrier(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            const original = $('#identificacion-original').value;
            const editing = original !== '';
            const data = buildTransportistaPayload({
                tipoCodigo: elements.types.value,
                numero: $('#identificacion-numero').value,
                nombre: $('#nombre').value,
                telefono: $('#telefono').value,
                correoElectronico: $('#correo-electronico').value,
                identificacionNumeroOriginal: original,
            });
            setSaving(elements.form, true, { submitButton: elements.save });
            try {
                const response = await request(API_URL, {
                    method: editing ? 'PUT' : 'POST',
                    body: JSON.stringify(data),
                });
                dialogs.close(elements.modal);
                toast.success(response.message);
                await listCarriers();
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                if (!editing && error.status === 409) offerReactivation(error);
                toast.error(error.message);
            } finally {
                setSaving(elements.form, false, { submitButton: elements.save });
            }
        });
    }

    function offerReactivation(error) {
        const identificacion = error.data?.reactivacion?.identificacionNumero;
        if (!identificacion) return;
        elements.reactivateExisting.dataset.id = identificacion;
        elements.reactivateExisting.hidden = false;
        elements.reactivateExisting.focus();
    }

    function reactivateExistingCarrier() {
        const identificacionNumero = elements.reactivateExisting.dataset.id || '';
        if (!identificacionNumero) return undefined;
        return changeStatus('PATCH', { identificacionNumero }, () => dialogs.close(elements.modal));
    }

    function openDeactivation(carrier) {
        transportistaPendiente = carrier;
        elements.deactivateMessage.textContent =
            `${carrier.nombre} quedará inactivo. Sus vehículos asignados y su bitácora se conservarán.`;
        dialogs.open(elements.deactivateModal, { focus: elements.confirmDeactivate });
    }

    function changeStatus(method, carrier, afterSuccess = null) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll(
                '[data-action], #confirmar-desactivacion, #reactivar-existente',
            );
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await request(API_URL, {
                    method,
                    body: JSON.stringify({ identificacionNumero: carrier.identificacionNumero }),
                });
                afterSuccess?.();
                toast.success(response.message);
                await listCarriers();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function deactivateCarrier() {
        if (!transportistaPendiente) return undefined;
        return changeStatus('DELETE', transportistaPendiente, () => {
            dialogs.close(elements.deactivateModal);
            transportistaPendiente = null;
        });
    }

    function reactivateCarrier(carrier) { return changeStatus('PATCH', carrier); }

    function closeForm() { if (!submit.busy) { dialogs.close(elements.modal); errores.clearErrors(); } }
    function closeDeactivation() {
        if (statusChange.busy) return;
        dialogs.close(elements.deactivateModal);
        transportistaPendiente = null;
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => listCarriers({ page: 1 }), 300);
    }

    elements.create.addEventListener('click', openCreateForm);
    elements.refresh.addEventListener('click', () => listCarriers());
    elements.retry.addEventListener('click', () => listCarriers());
    elements.previous.addEventListener('click', () => {
        if (state.page > 1) listCarriers({ page: state.page - 1 });
    });
    elements.next.addEventListener('click', () => listCarriers({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => listCarriers({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', saveCarrier);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.form.addEventListener('change', errores.clearControlError);
    elements.types.addEventListener('change', updateIdentificationInputMode);
    elements.reactivateExisting.addEventListener('click', reactivateExistingCarrier);
    elements.close.addEventListener('click', closeForm);
    elements.cancel.addEventListener('click', closeForm);
    elements.cancelDeactivate.addEventListener('click', closeDeactivation);
    elements.confirmDeactivate.addEventListener('click', deactivateCarrier);
    elements.body.addEventListener('click', handleTableAction);
    elements.closeDetail.addEventListener('click', closeDetail);
    elements.closeDetailSecondary.addEventListener('click', closeDetail);
    elements.editFromDetail.addEventListener('click', editFromDetail);
    elements.detailContent.addEventListener('click', handleUnassignClick);
    elements.openAssign.addEventListener('click', openAssignDialog);
    elements.closeAssign.addEventListener('click', closeAssignDialog);
    elements.cancelAssign.addEventListener('click', closeAssignDialog);
    elements.assignForm.addEventListener('submit', confirmAssignment);
    [elements.modal, elements.deactivateModal, elements.detailModal, elements.assignModal]
        .forEach((dialog) => {
            dialog.addEventListener('click', dialogs.handleBackdropClick);
            dialog.addEventListener('close', dialogs.restoreFocus);
        });

    listCarriers();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
