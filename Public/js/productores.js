import { request } from './shared/api.js';
import { createDialogController } from './shared/dialog.js';
import { aplicarRestriccionIdentificacion } from './shared/identificacion.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { conectarDireccion } from './shared/territorio.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/productores.php';
const FINCAS_DIRECCION_URL = 'api/fincas-direccion.php';
const ETIQUETAS = { singular: 'productor', plural: 'productores' };

/** Cuerpo enviado a la API. Exportado para la prueba de paridad de contrato. */
export function buildProductorPayload({
    tipoCodigo, numero, nombre, telefono, correoElectronico,
    provincia, canton, distrito, pueblo, senas, fincas, identificacionNumeroOriginal = '',
}) {
    const data = {
        identificacion: { tipoCodigo, numero: numero.trim() },
        nombre: nombre.trim(),
        telefono: telefono.trim(),
        correoElectronico: correoElectronico.trim().toLowerCase(),
        direccionPrincipal: {
            provincia: provincia.trim(),
            canton: canton.trim(),
            distrito: distrito.trim(),
            pueblo: nullable(pueblo),
            senas: nullable(senas),
        },
        fincas: fincas.split(/\r?\n/).map((n) => n.trim()).filter(Boolean).map((n) => ({ nombre: n })),
    };
    if (identificacionNumeroOriginal !== '') {
        data.identificacionNumeroOriginal = identificacionNumeroOriginal;
    }
    return data;
}

/** Cuerpo de la direccion de finca. Tambien exportado para la paridad. */
export function buildFincaDireccionPayload({
    identificacionNumero, nombreFinca, provincia, canton, distrito, pueblo, senas,
}) {
    return {
        identificacionNumero,
        nombreFinca,
        direccionFinca: {
            provincia: provincia.trim(),
            canton: canton.trim(),
            distrito: distrito.trim(),
            pueblo: nullable(pueblo),
            senas: nullable(senas),
        },
    };
}

/** Campo opcional: cadena vacia se envia como null, no como "". */
function nullable(value) {
    const text = String(value ?? '').trim();
    return text === '' ? null : text;
}

function getInitials(name = '') {
    return name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase()).join('') || 'P';
}

function formatAddress(address) {
    return address
        ? [address.distrito, address.canton, address.provincia].filter(Boolean).join(', ')
        : 'No registrada';
}

function formatFarms(farms) {
    return Array.isArray(farms) && farms.length
        ? farms.map((farm) => farm.nombre).join(', ')
        : 'Sin fincas';
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-productores'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-productores'), total: $('#total-productores'), search: $('#busqueda-productor'),
        status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'),
        next: $('#pagina-siguiente'), page: $('#pagina-actual'), create: $('#crear-productor'),
        modal: $('#modal-productor'), form: $('#formulario-productor'), modalTitle: $('#titulo-modal'),
        modalSubtitle: $('#subtitulo-modal'), close: $('#cerrar-modal'), cancel: $('#cancelar-formulario'),
        save: $('#guardar-productor'), reactivateExisting: $('#reactivar-existente'),
        types: $('#identificacion-tipo'), idHint: $('#ayuda-identificacion-numero'), farms: $('#fincas-nombres'),
        deactivateModal: $('#modal-desactivar'), deactivateMessage: $('#mensaje-desactivar'),
        cancelDeactivate: $('#cancelar-desactivacion'), confirmDeactivate: $('#confirmar-desactivacion'),
        detailModal: $('#modal-detalle'), detailTitle: $('#titulo-detalle'),
        detailContent: $('#detalle-contenido'), closeDetail: $('#cerrar-detalle'),
        closeDetailSecondary: $('#cerrar-detalle-secundario'), editFromDetail: $('#editar-desde-detalle'),
        fincaAddressModal: $('#modal-direccion-finca'), fincaAddressForm: $('#formulario-direccion-finca'),
        fincaAddressTitle: $('#titulo-direccion-finca'), closeFincaAddress: $('#cerrar-direccion-finca'),
        cancelFincaAddress: $('#cancelar-direccion-finca'), clearFincaAddress: $('#vaciar-direccion-finca'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const productores = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });

    // D2 + D3: cada formulario tiene su propio enlace de errores. El principal
    // colapsa las claves indexadas fincas.N sobre su unico textarea "fincas";
    // el de direccion de finca no colapsa nada y, sobre todo, no puede pintar
    // sus errores sobre el formulario principal.
    const errores = bindFormErrors(elements.form, { collapsePrefixes: ['fincas'] });
    const erroresFinca = bindFormErrors(elements.fincaAddressForm);

    // Provincia -> canton -> distrito, en los dos formularios que llevan
    // direccion. El distrito sigue siendo texto libre con sugerencias: el
    // catalogo de distritos aun no esta completo y no debe impedir escribir uno.
    const direccionPrincipal = conectarDireccion({
        provincia: $('#direccion-provincia'),
        canton: $('#direccion-canton'),
        distrito: $('#direccion-distrito'),
        pueblo: $('#direccion-pueblo'),
        listaDistritos: $('#lista-distritos'),
        listaPueblos: $('#lista-pueblos'),
    });
    const direccionFinca = conectarDireccion({
        provincia: $('#finca-direccion-provincia'),
        canton: $('#finca-direccion-canton'),
        distrito: $('#finca-direccion-distrito'),
        pueblo: $('#finca-direccion-pueblo'),
        listaDistritos: $('#lista-distritos-finca'),
        listaPueblos: $('#lista-pueblos-finca'),
    });

    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const fincaSubmit = createSubmitGuard();
    // D1: este panel vigila ademas el guardado de la direccion de finca.
    const dialogs = createDialogController({
        isBusy: () => submit.busy || statusChange.busy || fincaSubmit.busy,
    });

    let state = createListState({ pageSize: 25 });
    let tiposIdentificacion = [];
    let listController = null;
    let productorPendiente = null;
    let productorDetalle = null;
    let fincaDireccionContexto = null;
    let searchTimer = 0;

    async function listProducers({ page = state.page } = {}) {
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
            const list = Array.isArray(response.data?.productores) ? response.data.productores : [];
            productores.clear();
            list.forEach((producer) => productores.set(producer.identificacionNumero, producer));
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
        state.items.forEach((producer) => fragment.appendChild(createRow(producer)));
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

    function createRow(producer) {
        const row = document.createElement('tr');
        row.dataset.id = producer.identificacionNumero;

        const producerCell = createCell('Productor');
        const summary = document.createElement('div'); summary.className = 'producer-summary';
        const avatar = document.createElement('span');
        avatar.className = 'avatar'; avatar.textContent = getInitials(producer.nombre);
        const details = document.createElement('span');
        const name = document.createElement('strong'); name.textContent = producer.nombre || 'Sin nombre';
        const email = document.createElement('small');
        email.textContent = producer.correoElectronico || 'Sin correo';
        details.append(name, email); summary.append(avatar, details); producerCell.appendChild(summary);

        const identificationCell = createCell('Identificación');
        const type = document.createElement('small');
        type.className = 'secondary-data';
        type.textContent = producer.identificacion?.tipoCodigo || 'Sin tipo';
        const number = document.createElement('span');
        number.textContent = producer.identificacionNumero;
        identificationCell.append(type, number);

        const contactCell = createCell('Contacto');
        contactCell.textContent = producer.telefono || 'Sin teléfono';

        const addressCell = createCell('Dirección principal');
        const direccion = producer.direccionPrincipal;
        if (direccion?.provincia && direccion?.canton && direccion?.distrito) {
            addressCell.textContent = formatAddress(direccion);
        } else {
            addressCell.appendChild(
                createActionButton('editar', 'Completar dirección', producer.identificacionNumero),
            );
        }

        const farmCell = createCell('Fincas');
        farmCell.textContent = formatFarms(producer.fincas);

        const statusCell = createCell('Estado');
        const active = producer.estado === 'ACTIVO';
        const badge = document.createElement('span');
        badge.className = `badge badge--${active ? 'active' : 'inactive'}`;
        badge.textContent = active ? 'Activo' : 'Inactivo';
        statusCell.appendChild(badge);

        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        actionsCell.append(createActionButton('ver', 'Ver', producer.identificacionNumero));
        if (active) {
            actionsCell.append(
                createActionButton('editar', 'Editar', producer.identificacionNumero),
                createActionButton('desactivar', 'Desactivar', producer.identificacionNumero),
            );
        } else {
            actionsCell.append(createActionButton('reactivar', 'Reactivar', producer.identificacionNumero));
        }
        row.append(producerCell, identificationCell, contactCell, addressCell, farmCell, statusCell, actionsCell);
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const producer = productores.get(button.dataset.id);
        if (!producer) return toast.error('No se encontró el productor seleccionado.');
        if (button.dataset.action === 'ver') openDetail(producer);
        if (button.dataset.action === 'editar') openEditForm(producer);
        if (button.dataset.action === 'desactivar') openDeactivation(producer);
        if (button.dataset.action === 'reactivar') reactivateProducer(producer);
        return undefined;
    }

    function openDetail(producer) {
        productorDetalle = producer;
        elements.detailTitle.textContent = producer.nombre || 'Productor';
        const direccion = producer.direccionPrincipal || {};
        const fragment = document.createDocumentFragment();
        [
            ['Identificación', `${producer.identificacion?.tipoCodigo ?? 'Sin tipo'} · ${producer.identificacionNumero}`],
            ['Estado', producer.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
            ['Teléfono', producer.telefono || '—'],
            ['Correo electrónico', producer.correoElectronico || '—'],
            ['Provincia', direccion.provincia || '—'],
            ['Cantón', direccion.canton || '—'],
            ['Distrito', direccion.distrito || '—'],
            ['Pueblo', direccion.pueblo || '—'],
            ['Señas', direccion.senas || '—', true],
        ].forEach(([etiqueta, valor, completa]) => {
            const dt = document.createElement('dt'); dt.textContent = etiqueta;
            const dd = document.createElement('dd'); dd.textContent = valor;
            if (completa) dd.className = 'detail--full';
            fragment.append(dt, dd);
        });

        const fincas = Array.isArray(producer.fincas) ? producer.fincas : [];
        if (fincas.length === 0) {
            const dt = document.createElement('dt'); dt.textContent = 'Fincas';
            const dd = document.createElement('dd'); dd.textContent = 'Sin fincas registradas';
            fragment.append(dt, dd);
        } else {
            fincas.forEach((finca) => {
                const dt = document.createElement('dt'); dt.textContent = 'Finca';
                const dd = document.createElement('dd');
                const label = document.createElement('span'); label.textContent = finca.nombre;
                const addressButton = document.createElement('button');
                addressButton.type = 'button';
                addressButton.className = 'link-button';
                addressButton.dataset.action = 'direccion-finca';
                addressButton.dataset.finca = finca.nombre;
                addressButton.textContent = 'Dirección';
                addressButton.setAttribute('aria-label', `Dirección de la finca ${finca.nombre}`);
                dd.append(label, document.createTextNode(' — '), addressButton);
                fragment.append(dt, dd);
            });
        }
        elements.detailContent.replaceChildren(fragment);
        dialogs.open(elements.detailModal, { focus: elements.closeDetail });
    }

    function closeDetail() { dialogs.close(elements.detailModal); productorDetalle = null; }

    function editFromDetail() {
        if (!productorDetalle) return;
        const producer = productorDetalle;
        closeDetail();
        openEditForm(producer);
    }

    // --- direccion de finca (segundo formulario) ----------------------------------
    function handleDetailFincaAction(event) {
        const button = event.target.closest('[data-action="direccion-finca"]');
        if (button) openFincaAddressDialog(button.dataset.finca);
    }

    async function openFincaAddressDialog(nombreFinca) {
        if (!productorDetalle) return;
        const identificacionNumero = productorDetalle.identificacionNumero;
        fincaDireccionContexto = { identificacionNumero, nombreFinca, exists: false };
        elements.fincaAddressForm.reset();
        erroresFinca.clearErrors();
        direccionFinca.aplicar({});
        elements.clearFincaAddress.hidden = true;
        elements.fincaAddressTitle.textContent = `Dirección de ${nombreFinca}`;
        dialogs.open(elements.fincaAddressModal, { focus: $('#finca-direccion-provincia') });

        try {
            const response = await request(
                `${FINCAS_DIRECCION_URL}?${new URLSearchParams({ identificacionNumero, nombreFinca })}`,
            );
            const direccion = response.data?.direccionFinca ?? {};
            direccionFinca.aplicar(direccion);
            $('#finca-direccion-senas').value = direccion.senas ?? '';
            fincaDireccionContexto.exists = true;
            elements.clearFincaAddress.hidden = false;
        } catch (error) {
            // 404 es normal: la finca todavia no tiene direccion y se creara.
            if (error.status !== 404) {
                toast.error(error.message);
                closeFincaAddressDialog();
            }
        }
    }

    function closeFincaAddressDialog() {
        if (fincaSubmit.busy) return;
        dialogs.close(elements.fincaAddressModal);
        fincaDireccionContexto = null;
    }

    function saveFincaAddressSubmit(event) {
        event.preventDefault();
        if (!fincaDireccionContexto) return undefined;
        return fincaSubmit.run(async () => {
            erroresFinca.clearErrors();
            if (!elements.fincaAddressForm.checkValidity()) {
                erroresFinca.markFirstInvalid();
                return;
            }
            const { identificacionNumero, nombreFinca, exists } = fincaDireccionContexto;
            const data = buildFincaDireccionPayload({
                identificacionNumero,
                nombreFinca,
                provincia: $('#finca-direccion-provincia').value,
                canton: $('#finca-direccion-canton').value,
                distrito: $('#finca-direccion-distrito').value,
                pueblo: $('#finca-direccion-pueblo').value,
                senas: $('#finca-direccion-senas').value,
            });
            elements.fincaAddressForm.setAttribute('aria-busy', 'true');
            try {
                const response = await request(FINCAS_DIRECCION_URL, {
                    method: exists ? 'PUT' : 'POST',
                    body: JSON.stringify(data),
                });
                toast.success(response.message);
                dialogs.close(elements.fincaAddressModal);
                fincaDireccionContexto = null;
            } catch (error) {
                if (error.errors) erroresFinca.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                elements.fincaAddressForm.setAttribute('aria-busy', 'false');
            }
        });
    }

    function clearFincaAddressSubmit() {
        if (!fincaDireccionContexto) return undefined;
        const { identificacionNumero, nombreFinca } = fincaDireccionContexto;
        return fincaSubmit.run(async () => {
            elements.fincaAddressForm.setAttribute('aria-busy', 'true');
            try {
                const response = await request(FINCAS_DIRECCION_URL, {
                    method: 'DELETE',
                    body: JSON.stringify({ identificacionNumero, nombreFinca }),
                });
                toast.success(response.message);
                dialogs.close(elements.fincaAddressModal);
                fincaDireccionContexto = null;
            } catch (error) {
                toast.error(error.message);
            } finally {
                elements.fincaAddressForm.setAttribute('aria-busy', 'false');
            }
        });
    }

    // --- formulario principal ------------------------------------------------------
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
        direccionPrincipal.aplicar({});
        $('#identificacion-original').value = '';
        $('#identificacion-numero').readOnly = false;
        elements.reactivateExisting.hidden = true;
        delete elements.reactivateExisting.dataset.id;
    }

    function openCreateForm() {
        resetForm();
        elements.modalTitle.textContent = 'Crear productor';
        elements.modalSubtitle.textContent = 'Nuevo registro';
        elements.save.textContent = 'Guardar productor';
        dialogs.open(elements.modal, { focus: elements.types });
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
        direccionPrincipal.aplicar(producer.direccionPrincipal ?? {});
        $('#direccion-senas').value = producer.direccionPrincipal?.senas ?? '';
        elements.farms.value = (producer.fincas ?? []).map((farm) => farm.nombre).join('\n');
        elements.modalTitle.textContent = 'Editar productor';
        elements.modalSubtitle.textContent = 'Actualizar registro';
        elements.save.textContent = 'Guardar cambios';
        dialogs.open(elements.modal, { focus: $('#nombre') });
    }

    function saveProducer(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            const original = $('#identificacion-original').value;
            const editing = original !== '';
            const data = buildProductorPayload({
                tipoCodigo: elements.types.value,
                numero: $('#identificacion-numero').value,
                nombre: $('#nombre').value,
                telefono: $('#telefono').value,
                correoElectronico: $('#correo-electronico').value,
                provincia: $('#direccion-provincia').value,
                canton: $('#direccion-canton').value,
                distrito: $('#direccion-distrito').value,
                pueblo: $('#direccion-pueblo').value,
                senas: $('#direccion-senas').value,
                fincas: elements.farms.value,
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
                await listProducers();
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

    function reactivateExistingProducer() {
        const identificacionNumero = elements.reactivateExisting.dataset.id || '';
        if (!identificacionNumero) return undefined;
        return changeStatus('PATCH', { identificacionNumero }, () => dialogs.close(elements.modal));
    }

    function openDeactivation(producer) {
        productorPendiente = producer;
        elements.deactivateMessage.textContent =
            `${producer.nombre} quedará inactivo. Su dirección, fincas y bitácora se conservarán.`;
        dialogs.open(elements.deactivateModal, { focus: elements.confirmDeactivate });
    }

    function changeStatus(method, producer, afterSuccess = null) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll(
                '[data-action], #confirmar-desactivacion, #reactivar-existente',
            );
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await request(API_URL, {
                    method,
                    body: JSON.stringify({ identificacionNumero: producer.identificacionNumero }),
                });
                afterSuccess?.();
                toast.success(response.message);
                await listProducers();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function deactivateProducer() {
        if (!productorPendiente) return undefined;
        return changeStatus('DELETE', productorPendiente, () => {
            dialogs.close(elements.deactivateModal);
            productorPendiente = null;
        });
    }

    function reactivateProducer(producer) { return changeStatus('PATCH', producer); }

    function closeForm() { if (!submit.busy) { dialogs.close(elements.modal); errores.clearErrors(); } }
    function closeDeactivation() {
        if (statusChange.busy) return;
        dialogs.close(elements.deactivateModal);
        productorPendiente = null;
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => listProducers({ page: 1 }), 300);
    }

    elements.create.addEventListener('click', openCreateForm);
    elements.refresh.addEventListener('click', () => listProducers());
    elements.retry.addEventListener('click', () => listProducers());
    elements.previous.addEventListener('click', () => {
        if (state.page > 1) listProducers({ page: state.page - 1 });
    });
    elements.next.addEventListener('click', () => listProducers({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => listProducers({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.form.addEventListener('submit', saveProducer);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.form.addEventListener('change', errores.clearControlError);
    elements.types.addEventListener('change', updateIdentificationInputMode);
    elements.reactivateExisting.addEventListener('click', reactivateExistingProducer);
    elements.close.addEventListener('click', closeForm);
    elements.cancel.addEventListener('click', closeForm);
    elements.cancelDeactivate.addEventListener('click', closeDeactivation);
    elements.confirmDeactivate.addEventListener('click', deactivateProducer);
    elements.body.addEventListener('click', handleTableAction);
    elements.closeDetail.addEventListener('click', closeDetail);
    elements.closeDetailSecondary.addEventListener('click', closeDetail);
    elements.editFromDetail.addEventListener('click', editFromDetail);
    elements.detailContent.addEventListener('click', handleDetailFincaAction);
    elements.fincaAddressForm.addEventListener('submit', saveFincaAddressSubmit);
    elements.fincaAddressForm.addEventListener('invalid', erroresFinca.markNativeError, true);
    elements.fincaAddressForm.addEventListener('input', erroresFinca.clearControlError);
    elements.fincaAddressForm.addEventListener('change', erroresFinca.clearControlError);
    elements.closeFincaAddress.addEventListener('click', closeFincaAddressDialog);
    elements.cancelFincaAddress.addEventListener('click', closeFincaAddressDialog);
    elements.clearFincaAddress.addEventListener('click', clearFincaAddressSubmit);
    [elements.modal, elements.deactivateModal, elements.detailModal, elements.fincaAddressModal]
        .forEach((dialog) => {
            dialog.addEventListener('click', dialogs.handleBackdropClick);
            dialog.addEventListener('close', dialogs.restoreFocus);
        });


    // La busqueda puede venir en la URL desde el enlace "Abrir panel" de otra
    // capacidad, para caer directamente sobre la misma persona.
    const inicial = new URLSearchParams(window.location.search).get('q');
    if (inicial) elements.search.value = inicial;
    listProducers();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
