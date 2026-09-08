// Panel de compradores: solo lectura.
// Comprador es un contexto de negocio relacionado con Persona; no se administra
// como un rol manual desde esta pantalla.

import { request } from './shared/api.js';
import { consultarCapacidades, describirCapacidad } from './shared/capacidades.js';
import { createDialogController } from './shared/dialog.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/compradores.php';
const ETIQUETAS = { singular: 'comprador', plural: 'compradores' };

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
        search: $('#busqueda-comprador'), refresh: $('#actualizar-lista'),
        previous: $('#pagina-anterior'), next: $('#pagina-siguiente'), page: $('#pagina-actual'),
        detailModal: $('#modal-detalle'), detailTitle: $('#titulo-detalle'),
        detailContent: $('#detalle-contenido'), capacities: $('#lista-capacidades'),
        closeDetail: $('#cerrar-detalle'), closeDetailSecondary: $('#cerrar-detalle-secundario'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };

    const compradores = new Map();
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const dialogs = createDialogController();
    let state = createListState({ pageSize: 25 });
    let listController = null;
    let capacityController = null;

    function render() {
        const view = deriveListView(state, ETIQUETAS);
        elements.loading.hidden = !view.showSkeleton;
        elements.panel.setAttribute('aria-busy', view.showSkeleton ? 'true' : 'false');
        elements.empty.hidden = !view.showEmpty;
        elements.error.hidden = !view.showError;
        elements.errorMessage.textContent = view.errorMessage;
        elements.retry.hidden = !view.canRetry;
        elements.total.textContent = view.showError ? '' : view.totalLabel;
        elements.page.textContent = view.pageLabel;
        elements.previous.disabled = view.previousDisabled;
        elements.next.disabled = view.nextDisabled;
        elements.refresh.disabled = view.refreshDisabled;
        elements.body.replaceChildren(view.showList ? createRows(state.items) : document.createDocumentFragment());
    }

    function createRows(items) {
        const fragment = document.createDocumentFragment();
        items.forEach((item) => fragment.appendChild(createRow(item)));
        return fragment;
    }

    function createRow(item) {
        const row = document.createElement('tr');

        const persona = document.createElement('td');
        const identidad = document.createElement('button');
        identidad.type = 'button';
        identidad.className = 'link-button identity';
        identidad.dataset.identificacion = item.identificacionNumero;
        identidad.setAttribute('aria-label', `Ver comprador ${item.nombre}`);
        const avatar = document.createElement('span');
        avatar.className = 'avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = getInitials(item.nombre);
        const nombre = document.createElement('span');
        nombre.textContent = item.nombre || 'Sin nombre';
        identidad.append(avatar, nombre);
        persona.appendChild(identidad);

        const alias = document.createElement('td');
        alias.textContent = item.alias || '—';

        const identificacion = document.createElement('td');
        identificacion.textContent = item.identificacionNumero;

        const contacto = document.createElement('td');
        const correo = document.createElement('span');
        correo.textContent = item.correoElectronico || 'Sin correo';
        const telefono = document.createElement('small');
        telefono.textContent = item.telefono || 'Sin teléfono';
        contacto.append(correo, document.createElement('br'), telefono);

        const estado = document.createElement('td');
        const activo = item.estado === 'ACTIVO';
        const badge = document.createElement('span');
        badge.className = `badge badge--${activo ? 'active' : 'inactive'}`;
        badge.textContent = activo ? 'Activo' : 'Inactivo';
        estado.appendChild(badge);

        row.append(persona, alias, identificacion, contacto, estado);
        return row;
    }

    async function load({ page = state.page } = {}) {
        listController?.abort();
        listController = new AbortController();
        const { signal } = listController;
        const opened = nextRequest(state, { page });
        state = opened.state;
        render();

        const parametros = new URLSearchParams({
            q: elements.search.value.trim(),
            pagina: String(state.page),
            tamanoPagina: String(state.pageSize),
        });
        try {
            const respuesta = await request(`${API_URL}?${parametros}`, { signal });
            const datos = respuesta.data ?? {};
            const items = Array.isArray(datos.compradores) ? datos.compradores : [];
            compradores.clear();
            items.forEach((item) => compradores.set(item.identificacionNumero, item));
            state = applyResult(state, {
                sequence: opened.sequence,
                items,
                total: Number(datos.total) || 0,
                page: Number(datos.pagina) || state.page,
                pageSize: Number(datos.tamanoPagina) || state.pageSize,
            });
        } catch (error) {
            if (error?.name === 'AbortError') {
                state = applyAbort(state);
                return;
            }
            state = applyFailure(state, { sequence: opened.sequence, error });
        }
        render();
    }

    async function openDetail(identificacionNumero) {
        const item = compradores.get(identificacionNumero);
        if (!item) return;
        elements.detailTitle.textContent = item.nombre || 'Comprador';
        elements.detailContent.replaceChildren(createDetail(item));
        dialogs.open(elements.detailModal, { focus: elements.closeDetail });

        capacityController?.abort();
        capacityController = new AbortController();
        const { signal } = capacityController;
        elements.capacities.setAttribute('aria-busy', 'true');
        elements.capacities.replaceChildren();
        try {
            const capacidades = await consultarCapacidades(identificacionNumero, {
                requestImpl: (url) => request(url, { signal }),
            });
            if (signal.aborted) return;
            elements.capacities.setAttribute('aria-busy', 'false');
            const fragment = document.createDocumentFragment();
            capacidades.forEach((capacidad) => fragment.appendChild(createCapacityItem(capacidad)));
            elements.capacities.replaceChildren(fragment);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            elements.capacities.setAttribute('aria-busy', 'false');
            toast.alert(error?.message ?? 'No fue posible consultar las relaciones de la persona.');
        }
    }

    function createDetail(item) {
        const fragment = document.createDocumentFragment();
        const filas = [
            ['Identificación', `${item.identificacion?.tipoCodigo ?? ''} ${item.identificacionNumero}`.trim()],
            ['Nombre', item.nombre],
            ['Alias', item.alias || '—'],
            ['Teléfono', item.telefono || '—'],
            ['Correo electrónico', item.correoElectronico || '—'],
            ['Estado', item.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'],
        ];
        filas.forEach(([etiqueta, valor]) => {
            const dt = document.createElement('dt');
            dt.textContent = etiqueta;
            const dd = document.createElement('dd');
            dd.textContent = valor ?? '';
            fragment.append(dt, dd);
        });
        return fragment;
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
    }

    let debounce = null;
    elements.search.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => load({ page: 1 }), 250);
    });
    elements.refresh.addEventListener('click', () => load());
    elements.retry.addEventListener('click', () => load());
    elements.previous.addEventListener('click', () => load({ page: Math.max(1, state.page - 1) }));
    elements.next.addEventListener('click', () => load({ page: state.page + 1 }));
    elements.body.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-identificacion]');
        if (boton) openDetail(boton.dataset.identificacion);
    });
    elements.closeDetail.addEventListener('click', closeDetail);
    elements.closeDetailSecondary.addEventListener('click', closeDetail);
    elements.detailModal.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDetail();
    });
    elements.detailModal.addEventListener('click', dialogs.handleBackdropClick);
    elements.detailModal.addEventListener('close', dialogs.restoreFocus);

    const consulta = new URLSearchParams(window.location.search).get('q');
    if (consulta) elements.search.value = consulta;
    load({ page: 1 });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
}
