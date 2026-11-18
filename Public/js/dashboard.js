// Centro de inteligencia del administrador (/admin/dashboard, DEC-DASHBOARD-002). Una sola API de solo lectura
// (api/v1/admin/dashboard) autorizada en PHP. Tres niveles: visión global (KPI y red ganadera), procesos (árbol
// accesible con <details>: cada nodo trae conteo, definición, fuente, periodo y su evidencia paginada, que se pide al
// abrirla) y expediente individual (transportistas y personas). Lo que ningún flujo registra se marca
// «sin instrumentar» en vez de pintar un 0. Sin imágenes decorativas (decisión del PR #30). Todo con textContent.

import { request } from './shared/api.js?v=auth-gate-15';
import { el } from './dashboard-graficos.js?v=graficos-3';

const API = 'api/v1/admin/dashboard';
const ZONA = 'America/Costa_Rica';
const $ = (selector) => document.querySelector(selector);
const estado = { periodo: {}, pedido: 0 };

// ───────────────────────────── Funciones puras ─────────────────────────────

export function formatoNumero(valor) {
    return typeof valor === 'number' && Number.isFinite(valor) ? new Intl.NumberFormat('es-CR').format(valor) : '—';
}

export function formatoColones(valor) {
    if (typeof valor !== 'number' || !Number.isFinite(valor)) return '—';
    return `₡${new Intl.NumberFormat('es-CR', { maximumFractionDigits: 0 }).format(valor)}`;
}

export function formatoPorcentaje(valor) {
    return typeof valor === 'number' && Number.isFinite(valor) ? `${valor.toLocaleString('es-CR', { maximumFractionDigits: 1 })} %` : '—';
}

/** Suma de un objeto { clave: n } (conteos por estado). Puro. */
export function sumar(conteo = {}) {
    return Object.values(conteo ?? {}).reduce((total, n) => total + (Number(n) || 0), 0);
}

/** Desde/hasta AAAA-MM-DD (hora de Costa Rica) del formulario de periodo. Puro. */
export function parametrosPeriodo(valores, hoy = new Date()) {
    const dia = (fecha) => fecha.toLocaleDateString('en-CA', { timeZone: ZONA });
    if (valores.periodo === 'todo') return {};
    if (valores.periodo === 'rango') {
        return Object.fromEntries(['desde', 'hasta'].filter((k) => valores[k]).map((k) => [k, valores[k]]));
    }
    const dias = Number(valores.periodo) || 30;
    return { desde: dia(new Date(hoy.getTime() - (dias - 1) * 86400000)), hasta: dia(hoy) };
}

export function textoPeriodo(periodo = {}) {
    if (periodo.desde && periodo.hasta) return `del ${periodo.desde} al ${periodo.hasta}`;
    if (periodo.desde) return `desde el ${periodo.desde}`;
    if (periodo.hasta) return `hasta el ${periodo.hasta}`;
    return 'todo el tiempo';
}

/**
 * Procesos del árbol a partir del resumen. Cada nodo: { nombre, valor, definicion, fuente, periodo ('periodo' | 'actual'),
 * instrumentado, evidencia: { nodo, estado } | null, hijos }. Puro: la vista solo lo dibuja.
 */
export function procesos(d) {
    const s = d.subastas;
    const c = d.comercializacion;
    const t = d.transporte;
    const inv = d.inventario;
    const canal = (nombre) => c.ventasPorCanal?.[nombre] ?? { animales: 0, operaciones: 0, monto: 0 };
    const nodo = (nombre, valor, definicion, fuente, extra = {}) => ({ nombre, valor, definicion, fuente, periodo: 'periodo', instrumentado: true, evidencia: null, hijos: [], ...extra });
    const viajes = t.viajesRegistrados;

    return [
        {
            clave: 'directa', titulo: 'Comercialización directa', ruta: 'Publicación → Me interesa → Solicitud → Respuesta → Venta',
            resumen: `${formatoNumero(canal('SOLICITUD').operaciones)} ventas por solicitud`,
            nodos: [
                nodo('Me interesa registrados', formatoNumero(c.interes.registros), `Marcas «Me interesa» del periodo, de ${formatoNumero(c.interes.personas)} personas distintas sobre ${formatoNumero(c.interes.publicaciones)} publicaciones. No todo comprador pasa por aquí.`,
                    'tbanimalpublicacioninteraccion (tipo ME_INTERESA, acción REGISTRAR)', { evidencia: { nodo: 'interes', estado: 'REGISTRAR' } }),
                nodo('Solicitudes creadas', formatoNumero(sumar(c.solicitudesCreadas)), 'Solicitudes de compra con fecha de creación en el periodo, por su estado actual.',
                    'tbcomprasolicitud.tbcomprasolicitudfecha', {
                        evidencia: { nodo: 'solicitudes', estado: null },
                        hijos: ['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'].map((e) => nodo(etiquetaEstado(e), formatoNumero(c.solicitudesCreadas?.[e] ?? 0),
                            `Creadas en el periodo que hoy están ${etiquetaEstado(e).toLowerCase()}.`, 'tbcomprasolicitud', { evidencia: { nodo: 'solicitudes', estado: e }, tono: tonoEstado(e) })),
                    }),
                nodo('Respuestas del vendedor', formatoNumero(sumar(c.solicitudesRespondidas)), 'Solicitudes respondidas (aceptadas o rechazadas) con fecha de respuesta en el periodo.',
                    'tbcomprasolicitud.tbcomprasolicitudrespuestafecha'),
                nodo('Ventas originadas en solicitudes', `${formatoNumero(canal('SOLICITUD').operaciones)} · ${formatoColones(canal('SOLICITUD').monto)}`,
                    `Solicitudes aceptadas que registraron venta (${formatoNumero(canal('SOLICITUD').animales)} animales: un lote registra una venta por animal). Monto acordado, no pago confirmado.`,
                    'tbventa con tbcomprasolicitudid (la compra espejo no se suma)', { evidencia: { nodo: 'ventas', estado: 'SOLICITUD' }, tono: 'exito' }),
            ],
        },
        {
            clave: 'subastas', titulo: 'Subastas', ruta: 'Publicar animal → Subasta → Pujas → Adjudicación → Venta',
            resumen: `${formatoNumero(s.estados.adjudicadas.total)} adjudicadas · ${formatoColones(s.precioPromedio.montoTotal)}`,
            nodos: [
                nodo('Subastas del periodo', formatoNumero(s.estados.activas.total + s.estados.cerradas.total + s.estados.canceladas.total),
                    'Por la fecha del hecho: fin programado si cerró, la cancelación, o el inicio si sigue activa.', 'tbsubasta', {
                        evidencia: { nodo: 'subastas', estado: null },
                        hijos: [
                            nodo('Activas', formatoNumero(s.estados.activas.total), 'Reciben pujas ahora.', 'tbsubasta (ACTIVA)', { evidencia: { nodo: 'subastas', estado: 'ACTIVA' }, tono: 'info' }),
                            nodo('Adjudicadas', formatoNumero(s.estados.adjudicadas.total), 'Cerraron con una puja ganadora.', 'tbsubasta (FINALIZADA)', { evidencia: { nodo: 'subastas', estado: 'FINALIZADA' }, tono: 'exito' }),
                            nodo('Desiertas', formatoNumero(s.estados.desiertas.total), 'Cerraron sin ninguna puja.', 'tbsubasta (DESIERTA)', { evidencia: { nodo: 'subastas', estado: 'DESIERTA' }, tono: 'aviso' }),
                            nodo('Canceladas', formatoNumero(s.estados.canceladas.total), 'El vendedor la canceló sin pujas. No es una venta ni una desierta.', 'tbsubasta (CANCELADA)', { evidencia: { nodo: 'subastas', estado: 'CANCELADA' }, tono: 'neutro' }),
                        ],
                    }),
                nodo('Pujas válidas', formatoNumero(s.pujas), `De ${formatoNumero(s.compradores.total)} compradores distintos. Toda puja guardada ya cumplió el mínimo.`, 'tbsubastapuja'),
                nodo('Porcentaje de éxito', formatoPorcentaje(s.exito.porcentaje), 'Adjudicadas ÷ (adjudicadas + desiertas). Sin cerradas no se calcula.', 'tbsubasta'),
                nodo('Monto adjudicado', formatoColones(s.precioPromedio.montoTotal), `Suma de las pujas ganadoras. Promedio por animal ${formatoColones(s.precioPromedio.valor)}; incremento promedio ${formatoPorcentaje(s.incrementoPromedio.porcentaje)}. Una adjudicación no es un pago confirmado.`,
                    'tbsubastapuja ganadora', { evidencia: { nodo: 'subastas', estado: 'FINALIZADA' }, tono: 'dato' }),
                nodo('Ventas por subasta', `${formatoNumero(canal('SUBASTA').animales)} · ${formatoColones(canal('SUBASTA').monto)}`, 'Cada adjudicación registra una venta (y una compra espejo que no se suma).',
                    'tbventa (origen API_SUBASTAS)', { evidencia: { nodo: 'ventas', estado: 'SUBASTA' }, tono: 'exito' }),
            ],
        },
        {
            clave: 'transporte', titulo: 'Transporte', ruta: 'Ofrecer flete → Solicitud → Respuesta → Viaje realizado',
            resumen: `${formatoNumero(t.solicitudesFletePorEstado?.ACEPTADA ?? 0)} fletes aceptados`,
            nodos: [
                nodo('Ofertas vigentes', formatoNumero((t.ofertasPorEstado?.ACTIVA?.total ?? 0) + (t.ofertasPorEstado?.PAUSADA?.total ?? 0)),
                    `${formatoNumero(t.ofertasPorEstado?.ACTIVA?.total ?? 0)} activas (capacidad ofertada ${formatoNumero(t.ofertasPorEstado?.ACTIVA?.capacidad ?? 0)} cabezas) y ${formatoNumero(t.ofertasPorEstado?.PAUSADA?.total ?? 0)} pausadas. Una oferta no es un servicio contratado.`,
                    'tbtransportistaoferta (foto actual)', { periodo: 'actual' }),
                nodo('Solicitudes de flete', formatoNumero(sumar(t.solicitudesFletePorEstado)), 'Compras que eligieron una oferta de flete, creadas en el periodo.', 'tbcomprasolicitud.tbtransportistaofertaid', {
                    evidencia: { nodo: 'fletes', estado: null },
                    hijos: ['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'].map((e) => nodo(etiquetaEstado(e), formatoNumero(t.solicitudesFletePorEstado?.[e] ?? 0),
                        e === 'ACEPTADA' ? 'El transportista aceptó. Un flete aceptado no es un viaje completado.' : `Respuesta del transportista: ${etiquetaEstado(e).toLowerCase()}.`,
                        'tbcomprasolicitud.tbcomprasolicitudfleteestado', { evidencia: { nodo: 'fletes', estado: e }, tono: tonoEstado(e) })),
                }),
                nodo('Viajes realizados', d.transporte.viajesInstrumentados ? formatoNumero(viajes.total) : 'Sin instrumentar',
                    d.instrumentacion?.viajesRealizados?.motivo ?? 'No hay un flujo que registre viajes.', 'tbtransportistaflete', {
                        instrumentado: Boolean(d.transporte.viajesInstrumentados),
                        detalle: viajes.total ? `Hay ${formatoNumero(viajes.total)} filas cargadas fuera de la aplicación (${formatoNumero(viajes.cabezas)} cabezas, ${formatoNumero(viajes.km)} km, ingresos registrados ${formatoColones(viajes.ingresosRegistrados)}).` : null,
                        evidencia: viajes.total ? { nodo: 'viajes', estado: null } : null,
                    }),
            ],
        },
        {
            clave: 'publicaciones', titulo: 'Inventario y publicaciones', ruta: 'Registrar animal → Publicar → Cambios de estado',
            resumen: `${formatoNumero(inv.publicacionesPorEstado?.ACTIVO ?? 0)} publicaciones activas`,
            nodos: [
                nodo('Animales registrados', formatoNumero(sumar(inv.animalesPorEstado)), Object.entries(inv.animalesPorEstado ?? {}).map(([k, v]) => `${etiquetaEstado(k)} ${formatoNumero(v)}`).join(' · ') || 'Sin animales.',
                    'tbanimal (foto actual)', { periodo: 'actual' }),
                nodo('Publicaciones por estado vigente', formatoNumero(sumar(inv.publicacionesPorEstado)), 'Estado del periodo histórico abierto (sin fecha de fin), no una columna de estado.',
                    'tbanimalpublicacionestadoperiodo', {
                        periodo: 'actual', evidencia: { nodo: 'publicaciones', estado: null },
                        hijos: ['ACTIVO', 'PAUSADO', 'VENDIDO', 'RETIRADO'].map((e) => nodo(etiquetaEstado(e), formatoNumero(inv.publicacionesPorEstado?.[e] ?? 0), `Publicaciones hoy ${etiquetaEstado(e).toLowerCase()}.`,
                            'tbanimalpublicacionestadoperiodo', { periodo: 'actual', evidencia: { nodo: 'publicaciones', estado: e }, tono: tonoEstado(e) })),
                    }),
                nodo('Publicaciones nuevas', formatoNumero(inv.publicacionesNuevas), 'Con fecha de publicación en el periodo.', 'tbanimalpublicacion.tbanimalpublicacionfecha'),
                nodo('Cambios de estado', formatoNumero(sumar(inv.cambiosDeEstado)), Object.entries(inv.cambiosDeEstado ?? {}).map(([k, v]) => `a ${etiquetaEstado(k).toLowerCase()} ${formatoNumero(v)}`).join(' · ') || 'Sin cambios en el periodo.',
                    'tbanimalpublicacionestadoperiodo (inicio en el periodo)'),
                nodo('Con solicitudes pendientes', formatoNumero(inv.conSolicitudesPendientes), 'Publicaciones con al menos una solicitud de compra sin responder.', 'tbcomprasolicitud (PENDIENTE)', { periodo: 'actual', tono: 'aviso' }),
            ],
        },
    ];
}

const ESTADOS = {
    PENDIENTE: ['Pendientes', 'aviso'], ACEPTADA: ['Aceptadas', 'exito'], RECHAZADA: ['Rechazadas', 'neutro'], CANCELADA: ['Canceladas', 'neutro'],
    ACTIVO: ['Activas', 'info'], PAUSADO: ['Pausadas', 'aviso'], VENDIDO: ['Vendidas', 'exito'], RETIRADO: ['Retiradas', 'neutro'], SUBASTA: ['En subasta', 'info'],
    ACTIVA: ['Activa', 'info'], FINALIZADA: ['Adjudicada', 'exito'], DESIERTA: ['Desierta', 'aviso'], SOLICITUD: ['Por solicitud', 'exito'],
    REGISTRAR: ['Registrado', 'info'], RETIRAR: ['Retirado', 'neutro'], REGISTRADO: ['Registrado', 'dato'], SIN_DATO: ['Sin dato', 'neutro'],
};
export function etiquetaEstado(e) { return ESTADOS[e]?.[0] ?? String(e ?? '—'); }
export function tonoEstado(e) { return ESTADOS[e]?.[1] ?? 'neutro'; }

// ───────────────────────────── DOM ─────────────────────────────

function icono(nombre) {
    const i = el('i', `fa-solid ${nombre}`);
    i.setAttribute('aria-hidden', 'true');
    return i;
}

function consulta(extra = {}) {
    return new URLSearchParams({ ...estado.periodo, ...extra }).toString();
}

function kpi({ etiqueta, valor, detalle, tono }) {
    const t = el('article', `intel-kpi intel-kpi--${tono}`);
    t.append(el('span', 'intel-kpi__etiqueta', etiqueta), el('strong', 'intel-kpi__valor', valor), el('span', 'intel-kpi__detalle', detalle));
    return t;
}

function pintarKpis(d) {
    const ventas = Object.values(d.comercializacion.ventasPorCanal ?? {});
    const animales = ventas.reduce((n, v) => n + v.animales, 0);
    const monto = ventas.reduce((n, v) => n + v.monto, 0);
    const docs = d.control.documentosPorEstado?.PENDIENTE ?? 0;
    $('#intel-kpis').replaceChildren(
        kpi({ etiqueta: 'Personas únicas', valor: formatoNumero(d.personas.personas), tono: 'neutro',
            detalle: `${formatoNumero((d.personas.porNumeroDeActividades?.[2] ?? 0) + (d.personas.porNumeroDeActividades?.[3] ?? 0))} con más de una actividad` }),
        kpi({ etiqueta: 'Ventas registradas', valor: formatoColones(monto), tono: 'dato',
            detalle: `${formatoNumero(animales)} animales en el periodo (subasta y solicitud, sin la compra espejo)` }),
        kpi({ etiqueta: 'Subastas adjudicadas', valor: formatoNumero(d.subastas.estados.adjudicadas.total), tono: 'exito',
            detalle: `Éxito ${formatoPorcentaje(d.subastas.exito.porcentaje)} de ${formatoNumero(d.subastas.exito.finalizadas)} cerradas` }),
        kpi({ etiqueta: 'Subastas activas', valor: formatoNumero(d.subastas.estados.activas.total), tono: 'info', detalle: `${formatoNumero(d.subastas.pujas)} pujas válidas en el periodo` }),
        kpi({ etiqueta: 'Solicitudes pendientes', valor: formatoNumero(d.comercializacion.solicitudesCreadas?.PENDIENTE ?? 0), tono: 'aviso',
            detalle: `${formatoNumero(d.inventario.conSolicitudesPendientes)} publicaciones esperan respuesta` }),
        kpi({ etiqueta: 'Documentos por verificar', valor: formatoNumero(docs), tono: docs ? 'aviso' : 'neutro', detalle: 'Identidad pendiente de revisión' }),
    );
    $('#intel-kpis').setAttribute('aria-busy', 'false');
}

function pintarRed(p) {
    const tarjetas = [
        { etiqueta: 'Productores', valor: p.productores, detalle: `${formatoNumero(p.fincas.activas)} fincas activas`, icon: 'fa-cow', href: 'admin/productores' },
        { etiqueta: 'Compradores', valor: p.compradores.activos, detalle: `${formatoNumero(p.compradores.inactivos)} inactivos`, icon: 'fa-handshake', href: 'admin/compradores' },
        { etiqueta: 'Transportistas', valor: p.transportistas.activos, detalle: `${formatoNumero(p.transportistas.inactivos)} inactivos`, icon: 'fa-truck-fast', href: 'admin/transportistas' },
        { etiqueta: 'Vehículos activos', valor: p.vehiculos.activos, detalle: `${formatoNumero(p.vehiculos.inactivos)} inactivos`, icon: 'fa-truck-pickup', href: 'admin/vehiculos' },
    ];
    $('#dashboard-metrics').replaceChildren(...tarjetas.map((t) => {
        const card = el('article', 'dashboard-metric');
        const caja = el('span', 'dashboard-metric__icon');
        caja.append(icono(t.icon));
        const enlace = el('a', 'dashboard-metric__link', 'Abrir módulo');
        enlace.href = t.href;
        card.append(caja, el('span', 'dashboard-metric__label', t.etiqueta), el('strong', 'dashboard-metric__value', formatoNumero(t.valor)),
            el('span', 'dashboard-metric__detalle', t.detalle), enlace);
        return card;
    }));
    $('#dashboard-metrics').setAttribute('aria-busy', 'false');
}

/** Evidencia paginada de un nodo: se pide al abrirla y se queda con el periodo vigente. */
function panelEvidencia(evidencia) {
    const caja = el('div', 'intel-evidencia');
    const cuerpo = el('div', 'intel-evidencia__cuerpo');
    const paginas = el('nav', 'intel-paginas');
    paginas.setAttribute('aria-label', 'Páginas de la evidencia');
    caja.append(cuerpo, paginas);
    const cargar = async (pagina) => {
        cuerpo.replaceChildren(el('p', 'intel-vacio', 'Cargando filas…'));
        caja.setAttribute('aria-busy', 'true');
        try {
            const r = await request(`${API}?${consulta({ vista: 'EVIDENCIA', nodo: evidencia.nodo, ...(evidencia.estado ? { estado: evidencia.estado } : {}), pagina, tamanoPagina: 10 })}`);
            const datos = r.data;
            if (!datos.items.length) {
                cuerpo.replaceChildren(el('p', 'intel-vacio', 'No hay filas en este periodo.'));
                paginas.replaceChildren();
                return;
            }
            cuerpo.replaceChildren(tablaEvidencia(datos.items, evidencia.nodo));
            const total = Math.max(1, Math.ceil(datos.total / datos.tamanoPagina));
            const boton = (texto, destino, deshabilitado) => {
                const b = el('button', 'button button--secondary button--small', texto);
                b.type = 'button';
                b.disabled = deshabilitado;
                b.addEventListener('click', () => cargar(destino));
                return b;
            };
            paginas.replaceChildren(boton('Anterior', pagina - 1, pagina <= 1),
                el('span', 'intel-paginas__texto', `Página ${pagina} de ${total} · ${formatoNumero(datos.total)} filas`), boton('Siguiente', pagina + 1, pagina >= total));
        } catch (error) {
            cuerpo.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudo cargar la evidencia.'));
            const reintentar = el('button', 'button button--secondary button--small', 'Reintentar');
            reintentar.type = 'button';
            reintentar.addEventListener('click', () => cargar(pagina));
            cuerpo.append(reintentar);
        } finally {
            caja.setAttribute('aria-busy', 'false');
        }
    };
    return { caja, cargar };
}

function tablaEvidencia(items, nodo) {
    const contenedor = el('div', 'intel-tabla');
    contenedor.tabIndex = 0;
    contenedor.setAttribute('role', 'region');
    contenedor.setAttribute('aria-label', 'Filas de la evidencia');
    const t = el('table');
    const cab = el('tr');
    const cantidad = nodo === 'subastas' ? 'Pujas' : nodo === 'viajes' ? 'Cabezas' : null;
    ['#', 'Registro', 'Estado', 'Fecha', 'Monto', ...(cantidad ? [cantidad] : []), 'Persona'].forEach((c) => { const th = el('th', null, c); th.scope = 'col'; cab.append(th); });
    t.append(el('thead'), el('tbody'));
    t.tHead.append(cab);
    items.forEach((i) => {
        const tr = el('tr');
        const persona = el('td');
        const enlace = el('button', 'intel-enlace', i.persona ?? '—');
        enlace.type = 'button';
        enlace.addEventListener('click', () => abrirExpediente(i.personaId));
        persona.append(enlace);
        const est = el('td');
        est.append(etiqueta(i.estado));
        tr.append(el('td', 'intel-num', i.id), el('td', null, i.titulo ?? '—'), est,
            el('td', null, i.fecha ? new Date(i.fecha).toLocaleDateString('es-CR', { timeZone: ZONA }) : '—'),
            el('td', 'intel-num', formatoColones(i.monto)), ...(cantidad ? [el('td', 'intel-num', formatoNumero(i.cantidad))] : []), persona);
        t.tBodies[0].append(tr);
    });
    contenedor.append(t);
    return contenedor;
}

function etiqueta(estadoClave) {
    const e = el('span', `intel-estado intel-estado--${tonoEstado(estadoClave)}`);
    e.append(el('span', 'intel-estado__punto'), el('span', null, etiquetaEstado(estadoClave)));
    return e;
}

function pintarNodo(n) {
    const item = el('li', `intel-nodo${n.instrumentado ? '' : ' intel-nodo--sin'}${n.tono ? ` intel-nodo--${n.tono}` : ''}`);
    const cabeza = el('div', 'intel-nodo__cabeza');
    const nombre = el('span', 'intel-nodo__nombre');
    if (n.tono) nombre.append(el('span', 'intel-estado__punto'));
    nombre.append(el('span', null, n.nombre));
    cabeza.append(nombre, el('strong', 'intel-nodo__valor', n.valor));
    const meta = el('p', 'intel-nodo__meta');
    meta.append(el('span', null, n.definicion));
    const fuente = el('span', 'intel-nodo__fuente');
    fuente.append(el('span', null, `Fuente: ${n.fuente}`), el('span', null, n.periodo === 'actual' ? 'Foto actual' : `Periodo: ${textoPeriodo(estado.periodo)}`));
    if (!n.instrumentado) fuente.append(el('span', 'intel-insignia', 'Sin instrumentar'));
    item.append(cabeza, meta, fuente);
    if (n.detalle) item.append(el('p', 'intel-nodo__meta', n.detalle));
    if (n.evidencia) {
        const detalles = el('details', 'intel-nodo__evidencia');
        detalles.append(el('summary', null, 'Ver evidencia'));
        const panel = panelEvidencia(n.evidencia);
        detalles.append(panel.caja);
        detalles.addEventListener('toggle', () => { if (detalles.open && !detalles.dataset.cargado) { detalles.dataset.cargado = '1'; panel.cargar(1); } });
        item.append(detalles);
    }
    if (n.hijos?.length) {
        const hijos = el('ul', 'intel-nodos intel-nodos--hijos');
        hijos.append(...n.hijos.map(pintarNodo));
        item.append(hijos);
    }
    return item;
}

function pintarArbol(d) {
    const abiertos = new Set([...document.querySelectorAll('.intel-proceso[open]')].map((p) => p.dataset.clave));
    $('#intel-arbol').replaceChildren(...procesos(d).map((p, i) => {
        const det = el('details', 'intel-proceso');
        det.dataset.clave = p.clave;
        det.open = abiertos.size ? abiertos.has(p.clave) : i === 0;
        const sum = el('summary');
        const titulos = el('span', 'intel-proceso__titulos');
        titulos.append(el('strong', null, p.titulo), el('small', null, p.ruta));
        sum.append(titulos, el('span', 'intel-proceso__resumen', p.resumen));
        const lista = el('ul', 'intel-nodos');
        lista.append(...p.nodos.map(pintarNodo));
        det.append(sum, lista);
        return det;
    }));
}

function pintarControl(d) {
    const c = d.control;
    const lista = (titulo, filas) => {
        const card = el('article', 'intel-panel intel-panel--abierto');
        card.append(el('h3', 'intel-panel__titulo', titulo));
        const dl = el('dl', 'intel-lista');
        filas.forEach(([k, v, tono]) => {
            const fila = el('div', tono ? `intel-lista__fila intel-lista__fila--${tono}` : 'intel-lista__fila');
            fila.append(el('dt', null, k), el('dd', null, v));
            dl.append(fila);
        });
        card.append(dl);
        return card;
    };
    const inc = c.incompletos;
    const enlaceBitacora = el('a', 'text-link', 'Abrir la bitácora');
    enlaceBitacora.href = 'admin/bitacora';
    const control = lista('Control y trazabilidad', [
        ['Documentos pendientes', formatoNumero(c.documentosPorEstado?.PENDIENTE ?? 0), (c.documentosPorEstado?.PENDIENTE ?? 0) ? 'aviso' : null],
        ['Documentos verificados', formatoNumero(c.documentosPorEstado?.VERIFICADO ?? 0)],
        ['Cambios de correo pendientes', formatoNumero(c.correosPendientes), c.correosPendientes ? 'aviso' : null],
        ['Acciones en la bitácora (periodo)', formatoNumero(sumar(c.bitacoraPorEntidad))],
        ...Object.entries(c.bitacoraPorEntidad ?? {}).sort((a, b) => b[1] - a[1]).slice(0, 5).map(([k, v]) => [`· ${k.toLowerCase().replaceAll('_', ' ')}`, formatoNumero(v)]),
    ]);
    control.append(enlaceBitacora);
    const instr = Object.values(d.instrumentacion ?? {}).map((x) => x.motivo);
    const calidad = lista('Datos incompletos', [
        ['Publicaciones sin foto', formatoNumero(inc.publicacionesSinFoto), inc.publicacionesSinFoto ? 'aviso' : null],
        ['Animales sin tipo de ganado', formatoNumero(inc.animalesSinEspecie), inc.animalesSinEspecie ? 'aviso' : null],
        ['Fincas sin dirección', formatoNumero(inc.fincasSinDireccion), inc.fincasSinDireccion ? 'aviso' : null],
        ['Ofertas activas sin precio', formatoNumero(inc.ofertasActivasSinPrecio), inc.ofertasActivasSinPrecio ? 'aviso' : null],
        ['Ventas sin comprador enlazado', formatoNumero(inc.ventasSinComprador), inc.ventasSinComprador ? 'aviso' : null],
    ]);
    calidad.append(el('h3', 'intel-panel__titulo', 'Sin instrumentar'));
    const ul = el('ul', 'intel-sin');
    instr.forEach((m) => ul.append(el('li', null, m)));
    calidad.append(ul);
    $('#intel-control').replaceChildren(control, calidad);
}

async function cargarTransportistas(pagina = 1) {
    const cuerpo = $('#intel-transportistas [data-contenido]');
    cuerpo.replaceChildren(el('p', 'intel-vacio', 'Cargando transportistas…'));
    try {
        const { data } = await request(`${API}?${consulta({ vista: 'TRANSPORTISTAS', pagina, tamanoPagina: 10 })}`);
        if (!data.items.length) { cuerpo.replaceChildren(el('p', 'intel-vacio', 'No hay transportistas registrados.')); return; }
        const contenedor = el('div', 'intel-tabla');
        contenedor.tabIndex = 0;
        contenedor.setAttribute('role', 'region');
        contenedor.setAttribute('aria-label', 'Métricas por transportista');
        const t = el('table');
        const cab = el('tr');
        ['Transportista', 'Ofertas', 'Capacidad', 'Vehículos', 'Solicitudes', 'Aceptadas', 'Rechazadas', 'Viajes registrados', 'Ingresos registrados'].forEach((c) => { const th = el('th', null, c); th.scope = 'col'; cab.append(th); });
        t.append(el('thead'), el('tbody'));
        t.tHead.append(cab);
        data.items.forEach((f) => {
            const tr = el('tr');
            const nombre = el('td');
            const b = el('button', 'intel-enlace', f.nombre);
            b.type = 'button';
            b.addEventListener('click', () => abrirExpediente(f.personaId));
            nombre.append(b, el('small', 'intel-tabla__sub', f.activo ? 'Activo' : 'Inactivo'));
            tr.append(nombre, el('td', 'intel-num', `${f.ofertasActivas}/${f.ofertas}`), el('td', 'intel-num', formatoNumero(f.capacidadOfertada)),
                el('td', 'intel-num', f.vehiculos), el('td', 'intel-num', f.solicitudesRecibidas), el('td', 'intel-num', f.solicitudesAceptadas),
                el('td', 'intel-num', f.solicitudesRechazadas), el('td', 'intel-num', f.viajesRegistrados ? formatoNumero(f.viajesRegistrados) : 'Sin instrumentar'),
                el('td', 'intel-num', formatoColones(f.ingresosRegistrados)));
            t.tBodies[0].append(tr);
        });
        contenedor.append(t);
        const nota = el('p', 'intel-nota', 'Ofertas: activas/total. Capacidad: cabezas de las ofertas activas (nominal, no transportadas). Los viajes solo cuentan filas de tbtransportistaflete; un flete aceptado no es un viaje. «Ingresos registrados» no es ganancia neta.');
        const total = Math.max(1, Math.ceil(data.total / 10));
        const nav = el('nav', 'intel-paginas');
        nav.setAttribute('aria-label', 'Páginas de transportistas');
        const boton = (texto, destino, deshabilitado) => {
            const b = el('button', 'button button--secondary button--small', texto);
            b.type = 'button';
            b.disabled = deshabilitado;
            b.addEventListener('click', () => cargarTransportistas(destino));
            return b;
        };
        nav.append(boton('Anterior', pagina - 1, pagina <= 1), el('span', 'intel-paginas__texto', `Página ${pagina} de ${total}`), boton('Siguiente', pagina + 1, pagina >= total));
        cuerpo.replaceChildren(contenedor, nota, nav);
    } catch (error) {
        cuerpo.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudieron cargar los transportistas.'));
    }
}

async function buscarPersonas(q) {
    const destino = $('#intel-persona');
    destino.replaceChildren(el('p', 'intel-vacio', 'Buscando…'));
    try {
        const { data } = await request(API, { method: 'POST', body: JSON.stringify({ consulta: { vista: 'PERSONA', q } }) });
        if (!data.personas.length) { destino.replaceChildren(el('p', 'intel-vacio', 'Ninguna persona coincide.')); return; }
        const ul = el('ul', 'intel-resultados');
        data.personas.forEach((p) => {
            const li = el('li');
            const b = el('button', 'intel-enlace', p.alias ? `${p.nombre} (${p.alias})` : p.nombre);
            b.type = 'button';
            b.addEventListener('click', () => abrirExpediente(p.personaId));
            li.append(b, el('small', 'intel-tabla__sub', p.actividades.map((a) => a.toLowerCase()).join(' · ') || 'Sin actividades'));
            ul.append(li);
        });
        destino.replaceChildren(ul);
    } catch (error) {
        destino.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudo buscar.'));
    }
}

async function abrirExpediente(personaId) {
    const destino = $('#intel-persona');
    destino.replaceChildren(el('p', 'intel-vacio', 'Cargando expediente…'));
    destino.closest('.intel-panel')?.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    try {
        const { data } = await request(`${API}?vista=PERSONA&personaId=${Number(personaId)}`);
        const x = data.expediente;
        const card = el('article', 'intel-expediente');
        card.append(el('h3', 'intel-panel__titulo', x.alias ? `${x.nombre} (${x.alias})` : x.nombre),
            el('p', 'intel-nota', `Persona #${x.personaId} · ${x.activa ? 'activa' : 'inactiva'} · documento ${String(x.documento ?? 'sin enviar').toLowerCase()}. Una identidad; sus actividades abajo.`));
        const bloque = (titulo, filas) => {
            const sec = el('section', 'intel-expediente__bloque');
            sec.append(el('h4', null, titulo));
            const dl = el('dl', 'intel-lista');
            filas.forEach(([k, v]) => { const f = el('div', 'intel-lista__fila'); f.append(el('dt', null, k), el('dd', null, v)); dl.append(f); });
            sec.append(dl);
            return sec;
        };
        const conteo = (o) => Object.entries(o ?? {}).map(([k, v]) => `${etiquetaEstado(k)} ${v}`).join(' · ') || '—';
        if (x.comprador) card.append(bloque('Como comprador', [['Pujas', formatoNumero(x.comprador.pujas)], ['Subastas ganadas', formatoNumero(x.comprador.subastasGanadas)],
            ['Solicitudes', conteo(x.comprador.solicitudes)], ['Compras registradas', `${formatoNumero(x.comprador.compras)} · ${formatoColones(x.comprador.montoComprado)}`],
            ['Me interesa', formatoNumero(x.comprador.meInteresa)]]));
        if (x.vendedor) card.append(bloque('Como vendedor', [['Fincas', formatoNumero(x.vendedor.fincas)], ['Animales', formatoNumero(x.vendedor.animales)],
            ['Publicaciones', formatoNumero(x.vendedor.publicaciones)], ['Subastas', conteo(x.vendedor.subastas)],
            ['Ventas registradas', `${formatoNumero(x.vendedor.ventas)} · ${formatoColones(x.vendedor.montoVendido)}`]]));
        if (x.transportista) card.append(bloque('Como transportista', [['Estado', x.transportista.activo ? 'Activo' : 'Inactivo'], ['Ofertas', conteo(x.transportista.ofertas)],
            ['Capacidad ofertada', formatoNumero(x.transportista.capacidadOfertada)], ['Vehículos', formatoNumero(x.transportista.vehiculos)],
            ['Solicitudes de flete', conteo(x.transportista.solicitudesFlete)],
            ['Viajes registrados', x.transportista.viajesRegistrados ? `${formatoNumero(x.transportista.viajesRegistrados)} (${formatoNumero(x.transportista.vehiculosUsadosEnViajes)} vehículos)` : 'Sin instrumentar'],
            ...x.transportista.viajesPorMes.map((m) => [`· ${m.mes}`, `${m.viajes} viajes · ${m.cabezas} cabezas · ${formatoNumero(m.km)} km · ${formatoColones(m.ingresos)}`])]));
        if (!x.comprador && !x.vendedor && !x.transportista) card.append(el('p', 'intel-vacio', 'Sin actividades registradas.'));
        destino.replaceChildren(card);
    } catch (error) {
        destino.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudo cargar el expediente.'));
    }
}

async function cargar() {
    const pedido = ++estado.pedido;
    const form = $('#intel-periodo');
    estado.periodo = parametrosPeriodo(Object.fromEntries(new FormData(form)));
    const boton = $('#dashboard-refresh');
    const status = $('#dashboard-status');
    boton.disabled = true;
    status.textContent = 'Actualizando indicadores…';
    try {
        const { data } = await request(`${API}?${consulta()}`);
        if (pedido !== estado.pedido) return; // solo vale la última respuesta
        pintarKpis(data);
        pintarRed(data.personas);
        pintarArbol(data);
        pintarControl(data);
        if ($('#intel-transportistas').open) cargarTransportistas();
        const hora = new Intl.DateTimeFormat('es-CR', { hour: '2-digit', minute: '2-digit', timeZone: ZONA }).format(new Date(data.generado));
        status.textContent = `Periodo ${textoPeriodo(data.periodo)} · actualizado a las ${hora}.`;
    } catch (error) {
        if (pedido !== estado.pedido) return;
        status.textContent = error?.message || 'No se pudieron cargar los indicadores.';
        $('#intel-arbol').replaceChildren(el('p', 'intel-error', 'No se pudieron cargar los procesos. Usa «Actualizar» para reintentar.'));
    } finally {
        if (pedido === estado.pedido) boton.disabled = false;
    }
}

function iniciar() {
    const form = $('#intel-periodo');
    const rango = () => document.querySelectorAll('[data-rango]').forEach((c) => { c.hidden = form.elements.periodo.value !== 'rango'; });
    form.addEventListener('change', (e) => {
        if (e.target.name === 'periodo') rango();
        if (e.target.name === 'periodo' && e.target.value === 'rango') return;
        cargar();
    });
    form.addEventListener('submit', (e) => { e.preventDefault(); cargar(); });
    $('#intel-transportistas').addEventListener('toggle', (e) => { if (e.target.open) cargarTransportistas(); });
    $('#intel-busqueda').addEventListener('submit', (e) => {
        e.preventDefault();
        const q = e.target.elements.q.value.trim();
        if (q.length >= 2) buscarPersonas(q);
    });
    cargar();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
