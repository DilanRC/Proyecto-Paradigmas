(() => {
    'use strict';

    const API_URL = 'api/vehiculos.php';
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-vehiculos'), empty: $('#estado-vacio'), loading: $('#estado-carga'), panel: $('#panel-vehiculos'), total: $('#total-vehiculos'),
        search: $('#busqueda-vehiculo'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-vehiculo'), modal: $('#modal-vehiculo'),
        form: $('#formulario-vehiculo'), modalTitle: $('#titulo-modal'), modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'), save: $('#guardar-vehiculo'),
        deactivateModal: $('#modal-desactivar'), deactivateMessage: $('#mensaje-desactivar'),
        cancelDeactivate: $('#cancelar-desactivacion'), confirmDeactivate: $('#confirmar-desactivacion'), notification: $('#notificacion'),
        detailModal: $('#modal-detalle'), detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'), closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'), editFromDetail: $('#editar-desde-detalle'),
    };
    const vehiculos = new Map();
    let vehiculoPendiente = null;
    let vehiculoDetalle = null;
    let searchTimer = 0;
    let notificationTimer = 0;
    let listController = null;
    let listSequence = 0;
    let saving = false;
    let changingStatus = false;
    let focusReturn = null;
    let currentPage = 1;
    const pageSize = 25;

    document.addEventListener('DOMContentLoaded', initialize);

    function initialize() {
        elements.create.addEventListener('click', openCreateForm);
        elements.refresh.addEventListener('click', listVehicles);
        elements.previous.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; listVehicles(); } });
        elements.next.addEventListener('click', () => { currentPage += 1; listVehicles(); });
        elements.status.addEventListener('change', () => { currentPage = 1; listVehicles(); });
        elements.search.addEventListener('input', scheduleSearch);
        elements.form.addEventListener('submit', saveVehicle);
        elements.form.addEventListener('invalid', markNativeError, true);
        elements.form.addEventListener('input', clearControlError);
        elements.form.addEventListener('change', clearControlError);
        elements.close.addEventListener('click', closeForm);
        elements.cancel.addEventListener('click', closeForm);
        elements.cancelDeactivate.addEventListener('click', closeDeactivation);
        elements.confirmDeactivate.addEventListener('click', deactivateVehicle);
        elements.body.addEventListener('click', handleTableAction);
        elements.modal.addEventListener('click', closeOnBackdropClick);
        elements.deactivateModal.addEventListener('click', closeOnBackdropClick);
        elements.detailModal.addEventListener('click', closeOnBackdropClick);
        elements.modal.addEventListener('close', restoreFocus);
        elements.deactivateModal.addEventListener('close', restoreFocus);
        elements.detailModal.addEventListener('close', restoreFocus);
        elements.closeDetail.addEventListener('click', closeDetail);
        elements.closeDetailSecondary.addEventListener('click', closeDetail);
        elements.editFromDetail.addEventListener('click', editFromDetail);
        listVehicles();
    }

    async function listVehicles() {
        const sequence = ++listSequence;
        listController?.abort();
        listController = new AbortController();
        setLoading(true);
        const parameters = new URLSearchParams({ pagina: String(currentPage), tamanoPagina: String(pageSize) });
        if (elements.search.value.trim()) parameters.set('q', elements.search.value.trim());
        if (elements.status.value !== 'TODOS') parameters.set('estado', elements.status.value);
        try {
            const response = await request(`${API_URL}?${parameters}`, { signal: listController.signal });
            if (sequence !== listSequence) return;
            const list = Array.isArray(response.data?.vehiculos) ? response.data.vehiculos : [];
            vehiculos.clear();
            list.forEach((vehiculo) => vehiculos.set(String(vehiculo.vehiculoId), vehiculo));
            currentPage = Number(response.data?.pagina) || currentPage;
            renderVehicles(list, Number(response.data?.total) || 0, Number(response.data?.tamanoPagina) || pageSize);
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== listSequence) return;
            renderVehicles([], 0, pageSize);
            showNotification(error.message, 'error');
        } finally {
            if (sequence === listSequence) setLoading(false);
        }
    }

    function renderVehicles(list, total, size) {
        elements.body.replaceChildren();
        elements.empty.hidden = list.length > 0;
        elements.total.textContent = total === 1 ? '1 vehículo encontrado' : `${total} vehículos encontrados`;
        const totalPages = Math.max(1, Math.ceil(total / size));
        elements.page.textContent = `Página ${currentPage} de ${totalPages}`;
        elements.previous.disabled = currentPage <= 1;
        elements.next.disabled = currentPage >= totalPages;
        const fragment = document.createDocumentFragment();
        list.forEach((vehiculo) => fragment.appendChild(createRow(vehiculo)));
        elements.body.appendChild(fragment);
    }

    function createRow(vehiculo) {
        const row = document.createElement('tr');
        row.dataset.id = String(vehiculo.vehiculoId);
        const placaCell = createCell('Placa'); placaCell.textContent = vehiculo.placa || 'Sin placa';
        const vinCell = createCell('VIN'); vinCell.textContent = vehiculo.vin || 'Sin VIN';
        const modeloCell = createCell('Modelo'); modeloCell.textContent = vehiculo.modelo || 'Sin modelo';
        const statusCell = createCell('Estado');
        const active = vehiculo.estado === 'ACTIVO';
        const badge = document.createElement('span'); badge.className = `badge badge--${active ? 'active' : 'inactive'}`; badge.textContent = active ? 'Activo' : 'Inactivo'; statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones'); actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', vehiculo.vehiculoId));
        if (active) actionsCell.append(createActionButton('editar', 'Editar', vehiculo.vehiculoId), createActionButton('desactivar', 'Desactivar', vehiculo.vehiculoId));
        else actionsCell.append(createActionButton('reactivar', 'Reactivar', vehiculo.vehiculoId));
        row.append(placaCell, vinCell, modeloCell, statusCell, actionsCell);
        return row;
    }

    function createCell(label) { const cell = document.createElement('td'); cell.dataset.label = label; return cell; }
    function createActionButton(action, text, id) { const button = document.createElement('button'); button.type = 'button'; button.className = `action action--${action}`; button.dataset.action = action; button.dataset.id = String(id); button.textContent = text; return button; }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const vehiculo = vehiculos.get(button.dataset.id);
        if (!vehiculo) return showNotification('No se encontró el vehículo seleccionado.', 'error');
        if (button.dataset.action === 'ver') openDetail(vehiculo);
        if (button.dataset.action === 'editar') openEditForm(vehiculo);
        if (button.dataset.action === 'desactivar') openDeactivation(vehiculo);
        if (button.dataset.action === 'reactivar') reactivateVehicle(vehiculo);
    }

    function openDetail(vehiculo) {
        vehiculoDetalle = vehiculo;
        elements.detailTitle.textContent = vehiculo.placa || 'Vehículo';
        const campos = [
            ['Placa', vehiculo.placa || '—'],
            ['VIN', vehiculo.vin || '—'],
            ['Modelo', vehiculo.modelo || '—'],
            ['Estado', vehiculo.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
        ];
        const fragment = document.createDocumentFragment();
        campos.forEach(([etiqueta, valor]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor;
            fragment.append(dt, dd);
        });
        elements.detailContent.replaceChildren(fragment);
        openDialog(elements.detailModal); elements.closeDetail.focus();
    }

    function closeDetail() { if (elements.detailModal.open) elements.detailModal.close(); vehiculoDetalle = null; }
    function editFromDetail() { if (vehiculoDetalle) { const vehiculo = vehiculoDetalle; closeDetail(); openEditForm(vehiculo); } }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear vehículo'; elements.modalSubtitle.textContent = 'Nuevo registro'; elements.save.textContent = 'Guardar vehículo';
        openDialog(elements.modal); $('#placa').focus();
    }

    function openEditForm(vehiculo) {
        resetForm();
        $('#vehiculo-id').value = String(vehiculo.vehiculoId);
        $('#placa').value = vehiculo.placa ?? '';
        $('#vin').value = vehiculo.vin ?? '';
        $('#modelo').value = vehiculo.modelo ?? '';
        elements.modalTitle.textContent = 'Editar vehículo'; elements.modalSubtitle.textContent = 'Actualizar registro'; elements.save.textContent = 'Guardar cambios';
        openDialog(elements.modal); $('#placa').focus();
    }

    function resetForm() {
        elements.form.reset(); clearErrors();
        $('#vehiculo-id').value = '';
    }

    async function saveVehicle(event) {
        event.preventDefault();
        if (saving) return;
        clearErrors();
        if (!elements.form.checkValidity()) { markFirstInvalid(); return; }
        const id = $('#vehiculo-id').value;
        const editing = id !== '';
        const data = {
            placa: $('#placa').value.trim(),
            vin: $('#vin').value.trim(),
            modelo: $('#modelo').value.trim(),
        };
        if (editing) data.vehiculoId = Number(id);
        setSaving(true);
        try {
            const response = await request(API_URL, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(data) });
            elements.modal.close(); showNotification(response.message, 'success'); await listVehicles();
        } catch (error) {
            if (error.errors) showErrors(error.errors);
            showNotification(error.message, 'error');
        } finally { setSaving(false); }
    }

    function openDeactivation(vehiculo) {
        vehiculoPendiente = vehiculo;
        elements.deactivateMessage.textContent = `El vehículo ${vehiculo.placa} dejará de estar disponible para asignaciones.`;
        openDialog(elements.deactivateModal); elements.confirmDeactivate.focus();
    }
    async function deactivateVehicle() { if (vehiculoPendiente && !changingStatus) await changeStatus('DELETE', vehiculoPendiente, elements.confirmDeactivate, () => { elements.deactivateModal.close(); vehiculoPendiente = null; }); }
    async function reactivateVehicle(vehiculo) { if (!changingStatus) await changeStatus('PATCH', vehiculo, elements.body.querySelector(`[data-action="reactivar"][data-id="${CSS.escape(String(vehiculo.vehiculoId))}"]`)); }

    async function changeStatus(method, vehiculo, button, afterSuccess = null) {
        const busyContainer = button?.closest('dialog') || elements.panel;
        changingStatus = true;
        document.querySelectorAll('[data-action], #confirmar-desactivacion').forEach((control) => { control.disabled = true; });
        busyContainer.setAttribute('aria-busy', 'true');
        try {
            const response = await request(API_URL, { method, body: JSON.stringify({ vehiculoId: vehiculo.vehiculoId }) });
            afterSuccess?.(); showNotification(response.message, 'success'); await listVehicles();
        } catch (error) { showNotification(error.message, 'error'); }
        finally {
            changingStatus = false; busyContainer.setAttribute('aria-busy', 'false');
            document.querySelectorAll('[data-action], #confirmar-desactivacion').forEach((control) => { control.disabled = false; });
        }
    }

    async function request(url, options = {}) {
        const httpResponse = await fetch(url, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers ?? {}) } });
        let response;
        try { response = await httpResponse.json(); } catch { throw new Error('El servidor no devolvió JSON válido.'); }
        if (!httpResponse.ok || response.success !== true) {
            const error = new Error(response.message || 'No se pudo completar la operación.'); error.errors = response.errors ?? null; error.data = response.data ?? null; error.status = httpResponse.status; throw error;
        }
        return response;
    }

    function showErrors(errors) {
        let first = null;
        Object.entries(errors).forEach(([field, message]) => {
            const control = elements.form.elements.namedItem(field);
            const container = elements.form.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
            if (control instanceof HTMLElement) { control.setAttribute('aria-invalid', 'true'); first ??= control; }
            if (container) container.textContent = String(message);
        });
        first?.focus();
    }
    function markNativeError(event) { event.target.setAttribute('aria-invalid', 'true'); }
    function markFirstInvalid() { const first = elements.form.querySelector(':invalid'); if (first) { first.setAttribute('aria-invalid', 'true'); first.focus(); } }
    function clearControlError(event) { const control = event.target; if (!control.name) return; control.removeAttribute('aria-invalid'); const container = elements.form.querySelector(`[data-error-for="${CSS.escape(control.name)}"]`); if (container) container.textContent = ''; }
    function clearErrors() { elements.form.querySelectorAll('[aria-invalid]').forEach((control) => control.removeAttribute('aria-invalid')); elements.form.querySelectorAll('[data-error-for]').forEach((container) => { container.textContent = ''; }); }
    function setSaving(value) { saving = value; elements.form.setAttribute('aria-busy', String(value)); elements.form.querySelectorAll('button, input, select, textarea').forEach((control) => { control.disabled = value; }); if (value) { elements.save.dataset.label = elements.save.textContent; elements.save.textContent = 'Guardando…'; } else { elements.save.textContent = elements.save.dataset.label || elements.save.textContent; delete elements.save.dataset.label; } }
    function setLoading(value) { elements.loading.hidden = !value; elements.panel.setAttribute('aria-busy', String(value)); elements.refresh.disabled = value; }
    function scheduleSearch() { currentPage = 1; window.clearTimeout(searchTimer); searchTimer = window.setTimeout(listVehicles, 300); }
    function showNotification(message, type) { window.clearTimeout(notificationTimer); elements.notification.textContent = message; elements.notification.className = `notification notification--${type}`; elements.notification.setAttribute('role', type === 'error' ? 'alert' : 'status'); elements.notification.hidden = false; if (type !== 'error') notificationTimer = window.setTimeout(() => { elements.notification.hidden = true; }, 4500); }
    function closeForm() { if (!saving) { if (elements.modal.open) elements.modal.close(); clearErrors(); } }
    function closeDeactivation() { if (!changingStatus) { if (elements.deactivateModal.open) elements.deactivateModal.close(); vehiculoPendiente = null; } }
    function closeOnBackdropClick(event) { if (event.target === event.currentTarget && !saving && !changingStatus) event.currentTarget.close(); }
    function openDialog(dialog) { focusReturn = document.activeElement; if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', ''); }
    function restoreFocus() { if (focusReturn instanceof HTMLElement && focusReturn.isConnected) focusReturn.focus(); focusReturn = null; }
})();
