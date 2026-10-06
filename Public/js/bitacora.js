// Visor de la bitácora (P3-4), solo lectura. api.js se versiona por la cadena
// de caché de las rutas admin (MEMORIA.md, Cuidados #11).
import { request } from './shared/api.js?v=auth-gate-7';
import { createDialogController } from './shared/dialog.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';

const API_URL = 'api/v1/admin/bitacora';
const ETIQUETAS = { singular: 'evento', plural: 'eventos' };
const ACTORES = {
    PERSONA_AUTENTICADA: 'Persona',
    USUARIO_VERIFICADO: 'Cuenta sin perfil',
    NO_AUTENTICADO: 'Sistema',
};

/** La bitácora guarda la fecha en UTC; se muestra en la hora local. Exportado para pruebas. */
export function formatFecha(fechaUtc, locale = 'es-CR', timeZone = undefined) {
    const fecha = new Date(`${String(fechaUtc ?? '').replace(' ', 'T')}Z`);
    if (Number.isNaN(fecha.getTime())) return String(fechaUtc ?? '—');
    return fecha.toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short', timeZone });
}

/** Quién hizo el cambio. Un admin sin Persona deja su correo en "realizadoPor". Exportado para pruebas. */
export function describirActor(evento = {}) {
    if (evento.actor?.nombre) return evento.actor.nombre;
    const correo = evento.datosNuevos?.realizadoPor;
    if (typeof correo === 'string' && correo !== '') return correo;
    return ACTORES[evento.actor?.tipo] ?? 'Desconocido';
}

/** Filtros que se envían; los vacíos no viajan. Exportado para pruebas. */
export function buildConsulta({ q = '', entidad = '', desde = '', hasta = '' }, page, pageSize) {
    const consulta = { pagina: page, tamanoPagina: pageSize };
    if (q.trim()) consulta.q = q.trim();
    if (entidad) consulta.entidad = entidad;
    if (desde) consulta.desde = desde;
    if (hasta) consulta.hasta = hasta;
    return consulta;
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-bitacora'), empty: $('#estado-vacio'), error: $('#estado-error'),
        errorMessage: $('#mensaje-error'), retry: $('#reintentar'), loading: $('#estado-carga'),
        panel: $('#panel-bitacora'), total: $('#total-bitacora'), search: $('#busqueda-bitacora'),
        entity: $('#filtro-entidad'), from: $('#filtro-desde'), to: $('#filtro-hasta'),
        filterError: $('#error-filtros'), refresh: $('#actualizar-lista'), previous: $('#pagina-anterior'),
        next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        detail: $('#modal-detalle'), detailTitle: $('#titulo-detalle'), detailContent: $('#detalle-contenido'),
        detailClose: $('#cerrar-detalle'), detailClose2: $('#cerrar-detalle-secundario'),
    };
    const dialogs = createDialogController({ isBusy: () => false });
    const eventos = new Map();
    let state = createListState({ pageSize: 25 });
    let listController = null;
    let searchTimer = 0;

    async function list({ page = state.page } = {}) {
        listController?.abort();
        listController = new AbortController();
        const started = nextRequest(state, { page });
        state = started.state;
        elements.filterError.textContent = '';
        render();
        const consulta = buildConsulta({
            q: elements.search.value, entidad: elements.entity.value, desde: elements.from.value, hasta: elements.to.value,
        }, state.page, state.pageSize);
        try {
            const response = await request(API_URL, { method: 'POST', body: JSON.stringify({ consulta }), signal: listController.signal });
            const items = Array.isArray(response.data?.eventos) ? response.data.eventos : [];
            eventos.clear();
            items.forEach((item) => eventos.set(String(item.bitacoraId), item));
            renderEntidades(response.data?.entidades);
            state = applyResult(state, {
                sequence: started.sequence, items,
                total: Number(response.data?.total) || 0,
                page: Number(response.data?.pagina) || state.page,
                pageSize: Number(response.data?.tamanoPagina) || state.pageSize,
            });
        } catch (error) {
            if (error.name === 'AbortError') { state = applyAbort(state); return; }
            if (error.errors) elements.filterError.textContent = Object.values(error.errors).join(' ');
            state = applyFailure(state, { sequence: started.sequence, error });
        }
        render();
    }

    function renderEntidades(entidades) {
        if (!Array.isArray(entidades)) return;
        const actual = elements.entity.value;
        const opciones = [new Option('Todas', '')];
        entidades.forEach((entidad) => opciones.push(new Option(entidad, entidad)));
        elements.entity.replaceChildren(...opciones);
        elements.entity.value = entidades.includes(actual) ? actual : '';
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

    function createRow(item) {
        const row = document.createElement('tr');
        const actions = createCell('Detalle');
        actions.className = 'row-actions';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'action action--ver';
        button.dataset.id = String(item.bitacoraId);
        button.textContent = 'Ver detalle';
        actions.appendChild(button);
        row.append(
            createCell('Fecha', formatFecha(item.fecha)),
            createCell('Entidad', item.entidad),
            createCell('Acción', item.accion),
            createCell('Registro', item.registro),
            createCell('Hecho por', describirActor(item)),
            actions,
        );
        return row;
    }

    function detailRow(label, value, { json = false } = {}) {
        const dt = document.createElement('dt');
        dt.textContent = label;
        const dd = document.createElement('dd');
        if (json) {
            const pre = document.createElement('pre');
            pre.textContent = value == null ? '—' : JSON.stringify(value, null, 2);
            dd.appendChild(pre);
        } else {
            dd.textContent = value ?? '—';
        }
        return [dt, dd];
    }

    function openDetail(item) {
        elements.detailTitle.textContent = `${item.accion} · ${item.entidad}`;
        elements.detailContent.replaceChildren(
            ...detailRow('Fecha', formatFecha(item.fecha)),
            ...detailRow('Registro', item.registro),
            ...detailRow('Hecho por', describirActor(item)),
            ...detailRow('Origen', item.origen),
            ...detailRow('Datos anteriores', item.datosAnteriores, { json: true }),
            ...detailRow('Datos nuevos', item.datosNuevos, { json: true }),
        );
        dialogs.open(elements.detail, { focus: elements.detailClose2 });
    }

    function scheduleSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => list({ page: 1 }), 300);
    }

    elements.body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-id]');
        const item = button && eventos.get(button.dataset.id);
        if (item) openDetail(item);
    });
    elements.refresh.addEventListener('click', () => list());
    elements.retry.addEventListener('click', () => list());
    elements.previous.addEventListener('click', () => { if (state.page > 1) list({ page: state.page - 1 }); });
    elements.next.addEventListener('click', () => list({ page: state.page + 1 }));
    elements.search.addEventListener('input', scheduleSearch);
    for (const control of [elements.entity, elements.from, elements.to]) control.addEventListener('change', () => list({ page: 1 }));
    for (const boton of [elements.detailClose, elements.detailClose2]) boton.addEventListener('click', () => dialogs.close(elements.detail));
    elements.detail.addEventListener('click', dialogs.handleBackdropClick);
    elements.detail.addEventListener('close', dialogs.restoreFocus);

    list();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
