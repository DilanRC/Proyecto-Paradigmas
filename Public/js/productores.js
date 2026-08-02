(() => {
    'use strict';

    const API_URL = 'api/productores.php';
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-productores'), empty: $('#estado-vacio'), loading: $('#estado-carga'), panel: $('#panel-productores'), total: $('#total-productores'),
        search: $('#busqueda-productor'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-productor'), modal: $('#modal-productor'),
        form: $('#formulario-productor'), modalTitle: $('#titulo-modal'), modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'),
        save: $('#guardar-productor'), reactivateExisting: $('#reactivar-existente'), types: $('#identificacion-tipo'), farms: $('#opciones-fincas'), deactivateModal: $('#modal-desactivar'),
        deactivateMessage: $('#mensaje-desactivar'), cancelDeactivate: $('#cancelar-desactivacion'), confirmDeactivate: $('#confirmar-desactivacion'), notification: $('#notificacion'),
    };

    const productores = new Map();
    const catalogos = { tiposIdentificacion: [], fincas: [] };
    let productorPendiente = null;
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
        elements.modal.addEventListener('close', restoreFocus);
        elements.deactivateModal.addEventListener('close', restoreFocus);
        listProducers();
    }

    async function listProducers() {
        const sequence = ++listSequence;
        listController?.abort();
        listController = new AbortController();
        setLoading(true);
        const parameters = new URLSearchParams();
        const query = elements.search.value.trim();
        if (query) parameters.set('q', query);
        if (elements.status.value !== 'TODOS') parameters.set('estado', elements.status.value);
        parameters.set('pagina', String(currentPage));
        parameters.set('tamanoPagina', String(pageSize));

        try {
            const response = await request(`${API_URL}?${parameters}`, { signal: listController.signal });
            if (sequence !== listSequence) return;
            updateCatalogs(response.data?.catalogos);
            const list = getProducerList(response.data);
            productores.clear();
            list.forEach((producer) => productores.set(String(producer.participanteId), producer));
            currentPage = Number(response.data?.pagina) || currentPage;
            renderProducers(list, response.data?.total, currentPage, Number(response.data?.tamanoPagina) || pageSize);
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== listSequence) return;
            renderProducers([]);
            showNotification(error.message, 'error');
        } finally {
            if (sequence === listSequence) setLoading(false);
        }
    }

    function getProducerList(data) {
        if (Array.isArray(data)) return data;
        if (Array.isArray(data?.productores)) return data.productores;
        return [];
    }

    function updateCatalogs(source) {
        if (!source || typeof source !== 'object') return;
        if (Array.isArray(source.tiposIdentificacion)) catalogos.tiposIdentificacion = source.tiposIdentificacion;
        if (Array.isArray(source.fincasDisponibles)) catalogos.fincas = source.fincasDisponibles;
    }

    function renderProducers(list, total = list.length, page = 1, size = pageSize) {
        elements.body.replaceChildren();
        elements.empty.hidden = list.length > 0;
        const resultTotal = Number.isFinite(Number(total)) ? Number(total) : list.length;
        elements.total.textContent = resultTotal === 1 ? '1 productor encontrado' : `${resultTotal} productores encontrados`;
        const totalPages = Math.max(1, Math.ceil(resultTotal / size));
        elements.page.textContent = `Página ${page} de ${totalPages}`;
        elements.previous.disabled = page <= 1;
        elements.next.disabled = page >= totalPages;
        const fragment = document.createDocumentFragment();
        list.forEach((producer) => fragment.appendChild(createRow(producer)));
        elements.body.appendChild(fragment);
    }

    function createRow(producer) {
        const row = document.createElement('tr');
        row.dataset.id = producer.participanteId;

        const producerCell = createCell('Productor');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span'); avatar.className = 'avatar'; avatar.textContent = getInitials(producer.nombre);
        const details = document.createElement('span');
        const name = document.createElement('strong'); name.textContent = producer.nombre || 'Sin nombre';
        const email = document.createElement('small'); email.textContent = producer.correoElectronico || 'Sin correo';
        details.append(name, email); summary.append(avatar, details); producerCell.appendChild(summary);

        const identificationCell = createCell('Identificación');
        const type = document.createElement('small'); type.className = 'secondary-data'; type.textContent = producer.identificacion?.tipoCodigo || 'Sin tipo';
        const number = document.createElement('span'); number.textContent = producer.identificacion?.numero || 'Sin número';
        identificationCell.append(type, number);

        const contactCell = createCell('Contacto'); contactCell.textContent = producer.telefono || 'Sin teléfono';
        const addressCell = createCell('Dirección principal'); addressCell.textContent = formatAddress(producer.direccionPrincipal);
        if (!producer.direccionPrincipal) addressCell.classList.add('empty-data');
        const farmCell = createCell('Fincas'); farmCell.textContent = formatFarms(producer.fincas);
        if (!producer.fincas?.length) farmCell.classList.add('empty-data');

        const statusCell = createCell('Estado');
        const badge = document.createElement('span');
        const active = producer.estado === 'ACTIVO';
        badge.className = `badge badge--${active ? 'active' : 'inactive'}`; badge.textContent = active ? 'Activo' : 'Inactivo'; statusCell.appendChild(badge);

        const actionsCell = createCell('Acciones'); actionsCell.className = 'row-actions';
        if (active) actionsCell.append(createActionButton('editar', 'Editar', producer.participanteId), createActionButton('desactivar', 'Desactivar', producer.participanteId));
        else actionsCell.append(createActionButton('reactivar', 'Reactivar', producer.participanteId));

        row.append(producerCell, identificationCell, contactCell, addressCell, farmCell, statusCell, actionsCell);
        return row;
    }

    function createCell(label) { const cell = document.createElement('td'); cell.dataset.label = label; return cell; }
    function createActionButton(action, text, id) { const button = document.createElement('button'); button.type = 'button'; button.className = `action action--${action}`; button.dataset.action = action; button.dataset.id = id; button.textContent = text; return button; }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const producer = productores.get(String(button.dataset.id));
        if (!producer) return showNotification('No se encontró el productor seleccionado.', 'error');
        if (button.dataset.action === 'editar') openEditForm(producer);
        if (button.dataset.action === 'desactivar') openDeactivation(producer);
        if (button.dataset.action === 'reactivar') reactivateProducer(producer);
    }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear productor'; elements.modalSubtitle.textContent = 'Nuevo registro'; elements.save.textContent = 'Guardar productor';
        openDialog(elements.modal); elements.types.focus();
    }

    function openEditForm(producer) {
        resetForm();
        $('#participante-id').value = producer.participanteId;
        elements.types.value = producer.identificacion?.tipoId ?? '';
        $('#identificacion-numero').value = producer.identificacion?.numero ?? '';
        $('#nombre').value = producer.nombre ?? '';
        $('#telefono').value = producer.telefono ?? '';
        $('#correo-electronico').value = producer.correoElectronico ?? '';
        $('#direccion-provincia').value = producer.direccionPrincipal?.provincia ?? '';
        $('#direccion-canton').value = producer.direccionPrincipal?.canton ?? '';
        $('#direccion-distrito').value = producer.direccionPrincipal?.distrito ?? '';
        $('#direccion-pueblo').value = producer.direccionPrincipal?.pueblo ?? '';
        $('#direccion-senas').value = producer.direccionPrincipal?.senas ?? '';
        renderFarmOptions(producer.fincas ?? []);
        elements.modalTitle.textContent = 'Editar productor'; elements.modalSubtitle.textContent = 'Actualizar registro'; elements.save.textContent = 'Guardar cambios';
        openDialog(elements.modal); $('#nombre').focus();
    }

    function resetForm() { elements.form.reset(); clearErrors(); renderTypeOptions(); renderFarmOptions([]); $('#participante-id').value = ''; elements.reactivateExisting.hidden = true; delete elements.reactivateExisting.dataset.id; }

    function renderTypeOptions() {
        const selected = elements.types.value;
        const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Seleccione un tipo';
        const fragment = document.createDocumentFragment(); fragment.appendChild(placeholder);
        catalogos.tiposIdentificacion.forEach((type) => {
            const option = document.createElement('option'); option.value = type.tipoId; option.textContent = type.nombre || type.tipoNombre || type.codigo || type.tipoCodigo; fragment.appendChild(option);
        });
        elements.types.replaceChildren(fragment); elements.types.value = selected;
        updateIdentificationInputMode();
    }

    function renderFarmOptions(selectedFarms) {
        const normalizedSelection = selectedFarms.map((farm) => typeof farm === 'object' ? farm : { fincaId: farm });
        const selected = new Set(normalizedSelection.map((farm) => String(farm.fincaId)));
        const availableById = new Map(catalogos.fincas.map((farm) => [String(farm.fincaId), farm]));
        normalizedSelection.forEach((farm) => { if (!availableById.has(String(farm.fincaId))) availableById.set(String(farm.fincaId), farm); });
        const availableFarms = [...availableById.values()];
        const fragment = document.createDocumentFragment();
        if (availableFarms.length === 0) {
            const message = document.createElement('p'); message.className = 'empty-data'; message.textContent = 'No hay fincas activas disponibles.'; fragment.appendChild(message);
        } else {
            availableFarms.forEach((farm) => {
                const label = document.createElement('label'); label.className = 'farm-option';
                const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.name = 'fincas'; checkbox.value = farm.fincaId; checkbox.checked = selected.has(String(farm.fincaId));
                const text = document.createElement('span'); text.textContent = farm.nombre || farm.fincaNombre;
                label.append(checkbox, text); fragment.appendChild(label);
            });
        }
        elements.farms.replaceChildren(fragment);
    }

    async function saveProducer(event) {
        event.preventDefault();
        if (saving) return;
        clearErrors();
        if (!elements.form.checkValidity()) { markFirstInvalid(); return; }
        const participanteId = $('#participante-id').value;
        const data = {
            identificacion: { tipoId: Number(elements.types.value), numero: $('#identificacion-numero').value.trim() },
            nombre: $('#nombre').value.trim(), telefono: $('#telefono').value.trim(), correoElectronico: $('#correo-electronico').value.trim().toLowerCase(),
            direccionPrincipal: { provincia: $('#direccion-provincia').value.trim(), canton: $('#direccion-canton').value.trim(), distrito: $('#direccion-distrito').value.trim(), pueblo: nullableValue($('#direccion-pueblo')), senas: nullableValue($('#direccion-senas')) },
            fincas: [...elements.form.querySelectorAll('input[name="fincas"]:checked')].map((input) => ({ fincaId: Number(input.value) })),
        };
        const editing = participanteId !== '';
        if (editing) data.participanteId = Number(participanteId);
        setSaving(true);
        try {
            const response = await request(API_URL, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(data) });
            elements.modal.close(); showNotification(response.message, 'success'); await listProducers();
        } catch (error) {
            setSaving(false);
            if (error.errors) showErrors(error.errors);
            if (!editing && error.status === 409) await offerReactivation(error);
            showNotification(error.message, 'error');
        } finally { if (saving) setSaving(false); }
    }

    function openDeactivation(producer) {
        productorPendiente = producer;
        elements.deactivateMessage.textContent = `${producer.nombre} quedará inactivo. Su identificación, dirección, roles, fincas y bitácora se conservarán.`;
        openDialog(elements.deactivateModal); elements.confirmDeactivate.focus();
    }

    async function deactivateProducer() {
        if (!productorPendiente || changingStatus) return;
        await changeStatus('DELETE', productorPendiente, elements.confirmDeactivate, () => { elements.deactivateModal.close(); productorPendiente = null; });
    }

    async function reactivateProducer(producer) {
        if (changingStatus) return;
        const button = elements.body.querySelector(`[data-action="reactivar"][data-id="${producer.participanteId}"]`);
        await changeStatus('PATCH', producer, button);
    }

    async function offerReactivation(error) {
        let participanteId = Number(error.data?.reactivacion?.participanteId || error.data?.participanteId || 0);
        if (!participanteId) {
            const entered = normalizeIdentification($('#identificacion-numero').value);
            const known = [...productores.values()].find((producer) => producer.estado === 'INACTIVO' && normalizeIdentification(producer.identificacion?.numero) === entered && String(producer.identificacion?.tipoId) === elements.types.value);
            participanteId = Number(known?.participanteId || 0);
        }
        if (!participanteId) {
            try {
                const parameters = new URLSearchParams({ q: $('#identificacion-numero').value.trim(), estado: 'INACTIVO' });
                const response = await request(`${API_URL}?${parameters}`);
                updateCatalogs(response.data?.catalogos);
                const entered = normalizeIdentification($('#identificacion-numero').value);
                const match = getProducerList(response.data).find((producer) => normalizeIdentification(producer.identificacion?.numero) === entered && String(producer.identificacion?.tipoId) === elements.types.value);
                participanteId = Number(match?.participanteId || 0);
            } catch { return; }
        }
        if (!participanteId) return;
        elements.reactivateExisting.dataset.id = String(participanteId);
        elements.reactivateExisting.hidden = false;
        elements.reactivateExisting.focus();
    }

    async function reactivateExistingProducer() {
        const participanteId = Number(elements.reactivateExisting.dataset.id || 0);
        if (!participanteId || changingStatus) return;
        await changeStatus('PATCH', { participanteId }, elements.reactivateExisting, () => { elements.modal.close(); });
    }

    async function changeStatus(method, producer, button, afterSuccess = null) {
        const busyContainer = button?.closest('dialog') || elements.panel;
        changingStatus = true;
        document.querySelectorAll('[data-action], #confirmar-desactivacion, #reactivar-existente').forEach((control) => { control.disabled = true; });
        busyContainer.setAttribute('aria-busy', 'true');
        try {
            const response = await request(API_URL, { method, body: JSON.stringify({ participanteId: Number(producer.participanteId) }) });
            afterSuccess?.(); showNotification(response.message, 'success'); await listProducers();
        } catch (error) { showNotification(error.message, 'error'); }
        finally {
            changingStatus = false;
            busyContainer.setAttribute('aria-busy', 'false');
            document.querySelectorAll('[data-action], #confirmar-desactivacion, #reactivar-existente').forEach((control) => { control.disabled = false; });
        }
    }

    async function request(url, options = {}) {
        const httpResponse = await fetch(url, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers ?? {}) } });
        let response;
        try { response = await httpResponse.json(); }
        catch { throw new Error('El servidor no devolvió JSON válido.'); }
        if (!httpResponse.ok || response.success !== true) {
            const error = new Error(response.message || 'No se pudo completar la operación.'); error.errors = response.errors ?? null; error.data = response.data ?? null; error.status = httpResponse.status; throw error;
        }
        return response;
    }

    function showErrors(errors) {
        let first = null;
        Object.entries(errors).forEach(([field, message]) => {
            const normalizedField = normalizeErrorField(field);
            const control = normalizedField === 'fincas' ? elements.farms : elements.form.elements.namedItem(normalizedField);
            const container = elements.form.querySelector(`[data-error-for="${CSS.escape(normalizedField)}"]`);
            if (control instanceof HTMLElement) { control.setAttribute('aria-invalid', 'true'); first ??= control; }
            if (container) container.textContent = String(message);
        });
        (first || elements.form.querySelector('[aria-invalid="true"]'))?.focus();
    }

    function normalizeErrorField(field) {
        const aliases = { 'identificacion.tipo': 'identificacion.tipoId', tipoIdentificacionId: 'identificacion.tipoId', numeroIdentificacion: 'identificacion.numero', correo: 'correoElectronico', direccion: 'direccionPrincipal.provincia', fincaIds: 'fincas' };
        return aliases[field] || field;
    }

    function markNativeError(event) { event.target.setAttribute('aria-invalid', 'true'); }
    function markFirstInvalid() { const first = elements.form.querySelector(':invalid'); if (first) { first.setAttribute('aria-invalid', 'true'); first.focus(); } }
    function clearControlError(event) {
        const control = event.target;
        if (!control.name) return;
        control.removeAttribute('aria-invalid');
        const container = elements.form.querySelector(`[data-error-for="${CSS.escape(control.name)}"]`); if (container) container.textContent = '';
    }
    function clearErrors() { elements.form.querySelectorAll('[aria-invalid]').forEach((control) => control.removeAttribute('aria-invalid')); elements.form.querySelectorAll('[data-error-for]').forEach((container) => { container.textContent = ''; }); }
    function setSaving(value) {
        saving = value;
        elements.form.setAttribute('aria-busy', String(value));
        elements.form.querySelectorAll('button, input, select, textarea').forEach((control) => { control.disabled = value; });
        if (value) { elements.save.dataset.label = elements.save.textContent; elements.save.textContent = 'Guardando…'; }
        else { elements.save.textContent = elements.save.dataset.label || elements.save.textContent; delete elements.save.dataset.label; }
    }
    function setLoading(value) { elements.loading.hidden = !value; elements.panel.setAttribute('aria-busy', String(value)); elements.refresh.disabled = value; }
    function scheduleSearch() { currentPage = 1; window.clearTimeout(searchTimer); searchTimer = window.setTimeout(listProducers, 300); }
    function showNotification(message, type) {
        window.clearTimeout(notificationTimer);
        elements.notification.textContent = message;
        elements.notification.className = `notification notification--${type}`;
        elements.notification.setAttribute('role', type === 'error' ? 'alert' : 'status');
        elements.notification.hidden = false;
        if (type !== 'error') notificationTimer = window.setTimeout(() => { elements.notification.hidden = true; }, 4500);
    }
    function closeForm() { if (saving) return; if (elements.modal.open) elements.modal.close(); clearErrors(); }
    function closeDeactivation() { if (changingStatus) return; if (elements.deactivateModal.open) elements.deactivateModal.close(); productorPendiente = null; }
    function closeOnBackdropClick(event) { if (event.target === event.currentTarget && !saving && !changingStatus) event.currentTarget.close(); }
    function openDialog(dialog) { focusReturn = document.activeElement; if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', ''); }
    function restoreFocus() { if (focusReturn instanceof HTMLElement && focusReturn.isConnected) focusReturn.focus(); focusReturn = null; }
    function nullableValue(control) { const value = control.value.trim(); return value === '' ? null : value; }
    function updateIdentificationInputMode() {
        const selected = catalogos.tiposIdentificacion.find((type) => String(type.tipoId) === elements.types.value);
        const numeric = ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'].includes(selected?.codigo);
        $('#identificacion-numero').inputMode = numeric ? 'numeric' : 'text';
    }
    function getInitials(name = '') { return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'P'; }
    function formatAddress(address) { if (!address) return 'No registrada'; return [address.distrito, address.canton, address.provincia].filter(Boolean).join(', ') || 'No registrada'; }
    function formatFarms(farms) { return Array.isArray(farms) && farms.length ? farms.map((farm) => farm.nombre || farm.fincaNombre).filter(Boolean).join(', ') : 'Sin fincas asociadas'; }
    function normalizeIdentification(value = '') { return String(value).normalize('NFKC').toUpperCase().replace(/[\s-]+/g, ''); }
})();
