(() => {
    'use strict';

    const API_URL = 'api/productores.php';
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-productores'), empty: $('#estado-vacio'), loading: $('#estado-carga'), panel: $('#panel-productores'), total: $('#total-productores'),
        search: $('#busqueda-productor'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-productor'), modal: $('#modal-productor'),
        form: $('#formulario-productor'), modalTitle: $('#titulo-modal'), modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'), save: $('#guardar-productor'),
        reactivateExisting: $('#reactivar-existente'), types: $('#identificacion-tipo'), farms: $('#fincas-nombres'), deactivateModal: $('#modal-desactivar'), deactivateMessage: $('#mensaje-desactivar'),
        cancelDeactivate: $('#cancelar-desactivacion'), confirmDeactivate: $('#confirmar-desactivacion'), notification: $('#notificacion'),
        detailModal: $('#modal-detalle'), detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'), closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'), editFromDetail: $('#editar-desde-detalle'),
    };
    const productores = new Map();
    let tiposIdentificacion = [];
    let productorPendiente = null;
    let productorDetalle = null;
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
        elements.refresh.addEventListener('click', listProducers);
        elements.previous.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; listProducers(); } });
        elements.next.addEventListener('click', () => { currentPage += 1; listProducers(); });
        elements.status.addEventListener('change', () => { currentPage = 1; listProducers(); });
        elements.search.addEventListener('input', scheduleSearch);
        elements.form.addEventListener('submit', saveProducer);
        elements.form.addEventListener('invalid', markNativeError, true);
        elements.form.addEventListener('input', clearControlError);
        elements.form.addEventListener('change', clearControlError);
        elements.types.addEventListener('change', updateIdentificationInputMode);
        elements.reactivateExisting.addEventListener('click', reactivateExistingProducer);
        elements.close.addEventListener('click', closeForm);
        elements.cancel.addEventListener('click', closeForm);
        elements.cancelDeactivate.addEventListener('click', closeDeactivation);
        elements.confirmDeactivate.addEventListener('click', deactivateProducer);
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
        listProducers();
    }

    async function listProducers() {
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
            const list = Array.isArray(response.data?.productores) ? response.data.productores : [];
            productores.clear();
            list.forEach((producer) => productores.set(producer.identificacionNumero, producer));
            currentPage = Number(response.data?.pagina) || currentPage;
            renderProducers(list, Number(response.data?.total) || 0, Number(response.data?.tamanoPagina) || pageSize);
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== listSequence) return;
            renderProducers([], 0, pageSize);
            showNotification(error.message, 'error');
        } finally {
            if (sequence === listSequence) setLoading(false);
        }
    }

    function renderProducers(list, total, size) {
        elements.body.replaceChildren();
        elements.empty.hidden = list.length > 0;
        elements.total.textContent = total === 1 ? '1 productor encontrado' : `${total} productores encontrados`;
        const totalPages = Math.max(1, Math.ceil(total / size));
        elements.page.textContent = `Página ${currentPage} de ${totalPages}`;
        elements.previous.disabled = currentPage <= 1;
        elements.next.disabled = currentPage >= totalPages;
        const fragment = document.createDocumentFragment();
        list.forEach((producer) => fragment.appendChild(createRow(producer)));
        elements.body.appendChild(fragment);
    }

    function createRow(producer) {
        const row = document.createElement('tr');
        row.dataset.id = producer.identificacionNumero;
        const producerCell = createCell('Productor');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span'); avatar.className = 'avatar'; avatar.textContent = getInitials(producer.nombre);
        const details = document.createElement('span');
        const name = document.createElement('strong'); name.textContent = producer.nombre || 'Sin nombre';
        const email = document.createElement('small'); email.textContent = producer.correoElectronico || 'Sin correo';
        details.append(name, email); summary.append(avatar, details); producerCell.appendChild(summary);
        const identificationCell = createCell('Identificación');
        const type = document.createElement('small'); type.className = 'secondary-data'; type.textContent = producer.identificacion?.tipoCodigo || 'Sin tipo';
        const number = document.createElement('span'); number.textContent = producer.identificacionNumero;
        identificationCell.append(type, number);
        const contactCell = createCell('Contacto'); contactCell.textContent = producer.telefono || 'Sin teléfono';
        const addressCell = createCell('Dirección principal');
        const direccion = producer.direccionPrincipal;
        if (direccion?.provincia && direccion?.canton && direccion?.distrito) addressCell.textContent = formatAddress(direccion);
        else addressCell.appendChild(createActionButton('editar', 'Completar dirección', producer.identificacionNumero));
        const farmCell = createCell('Fincas'); farmCell.textContent = formatFarms(producer.fincas);
        const statusCell = createCell('Estado');
        const active = producer.estado === 'ACTIVO';
        const badge = document.createElement('span'); badge.className = `badge badge--${active ? 'active' : 'inactive'}`; badge.textContent = active ? 'Activo' : 'Inactivo'; statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones'); actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', producer.identificacionNumero));
        if (active) actionsCell.append(createActionButton('editar', 'Editar', producer.identificacionNumero), createActionButton('desactivar', 'Desactivar', producer.identificacionNumero));
        else actionsCell.append(createActionButton('reactivar', 'Reactivar', producer.identificacionNumero));
        row.append(producerCell, identificationCell, contactCell, addressCell, farmCell, statusCell, actionsCell);
        return row;
    }

    function createCell(label) { const cell = document.createElement('td'); cell.dataset.label = label; return cell; }
    function createActionButton(action, text, id) { const button = document.createElement('button'); button.type = 'button'; button.className = `action action--${action}`; button.dataset.action = action; button.dataset.id = id; button.textContent = text; return button; }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const producer = productores.get(button.dataset.id);
        if (!producer) return showNotification('No se encontró el productor seleccionado.', 'error');
        if (button.dataset.action === 'ver') openDetail(producer);
        if (button.dataset.action === 'editar') openEditForm(producer);
        if (button.dataset.action === 'desactivar') openDeactivation(producer);
        if (button.dataset.action === 'reactivar') reactivateProducer(producer);
    }

    function openDetail(producer) {
        productorDetalle = producer;
        elements.detailTitle.textContent = producer.nombre || 'Productor';
        const direccion = producer.direccionPrincipal || {};
        const campos = [
            ['Identificación', `${producer.identificacion?.tipoCodigo ?? 'Sin tipo'} · ${producer.identificacionNumero}`],
            ['Estado', producer.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
            ['Teléfono', producer.telefono || '—'],
            ['Correo electrónico', producer.correoElectronico || '—'],
            ['Provincia', direccion.provincia || '—'],
            ['Cantón', direccion.canton || '—'],
            ['Distrito', direccion.distrito || '—'],
            ['Pueblo', direccion.pueblo || '—'],
            ['Señas', direccion.senas || '—', true],
            ['Fincas', formatFarms(producer.fincas), true],
        ];
        const fragment = document.createDocumentFragment();
        campos.forEach(([etiqueta, valor, completa]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor; if (completa) dd.className = 'detail--full';
            fragment.append(dt, dd);
        });
        elements.detailContent.replaceChildren(fragment);
        openDialog(elements.detailModal); elements.closeDetail.focus();
    }

    function closeDetail() { if (elements.detailModal.open) elements.detailModal.close(); productorDetalle = null; }
    function editFromDetail() { if (productorDetalle) { const producer = productorDetalle; closeDetail(); openEditForm(producer); } }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear productor'; elements.modalSubtitle.textContent = 'Nuevo registro'; elements.save.textContent = 'Guardar productor';
        openDialog(elements.modal); elements.types.focus();
    }

    function openEditForm(producer) {
        resetForm();
        $('#identificacion-original').value = producer.identificacionNumero;
        elements.types.value = producer.identificacion?.tipoCodigo ?? '';
        $('#identificacion-numero').value = producer.identificacionNumero;
        $('#identificacion-numero').readOnly = true;
        $('#nombre').value = producer.nombre ?? '';
        $('#telefono').value = producer.telefono ?? '';
        $('#correo-electronico').value = producer.correoElectronico ?? '';
        $('#direccion-provincia').value = producer.direccionPrincipal?.provincia ?? '';
        $('#direccion-canton').value = producer.direccionPrincipal?.canton ?? '';
        $('#direccion-distrito').value = producer.direccionPrincipal?.distrito ?? '';
        $('#direccion-pueblo').value = producer.direccionPrincipal?.pueblo ?? '';
        $('#direccion-senas').value = producer.direccionPrincipal?.senas ?? '';
        elements.farms.value = (producer.fincas ?? []).map((farm) => farm.nombre).join('\n');
        elements.modalTitle.textContent = 'Editar productor'; elements.modalSubtitle.textContent = 'Actualizar registro'; elements.save.textContent = 'Guardar cambios';
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

    async function saveProducer(event) {
        event.preventDefault();
        if (saving) return;
        clearErrors();
        if (!elements.form.checkValidity()) { markFirstInvalid(); return; }
        const original = $('#identificacion-original').value;
        const data = {
            identificacion: { tipoCodigo: elements.types.value, numero: $('#identificacion-numero').value.trim() },
            nombre: $('#nombre').value.trim(), telefono: $('#telefono').value.trim(), correoElectronico: $('#correo-electronico').value.trim().toLowerCase(),
            direccionPrincipal: { provincia: $('#direccion-provincia').value.trim(), canton: $('#direccion-canton').value.trim(), distrito: $('#direccion-distrito').value.trim(), pueblo: nullableValue($('#direccion-pueblo')), senas: nullableValue($('#direccion-senas')) },
            fincas: elements.farms.value.split(/\r?\n/).map((nombre) => nombre.trim()).filter(Boolean).map((nombre) => ({ nombre })),
        };
        const editing = original !== '';
        if (editing) data.identificacionNumeroOriginal = original;
        setSaving(true);
        try {
            const response = await request(API_URL, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(data) });
            elements.modal.close(); showNotification(response.message, 'success'); await listProducers();
        } catch (error) {
            if (error.errors) showErrors(error.errors);
            if (!editing && error.status === 409) offerReactivation(error);
            showNotification(error.message, 'error');
        } finally { setSaving(false); }
    }

    function openDeactivation(producer) {
        productorPendiente = producer;
        elements.deactivateMessage.textContent = `${producer.nombre} quedará inactivo. Su dirección, fincas y bitácora se conservarán.`;
        openDialog(elements.deactivateModal); elements.confirmDeactivate.focus();
    }
    async function deactivateProducer() { if (productorPendiente && !changingStatus) await changeStatus('DELETE', productorPendiente, elements.confirmDeactivate, () => { elements.deactivateModal.close(); productorPendiente = null; }); }
    async function reactivateProducer(producer) { if (!changingStatus) await changeStatus('PATCH', producer, elements.body.querySelector(`[data-action="reactivar"][data-id="${CSS.escape(producer.identificacionNumero)}"]`)); }

    function offerReactivation(error) {
        const identificacion = error.data?.reactivacion?.identificacionNumero;
        if (!identificacion) return;
        elements.reactivateExisting.dataset.id = identificacion;
        elements.reactivateExisting.hidden = false;
        elements.reactivateExisting.focus();
    }
    async function reactivateExistingProducer() {
        const identificacionNumero = elements.reactivateExisting.dataset.id || '';
        if (identificacionNumero && !changingStatus) await changeStatus('PATCH', { identificacionNumero }, elements.reactivateExisting, () => elements.modal.close());
    }

    async function changeStatus(method, producer, button, afterSuccess = null) {
        const busyContainer = button?.closest('dialog') || elements.panel;
        changingStatus = true;
        document.querySelectorAll('[data-action], #confirmar-desactivacion, #reactivar-existente').forEach((control) => { control.disabled = true; });
        busyContainer.setAttribute('aria-busy', 'true');
        try {
            const response = await request(API_URL, { method, body: JSON.stringify({ identificacionNumero: producer.identificacionNumero }) });
            afterSuccess?.(); showNotification(response.message, 'success'); await listProducers();
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
            const normalized = field.startsWith('fincas.') ? 'fincas' : field;
            const control = elements.form.elements.namedItem(normalized);
            const container = elements.form.querySelector(`[data-error-for="${CSS.escape(normalized)}"]`);
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
    function scheduleSearch() { currentPage = 1; window.clearTimeout(searchTimer); searchTimer = window.setTimeout(listProducers, 300); }
    function showNotification(message, type) { window.clearTimeout(notificationTimer); elements.notification.textContent = message; elements.notification.className = `notification notification--${type}`; elements.notification.setAttribute('role', type === 'error' ? 'alert' : 'status'); elements.notification.hidden = false; if (type !== 'error') notificationTimer = window.setTimeout(() => { elements.notification.hidden = true; }, 4500); }
    function closeForm() { if (!saving) { if (elements.modal.open) elements.modal.close(); clearErrors(); } }
    function closeDeactivation() { if (!changingStatus) { if (elements.deactivateModal.open) elements.deactivateModal.close(); productorPendiente = null; } }
    function closeOnBackdropClick(event) { if (event.target === event.currentTarget && !saving && !changingStatus) event.currentTarget.close(); }
    function openDialog(dialog) { focusReturn = document.activeElement; if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', ''); }
    function restoreFocus() { if (focusReturn instanceof HTMLElement && focusReturn.isConnected) focusReturn.focus(); focusReturn = null; }
    function nullableValue(control) { const value = control.value.trim(); return value === '' ? null : value; }
    function updateIdentificationInputMode() { $('#identificacion-numero').inputMode = ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'].includes(elements.types.value) ? 'numeric' : 'text'; }
    function getInitials(name = '') { return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'P'; }
    function formatAddress(address) { return address ? [address.distrito, address.canton, address.provincia].filter(Boolean).join(', ') : 'No registrada'; }
    function formatFarms(farms) { return Array.isArray(farms) && farms.length ? farms.map((farm) => farm.nombre).join(', ') : 'Sin fincas'; }
})();
