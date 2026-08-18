(() => {
    'use strict';

    const API_URL = 'api/transportistas.php';
    const VEHICULOS_URL = 'api/vehiculos.php';
    const ASIGNACION_URL = 'api/transportistas-vehiculos.php';
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-transportistas'), empty: $('#estado-vacio'), loading: $('#estado-carga'), panel: $('#panel-transportistas'), total: $('#total-transportistas'),
        search: $('#busqueda-transportista'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-transportista'), modal: $('#modal-transportista'),
        form: $('#formulario-transportista'), modalTitle: $('#titulo-modal'), modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'), save: $('#guardar-transportista'),
        reactivateExisting: $('#reactivar-existente'), types: $('#identificacion-tipo'), deactivateModal: $('#modal-desactivar'), deactivateMessage: $('#mensaje-desactivar'),
        cancelDeactivate: $('#cancelar-desactivacion'), confirmDeactivate: $('#confirmar-desactivacion'), notification: $('#notificacion'),
        detailModal: $('#modal-detalle'), detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'), closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'), editFromDetail: $('#editar-desde-detalle'),
        openAssign: $('#abrir-asignar-vehiculo'), assignModal: $('#modal-asignar-vehiculo'), assignForm: $('#formulario-asignar-vehiculo'), assignSelect: $('#asignar-vehiculo-select'), closeAssign: $('#cerrar-asignar'), cancelAssign: $('#cancelar-asignacion'),
    };
    const transportistas = new Map();
    let tiposIdentificacion = [];
    let transportistaPendiente = null;
    let transportistaDetalle = null;
    let searchTimer = 0;
    let notificationTimer = 0;
    let listController = null;
    let listSequence = 0;
    let saving = false;
    let changingStatus = false;
    let assigning = false;
    let focusReturn = null;
    let currentPage = 1;
    const pageSize = 25;

    document.addEventListener('DOMContentLoaded', initialize);

    function initialize() {
        elements.create.addEventListener('click', openCreateForm);
        elements.refresh.addEventListener('click', listCarriers);
        elements.previous.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; listCarriers(); } });
        elements.next.addEventListener('click', () => { currentPage += 1; listCarriers(); });
        elements.status.addEventListener('change', () => { currentPage = 1; listCarriers(); });
        elements.search.addEventListener('input', scheduleSearch);
        elements.form.addEventListener('submit', saveCarrier);
        elements.form.addEventListener('invalid', markNativeError, true);
        elements.form.addEventListener('input', clearControlError);
        elements.form.addEventListener('change', clearControlError);
        elements.types.addEventListener('change', updateIdentificationInputMode);
        elements.reactivateExisting.addEventListener('click', reactivateExistingCarrier);
        elements.close.addEventListener('click', closeForm);
        elements.cancel.addEventListener('click', closeForm);
        elements.cancelDeactivate.addEventListener('click', closeDeactivation);
        elements.confirmDeactivate.addEventListener('click', deactivateCarrier);
        elements.body.addEventListener('click', handleTableAction);
        elements.modal.addEventListener('click', closeOnBackdropClick);
        elements.deactivateModal.addEventListener('click', closeOnBackdropClick);
        elements.detailModal.addEventListener('click', closeOnBackdropClick);
        elements.assignModal.addEventListener('click', closeOnBackdropClick);
        elements.modal.addEventListener('close', restoreFocus);
        elements.deactivateModal.addEventListener('close', restoreFocus);
        elements.detailModal.addEventListener('close', restoreFocus);
        elements.assignModal.addEventListener('close', restoreFocus);
        elements.closeDetail.addEventListener('click', closeDetail);
        elements.closeDetailSecondary.addEventListener('click', closeDetail);
        elements.editFromDetail.addEventListener('click', editFromDetail);
        elements.openAssign.addEventListener('click', openAssignDialog);
        elements.closeAssign.addEventListener('click', closeAssignDialog);
        elements.cancelAssign.addEventListener('click', closeAssignDialog);
        elements.assignForm.addEventListener('submit', confirmAssignment);
        elements.detailContent.addEventListener('click', handleUnassignClick);
        listCarriers();
    }

    async function listCarriers() {
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
            tiposIdentificacion = Array.isArray(response.data?.catalogos?.tiposIdentificacion) ? response.data.catalogos.tiposIdentificacion : [];
            const list = Array.isArray(response.data?.transportistas) ? response.data.transportistas : [];
            transportistas.clear();
            list.forEach((carrier) => transportistas.set(carrier.identificacionNumero, carrier));
            currentPage = Number(response.data?.pagina) || currentPage;
            renderCarriers(list, Number(response.data?.total) || 0, Number(response.data?.tamanoPagina) || pageSize);
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== listSequence) return;
            renderCarriers([], 0, pageSize);
            showNotification(error.message, 'error');
        } finally {
            if (sequence === listSequence) setLoading(false);
        }
    }

    function renderCarriers(list, total, size) {
        elements.body.replaceChildren();
        elements.empty.hidden = list.length > 0;
        elements.total.textContent = total === 1 ? '1 transportista encontrado' : `${total} transportistas encontrados`;
        const totalPages = Math.max(1, Math.ceil(total / size));
        elements.page.textContent = `Página ${currentPage} de ${totalPages}`;
        elements.previous.disabled = currentPage <= 1;
        elements.next.disabled = currentPage >= totalPages;
        const fragment = document.createDocumentFragment();
        list.forEach((carrier) => fragment.appendChild(createRow(carrier)));
        elements.body.appendChild(fragment);
    }

    function createRow(carrier) {
        const row = document.createElement('tr');
        row.dataset.id = carrier.identificacionNumero;
        const carrierCell = createCell('Transportista');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span'); avatar.className = 'avatar'; avatar.textContent = getInitials(carrier.nombre);
        const details = document.createElement('span');
        const name = document.createElement('strong'); name.textContent = carrier.nombre || 'Sin nombre';
        const email = document.createElement('small'); email.textContent = carrier.correoElectronico || 'Sin correo';
        details.append(name, email); summary.append(avatar, details); carrierCell.appendChild(summary);
        const identificationCell = createCell('Identificación');
        const type = document.createElement('small'); type.className = 'secondary-data'; type.textContent = carrier.identificacion?.tipoCodigo || 'Sin tipo';
        const number = document.createElement('span'); number.textContent = carrier.identificacionNumero;
        identificationCell.append(type, number);
        const contactCell = createCell('Contacto'); contactCell.textContent = carrier.telefono || 'Sin teléfono';
        const vehiclesCell = createCell('Vehículos'); vehiclesCell.textContent = formatVehicles(carrier.vehiculos);
        const statusCell = createCell('Estado');
        const active = carrier.estado === 'ACTIVO';
        const badge = document.createElement('span'); badge.className = `badge badge--${active ? 'active' : 'inactive'}`; badge.textContent = active ? 'Activo' : 'Inactivo'; statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones'); actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', carrier.identificacionNumero));
        if (active) actionsCell.append(createActionButton('editar', 'Editar', carrier.identificacionNumero), createActionButton('desactivar', 'Desactivar', carrier.identificacionNumero));
        else actionsCell.append(createActionButton('reactivar', 'Reactivar', carrier.identificacionNumero));
        row.append(carrierCell, identificationCell, contactCell, vehiclesCell, statusCell, actionsCell);
        return row;
    }

    function createCell(label) { const cell = document.createElement('td'); cell.dataset.label = label; return cell; }
    function createActionButton(action, text, id) { const button = document.createElement('button'); button.type = 'button'; button.className = `action action--${action}`; button.dataset.action = action; button.dataset.id = id; button.textContent = text; return button; }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const carrier = transportistas.get(button.dataset.id);
        if (!carrier) return showNotification('No se encontró el transportista seleccionado.', 'error');
        if (button.dataset.action === 'ver') openDetail(carrier);
        if (button.dataset.action === 'editar') openEditForm(carrier);
        if (button.dataset.action === 'desactivar') openDeactivation(carrier);
        if (button.dataset.action === 'reactivar') reactivateCarrier(carrier);
    }

    function openDetail(carrier) {
        transportistaDetalle = carrier;
        elements.detailTitle.textContent = carrier.nombre || 'Transportista';
        const campos = [
            ['Identificación', `${carrier.identificacion?.tipoCodigo ?? 'Sin tipo'} · ${carrier.identificacionNumero}`],
            ['Estado', carrier.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
            ['Teléfono', carrier.telefono || '—'],
            ['Correo electrónico', carrier.correoElectronico || '—'],
        ];
        const fragment = document.createDocumentFragment();
        campos.forEach(([etiqueta, valor]) => {
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
                const label = document.createElement('span'); label.textContent = `${vehiculo.placa} — ${vehiculo.modelo}`;
                const removeButton = document.createElement('button');
                removeButton.type = 'button'; removeButton.className = 'link-button'; removeButton.dataset.vehiculoId = String(vehiculo.vehiculoId); removeButton.textContent = 'Quitar';
                dd.append(label, document.createTextNode(' — '), removeButton);
                fragment.append(dt, dd);
            });
        }
        elements.detailContent.replaceChildren(fragment);
        openDialog(elements.detailModal); elements.closeDetail.focus();
    }

    function closeDetail() { if (elements.detailModal.open) elements.detailModal.close(); transportistaDetalle = null; }
    function editFromDetail() { if (transportistaDetalle) { const carrier = transportistaDetalle; closeDetail(); openEditForm(carrier); } }

    async function handleUnassignClick(event) {
        const button = event.target.closest('[data-vehiculo-id]');
        if (!button || assigning) return;
        const vehiculoId = Number(button.dataset.vehiculoId);
        assigning = true;
        button.disabled = true;
        try {
            const response = await request(ASIGNACION_URL, { method: 'DELETE', body: JSON.stringify({ vehiculoId }) });
            showNotification(response.message, 'success');
            closeDetail();
            await listCarriers();
        } catch (error) {
            showNotification(error.message, 'error');
            button.disabled = false;
        } finally { assigning = false; }
    }

    function openAssignDialog() {
        if (!transportistaDetalle) return;
        elements.assignSelect.replaceChildren(new Option('Cargando vehículos…', ''));
        openDialog(elements.assignModal);
        loadActiveVehicles();
    }

    async function loadActiveVehicles() {
        try {
            const response = await request(`${VEHICULOS_URL}?${new URLSearchParams({ estado: 'ACTIVO', tamanoPagina: '100' })}`);
            const list = Array.isArray(response.data?.vehiculos) ? response.data.vehiculos : [];
            const fragment = document.createDocumentFragment();
            const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Seleccione un vehículo'; fragment.appendChild(placeholder);
            list.forEach((vehiculo) => {
                const option = document.createElement('option'); option.value = String(vehiculo.vehiculoId); option.textContent = `${vehiculo.placa} — ${vehiculo.modelo}`;
                fragment.appendChild(option);
            });
            elements.assignSelect.replaceChildren(fragment);
            if (list.length === 0) showNotification('No hay vehículos activos disponibles para asignar.', 'error');
        } catch (error) {
            elements.assignSelect.replaceChildren(new Option('No se pudieron cargar los vehículos', ''));
            showNotification(error.message, 'error');
        }
    }

    function closeAssignDialog() { if (!assigning && elements.assignModal.open) elements.assignModal.close(); }

    async function confirmAssignment(event) {
        event.preventDefault();
        if (assigning || !transportistaDetalle) return;
        const vehiculoId = Number(elements.assignSelect.value || 0);
        if (!vehiculoId) { showNotification('Seleccione un vehículo.', 'error'); return; }
        assigning = true;
        elements.assignForm.setAttribute('aria-busy', 'true');
        try {
            const response = await request(ASIGNACION_URL, { method: 'PUT', body: JSON.stringify({ identificacionNumero: transportistaDetalle.identificacionNumero, vehiculoId }) });
            showNotification(response.message, 'success');
            elements.assignModal.close();
            closeDetail();
            await listCarriers();
        } catch (error) {
            showNotification(error.message, 'error');
        } finally { assigning = false; elements.assignForm.setAttribute('aria-busy', 'false'); }
    }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear transportista'; elements.modalSubtitle.textContent = 'Nuevo registro'; elements.save.textContent = 'Guardar transportista';
        openDialog(elements.modal); elements.types.focus();
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
        elements.modalTitle.textContent = 'Editar transportista'; elements.modalSubtitle.textContent = 'Actualizar registro'; elements.save.textContent = 'Guardar cambios';
        openDialog(elements.modal); $('#nombre').focus();
    }

    function resetForm() {
        elements.form.reset(); clearErrors(); renderTypeOptions();
        $('#identificacion-original').value = ''; $('#identificacion-numero').readOnly = false;
        elements.reactivateExisting.hidden = true; delete elements.reactivateExisting.dataset.id;
    }

    function renderTypeOptions() {
        const selected = elements.types.value;
        const fragment = document.createDocumentFragment();
        const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Seleccione un tipo'; fragment.appendChild(placeholder);
        tiposIdentificacion.forEach((type) => { const option = document.createElement('option'); option.value = type.codigo; option.textContent = type.nombre; fragment.appendChild(option); });
        elements.types.replaceChildren(fragment); elements.types.value = selected; updateIdentificationInputMode();
    }

    async function saveCarrier(event) {
        event.preventDefault();
        if (saving) return;
        clearErrors();
        if (!elements.form.checkValidity()) { markFirstInvalid(); return; }
        const original = $('#identificacion-original').value;
        const data = {
            identificacion: { tipoCodigo: elements.types.value, numero: $('#identificacion-numero').value.trim() },
            nombre: $('#nombre').value.trim(), telefono: $('#telefono').value.trim(), correoElectronico: $('#correo-electronico').value.trim().toLowerCase(),
        };
        const editing = original !== '';
        if (editing) data.identificacionNumeroOriginal = original;
        setSaving(true);
        try {
            const response = await request(API_URL, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(data) });
            elements.modal.close(); showNotification(response.message, 'success'); await listCarriers();
        } catch (error) {
            if (error.errors) showErrors(error.errors);
            if (!editing && error.status === 409) offerReactivation(error);
            showNotification(error.message, 'error');
        } finally { setSaving(false); }
    }

    function openDeactivation(carrier) {
        transportistaPendiente = carrier;
        elements.deactivateMessage.textContent = `${carrier.nombre} quedará inactivo. Sus vehículos asignados y su bitácora se conservarán.`;
        openDialog(elements.deactivateModal); elements.confirmDeactivate.focus();
    }
    async function deactivateCarrier() { if (transportistaPendiente && !changingStatus) await changeStatus('DELETE', transportistaPendiente, elements.confirmDeactivate, () => { elements.deactivateModal.close(); transportistaPendiente = null; }); }
    async function reactivateCarrier(carrier) { if (!changingStatus) await changeStatus('PATCH', carrier, elements.body.querySelector(`[data-action="reactivar"][data-id="${CSS.escape(carrier.identificacionNumero)}"]`)); }

    function offerReactivation(error) {
        const identificacion = error.data?.reactivacion?.identificacionNumero;
        if (!identificacion) return;
        elements.reactivateExisting.dataset.id = identificacion;
        elements.reactivateExisting.hidden = false;
        elements.reactivateExisting.focus();
    }
    async function reactivateExistingCarrier() {
        const identificacionNumero = elements.reactivateExisting.dataset.id || '';
        if (identificacionNumero && !changingStatus) await changeStatus('PATCH', { identificacionNumero }, elements.reactivateExisting, () => elements.modal.close());
    }

    async function changeStatus(method, carrier, button, afterSuccess = null) {
        const busyContainer = button?.closest('dialog') || elements.panel;
        changingStatus = true;
        document.querySelectorAll('[data-action], #confirmar-desactivacion, #reactivar-existente').forEach((control) => { control.disabled = true; });
        busyContainer.setAttribute('aria-busy', 'true');
        try {
            const response = await request(API_URL, { method, body: JSON.stringify({ identificacionNumero: carrier.identificacionNumero }) });
            afterSuccess?.(); showNotification(response.message, 'success'); await listCarriers();
        } catch (error) { showNotification(error.message, 'error'); }
        finally {
            changingStatus = false; busyContainer.setAttribute('aria-busy', 'false');
            document.querySelectorAll('[data-action], #confirmar-desactivacion, #reactivar-existente').forEach((control) => { control.disabled = false; });
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
    function scheduleSearch() { currentPage = 1; window.clearTimeout(searchTimer); searchTimer = window.setTimeout(listCarriers, 300); }
    function showNotification(message, type) { window.clearTimeout(notificationTimer); elements.notification.textContent = message; elements.notification.className = `notification notification--${type}`; elements.notification.setAttribute('role', type === 'error' ? 'alert' : 'status'); elements.notification.hidden = false; if (type !== 'error') notificationTimer = window.setTimeout(() => { elements.notification.hidden = true; }, 4500); }
    function closeForm() { if (!saving) { if (elements.modal.open) elements.modal.close(); clearErrors(); } }
    function closeDeactivation() { if (!changingStatus) { if (elements.deactivateModal.open) elements.deactivateModal.close(); transportistaPendiente = null; } }
    function closeOnBackdropClick(event) { if (event.target === event.currentTarget && !saving && !changingStatus && !assigning) event.currentTarget.close(); }
    function openDialog(dialog) { focusReturn = document.activeElement; if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', ''); }
    function restoreFocus() { if (focusReturn instanceof HTMLElement && focusReturn.isConnected) focusReturn.focus(); focusReturn = null; }
    function updateIdentificationInputMode() { $('#identificacion-numero').inputMode = ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'].includes(elements.types.value) ? 'numeric' : 'text'; }
    function getInitials(name = '') { return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'T'; }
    function formatVehicles(vehiculos) { return Array.isArray(vehiculos) && vehiculos.length ? vehiculos.map((vehiculo) => vehiculo.placa).join(', ') : 'Sin vehículos'; }
})();
