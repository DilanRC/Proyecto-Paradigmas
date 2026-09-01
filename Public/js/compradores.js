// Panel de compradores: solo lectura.
//
// El CRUD legacy se retiró en el paso (d). Comprador no es un registro que
// alguien dé de alta: es una clasificación del Productor cuya única fuente de
// verdad es `tbproductorclasificacionperiodo` con un periodo COMPRADOR abierto.
//
// Este módulo no tiene formulario ni acciones de crear, editar, desactivar o
// reactivar. Mientras T10 no exista, muestra únicamente clasificaciones ya
// registradas o migradas; no genera nuevas.

import { request } from './shared/api.js';
import { consultarCapacidades, describirCapacidad } from './shared/capacidades.js';
import { createDialogController } from './shared/dialog.js';
import {
    applyAbort, applyFailure, applyResult, createListState, deriveListView, nextRequest,
} from './shared/list-state.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/compradores.php';
const ETIQUETAS = { singular: 'productor clasificado', plural: 'productores clasificados' };

/** Iniciales del productor clasificado; la letra de reserva es de este panel. */
function getInitials(name = '') {
    return name.split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase()).join('') || 'C';
}

/** Fecha legible del inicio de la clasificación. Pura, para poder probarla. */
export function formatearClasificadoDesde(valor) {
    if (typeof valor !== 'string' || valor.trim() === '') return 'Sin fecha registrada';
    const fecha = new Date(valor.replace(' ', 'T'));
    if (Number.isNaN(fecha.getTime())) return valor;
    return fecha.toLocaleDateString('es-CR', { year: 'numeric', month: 'long', day: 'numeric' });
}

/** Traduce motivos técnicos/legacy a texto defendible para la vista. */
export function describirOrigen(motivo) {
    const etiquetas = {
        MIGRACION_TBCOMPRADOR_LEGACY: 'Migración del registro anterior',
        ALTA_CRUD_COMPRADOR: 'Alta registrada antes del retiro del CRUD',
        REACTIVACION_CRUD_COMPRADOR: 'Reactivación registrada antes del retiro del CRUD',
    };
    if (typeof motivo !== 'string' || motivo.trim() === '') return 'Sin origen declarado';
    return etiquetas[motivo] ?? motivo;
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

    const clasificados = new Map();
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
        const avatar = document.createElement('span');
        avatar.className = 'avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = getInitials(item.nombre);
        const nombre = document.createElement('span');
        nombre.textContent = item.nombre;
        identidad.append(avatar, nombre);
        identidad.setAttribute('aria-label', `Ver la clasificación de ${item.nombre}`);
        persona.appendChild(identidad);

        const identificacion = document.createElement('td');
        identificacion.textContent = item.identificacionNumero;

        const contacto = document.createElement('td');
        const correo = document.createElement('span');
        correo.textContent = item.correoElectronico;
        const telefono = document.createElement('small');
        telefono.textContent = item.telefono;
        contacto.append(correo, document.createElement('br'), telefono);

        const desde = document.createElement('td');
        desde.textContent = formatearClasificadoDesde(item.clasificadoDesde);

        const origen = document.createElement('td');
        origen.textContent = describirOrigen(item.motivo);

        const disponibilidad = document.createElement('td');
        const personaActiva = item.personaEstado === 'ACTIVA';
        const badge = document.createElement('span');
        badge.className = `badge badge--${personaActiva ? 'active' : 'inactive'}`;
        badge.textContent = personaActiva ? 'Persona activa' : 'Persona inactiva';
        disponibilidad.appendChild(badge);

        row.append(persona, identificacion, contacto, desde, origen, disponibilidad);
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
            const items = datos.clasificados ?? [];
            clasificados.clear();
            items.forEach((item) => clasificados.set(item.identificacionNumero, item));
            state = applyResult(state, {
                sequence: opened.sequence,
                items,
                total: datos.total ?? items.length,
                page: datos.pagina ?? state.page,
                pageSize: datos.tamanoPagina ?? state.pageSize,
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
        const item = clasificados.get(identificacionNumero);
        if (!item) return;
        elements.detailTitle.textContent = item.nombre;
        elements.detailContent.replaceChildren(createDetail(item));
        dialogs.open(elements.detailModal);

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
            ['Teléfono', item.telefono],
            ['Correo electrónico', item.correoElectronico],
            ['Clasificado desde', formatearClasificadoDesde(item.clasificadoDesde)],
            ['Origen de la clasificación', describirOrigen(item.motivo)],
            ['Disponibilidad de la persona', item.personaEstado === 'ACTIVA' ? 'Activa' : 'Inactiva'],
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
