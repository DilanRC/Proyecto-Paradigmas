(() => {
    'use strict';

    const API_URL = 'api/producers.php';
    const elements = {
        body: document.querySelector('#producers-body'), empty: document.querySelector('#empty-state'), loading: document.querySelector('#loading-state'), total: document.querySelector('#producers-total'), search: document.querySelector('#producer-search'), statusFilter: document.querySelector('#status-filter'), refresh: document.querySelector('#refresh-button'), create: document.querySelector('#create-button'), modal: document.querySelector('#producer-modal'), form: document.querySelector('#producer-form'), modalTitle: document.querySelector('#modal-title'), modalSubtitle: document.querySelector('#modal-subtitle'), close: document.querySelector('#close-modal'), cancelForm: document.querySelector('#cancel-form'), save: document.querySelector('#save-producer'), deactivateModal: document.querySelector('#deactivate-modal'), deactivateMessage: document.querySelector('#deactivate-message'), cancelDeactivation: document.querySelector('#cancel-deactivation'), confirmDeactivation: document.querySelector('#confirm-deactivation'), notification: document.querySelector('#notification'),
    };
    const producers = new Map();
    let producerToDeactivate = null;
    let searchTimer = null;
    let notificationTimer = null;

    document.addEventListener('DOMContentLoaded', initialize);

    function initialize() {
        elements.create.addEventListener('click', openCreateForm);
        elements.refresh.addEventListener('click', listProducers);
        elements.statusFilter.addEventListener('change', listProducers);
        elements.search.addEventListener('input', scheduleSearch);
        elements.form.addEventListener('submit', saveProducer);
        elements.close.addEventListener('click', closeForm);
        elements.cancelForm.addEventListener('click', closeForm);
        elements.cancelDeactivation.addEventListener('click', closeDeactivation);
        elements.confirmDeactivation.addEventListener('click', deactivateProducer);
        elements.body.addEventListener('click', handleTableAction);
        elements.modal.addEventListener('click', closeOnBackdropClick);
        elements.deactivateModal.addEventListener('click', closeOnBackdropClick);
        listProducers();
    }

    async function listProducers() {
        setLoading(true);
        const parameters = new URLSearchParams({ q: elements.search.value.trim(), status: elements.statusFilter.value });
        try {
            const response = await request(`${API_URL}?${parameters}`);
            const list = response.data.producers ?? [];
            producers.clear();
            list.forEach((producer) => producers.set(String(producer.producer_id), producer));
            renderProducers(list);
        } catch (error) {
            renderProducers([]);
            showNotification(error.message, 'error');
        } finally { setLoading(false); }
    }

    function renderProducers(list) {
        elements.body.replaceChildren();
        elements.empty.hidden = list.length > 0;
        elements.total.textContent = list.length === 1 ? '1 producer found' : `${list.length} producers found`;
        const fragment = document.createDocumentFragment();
        list.forEach((producer) => fragment.appendChild(createRow(producer)));
        elements.body.appendChild(fragment);
    }

    function createRow(producer) {
        const row = document.createElement('tr'); row.dataset.id = producer.producer_id;
        const producerCell = createCell('Producer');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span'); avatar.className = 'avatar'; avatar.textContent = getInitials(producer.name);
        const details = document.createElement('span'); const name = document.createElement('strong'); name.textContent = producer.name;
        const email = document.createElement('small'); email.textContent = producer.email; details.append(name, email); summary.append(avatar, details); producerCell.appendChild(summary);
        const identificationCell = createCell('Identification'); const identificationType = document.createElement('small'); identificationType.className = 'secondary-data'; identificationType.textContent = identificationTypeLabel(producer.identification_type); const identificationNumber = document.createElement('span'); identificationNumber.textContent = producer.identification_number; identificationCell.append(identificationType, identificationNumber);
        const contactCell = createCell('Contact'); contactCell.textContent = producer.phone;
        const farmCell = createCell('Farm'); farmCell.textContent = producer.farm_name || 'Not provided'; if (!producer.farm_name) farmCell.classList.add('empty-data');
        const statusCell = createCell('Status'); const badge = document.createElement('span'); badge.className = `badge badge--${producer.status.toLowerCase()}`; badge.textContent = capitalize(producer.status); statusCell.appendChild(badge);
        const actionsCell = createCell('Actions'); actionsCell.className = 'row-actions'; actionsCell.append(createActionButton('edit', 'Edit', producer.producer_id), producer.status === 'ACTIVE' ? createActionButton('deactivate', 'Deactivate', producer.producer_id) : createActionButton('reactivate', 'Reactivate', producer.producer_id));
        row.append(producerCell, identificationCell, contactCell, farmCell, statusCell, actionsCell); return row;
    }

    function createCell(label) { const cell = document.createElement('td'); cell.dataset.label = label; return cell; }
    function createActionButton(action, text, id) { const button = document.createElement('button'); button.type = 'button'; button.className = `action action--${action}`; button.dataset.action = action; button.dataset.id = id; button.textContent = text; return button; }
    function handleTableAction(event) { const button = event.target.closest('[data-action]'); if (!button) return; const producer = producers.get(String(button.dataset.id)); if (!producer) return showNotification('The selected producer could not be found.', 'error'); if (button.dataset.action === 'edit') openEditForm(producer); else if (button.dataset.action === 'deactivate') openDeactivation(producer); else if (button.dataset.action === 'reactivate') changeStatus(producer, 'ACTIVE'); }
    function openCreateForm() { elements.form.reset(); clearErrors(); document.querySelector('#producer-id').value = ''; document.querySelector('#status').value = 'ACTIVE'; elements.modalTitle.textContent = 'Create producer'; elements.modalSubtitle.textContent = 'New record'; elements.save.textContent = 'Save producer'; openDialog(elements.modal); document.querySelector('#identification-type').focus(); }
    function openEditForm(producer) { elements.form.reset(); clearErrors(); ['producer_id', 'identification_type', 'identification_number', 'name', 'farm_name', 'phone', 'email', 'address', 'status'].forEach((field) => { const control = elements.form.elements.namedItem(field); if (control) control.value = producer[field] ?? ''; }); elements.modalTitle.textContent = 'Edit producer'; elements.modalSubtitle.textContent = 'Update record'; elements.save.textContent = 'Save changes'; openDialog(elements.modal); document.querySelector('#name').focus(); }
    async function saveProducer(event) { event.preventDefault(); clearErrors(); if (!elements.form.checkValidity()) return elements.form.reportValidity(); const data = Object.fromEntries(new FormData(elements.form).entries()); const editing = data.producer_id !== ''; if (editing) data.producer_id = Number(data.producer_id); else delete data.producer_id; setSaving(true); try { const response = await request(API_URL, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(data) }); elements.modal.close(); showNotification(response.message, 'success'); await listProducers(); } catch (error) { if (error.errors) showErrors(error.errors); showNotification(error.message, 'error'); } finally { setSaving(false); } }
    function openDeactivation(producer) { producerToDeactivate = producer; elements.deactivateMessage.textContent = `${producer.name} will no longer appear among active records. Their information will be retained.`; openDialog(elements.deactivateModal); elements.confirmDeactivation.focus(); }
    async function deactivateProducer() { if (!producerToDeactivate) return; elements.confirmDeactivation.disabled = true; try { const response = await request(API_URL, { method: 'DELETE', body: JSON.stringify({ producer_id: Number(producerToDeactivate.producer_id) }) }); closeDeactivation(); showNotification(response.message, 'success'); await listProducers(); } catch (error) { showNotification(error.message, 'error'); } finally { elements.confirmDeactivation.disabled = false; } }
    async function changeStatus(producer, status) { const data = { ...producer, status, producer_id: Number(producer.producer_id) }; try { const response = await request(API_URL, { method: 'PUT', body: JSON.stringify(data) }); showNotification(response.message, 'success'); await listProducers(); } catch (error) { showNotification(error.message, 'error'); } }
    async function request(url, options = {}) { const httpResponse = await fetch(url, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers ?? {}) } }); let response; try { response = await httpResponse.json(); } catch { throw new Error('The server did not return valid JSON.'); } if (!httpResponse.ok || !response.success) { const error = new Error(response.message || 'The operation could not be completed.'); error.errors = response.errors ?? null; throw error; } return response; }
    function showErrors(errors) { Object.entries(errors).forEach(([field, message]) => { const control = elements.form.elements.namedItem(field); const errorContainer = elements.form.querySelector(`[data-error-for="${field}"]`); if (control) control.setAttribute('aria-invalid', 'true'); if (errorContainer) errorContainer.textContent = message; }); }
    function clearErrors() { elements.form.querySelectorAll('[aria-invalid="true"]').forEach((control) => control.removeAttribute('aria-invalid')); elements.form.querySelectorAll('[data-error-for]').forEach((container) => { container.textContent = ''; }); }
    function setSaving(saving) { elements.save.disabled = saving; if (saving) { elements.save.dataset.text = elements.save.textContent; elements.save.textContent = 'Saving…'; } else if (elements.save.dataset.text) { elements.save.textContent = elements.save.dataset.text; delete elements.save.dataset.text; } }
    function setLoading(loading) { elements.loading.hidden = !loading; elements.refresh.disabled = loading; }
    function scheduleSearch() { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(listProducers, 300); }
    function showNotification(message, type) { window.clearTimeout(notificationTimer); elements.notification.textContent = message; elements.notification.className = `notification notification--${type}`; elements.notification.hidden = false; notificationTimer = window.setTimeout(() => { elements.notification.hidden = true; }, 4500); }
    function closeForm() { elements.modal.close(); clearErrors(); }
    function closeDeactivation() { if (elements.deactivateModal.open) elements.deactivateModal.close(); producerToDeactivate = null; }
    function closeOnBackdropClick(event) { if (event.target === event.currentTarget) event.currentTarget.close(); }
    function openDialog(dialog) { if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', ''); }
    function getInitials(name) { return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join(''); }
    function identificationTypeLabel(type) { return { NATIONAL_ID: 'National ID', LEGAL_ID: 'Legal ID', DIMEX: 'DIMEX', NITE: 'NITE' }[type] ?? type; }
    function capitalize(text) { return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase(); }
})();
