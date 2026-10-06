// Verificación de documentos de identidad (P2-6). api.js se versiona por la
// cadena de caché de las rutas admin (MEMORIA.md, Cuidados #11).
import { request } from './shared/api.js?v=auth-gate-8';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/v1/admin/documentos';
const ETIQUETAS = { singular: 'documento', plural: 'documentos' };
const ESTADOS = {
    PENDIENTE: ['Pendiente', 'inactive'], VERIFICADO: ['Verificado', 'active'], RECHAZADO: ['Rechazado', 'inactive'],
};
const TIPOS = {
    CEDULA_FISICA: 'Cédula física', CEDULA_JURIDICA: 'Cédula jurídica', DIMEX: 'DIMEX', NITE: 'NITE', PASAPORTE: 'Pasaporte',
};

/** Fecha UTC de la base en hora local. Exportado para pruebas. */
export function formatFecha(fechaUtc, locale = 'es-CR', timeZone = undefined) {
    const fecha = new Date(`${String(fechaUtc ?? '').replace(' ', 'T')}Z`);
    if (Number.isNaN(fecha.getTime())) return '—';
    return fecha.toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short', timeZone });
}

/** "Cédula física · 1-1111-1111". Exportado para pruebas. */
export function formatIdentificacion(persona = {}) {
    return `${TIPOS[persona.identificacionTipo] ?? persona.identificacionTipo ?? '—'} · ${persona.identificacionNumero ?? '—'}`;
}

/**
 * Lectura automática (OCR en el navegador de la persona; el resultado lo calcula el
 * servidor). Solo ayuda a revisar: el admin decide mirando la imagen. Exportado para pruebas.
 */
export function textoLectura(documento = {}) {
    const leido = documento.numeroLeido ? ` (leído: ${documento.numeroLeido})` : '';
    return {
        COINCIDE: `✅ El número coincide${leido}`,
        NO_COINCIDE: `⚠️ El número no coincide${leido}`,
        OTRA_CUENTA: `⚠️ Es la identificación de otra cuenta${leido}`,
        SIN_LECTURA: '— No se pudo leer el número',
    }[documento.lectura] ?? '— Sin lectura automática';
}

/** Solo un documento pendiente se verifica o rechaza. Exportado para pruebas. */
export function accionesDocumento(documento = {}) {
    return documento.estado === 'PENDIENTE' ? ['ver', 'verificar', 'rechazar'] : ['ver'];
}

/**
 * Abre la pestaña en el mismo clic (si no, el navegador la bloquea) y le carga
 * el enlace firmado cuando llega. Sin acceso a esta página desde la pestaña nueva.
 */
async function abrirDocumento(personaId, toast) {
    const pestaña = window.open('', '_blank');
    if (pestaña) pestaña.opener = null;
    try {
        const response = await request(API_URL, { method: 'POST', body: JSON.stringify({ personaId }) });
        if (pestaña) pestaña.location.replace(response.data.url);
        else toast.warning('El navegador bloqueó la pestaña nueva. Permita las ventanas emergentes para este sitio.');
    } catch (error) {
        pestaña?.close();
        toast.error(error.message);
    }
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-documentos'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-documentos'), total: $('#total-documentos'), search: $('#busqueda-documento'),
        status: $('#filtro-estado'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'),
        next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        rejectModal: $('#modal-rechazar'), rejectForm: $('#formulario-rechazar'), rejectMessage: $('#mensaje-rechazar'),
        rejectClose: $('#cerrar-rechazar'), rejectCancel: $('#cancelar-rechazar'), rejectConfirm: $('#confirmar-rechazar'),
        reason: $('#motivo'),
        verifyModal: $('#modal-verificar'), verifyMessage: $('#mensaje-verificar'),
        verifyCancel: $('#cancelar-verificar'), verifyConfirm: $('#confirmar-verificar'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.rejectForm);
    const submit = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy });
    const personas = new Map();
    let state = createListState({ pageSize: 25 });
    let listController = null;
    let pendiente = null;
    let searchTimer = 0;

    async function list({ page = state.page } = {}) {
        listController?.abort();
        listController = new AbortController();
        const started = nextRequest(state, { page });
        state = started.state;
        render();
        const consulta = { pagina: state.page, tamanoPagina: state.pageSize, estado: elements.status.value };
        if (elements.search.value.trim()) consulta.q = elements.search.value.trim();
        try {
            const response = await request(API_URL, { method: 'POST', body: JSON.stringify({ consulta }), signal: listController.signal });
            const items = Array.isArray(response.data?.personas) ? response.data.personas : [];
            personas.clear();
            items.forEach((item) => personas.set(String(item.personaId), item));
            state = applyResult(state, {
                sequence: started.sequence, items,
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
        state.items.forEach((item) => fragment.appendChild(createRow(item)));
        elements.body.appendChild(fragment);
    }

    function createCell(label, text = '') {
        const cell = document.createElement('td');
        cell.dataset.label = label;
        cell.textContent = text;
        return cell;
    }

    function createButton(action, text, id) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `action action--${{ ver: 'ver', verificar: 'reactivar', rechazar: 'desactivar' }[action]}`;
        button.dataset.action = action;
        button.dataset.id = String(id);
        button.textContent = text;
        return button;
    }

    function createRow(item) {
        const row = document.createElement('tr');
        const [etiqueta, clase] = ESTADOS[item.documento?.estado] ?? [String(item.documento?.estado ?? '—'), 'inactive'];
        const statusCell = createCell('Estado');
        const badge = document.createElement('span');
        badge.className = `badge badge--${clase}`;
        badge.textContent = etiqueta;
        statusCell.appendChild(badge);
        const lectura = document.createElement('small');
        lectura.textContent = ` ${textoLectura(item.documento)}`;
        statusCell.appendChild(lectura);
        if (item.documento?.estado === 'RECHAZADO' && item.documento?.motivo) {
            const motivo = document.createElement('small');
            motivo.textContent = ` ${item.documento.motivo}`;
            statusCell.appendChild(motivo);
        }
        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        const textos = { ver: 'Ver documento', verificar: 'Verificar', rechazar: 'Rechazar' };
        accionesDocumento(item.documento).forEach((accion) => actionsCell.appendChild(createButton(accion, textos[accion], item.personaId)));
        row.append(
            createCell('Persona', item.nombre || '—'),
            createCell('Identificación', formatIdentificacion(item)),
            createCell('Correo', item.correoElectronico || '—'),
            createCell('Enviado', formatFecha(item.documento?.fecha)),
            statusCell,
            actionsCell,
        );
        return row;
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const item = personas.get(button.dataset.id);
        if (!item) return;
        if (button.dataset.action === 'ver') { abrirDocumento(item.personaId, toast); return; }
        pendiente = item;
        if (button.dataset.action === 'verificar') {
            elements.verifyMessage.textContent = `Confirme que revisó el documento de ${item.nombre} y que coincide con ${formatIdentificacion(item)}. Lectura automática: ${textoLectura(item.documento)}.`;
            dialogs.open(elements.verifyModal, { focus: elements.verifyCancel });
        } else {
            errores.clearErrors();
            elements.rejectForm.reset();
            elements.rejectMessage.textContent = `${item.nombre} verá el motivo en Ajustes y podrá subir otro documento.`;
            dialogs.open(elements.rejectModal, { focus: elements.reason });
        }
    }

    function decidir(estado, motivo = '') {
        return submit.run(async () => {
            if (!pendiente) return;
            const boton = estado === 'VERIFICADO' ? elements.verifyConfirm : elements.rejectConfirm;
            boton.disabled = true;
            if (estado === 'RECHAZADO') setSaving(elements.rejectForm, true, { submitButton: boton });
            try {
                const cuerpo = { personaId: pendiente.personaId, estado };
                if (motivo.trim()) cuerpo.motivo = motivo.trim();
                const response = await request(API_URL, { method: 'PATCH', body: JSON.stringify(cuerpo) });
                dialogs.close(estado === 'VERIFICADO' ? elements.verifyModal : elements.rejectModal);
                pendiente = null;
                toast.success(response.message);
                await list();
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                boton.disabled = false;
                if (estado === 'RECHAZADO') setSaving(elements.rejectForm, false, { submitButton: boton });
            }
        });
    }

    function confirmarRechazo(event) {
        event.preventDefault();
        errores.clearErrors();
        if (!elements.rejectForm.checkValidity()) { errores.markFirstInvalid(); return; }
        decidir('RECHAZADO', elements.reason.value);
    }

    function cerrar(modal) {
        if (submit.busy) return;
        dialogs.close(modal);
        pendiente = null;
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => list({ page: 1 }), 300);
    }

    elements.refresh.addEventListener('click', () => list());
    elements.retry.addEventListener('click', () => list());
    elements.previous.addEventListener('click', () => { if (state.page > 1) list({ page: state.page - 1 }); });
    elements.next.addEventListener('click', () => list({ page: state.page + 1 }));
    elements.status.addEventListener('change', () => list({ page: 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    elements.body.addEventListener('click', handleTableAction);
    elements.rejectForm.addEventListener('submit', confirmarRechazo);
    elements.rejectForm.addEventListener('invalid', errores.markNativeError, true);
    elements.rejectForm.addEventListener('input', errores.clearControlError);
    elements.rejectClose.addEventListener('click', () => cerrar(elements.rejectModal));
    elements.rejectCancel.addEventListener('click', () => cerrar(elements.rejectModal));
    elements.verifyCancel.addEventListener('click', () => cerrar(elements.verifyModal));
    elements.verifyConfirm.addEventListener('click', () => decidir('VERIFICADO'));
    for (const modal of [elements.rejectModal, elements.verifyModal]) {
        modal.addEventListener('click', dialogs.handleBackdropClick);
        modal.addEventListener('close', dialogs.restoreFocus);
    }

    list();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
