import { request } from './shared/api.js';
import { consultarCapacidades, describirCapacidad } from './shared/capacidades.js';
import { createDialogController } from './shared/dialog.js';
import { aplicarRestriccionIdentificacion } from './shared/identificacion.js';
import { aplicarRestriccionTelefono } from './shared/telefono.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/compradores.php';
const ETIQUETAS = { singular: 'comprador', plural: 'compradores' };

/** Cuerpo enviado a la API. Exportado para la prueba de paridad de contrato. */
export function buildCompradorPayload({
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

/** Iniciales del comprador; la letra de reserva es propia de este panel. */
function getInitials(name = '') {
    return name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase()).join('') || 'C';
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-compradores'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-compradores'), total: $('#total-compradores'),
        search: $('#busqueda-comprador'), status: $('#filtro-estado'), refresh: $('#actualizar-lista'),
        previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        create: $('#crear-comprador'), modal: $('#modal-comprador'),
        form: $('#formulario-comprador'), modalTitle: $('#titulo-modal'),
        modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'),
        save: $('#guardar-comprador'), reactivateExisting: $('#reactivar-existente'),
        types: $('#identificacion-tipo'), idHint: $('#ayuda-identificacion-numero'),
        deactivateModal: $('#modal-desactivar'), deactivateMessage: $('#mensaje-desactivar'),
        cancelDeactivate: $('#cancelar-desactivacion'), confirmDeactivate: $('#confirmar-desactivacion'),
        detailModal: $('#modal-detalle'), detailTitle: $('#titulo-detalle'),
        detailContent: $('#detalle-contenido'), capacities: $('#lista-capacidades'),
        closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'),
        editFromDetail: $('#editar-desde-detalle'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const compradores = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({
        isBusy: () => submit.busy || statusChange.busy,
    });

    let state = createListState({ pageSize: 25 });
    let tiposIdentificacion = [];
    let listController = null;
    let capacityController = null;
    let compradorPendiente = null;
    let compradorDetalle = null;
    let searchTimer = 0;

    async function listBuyers({ page = state.page } = {}) {
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
            const list = Array.isArray(response.data?.compradores) ? response.data.compradores : [];
            compradores.clear();
            list.forEach((buyer) => compradores.set(buyer.identificacionNumero, buyer));
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
        state.items.forEach((buyer) => fragment.appendChild(createRow(buyer)));
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

    function createRow(buyer) {
        const row = document.createElement('tr');
        row.dataset.id = buyer.identificacionNumero;
        const buyerCell = createCell('Comprador');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span');
        avatar.className = 'avatar';
        avatar.textContent = getInitials(buyer.nombre);
        const details = document.createElement('span');
        const name = document.createElement('strong'); name.textContent = buyer.nombre || 'Sin nombre';
        const email = document.createElement('small');
        email.textContent = buyer.correoElectronico || 'Sin correo';
        details.append(name, email); summary.append(avatar, details); buyerCell.appendChild(summary);

        const identificationCell = createCell('Identificación');
        const type = document.createElement('small');
        type.className = 'secondary-data';
        type.textContent = buyer.identificacion?.tipoCodigo || 'Sin tipo';
        const number = document.createElement('span');
        number.textContent = buyer.identificacionNumero;
        identificationCell.append(type, number);

        const contactCell = createCell('Contacto');
        contactCell.textContent = buyer.telefono || 'Sin teléfono';
        const statusCell = createCell('Estado');
        const active = buyer.estado === 'ACTIVO';
        const badge = document.createElement('span');
        badge.className = `badge badge--${active ? 'active' : 'inactive'}`;
        badge.textContent = active ? 'Activo' : 'Inactivo';
        statusCell.appendChild(badge);

        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', buyer.identificacionNumero));
        if (active) {
            actionsCell.append(
                createActionButton('editar', 'Editar', buyer.identificacionNumero),
                createActionButton('desactivar', 'Desactivar', buyer.identificacionNumero),
            );
        } else {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', buyer.identificacionNumero));
        }
        row.append(buyerCell, identificationCell, contactCell, statusCell, actionsCell);
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const buyer = compradores.get(button.dataset.id);
        if (!buyer) return toast.error('No se encontró el comprador seleccionado.');
        if (button.dataset.action === 'ver') openDetail(buyer);
        if (button.dataset.action === 'editar') openEditForm(buyer);
        if (button.dataset.action === 'desactivar') openDeactivation(buyer);
        if (button.dataset.action === 'reactivar') reactivateBuyer(buyer);
        return undefined;
    }

    function openDetail(buyer) {
        compradorDetalle = buyer;
        elements.detailTitle.textContent = buyer.nombre || 'Comprador';
        const fragment = document.createDocumentFragment();
        [
            ['Identificación', `${buyer.identificacion?.tipoCodigo ?? 'Sin tipo'} · ${buyer.identificacionNumero}`],
            ['Estado', buyer.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
            ['Teléfono', buyer.telefono || '—'],
            ['Correo electrónico', buyer.correoElectronico || '—'],
        ].forEach(([etiqueta, valor]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor;
            fragment.append(dt, dd);
        });
        elements.detailContent.replaceChildren(fragment);
        dialogs.open(elements.detailModal, { focus: elements.closeDetail });
        loadCapacities(buyer.identificacionNumero);
    }

    /**
     * Que capacidades tiene la persona detras de esta identificacion.
     *
     * La consulta se aborta al cerrar el detalle: sin eso, abrir dos fichas
     * seguidas dejaria que la respuesta lenta de la primera pintara sus
     * capacidades sobre la segunda.
     */
    async function loadCapacities(identificacionNumero) {
        capacityController?.abort();
        capacityController = new AbortController();
        const { signal } = capacityController;
        elements.capacities.setAttribute('aria-busy', 'true');
        elements.capacities.replaceChildren(createCapacityItem({
            etiqueta: 'Consultando capacidades…', situacion: 'cargando',
        }));

        const capacidades = await consultarCapacidades(identificacionNumero, {
            requestImpl: (url) => request(url, { signal }),
        });
        if (signal.aborted) return;

        elements.capacities.setAttribute('aria-busy', 'false');
        const fragment = document.createDocumentFragment();
        capacidades.forEach((capacidad) => fragment.appendChild(createCapacityItem(capacidad)));
        elements.capacities.replaceChildren(fragment);
    }

    function createCapacityItem(capacidad) {
        const item = document.createElement('li');
        item.className = `capacidad capacidad--${capacidad.situacion}`;
        const nombre = document.createElement('span');
        nombre.className = 'capacidad__nombre';
        nombre.textContent = capacidad.alias
            ? `${capacidad.etiqueta} (${capacidad.alias})`
            : capacidad.etiqueta;
        item.appendChild(nombre);
        if (capacidad.situacion === 'cargando') return item;

        const estado = document.createElement('span');
        estado.className = 'capacidad__estado';
        estado.textContent = describirCapacidad(capacidad);
        item.appendChild(estado);
        // La capacidad actual ya se esta viendo; enlazar a su propio panel no
        // lleva a ninguna parte nueva.
        if (capacidad.situacion === 'registrado' && capacidad.clave !== 'comprador') {
            const enlace = document.createElement('a');
            enlace.className = 'capacidad__enlace';
            enlace.href = `${capacidad.panel}?q=${encodeURIComponent(capacidad.identificacionNumero ?? '')}`;
            enlace.textContent = 'Abrir panel';
            enlace.setAttribute('aria-label', `Abrir el panel de ${capacidad.etiqueta}`);
            item.appendChild(enlace);
        }
        return item;
    }

    function closeDetail() {
        capacityController?.abort();
        dialogs.close(elements.detailModal);
        compradorDetalle = null;
    }

    function editFromDetail() {
        if (!compradorDetalle) return;
        const buyer = compradorDetalle;
        closeDetail();
        openEditForm(buyer);
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
        elements.modalTitle.textContent = 'Crear comprador';
        elements.modalSubtitle.textContent = 'Nuevo registro';
        elements.save.textContent = 'Guardar comprador';
        dialogs.open(elements.modal, { focus: elements.types });
    }

    function openEditForm(buyer) {
        resetForm();
        $('#identificacion-original').value = buyer.identificacionNumero;
        elements.types.value = buyer.identificacion?.tipoCodigo ?? '';
        $('#identificacion-numero').value = buyer.identificacionNumero;
        $('#identificacion-numero').readOnly = true;
        $('#nombre').value = buyer.nombre ?? '';
        $('#telefono').value = buyer.telefono ?? '';
        $('#correo-electronico').value = buyer.correoElectronico ?? '';
        elements.modalTitle.textContent = 'Editar comprador';
        elements.modalSubtitle.textContent = 'Actualizar registro';
        elements.save.textContent = 'Guardar cambios';
        dialogs.open(elements.modal, { focus: $('#nombre') });
    }

    function saveBuyer(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            const original = $('#identificacion-original').value;
            const editing = original !== '';
            const data = buildCompradorPayload({
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
                await listBuyers();
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

    function reactivateExistingBuyer() {
        const identificacionNumero = elements.reactivateExisting.dataset.id || '';
        if (!identificacionNumero) return undefined;
        return changeStatus('PATCH', { identificacionNumero }, () => dialogs.close(elements.modal));
    }

    function openDeactivation(buyer) {
        compradorPendiente = buyer;
        elements.deactivateMessage.textContent =
            `${buyer.nombre} dejará de comprar. Sus otras capacidades y su bitácora se conservarán.`;
        dialogs.open(elements.deactivateModal, { focus: elements.confirmDeactivate });
    }

    function changeStatus(method, buyer, afterSuccess = null) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll(
                '[data-action], #confirmar-desactivacion, #reactivar-existente',
            );
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await request(API_URL, {
                    method,
                    body: JSON.stringify({ identificacionNumero: buyer.identificacionNumero }),
                });
                afterSuccess?.();
                toast.success(response.message);
                await listBuyers();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function deactivateBuyer() {
        if (!compradorPendiente) return undefined;
        return changeStatus('DELETE', compradorPendiente, () => {
            dialogs.close(elements.deactivateModal);
            compradorPendiente = null;
        });
    }

    function reactivateBuyer(buyer) { return changeStatus('PATCH', buyer); }

    function closeForm() { if (!submit.busy) { dialogs.close(elements.modal); errores.clearErrors(); } }
    function closeDeactivation() {
        if (statusChange.busy) return;
        dialogs.close(elements.deactivateModal);
        compradorPendiente = null;
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => listBuyers({ page: 1 }), 300);
    }

    elements.create.addEventListener('click', openCreateForm);
    elements.refresh.addEventListener('click', () => listBuyers());
    elements.retry.addEventListener('click', () => listBuyers());
    elements.previous.addEventListener('click', () => {
        if (state.page > 1) listBuyers({ page: state.page - 1 });
    });
    elements.next.addEventListener('click', () => listBuyers({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => listBuyers({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', saveBuyer);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.form.addEventListener('change', errores.clearControlError);
    elements.types.addEventListener('change', updateIdentificationInputMode);
    // El campo solo llega a contener lo que el backend admite; el atributo
    // pattern sigue siendo la validacion, esto evita escribir lo imposible.
    aplicarRestriccionTelefono($('#telefono'));
    elements.reactivateExisting.addEventListener('click', reactivateExistingBuyer);
    elements.close.addEventListener('click', closeForm);
    elements.cancel.addEventListener('click', closeForm);
    elements.cancelDeactivate.addEventListener('click', closeDeactivation);
    elements.confirmDeactivate.addEventListener('click', deactivateBuyer);
    elements.body.addEventListener('click', handleTableAction);
    elements.closeDetail.addEventListener('click', closeDetail);
    elements.closeDetailSecondary.addEventListener('click', closeDetail);
    elements.editFromDetail.addEventListener('click', editFromDetail);
    [elements.modal, elements.deactivateModal, elements.detailModal].forEach((dialog) => {
        dialog.addEventListener('click', dialogs.handleBackdropClick);
        dialog.addEventListener('close', dialogs.restoreFocus);
    });

    // La busqueda puede venir en la URL desde el enlace "Abrir panel" de otra
    // capacidad, para caer directamente sobre la misma persona.
    const inicial = new URLSearchParams(window.location.search).get('q');
    if (inicial) elements.search.value = inicial;
    listBuyers();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
