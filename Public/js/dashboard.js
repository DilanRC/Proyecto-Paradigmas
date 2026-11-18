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
const estado = { periodo: {}, pedido: 0, busqueda: 0 };

// ───────────────────────────── Funciones puras ─────────────────────────────

export function formatoNumero(valor) {
    return typeof valor === 'number' && Number.isFinite(valor) ? new Intl.NumberFormat('es-CR').format(valor) : '—';
}

export function formatoColones(valor) {
    if (typeof valor !== 'number' || !Number.isFinite(valor)) return '—';
    return `₡${new Intl.NumberFormat('es-CR', { maximumFractionDigits: 0 }).format(valor)}`;
}

/** Colones para una tarjeta angosta: desde un millón en notación compacta («₡4,3 M», «₡20 mil M»). Puro. */
export function formatoColonesCorto(valor) {
    if (typeof valor !== 'number' || !Number.isFinite(valor) || Math.abs(valor) < 1e6) return formatoColones(valor);
    return `₡${new Intl.NumberFormat('es-CR', { notation: 'compact', maximumFractionDigits: 1 }).format(valor)}`;
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
 * Áreas del panel a partir del resumen. Cada indicador: { nombre, valor, evidencia: { nodo, estado } | null, hijos, tono }.
 * Solo se muestra lo que la plataforma registra: lo que no tiene registro no aparece (ni como cero). Puro.
 */
export function procesos(d) {
    const s = d.subastas;
    const c = d.comercializacion;
    const t = d.transporte;
    const inv = d.inventario;
    const canal = (nombre) => c.ventasPorCanal?.[nombre] ?? { animales: 0, operaciones: 0, monto: 0 };
    const nodo = (nombre, valor, extra = {}) => ({ nombre, valor, evidencia: null, hijos: [], ...extra });
    const porEstado = (estados, conteos, nodoEvidencia) => estados.map((e) => nodo(etiquetaEstado(e), formatoNumero(conteos?.[e] ?? 0),
        { evidencia: { nodo: nodoEvidencia, estado: e }, tono: tonoEstado(e) }));
    const ofertas = t.ofertasPorEstado ?? {};

    return [
        {
            clave: 'directa', titulo: 'Compras directas',
            resumen: `${formatoNumero(canal('SOLICITUD').operaciones)} ventas`,
            nodos: [
                nodo('Me interesa', formatoNumero(c.interes.registros), { detalle: `${formatoNumero(c.interes.personas)} personas · ${formatoNumero(c.interes.publicaciones)} publicaciones`, evidencia: { nodo: 'interes', estado: 'REGISTRAR' } }),
                nodo('Solicitudes de compra', formatoNumero(sumar(c.solicitudesCreadas)), {
                    evidencia: { nodo: 'solicitudes', estado: null },
                    hijos: porEstado(['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'], c.solicitudesCreadas, 'solicitudes'),
                }),
                nodo('Respondidas por el vendedor', formatoNumero(sumar(c.solicitudesRespondidas))),
                nodo('Ventas', `${formatoNumero(canal('SOLICITUD').operaciones)} · ${formatoColones(canal('SOLICITUD').monto)}`,
                    { detalle: `${formatoNumero(canal('SOLICITUD').animales)} animales`, evidencia: { nodo: 'ventas', estado: 'SOLICITUD' }, tono: 'exito' }),
            ],
        },
        {
            clave: 'subastas', titulo: 'Subastas',
            resumen: `${formatoNumero(s.estados.adjudicadas.total)} adjudicadas · ${formatoColones(s.precioPromedio.montoTotal)}`,
            nodos: [
                nodo('Subastas', formatoNumero(s.estados.activas.total + s.estados.cerradas.total + s.estados.canceladas.total), {
                    evidencia: { nodo: 'subastas', estado: null },
                    hijos: [
                        nodo('Activas', formatoNumero(s.estados.activas.total), { evidencia: { nodo: 'subastas', estado: 'ACTIVA' }, tono: 'info' }),
                        nodo('Adjudicadas', formatoNumero(s.estados.adjudicadas.total), { evidencia: { nodo: 'subastas', estado: 'FINALIZADA' }, tono: 'exito' }),
                        nodo('Desiertas', formatoNumero(s.estados.desiertas.total), { evidencia: { nodo: 'subastas', estado: 'DESIERTA' }, tono: 'aviso' }),
                        nodo('Canceladas', formatoNumero(s.estados.canceladas.total), { evidencia: { nodo: 'subastas', estado: 'CANCELADA' }, tono: 'neutro' }),
                    ],
                }),
                nodo('Pujas', formatoNumero(s.pujas), { detalle: `${formatoNumero(s.compradores.total)} compradores` }),
                nodo('Éxito', formatoPorcentaje(s.exito.porcentaje), { detalle: 'Adjudicadas sobre las que cerraron' }),
                nodo('Monto adjudicado', formatoColones(s.precioPromedio.montoTotal), {
                    detalle: `Promedio por animal ${formatoColones(s.precioPromedio.valor)} · incremento ${formatoPorcentaje(s.incrementoPromedio.porcentaje)}`,
                    evidencia: { nodo: 'subastas', estado: 'FINALIZADA' }, tono: 'dato',
                }),
                nodo('Ventas', `${formatoNumero(canal('SUBASTA').animales)} · ${formatoColones(canal('SUBASTA').monto)}`, { evidencia: { nodo: 'ventas', estado: 'SUBASTA' }, tono: 'exito' }),
            ],
        },
        {
            clave: 'transporte', titulo: 'Transporte',
            resumen: `${formatoNumero(t.solicitudesFletePorEstado?.ACEPTADA ?? 0)} fletes aceptados`,
            nodos: [
                nodo('Ofertas de flete', formatoNumero((ofertas.ACTIVA?.total ?? 0) + (ofertas.PAUSADA?.total ?? 0)),
                    { detalle: `${formatoNumero(ofertas.ACTIVA?.total ?? 0)} activas · ${formatoNumero(ofertas.ACTIVA?.capacidad ?? 0)} cabezas de capacidad · ${formatoNumero(ofertas.PAUSADA?.total ?? 0)} pausadas` }),
                nodo('Solicitudes de flete', formatoNumero(sumar(t.solicitudesFletePorEstado)), {
                    evidencia: { nodo: 'fletes', estado: null },
                    hijos: porEstado(['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'], t.solicitudesFletePorEstado, 'fletes'),
                }),
            ],
        },
        {
            clave: 'publicaciones', titulo: 'Animales y publicaciones',
            resumen: `${formatoNumero(inv.publicacionesPorEstado?.ACTIVO ?? 0)} publicaciones activas`,
            nodos: [
                nodo('Animales', formatoNumero(sumar(inv.animalesPorEstado)),
                    { detalle: Object.entries(inv.animalesPorEstado ?? {}).map(([k, v]) => `${etiquetaEstado(k)} ${formatoNumero(v)}`).join(' · ') || null }),
                nodo('Publicaciones', formatoNumero(sumar(inv.publicacionesPorEstado)), {
                    evidencia: { nodo: 'publicaciones', estado: null },
                    hijos: porEstado(['ACTIVO', 'PAUSADO', 'VENDIDO', 'RETIRADO'], inv.publicacionesPorEstado, 'publicaciones'),
                }),
                nodo('Publicaciones nuevas', formatoNumero(inv.publicacionesNuevas)),
                nodo('Cambios de estado', formatoNumero(sumar(inv.cambiosDeEstado)),
                    { detalle: Object.entries(inv.cambiosDeEstado ?? {}).map(([k, v]) => `${etiquetaEstado(k)} ${formatoNumero(v)}`).join(' · ') || null }),
                nodo('Esperan respuesta', formatoNumero(inv.conSolicitudesPendientes), { detalle: 'Publicaciones con solicitudes sin responder', tono: 'aviso' }),
            ],
        },
    ];
}

const ESTADOS = {
    PENDIENTE: ['Pendientes', 'aviso'], ACEPTADA: ['Aceptadas', 'exito'], RECHAZADA: ['Rechazadas', 'neutro'], CANCELADA: ['Canceladas', 'neutro'],
    ACTIVO: ['Activas', 'info'], PAUSADO: ['Pausadas', 'aviso'], VENDIDO: ['Vendidas', 'exito'], RETIRADO: ['Retiradas', 'neutro'], SUBASTA: ['En subasta', 'info'],
    ACTIVA: ['Activa', 'info'], FINALIZADA: ['Adjudicada', 'exito'], DESIERTA: ['Desierta', 'aviso'], SOLICITUD: ['Por solicitud', 'exito'],
    REGISTRAR: ['Registrado', 'info'], RETIRAR: ['Retirado', 'neutro'], REGISTRADO: ['Registrado', 'dato'], SIN_DATO: ['Sin dato', 'neutro'],
    COMPRADOR: ['Comprador', 'neutro'], VENDEDOR: ['Vendedor', 'neutro'], TRANSPORTISTA: ['Transportista', 'neutro'],
};
const DOCUMENTO = { PENDIENTE: 'por verificar', VERIFICADO: 'verificado', RECHAZADO: 'rechazado' };

/** Nombre legible del área de un movimiento de la bitácora (la clave interna nunca se muestra). Puro. */
const AREAS = {
    SUBASTA: 'Subastas', PUBLICACION: 'Publicaciones', PUBLICACION_INTERACCION: 'Me interesa', OFERTA_FLETE: 'Ofertas de flete',
    PERSONA: 'Personas', VEHICULO: 'Vehículos', TRANSPORTISTA_VEHICULO: 'Vehículos', FINCA: 'Fincas', PRODUCTORUBICACION: 'Fincas', ANIMAL: 'Animales',
    ANIMAL_VACUNACION: 'Vacunas', COMPRA_SOLICITUD: 'Solicitudes de compra', PAGOMETODO: 'Métodos de pago',
    ADMINISTRADOR: 'Administradores', CATALOGO: 'Catálogos', DOCUMENTO: 'Documentos', COMPRADOR: 'Compradores',
    PRODUCTOR: 'Productores', TRANSPORTISTA: 'Transportistas', VACUNA: 'Vacunas',
};
export function nombreArea(clave) {
    const k = String(clave ?? '').toUpperCase();
    if (AREAS[k]) return AREAS[k];
    const texto = k.toLowerCase().replaceAll('_', ' ').trim();
    return texto ? texto[0].toUpperCase() + texto.slice(1) : 'Otros';
}
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

function kpi({ etiqueta, valor, completo, detalle, tono }) {
    const t = el('article', `intel-kpi intel-kpi--${tono}`);
    const cifra = el('strong', 'intel-kpi__valor', valor);
    // El valor compacto se ve; el exacto queda en el título y lo lee el lector de pantalla.
    if (completo && completo !== valor) { cifra.title = completo; cifra.setAttribute('aria-label', completo); }
    t.append(el('span', 'intel-kpi__etiqueta', etiqueta), cifra, el('span', 'intel-kpi__detalle', detalle));
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
        kpi({ etiqueta: 'Ventas registradas', valor: formatoColonesCorto(monto), completo: formatoColones(monto), tono: 'dato',
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
    const item = el('li', `intel-nodo${n.tono ? ` intel-nodo--${n.tono}` : ''}`);
    const cabeza = el('div', 'intel-nodo__cabeza');
    const nombre = el('span', 'intel-nodo__nombre');
    if (n.tono) nombre.append(el('span', 'intel-estado__punto'));
    nombre.append(el('span', null, n.nombre));
    cabeza.append(nombre, el('strong', 'intel-nodo__valor', n.valor));
    item.append(cabeza);
    if (n.detalle) item.append(el('p', 'intel-nodo__meta', n.detalle));
    if (n.evidencia) {
        const detalles = el('details', 'intel-nodo__evidencia');
        detalles.append(el('summary', null, 'Ver registros'));
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
    $('#intel-arbol').setAttribute('aria-busy', 'false');
    $('#intel-arbol').replaceChildren(...procesos(d).map((p) => {
        const det = el('details', 'intel-proceso');
        det.dataset.clave = p.clave;
        // Cerrados al llegar (misma altura que el esqueleto): la persona abre el proceso que le interesa.
        det.open = abiertos.has(p.clave);
        const sum = el('summary');
        const titulos = el('span', 'intel-proceso__titulos');
        titulos.append(el('strong', null, p.titulo));
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
    const control = lista('Revisión y actividad', [
        ['Documentos pendientes', formatoNumero(c.documentosPorEstado?.PENDIENTE ?? 0), (c.documentosPorEstado?.PENDIENTE ?? 0) ? 'aviso' : null],
        ['Documentos verificados', formatoNumero(c.documentosPorEstado?.VERIFICADO ?? 0)],
        ['Cambios de correo pendientes', formatoNumero(c.correosPendientes), c.correosPendientes ? 'aviso' : null],
        ['Movimientos en la bitácora', formatoNumero(sumar(c.bitacoraPorEntidad))],
        ...Object.entries(c.bitacoraPorEntidad ?? {}).sort((a, b) => b[1] - a[1]).slice(0, 5).map(([k, v]) => [`· ${nombreArea(k)}`, formatoNumero(v)]),
    ]);
    control.append(enlaceBitacora);
    const calidad = lista('Datos incompletos', [
        ['Publicaciones sin foto', formatoNumero(inc.publicacionesSinFoto), inc.publicacionesSinFoto ? 'aviso' : null],
        ['Animales sin tipo de ganado', formatoNumero(inc.animalesSinEspecie), inc.animalesSinEspecie ? 'aviso' : null],
        ['Fincas sin dirección', formatoNumero(inc.fincasSinDireccion), inc.fincasSinDireccion ? 'aviso' : null],
        ['Ofertas activas sin precio', formatoNumero(inc.ofertasActivasSinPrecio), inc.ofertasActivasSinPrecio ? 'aviso' : null],
        ['Ventas sin comprador', formatoNumero(inc.ventasSinComprador), inc.ventasSinComprador ? 'aviso' : null],
        ['Subastas adjudicadas sin venta', formatoNumero(inc.adjudicadasSinVenta), inc.adjudicadasSinVenta ? 'aviso' : null],
    ]);
    $('#intel-control').replaceChildren(control, calidad);
}

async function cargarTransportistas(pagina = 1) {
    const cuerpo = $('#intel-transportistas-cuerpo');
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
        ['Transportista', 'Ofertas activas', 'Capacidad (cabezas)', 'Vehículos', 'Solicitudes', 'Aceptadas', 'Rechazadas'].forEach((c) => { const th = el('th', null, c); th.scope = 'col'; cab.append(th); });
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
                el('td', 'intel-num', f.solicitudesRechazadas));
            t.tBodies[0].append(tr);
        });
        contenedor.append(t);
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
        cuerpo.replaceChildren(contenedor, ...(total > 1 ? [nav] : []));
    } catch (error) {
        cuerpo.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudieron cargar los transportistas.'));
    }
}

async function buscarPersonas(q) {
    const destino = $('#intel-persona');
    const pedido = ++estado.busqueda; // al escribir rápido solo vale la última búsqueda
    destino.replaceChildren(el('p', 'intel-vacio', 'Buscando…'));
    try {
        const { data } = await request(API, { method: 'POST', body: JSON.stringify({ consulta: { vista: 'PERSONA', q } }) });
        if (pedido !== estado.busqueda) return;
        if (!data.personas.length) { destino.replaceChildren(el('p', 'intel-vacio', 'Ninguna persona coincide.')); return; }
        const ul = el('ul', 'intel-resultados');
        data.personas.forEach((p) => {
            const li = el('li');
            const b = el('button', 'intel-enlace', p.alias ? `${p.nombre} (${p.alias})` : p.nombre);
            b.type = 'button';
            b.addEventListener('click', () => abrirExpediente(p.personaId));
            li.append(b, el('small', 'intel-tabla__sub', p.actividades.map((a) => etiquetaEstado(a)).join(' · ') || 'Sin actividades'));
            ul.append(li);
        });
        destino.replaceChildren(ul);
    } catch (error) {
        if (pedido !== estado.busqueda) return;
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
            el('p', 'intel-nota', `${x.activa ? 'Cuenta activa' : 'Cuenta inactiva'} · documento ${DOCUMENTO[x.documento] ?? 'sin enviar'}`));
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
            ['Solicitudes de flete', conteo(x.transportista.solicitudesFlete)]]));
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
        cargarTransportistas();
        const hora = new Intl.DateTimeFormat('es-CR', { hour: '2-digit', minute: '2-digit', timeZone: ZONA }).format(new Date(data.generado));
        status.textContent = `Periodo ${textoPeriodo(data.periodo)} · actualizado a las ${hora}.`;
    } catch (error) {
        if (pedido !== estado.pedido) return;
        status.textContent = error?.message || 'No se pudieron cargar los indicadores.';
        $('#intel-arbol').setAttribute('aria-busy', 'false');
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
    const busqueda = $('#intel-busqueda');
    let espera = 0;
    const buscar = () => {
        clearTimeout(espera);
        const q = busqueda.elements.q.value.trim();
        if (q.length < 2) { estado.busqueda++; $('#intel-persona').replaceChildren(); return; }
        espera = setTimeout(() => buscarPersonas(q), 250);
    };
    busqueda.addEventListener('input', buscar);
    busqueda.addEventListener('submit', (e) => { e.preventDefault(); buscar(); });
    cargar();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
