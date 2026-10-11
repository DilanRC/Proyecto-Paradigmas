// Subastas en el panel admin (DEC-SUBASTA-001): solo lectura, en vivo. api.js se versiona por la cadena de caché de
// las rutas admin (MEMORIA.md, Cuidados #11). El tiempo real llega por Supabase Realtime (shared/tiempo-real.js).
import { request } from './shared/api.js?v=auth-gate-11';
import { createDialogController } from './shared/dialog.js';
import { createToast } from './shared/toast.js';
import { agrupar, alCambiarConexion, escuchar } from './shared/tiempo-real.js?v=tiempo-real-1';

const API_URL = 'api/v1/admin/subastas';
const ESTADOS = { ACTIVA: ['En vivo', 'active'], FINALIZADA: ['Finalizada', 'inactive'], DESIERTA: ['Sin pujas', 'inactive'], CANCELADA: ['Cancelada', 'inactive'] };

/** ₡1.250.000 (igual que formatPrice del sitio público). Exportado para pruebas. */
export function colones(monto) {
    if (typeof monto !== 'number' || !Number.isFinite(monto)) return '—';
    return `₡${String(Math.round(monto)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

function fecha(iso) {
    const d = new Date(iso ?? '');
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('es-CR', { dateStyle: 'short', timeStyle: 'short' });
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const el = {
        body: $('#cuerpo-subastas'), empty: $('#estado-vacio'), error: $('#estado-error'), errorMessage: $('#mensaje-error'),
        retry: $('#reintentar'), loading: $('#estado-carga'), panel: $('#panel-subastas'), total: $('#total-subastas'),
        refresh: $('#actualizar-lista'), vista: $('#filtro-vista'), busqueda: $('#filtro-busqueda'), vivo: $('#estado-vivo'),
        modal: $('#modal-detalle'), title: $('#titulo-detalle'), subtitle: $('#subtitulo-detalle'), close: $('#cerrar-detalle'),
        datos: $('#detalle-datos'), pujas: $('#detalle-pujas'), comentarios: $('#detalle-comentarios'),
    };
    if (!el.body) return;
    const toast = createToast({ polite: $('#toast-status'), assertive: $('#toast-alert') });
    const dialogs = createDialogController();
    let detalleId = null;
    let dejarDetalle = null;

    const cell = (label, text = '') => {
        const td = document.createElement('td');
        td.dataset.label = label;
        td.textContent = text;
        return td;
    };

    const par = (dl, termino, valor) => {
        const dt = document.createElement('dt');
        dt.textContent = termino;
        const dd = document.createElement('dd');
        dd.textContent = valor ?? '—';
        dl.append(dt, dd);
    };

    function vista(estado, mensaje = '') {
        el.loading.hidden = estado !== 'loading';
        el.error.hidden = estado !== 'error';
        el.empty.hidden = estado !== 'empty';
        el.panel.setAttribute('aria-busy', String(estado === 'loading'));
        if (mensaje) el.errorMessage.textContent = mensaje;
    }

    function fila(s) {
        const tr = document.createElement('tr');
        const [etiqueta, clase] = ESTADOS[s.estado] ?? [s.estado, 'inactive'];
        const estado = cell('Estado');
        const badge = document.createElement('span');
        badge.className = `badge badge--${clase}`;
        badge.textContent = etiqueta;
        estado.append(badge);
        const acciones = cell('Acciones');
        acciones.className = 'row-actions';
        const ver = document.createElement('button');
        ver.type = 'button';
        ver.className = 'action action--editar';
        ver.textContent = 'Ver';
        ver.addEventListener('click', () => abrirDetalle(s.subastaId));
        acciones.append(ver);
        tr.append(
            cell('Subasta', s.titulo),
            cell('Vendedor', s.vendedor?.nombre ?? '—'),
            cell('Animal', [s.animal?.tipo || s.animal?.especie, s.animal?.raza].filter(Boolean).join(' · ') || '—'),
            cell('Precio', s.precioActual == null ? `${colones(s.precioBase)} (base)` : colones(s.precioActual)),
            cell('Pujas', String(s.pujasTotal)),
            cell('Cierre', fecha(s.fechaCierre ?? s.fechaFin)),
            estado,
            acciones,
        );
        return tr;
    }

    async function cargar({ silencioso = false } = {}) {
        if (!silencioso) vista('loading');
        try {
            const respuesta = await request(API_URL, { method: 'POST', body: JSON.stringify({ consulta: { vista: el.vista.value, q: el.busqueda.value.trim() } }) });
            const subastas = respuesta.data?.subastas ?? [];
            el.body.replaceChildren(...subastas.map(fila));
            el.total.textContent = `${respuesta.data?.total ?? subastas.length} subasta${subastas.length === 1 ? '' : 's'}`;
            vista(subastas.length ? 'content' : 'empty');
        } catch (error) {
            if (!silencioso) vista('error', error?.message || 'Intente de nuevo.');
        }
    }

    async function pintarDetalle() {
        if (detalleId === null) return;
        try {
            const s = (await request(API_URL, { method: 'POST', body: JSON.stringify({ subastaId: detalleId }) })).data.subasta;
            el.title.textContent = s.titulo;
            el.subtitle.textContent = (ESTADOS[s.estado] ?? [s.estado])[0];
            el.datos.replaceChildren();
            par(el.datos, 'Vendedor', s.vendedor?.nombre);
            par(el.datos, 'Finca', [s.finca?.nombre, s.finca?.canton, s.finca?.provincia].filter(Boolean).join(', '));
            par(el.datos, 'Animal', [s.animal?.tipo || s.animal?.especie, s.animal?.raza, s.animal?.sexo].filter(Boolean).join(' · '));
            par(el.datos, 'Precio base', colones(s.precioBase));
            par(el.datos, 'Incremento mínimo', colones(s.incrementoMinimo));
            par(el.datos, 'Puja más alta', colones(s.precioActual));
            par(el.datos, 'Inicio', fecha(s.fechaInicio));
            par(el.datos, s.estado === 'ACTIVA' ? 'Termina' : 'Cerró', fecha(s.fechaCierre ?? s.fechaFin));
            if (s.ganador) par(el.datos, 'Ganador', s.ganador.nombre);
            el.pujas.replaceChildren();
            if (!s.pujas.length) par(el.pujas, 'Sin pujas', 'Nadie ha pujado todavía.');
            for (const p of s.pujas) par(el.pujas, `${colones(p.monto)} · ${fecha(p.fecha)}`, p.participante);
            el.comentarios.replaceChildren();
            if (!s.comentarios.length) par(el.comentarios, 'Sin comentarios', '—');
            for (const c of s.comentarios) par(el.comentarios, `${c.autor} · ${fecha(c.fecha)}`, c.texto);
        } catch (error) {
            toast.error(error?.message || 'No fue posible cargar la subasta.');
        }
    }

    async function abrirDetalle(id) {
        detalleId = id;
        dejarDetalle?.();
        dejarDetalle = escuchar(`subasta-${id}`, agrupar(pintarDetalle));
        await pintarDetalle();
        dialogs.open(el.modal, { focus: el.close });
    }

    el.close.addEventListener('click', () => dialogs.close(el.modal));
    el.modal.addEventListener('close', () => { detalleId = null; dejarDetalle?.(); dejarDetalle = null; });
    el.vista.addEventListener('change', () => cargar());
    let temporizador = null;
    el.busqueda.addEventListener('input', () => { clearTimeout(temporizador); temporizador = setTimeout(() => cargar(), 350); });
    el.refresh.addEventListener('click', () => cargar());
    el.retry.addEventListener('click', () => cargar());
    alCambiarConexion((conectado) => { el.vivo.textContent = conectado ? 'En vivo' : 'Reconectando…'; });
    escuchar('subastas', agrupar(() => cargar({ silencioso: true })));
    setInterval(() => cargar({ silencioso: true }), 30000);
    cargar();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
