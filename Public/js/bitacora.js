// Visor de la bitácora (P3-4), solo lectura. api.js se versiona por la cadena
// de caché de las rutas admin (MEMORIA.md, Cuidados #11).
import { request } from './shared/api.js?v=auth-gate-18';
import { createDialogController } from './shared/dialog.js?v=dialog-2';
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

const ACCIONES = {
    CREAR: 'Creación', EDITAR: 'Edición', ELIMINAR: 'Eliminación', DESACTIVAR: 'Desactivación', REACTIVAR: 'Reactivación',
    REGISTRO_PUBLICO: 'Registro público', PUJAR: 'Puja', COMENTAR: 'Comentario', FINALIZAR: 'Cierre', CANCELAR: 'Cancelación',
    ACEPTAR: 'Aceptación', RECHAZAR: 'Rechazo', ACEPTAR_FLETE: 'Flete aceptado', RECHAZAR_FLETE: 'Flete rechazado',
    VER_DOCUMENTO: 'Documento consultado', VERIFICAR: 'Verificación', PESAR: 'Pesaje',
};
const AREAS = {
    PERSONA: 'Persona', SUBASTA: 'Subasta', PUBLICACION: 'Publicación', PUBLICACION_INTERACCION: 'Me interesa', OFERTA_FLETE: 'Oferta de flete',
    VEHICULO: 'Vehículo', TRANSPORTISTA_VEHICULO: 'Vehículo', FINCA: 'Finca', PRODUCTORUBICACION: 'Finca', ANIMAL: 'Animal',
    ANIMAL_VACUNACION: 'Vacuna', COMPRA_SOLICITUD: 'Solicitud de compra', PAGOMETODO: 'Método de pago', ADMINISTRADOR: 'Administrador',
    CATALOGO: 'Catálogo', DOCUMENTO: 'Documento', COMPRADOR: 'Comprador', PRODUCTOR: 'Productor', TRANSPORTISTA: 'Transportista',
};

/** «REGISTRO_PUBLICO» → «Registro público» cuando no hay nombre propio. Puro y exportado para pruebas. */
export function humanizar(clave) {
    const texto = String(clave ?? '').replace(/^API_/, '').replaceAll('_', ' ').toLowerCase().trim();
    return texto ? texto[0].toUpperCase() + texto.slice(1) : '—';
}
export function nombreAccion(accion) { return ACCIONES[accion] ?? humanizar(accion); }
export function nombreAreaBitacora(entidad) { return AREAS[entidad] ?? humanizar(entidad); }
/** «API_REGISTRO_PUBLICO» → «Registro público»: el origen se nombra como su acción si la hay. Puro. */
export function nombreOrigen(origen) { return nombreAccion(String(origen ?? '').replace(/^API_/, '')); }

/** «capacidadesCreadas» → «Capacidades creadas»; «personaId» → «Persona (id)». Puro y exportado para pruebas. */
export function nombreCampo(clave) {
    const k = String(clave);
    if (/^[a-z]+Id$/.test(k)) return `${humanizar(k.slice(0, -2))} (id)`;
    return humanizar(k.replace(/([a-z0-9])([A-Z])/g, '$1_$2'));
}

/** Valor legible: listas separadas por coma, sí/no, objetos como «campo: valor». Puro y exportado para pruebas. */
export function valorLegible(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    if (typeof valor === 'boolean') return valor ? 'Sí' : 'No';
    if (Array.isArray(valor)) return valor.length ? valor.map((v) => (typeof v === 'object' && v !== null ? valorLegible(v) : /^[A-Z_]+$/.test(String(v)) ? humanizar(v) : String(v))).join(', ') : '—';
    if (typeof valor === 'object') return Object.entries(valor).map(([k, v]) => `${nombreCampo(k)}: ${valorLegible(v)}`).join(' · ') || '—';
    return /^[A-Z][A-Z_]+$/.test(String(valor)) ? humanizar(valor) : String(valor);
}

/**
 * Filas «campo, antes, después» del evento. Con datos anteriores solo salen los campos que cambiaron; sin ellos (una
 * creación) salen todos los nuevos. Puro y exportado para pruebas.
 */
export function cambiosEvento(anteriores, nuevos) {
    const objeto = (v) => (v && typeof v === 'object' && !Array.isArray(v) ? v : (v == null ? {} : { valor: v }));
    const antes = objeto(anteriores);
    const despues = objeto(nuevos);
    const claves = [...new Set([...Object.keys(antes), ...Object.keys(despues)])].filter((k) => k !== 'realizadoPor');
    const hayAntes = anteriores != null;
    return claves
        .filter((k) => !hayAntes || JSON.stringify(antes[k]) !== JSON.stringify(despues[k]))
        .map((k) => ({ campo: nombreCampo(k), antes: hayAntes ? valorLegible(antes[k]) : null, despues: valorLegible(despues[k]) }));
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
        button.setAttribute('aria-label', `Ver detalle: ${nombreAccion(item.accion)} de ${nombreAreaBitacora(item.entidad)}`);
        actions.appendChild(button);
        row.append(
            createCell('Fecha', formatFecha(item.fecha)),
            createCell('Entidad', nombreAreaBitacora(item.entidad)),
            createCell('Acción', nombreAccion(item.accion)),
            createCell('Registro', item.registro),
            createCell('Hecho por', describirActor(item)),
            actions,
        );
        return row;
    }

    function detailRow(label, value) {
        const dt = document.createElement('dt');
        dt.textContent = label;
        const dd = document.createElement('dd');
        dd.textContent = value ?? '—';
        return [dt, dd];
    }

    /** Tabla de cambios legible; el JSON original queda plegado para quien audite el dato exacto. */
    function cambios(item) {
        const filas = cambiosEvento(item.datosAnteriores, item.datosNuevos);
        const seccion = document.createElement('section');
        seccion.className = 'bitacora-cambios detail--full';
        const titulo = document.createElement('h3');
        titulo.textContent = item.datosAnteriores == null ? 'Datos registrados' : 'Qué cambió';
        seccion.appendChild(titulo);
        if (!filas.length) {
            const vacio = document.createElement('p');
            vacio.textContent = 'El evento no guarda datos.';
            seccion.appendChild(vacio);
        } else {
            const tabla = document.createElement('table');
            const cabeza = document.createElement('tr');
            for (const texto of ['Campo', ...(item.datosAnteriores == null ? [] : ['Antes']), item.datosAnteriores == null ? 'Valor' : 'Después']) {
                const th = document.createElement('th');
                th.scope = 'col';
                th.textContent = texto;
                cabeza.appendChild(th);
            }
            tabla.createTHead().appendChild(cabeza);
            const cuerpo = tabla.createTBody();
            for (const f of filas) {
                const tr = cuerpo.insertRow();
                const th = document.createElement('th');
                th.scope = 'row';
                th.textContent = f.campo;
                tr.appendChild(th);
                for (const v of f.antes === null ? [f.despues] : [f.antes, f.despues]) tr.insertCell().textContent = v;
            }
            seccion.appendChild(tabla);
        }
        const tecnico = document.createElement('details');
        const resumen = document.createElement('summary');
        resumen.textContent = 'Ver datos técnicos';
        const pre = document.createElement('pre');
        pre.textContent = JSON.stringify({ antes: item.datosAnteriores ?? null, despues: item.datosNuevos ?? null }, null, 2);
        tecnico.append(resumen, pre);
        seccion.appendChild(tecnico);
        return seccion;
    }

    function openDetail(item) {
        elements.detailTitle.textContent = `${nombreAccion(item.accion)} · ${nombreAreaBitacora(item.entidad)}`;
        elements.detailContent.replaceChildren(
            ...detailRow('Fecha', formatFecha(item.fecha)),
            ...detailRow('Registro', item.registro),
            ...detailRow('Hecho por', describirActor(item)),
            ...detailRow('Desde', nombreOrigen(item.origen)),
            cambios(item),
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
