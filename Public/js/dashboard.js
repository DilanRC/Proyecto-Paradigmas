// Centro de inteligencia del administrador (/admin/dashboard, DEC-DASHBOARD-002). Una sola API de solo lectura
// (api/v1/admin/dashboard) autorizada en PHP. Secciones: visión global, requieren atención (pendientes de la operación),
// actividad comercial (cuatro áreas; cada una abre sus etapas, y cada indicador su evidencia paginada en un diálogo)
// y personas y actividades (explorador y transportistas). Las etapas son conceptuales: no se calculan tasas entre ellas
// porque los registros no prueban que las mismas personas pasaran por todas. Lo que la plataforma no registra se dice
// «todavía no disponible», nunca 0. Sin imágenes decorativas (decisión del PR #30). Todo con textContent.

import { request } from './shared/api.js?v=auth-gate-18';
import { el } from './dashboard-graficos.js?v=graficos-3';

const API = 'api/v1/admin/dashboard';
const ZONA = 'America/Costa_Rica';
const $ = (selector) => document.querySelector(selector);
const estado = { periodo: {}, pedido: 0, busqueda: 0, expediente: 0, areas: [], areaAbierta: null };

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


/** Definición de cada evidencia: lo que se ve en el diálogo encima de la tabla. */
const DEFINICIONES = {
    interes: 'Marcas «Me interesa» registradas en publicaciones.',
    solicitudes: 'Solicitudes de compra creadas por compradores, con su estado actual.',
    fletes: 'Solicitudes de compra que pidieron un flete, con la respuesta del transportista.',
    ventas: 'Ventas registradas: una fila por animal vendido.',
    subastas: 'Subastas por la fecha de su hecho (cierre para las terminadas, inicio para las activas).',
    publicaciones: 'Publicaciones por fecha de alta, con su estado vigente.',
    viajes: 'Viajes que el transportista marcó como realizados, por la fecha del viaje.',
};

/**
 * Áreas de actividad comercial a partir del resumen. Cada área: { clave, titulo, icono, principal, resumen, etapas,
 * salidas, noDisponible }. Cada etapa: { nombre, valor, explicacion, estados: [{ nombre, valor, tono, evidencia }],
 * evidencia }. Las etapas no se encadenan con tasas: cada una cuenta sus propios registros. Puro.
 */
export function procesos(d) {
    const s = d.subastas;
    const c = d.comercializacion;
    const t = d.transporte;
    const inv = d.inventario;
    const canal = (nombre) => c.ventasPorCanal?.[nombre] ?? { animales: 0, operaciones: 0, monto: 0 };
    const ev = (nodo, estado = null) => ({ nodo, estado });
    const estadosDe = (lista, conteos, nodo) => lista.map((e) => ({ nombre: etiquetaEstado(e), valor: formatoNumero(conteos?.[e] ?? 0),
        tono: tonoEstado(e), evidencia: nodo ? ev(nodo, e) : null }));
    const ofertas = t.ofertasPorEstado ?? {};
    const fletes = t.solicitudesFletePorEstado ?? {};
    const respondidas = c.solicitudesRespondidas ?? {};
    const sinViajes = t.viajesInstrumentados === false;

    return [
        {
            clave: 'directa', titulo: 'Compras directas', icono: 'fa-handshake',
            principal: { valor: formatoNumero(canal('SOLICITUD').operaciones), etiqueta: 'ventas por solicitud' },
            resumen: `${formatoNumero(c.solicitudesCreadas?.PENDIENTE ?? 0)} solicitudes esperan respuesta · ${formatoColones(canal('SOLICITUD').monto)} vendidos`,
            etapas: [
                { nombre: 'Interés registrado', valor: formatoNumero(c.interes.registros), evidencia: ev('interes', 'REGISTRAR'),
                    explicacion: `${formatoNumero(c.interes.personas)} personas marcaron ${formatoNumero(c.interes.publicaciones)} publicaciones con «Me interesa».`, estados: [] },
                { nombre: 'Solicitudes', valor: formatoNumero(sumar(c.solicitudesCreadas)), evidencia: ev('solicitudes'),
                    explicacion: 'Compradores que pidieron comprar una publicación.',
                    estados: estadosDe(['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA'], c.solicitudesCreadas, 'solicitudes') },
                { nombre: 'Respuestas', valor: formatoNumero(sumar(respondidas)), evidencia: null,
                    explicacion: 'Solicitudes que el vendedor aceptó o rechazó en el periodo.',
                    estados: estadosDe(['ACEPTADA', 'RECHAZADA'], respondidas, 'solicitudes') },
                { nombre: 'Ventas', valor: formatoNumero(canal('SOLICITUD').operaciones), evidencia: ev('ventas', 'SOLICITUD'),
                    explicacion: `${formatoNumero(canal('SOLICITUD').animales)} animales por ${formatoColones(canal('SOLICITUD').monto)}. Cada solicitud aceptada registra su venta.`, estados: [] },
            ],
            salidas: [], noDisponible: [],
        },
        {
            clave: 'subastas', titulo: 'Subastas', icono: 'fa-gavel',
            principal: { valor: formatoNumero(s.estados.adjudicadas.total), etiqueta: 'adjudicadas' },
            resumen: `${formatoNumero(s.estados.activas.total)} activas · ${formatoColones(s.precioPromedio.montoTotal)} adjudicados`,
            etapas: [
                { nombre: 'Subastas abiertas', valor: formatoNumero(s.estados.activas.total + s.estados.cerradas.total + s.estados.canceladas.total),
                    evidencia: ev('subastas'), explicacion: 'Subastas del periodo, en cualquier estado.',
                    estados: [{ nombre: 'Activas ahora', valor: formatoNumero(s.estados.activas.total), tono: 'info', evidencia: ev('subastas', 'ACTIVA') }] },
                { nombre: 'Pujas', valor: formatoNumero(s.pujas), evidencia: null,
                    explicacion: `${formatoNumero(s.compradores.total)} compradores distintos pujaron.`, estados: [] },
                { nombre: 'Cierre', valor: formatoNumero(s.estados.cerradas.total), evidencia: null,
                    explicacion: `Terminaron por tiempo. Éxito ${formatoPorcentaje(s.exito.porcentaje)}: adjudicadas sobre las que cerraron.`, estados: [] },
                { nombre: 'Resultado', valor: formatoColones(s.precioPromedio.montoTotal), evidencia: ev('subastas', 'FINALIZADA'),
                    explicacion: `Promedio por animal ${formatoColones(s.precioPromedio.valor)}; las pujas subieron ${formatoPorcentaje(s.incrementoPromedio.porcentaje)} sobre la base.`,
                    estados: [
                        { nombre: 'Adjudicadas', valor: formatoNumero(s.estados.adjudicadas.total), tono: 'exito', evidencia: ev('subastas', 'FINALIZADA') },
                        { nombre: 'Desiertas', valor: formatoNumero(s.estados.desiertas.total), tono: 'aviso', evidencia: ev('subastas', 'DESIERTA') },
                        { nombre: 'Ventas registradas', valor: formatoNumero(canal('SUBASTA').animales), tono: 'dato', evidencia: ev('ventas', 'SUBASTA') },
                    ] },
            ],
            salidas: [{ nombre: 'Canceladas', valor: formatoNumero(s.estados.canceladas.total), tono: 'neutro', evidencia: ev('subastas', 'CANCELADA'),
                explicacion: 'El vendedor la canceló antes de recibir pujas.' }],
            noDisponible: [],
        },
        {
            clave: 'transporte', titulo: 'Transporte', icono: 'fa-truck-fast',
            principal: { valor: formatoNumero(sinViajes ? (fletes.ACEPTADA ?? 0) : (t.viajesRegistrados?.total ?? 0)), etiqueta: sinViajes ? 'fletes aceptados' : 'viajes realizados' },
            resumen: `${formatoNumero(ofertas.ACTIVA?.total ?? 0)} ofertas activas · ${formatoNumero(fletes.PENDIENTE ?? 0)} solicitudes pendientes`,
            etapas: [
                { nombre: 'Ofertas', valor: formatoNumero((ofertas.ACTIVA?.total ?? 0) + (ofertas.PAUSADA?.total ?? 0)), evidencia: null,
                    explicacion: `${formatoNumero(ofertas.ACTIVA?.capacidad ?? 0)} cabezas de capacidad en las ofertas activas.`,
                    estados: [{ nombre: 'Activas', valor: formatoNumero(ofertas.ACTIVA?.total ?? 0), tono: 'info', evidencia: null },
                        { nombre: 'Pausadas', valor: formatoNumero(ofertas.PAUSADA?.total ?? 0), tono: 'aviso', evidencia: null }] },
                { nombre: 'Solicitudes', valor: formatoNumero(sumar(fletes)), evidencia: ev('fletes'),
                    explicacion: 'Compras que pidieron un flete a un transportista.',
                    estados: estadosDe(['PENDIENTE'], fletes, 'fletes') },
                { nombre: 'Aceptación o rechazo', valor: formatoNumero((fletes.ACEPTADA ?? 0) + (fletes.RECHAZADA ?? 0)), evidencia: null,
                    explicacion: 'Respuesta del transportista. Aceptar un flete no significa que el viaje ya ocurrió.',
                    estados: estadosDe(['ACEPTADA', 'RECHAZADA'], fletes, 'fletes') },
                ...(sinViajes ? [] : [{ nombre: 'Viaje realizado', valor: formatoNumero(t.viajesRegistrados?.total ?? 0), evidencia: ev('viajes'),
                    explicacion: `El transportista marcó el viaje como hecho: ${formatoNumero(t.viajesRegistrados?.cabezas ?? 0)} cabezas${t.viajesRegistrados?.km ? ` · ${formatoNumero(t.viajesRegistrados.km)} km` : ''}${t.viajesRegistrados?.ingresosRegistrados ? ` · ${formatoColones(t.viajesRegistrados.ingresosRegistrados)}` : ''}.`,
                    estados: [] }]),
            ],
            salidas: [{ nombre: 'Canceladas', valor: formatoNumero(fletes.CANCELADA ?? 0), tono: 'neutro', evidencia: ev('fletes', 'CANCELADA'),
                explicacion: 'La solicitud de compra se canceló antes del viaje.' }],
            noDisponible: sinViajes ? [{ nombre: 'Viajes realizados', motivo: 'Información todavía no disponible: la plataforma aún no registra cuándo se hace un viaje, así que no se muestra un número.' }] : [],
        },
        {
            clave: 'publicaciones', titulo: 'Animales y publicaciones', icono: 'fa-cow',
            principal: { valor: formatoNumero(inv.publicacionesPorEstado?.ACTIVO ?? 0), etiqueta: 'publicaciones activas' },
            resumen: `${formatoNumero(sumar(inv.animalesPorEstado))} animales · ${formatoNumero(inv.publicacionesNuevas)} publicaciones nuevas`,
            etapas: [
                { nombre: 'Animales registrados', valor: formatoNumero(sumar(inv.animalesPorEstado)), evidencia: null,
                    explicacion: 'Inventario actual de los vendedores, por estado del animal.',
                    estados: Object.entries(inv.animalesPorEstado ?? {}).map(([k, v]) => ({ nombre: etiquetaEstado(k), valor: formatoNumero(v), tono: tonoEstado(k), evidencia: null })) },
                { nombre: 'Publicaciones', valor: formatoNumero(inv.publicacionesNuevas), evidencia: ev('publicaciones'),
                    explicacion: 'Publicaciones creadas en el periodo.', estados: [] },
                { nombre: 'Estado vigente', valor: formatoNumero(sumar(inv.publicacionesPorEstado)), evidencia: ev('publicaciones'),
                    explicacion: `${formatoNumero(sumar(inv.cambiosDeEstado))} cambios de estado en el periodo, según el historial de cada publicación.`,
                    estados: estadosDe(['ACTIVO', 'PAUSADO', 'VENDIDO', 'RETIRADO'], inv.publicacionesPorEstado, 'publicaciones') },
                { nombre: 'Solicitud o venta', valor: formatoNumero(inv.conSolicitudesPendientes), evidencia: ev('solicitudes', 'PENDIENTE'),
                    explicacion: 'Publicaciones con solicitudes de compra sin responder.', estados: [] },
            ],
            salidas: [], noDisponible: [],
        },
    ];
}

/** Pendientes de la operación que esperan respuesta de alguien. Puro. */
export function atencion(d) {
    return [
        { nombre: 'Solicitudes de compra sin responder', valor: d.comercializacion.solicitudesCreadas?.PENDIENTE ?? 0, evidencia: { nodo: 'solicitudes', estado: 'PENDIENTE' }, accion: 'Ver solicitudes' },
        { nombre: 'Fletes sin respuesta del transportista', valor: d.transporte.solicitudesFletePorEstado?.PENDIENTE ?? 0, evidencia: { nodo: 'fletes', estado: 'PENDIENTE' }, accion: 'Ver fletes' },
        { nombre: 'Fletes aceptados sin viaje registrado', valor: d.transporte.fletesSinViaje ?? 0,
            evidencia: { nodo: 'fletes', estado: 'ACEPTADA' }, accion: 'Ver fletes' },
        { nombre: 'Documentos de identidad por verificar', valor: d.control.documentosPorEstado?.PENDIENTE ?? 0, enlace: 'admin/documentos', accion: 'Abrir documentos', icono: 'fa-id-card' },
    ].filter((x) => x.valor > 0);
}

const ESTADOS = {
    PENDIENTE: ['Pendientes', 'aviso'], ACEPTADA: ['Aceptadas', 'exito'], RECHAZADA: ['Rechazadas', 'neutro'], CANCELADA: ['Canceladas', 'neutro'],
    ACTIVO: ['Activas', 'info'], PAUSADO: ['Pausadas', 'aviso'], VENDIDO: ['Vendidas', 'exito'], RETIRADO: ['Retiradas', 'neutro'], SUBASTA: ['En subasta', 'info'],
    ACTIVA: ['Activa', 'info'], FINALIZADA: ['Adjudicada', 'exito'], DESIERTA: ['Desierta', 'aviso'], SOLICITUD: ['Por solicitud', 'exito'],
    REGISTRAR: ['Registrado', 'info'], RETIRAR: ['Retirado', 'neutro'], REGISTRADO: ['Registrado', 'dato'], SIN_DATO: ['Sin dato', 'neutro'],
    COMPRADOR: ['Comprador', 'neutro'], VENDEDOR: ['Vendedor', 'neutro'], TRANSPORTISTA: ['Transportista', 'neutro'],
    OTRO: ['Otro canal', 'neutro'], EDITAR: ['Editado', 'neutro'],
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

/** Botón con ícono y texto (el ícono es decorativo: el texto da el nombre accesible). */
function boton(texto, clase = 'button button--secondary button--small', nombreIcono = null) {
    const b = el('button', clase);
    b.type = 'button';
    if (nombreIcono) b.append(icono(nombreIcono));
    if (texto) b.append(el('span', null, texto));
    return b;
}

/** Enlace con forma de botón secundario, con ícono y texto. */
function enlaceBoton(texto, href, nombreIcono = 'fa-arrow-right') {
    const a = el('a', 'button button--secondary button--small intel-boton-enlace');
    a.href = href;
    a.append(icono(nombreIcono), el('span', null, texto));
    return a;
}

function consulta(extra = {}) {
    return new URLSearchParams({ ...estado.periodo, ...extra }).toString();
}

function etiqueta(estadoClave, texto = etiquetaEstado(estadoClave), tono = tonoEstado(estadoClave)) {
    const e = el('span', `intel-estado intel-estado--${tono}`);
    e.append(el('span', 'intel-estado__punto'), el('span', null, texto));
    return e;
}

function fecha(valor) {
    return valor ? new Date(valor).toLocaleDateString('es-CR', { timeZone: ZONA }) : '—';
}

/** Navegación Anterior/Siguiente. */
function paginacion(pagina, total, tamano, ir, etiquetaNav) {
    const paginas = Math.max(1, Math.ceil(total / tamano));
    const nav = el('nav', 'intel-paginas');
    nav.setAttribute('aria-label', etiquetaNav);
    const anterior = boton('Anterior', undefined, 'fa-chevron-left');
    anterior.disabled = pagina <= 1;
    anterior.addEventListener('click', () => ir(pagina - 1));
    const siguiente = boton('Siguiente', undefined, 'fa-chevron-right');
    siguiente.disabled = pagina >= paginas;
    siguiente.addEventListener('click', () => ir(pagina + 1));
    nav.append(anterior, el('span', 'intel-paginas__texto', `Página ${pagina} de ${paginas} · ${formatoNumero(total)} registros`), siguiente);
    return nav;
}

function tabla(etiquetaRegion, columnas, filas) {
    const contenedor = el('div', 'intel-tabla');
    contenedor.tabIndex = 0;
    contenedor.setAttribute('role', 'region');
    contenedor.setAttribute('aria-label', etiquetaRegion);
    const t = el('table');
    const cab = el('tr');
    columnas.forEach(([texto, clase]) => { const th = el('th', clase ?? null, texto); th.scope = 'col'; cab.append(th); });
    t.append(el('thead'), el('tbody'));
    t.tHead.append(cab);
    filas.forEach((celdas) => {
        const tr = el('tr');
        celdas.forEach((contenido, i) => {
            const td = el('td', columnas[i][1] ?? null);
            td.dataset.label = columnas[i][0];
            if (contenido instanceof Node) td.append(contenido); else td.textContent = contenido ?? '—';
            tr.append(td);
        });
        t.tBodies[0].append(tr);
    });
    contenedor.append(t);
    return contenedor;
}

function enlacePersona(personaId, nombre) {
    if (!personaId) return el('span', null, nombre ?? '—');
    const b = boton(nombre ?? '—', 'intel-enlace');
    b.addEventListener('click', () => { cerrarDialogo(); abrirExpediente(personaId); });
    return b;
}

// ───── Diálogo de evidencia (dialog nativo: atrapa el foco, Esc cierra; el foco vuelve a quien lo abrió) ─────

let origenDialogo = null;
function abrirDialogo(titulo, pintar) {
    const dialogo = $('#intel-dialogo');
    origenDialogo = document.activeElement;
    $('#intel-dialogo-titulo').textContent = titulo;
    const cuerpo = $('#intel-dialogo-cuerpo');
    cuerpo.replaceChildren();
    pintar(cuerpo);
    if (!dialogo.open) dialogo.showModal();
}
function cerrarDialogo() {
    const dialogo = $('#intel-dialogo');
    if (dialogo?.open) dialogo.close();
}

/** Carga paginada en un contenedor, con estados de carga, vacío, error y reintento. */
function cargaPaginada(destino, pedir, pintar, etiquetaNav) {
    const cargar = async (pagina) => {
        destino.setAttribute('aria-busy', 'true');
        destino.replaceChildren(el('p', 'intel-vacio', 'Cargando registros…'));
        try {
            const { data } = await pedir(pagina);
            if (!data.items.length) { destino.replaceChildren(el('p', 'intel-vacio', 'No hay registros.')); return; }
            destino.replaceChildren(pintar(data), paginacion(pagina, data.total, data.tamanoPagina, cargar, etiquetaNav));
        } catch (error) {
            if (error?.obsoleta) return; // llegó otra búsqueda más nueva
            const reintentar = boton('Reintentar', undefined, 'fa-rotate');
            reintentar.addEventListener('click', () => cargar(pagina));
            destino.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudieron cargar los registros.'), reintentar);
        } finally {
            destino.setAttribute('aria-busy', 'false');
        }
    };
    return cargar;
}

function abrirEvidencia(titulo, evidencia) {
    abrirDialogo(titulo, (cuerpo) => {
        const meta = el('p', 'intel-nota', `${DEFINICIONES[evidencia.nodo] ?? ''} Periodo: ${textoPeriodo(estado.periodo)}.`);
        const filtro = evidencia.estado ? el('p', 'intel-nota') : null;
        if (filtro) filtro.append('Filtrado por estado: ', etiqueta(evidencia.estado));
        const lista = el('div', 'intel-evidencia');
        cuerpo.append(meta, ...(filtro ? [filtro] : []), lista);
        const cantidad = evidencia.nodo === 'subastas' ? 'Pujas' : null;
        cargaPaginada(lista,
            (pagina) => request(`${API}?${consulta({ vista: 'EVIDENCIA', nodo: evidencia.nodo, ...(evidencia.estado ? { estado: evidencia.estado } : {}), pagina, tamanoPagina: 10 })}`),
            (data) => tabla('Registros de la evidencia', [['#', 'intel-num'], ['Registro'], ['Estado'], ['Fecha'], ['Monto', 'intel-num'], ...(cantidad ? [[cantidad, 'intel-num']] : []), ['Persona']],
                data.items.map((i) => [String(i.id), i.titulo ?? '—', etiqueta(i.estado), fecha(i.fecha), formatoColones(i.monto),
                    ...(cantidad ? [formatoNumero(i.cantidad)] : []), enlacePersona(i.personaId, i.persona)])),
            'Páginas de la evidencia')(1);
    });
}

function botonEvidencia(texto, tituloDialogo, evidencia, clase = 'intel-accion') {
    const b = boton(texto, clase, texto ? 'fa-table-list' : null);
    b.addEventListener('click', () => abrirEvidencia(tituloDialogo, evidencia));
    return b;
}

// ───── Visión global ─────

function kpi({ etiqueta: rotulo, valor, completo, detalle, tono }) {
    const t = el('article', `intel-kpi intel-kpi--${tono}`);
    const cifra = el('strong', 'intel-kpi__valor', valor);
    // El valor compacto se ve; el exacto queda en el título y lo lee el lector de pantalla.
    if (completo && completo !== valor) { cifra.title = completo; cifra.setAttribute('aria-label', completo); }
    t.append(el('span', 'intel-kpi__etiqueta', rotulo), cifra, el('span', 'intel-kpi__detalle', detalle));
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
            detalle: `${formatoNumero(animales)} animales vendidos en el periodo` }),
        kpi({ etiqueta: 'Subastas adjudicadas', valor: formatoNumero(d.subastas.estados.adjudicadas.total), tono: 'exito',
            detalle: `Éxito ${formatoPorcentaje(d.subastas.exito.porcentaje)} de ${formatoNumero(d.subastas.exito.finalizadas)} cerradas` }),
        kpi({ etiqueta: 'Subastas activas', valor: formatoNumero(d.subastas.estados.activas.total), tono: 'info', detalle: `${formatoNumero(d.subastas.pujas)} pujas en el periodo` }),
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
        const enlace = enlaceBoton(`Abrir ${t.etiqueta.toLowerCase()}`, t.href);
        enlace.classList.add('dashboard-metric__link');
        card.append(caja, el('span', 'dashboard-metric__label', t.etiqueta), el('strong', 'dashboard-metric__value', formatoNumero(t.valor)),
            el('span', 'dashboard-metric__detalle', t.detalle), enlace);
        return card;
    }));
    $('#dashboard-metrics').setAttribute('aria-busy', 'false');
}

// ───── Requieren atención ─────

function pintarAtencion(d) {
    const items = atencion(d);
    const caja = el('section', 'intel-atencion__grupo');
    caja.append(el('h3', 'intel-panel__titulo', 'Pendientes de la operación'), el('p', 'intel-nota', 'Esperan que alguien responda. Son normales en una plataforma activa.'));
    if (!items.length) caja.append(etiqueta(null, 'Nada pendiente', 'exito'));
    else {
        const ul = el('ul', 'intel-atencion__lista');
        items.forEach((x) => {
            const li = el('li');
            const accion = x.evidencia ? botonEvidencia(x.accion, x.nombre, x.evidencia, 'button button--secondary button--small') : enlaceBoton(x.accion, x.enlace, x.icono);
            li.append(etiqueta(null, x.nombre, 'aviso'), el('strong', 'intel-atencion__valor', formatoNumero(x.valor)), accion);
            ul.append(li);
        });
        caja.append(ul);
    }
    $('#intel-atencion').replaceChildren(caja);
    $('#intel-atencion').setAttribute('aria-busy', 'false');
}

// ───── Actividad comercial: áreas y explorador de procesos ─────

function pintarAreas(d) {
    const areas = procesos(d);
    estado.areas = areas;
    const contenedor = $('#intel-arbol');
    contenedor.setAttribute('aria-busy', 'false');
    contenedor.replaceChildren(...areas.map((a) => {
        const card = el('article', 'intel-area');
        card.dataset.clave = a.clave;
        const cabeza = el('div', 'intel-area__cabeza');
        const caja = el('span', 'intel-area__icono');
        caja.append(icono(a.icono));
        cabeza.append(caja, el('h3', 'intel-area__titulo', a.titulo));
        const cifra = el('p', 'intel-area__cifra');
        cifra.append(el('strong', null, a.principal.valor), el('span', null, ` ${a.principal.etiqueta}`));
        const explorar = boton('Explorar proceso', 'button button--secondary button--small intel-area__boton', 'fa-route');
        explorar.setAttribute('aria-controls', 'intel-proceso');
        explorar.setAttribute('aria-expanded', String(estado.areaAbierta === a.clave));
        explorar.addEventListener('click', () => abrirProceso(a.clave, true));
        card.append(cabeza, cifra, el('p', 'intel-area__resumen', a.resumen), explorar);
        if (estado.areaAbierta === a.clave) card.classList.add('intel-area--abierta');
        return card;
    }));
    if (estado.areaAbierta) abrirProceso(estado.areaAbierta, false);
}

function pintarEstados(estados, area) {
    const ul = el('ul', 'intel-etapa__estados');
    estados.forEach((e) => {
        const li = el('li');
        const nombre = etiqueta(null, e.nombre, e.tono);
        if (e.evidencia) {
            const b = botonEvidencia('', `${area} · ${e.nombre}`, e.evidencia, 'intel-chip');
            b.append(nombre, el('strong', null, e.valor));
            b.setAttribute('aria-label', `${e.nombre}: ${e.valor}. Ver registros`);
            li.append(b);
        } else {
            const s = el('span', 'intel-chip intel-chip--fijo');
            s.append(nombre, el('strong', null, e.valor));
            li.append(s);
        }
        ul.append(li);
    });
    return ul;
}

function abrirProceso(clave, enfocar) {
    const area = estado.areas?.find((a) => a.clave === clave);
    const panel = $('#intel-proceso');
    if (!area) return;
    estado.areaAbierta = clave;
    document.querySelectorAll('.intel-area').forEach((c) => {
        const abierta = c.dataset.clave === clave;
        c.classList.toggle('intel-area--abierta', abierta);
        c.querySelector('.intel-area__boton')?.setAttribute('aria-expanded', String(abierta));
    });
    const cabeza = el('div', 'intel-proceso-panel__cabeza');
    const titulo = el('h3', null, `Proceso: ${area.titulo}`);
    titulo.id = 'intel-proceso-titulo';
    titulo.tabIndex = -1;
    const cerrar = boton('Cerrar proceso', undefined, 'fa-xmark');
    cerrar.addEventListener('click', () => {
        estado.areaAbierta = null;
        panel.hidden = true;
        document.querySelectorAll('.intel-area').forEach((c) => {
            c.classList.remove('intel-area--abierta');
            c.querySelector('.intel-area__boton')?.setAttribute('aria-expanded', 'false');
        });
        document.querySelector(`.intel-area[data-clave="${clave}"] .intel-area__boton`)?.focus();
    });
    cabeza.append(titulo, el('p', 'intel-nota', `Etapas en orden; cada una cuenta sus propios registros del periodo (${textoPeriodo(estado.periodo)}).`), cerrar);
    const etapas = el('ol', 'intel-etapas');
    area.etapas.forEach((e, i) => {
        const li = el('li', 'intel-etapa');
        const encabezado = el('div', 'intel-etapa__cabeza');
        encabezado.append(el('span', 'intel-etapa__paso', `Etapa ${i + 1}`), el('h4', null, e.nombre));
        li.append(encabezado, el('strong', 'intel-etapa__valor', e.valor), el('p', 'intel-etapa__texto', e.explicacion));
        if (e.estados.length) li.append(pintarEstados(e.estados, area.titulo));
        if (e.evidencia) li.append(botonEvidencia('Ver registros', `${area.titulo} · ${e.nombre}`, e.evidencia));
        etapas.append(li);
    });
    const extras = [];
    if (area.salidas.length || area.noDisponible.length) {
        const pie = el('div', 'intel-proceso-panel__pie');
        area.salidas.forEach((s) => {
            const caja = el('div', 'intel-salida');
            caja.append(el('span', 'intel-etapa__paso', 'Salida aparte'), el('h4', null, `${s.nombre}: ${s.valor}`), el('p', 'intel-etapa__texto', s.explicacion));
            if (s.evidencia) caja.append(botonEvidencia('Ver registros', `${area.titulo} · ${s.nombre}`, s.evidencia));
            pie.append(caja);
        });
        area.noDisponible.forEach((n) => {
            const caja = el('div', 'intel-salida intel-salida--sin');
            caja.append(el('span', 'intel-etapa__paso', 'Sin registro'), el('h4', null, n.nombre), el('p', 'intel-etapa__texto', n.motivo));
            pie.append(caja);
        });
        extras.push(pie);
    }
    panel.replaceChildren(cabeza, etapas, ...extras);
    panel.hidden = false;
    if (enfocar) titulo.focus();
}

// ───── Personas y actividades ─────

function pintarResumenTransporte(d) {
    const ofertas = d.transporte.ofertasPorEstado ?? {};
    const fletes = d.transporte.solicitudesFletePorEstado ?? {};
    const mini = (rotulo, valor, tono = 'neutro') => {
        const m = el('div', `intel-mini intel-mini--${tono}`);
        m.append(el('span', null, rotulo), el('strong', null, formatoNumero(valor)));
        return m;
    };
    $('#intel-transporte-resumen').replaceChildren(
        mini('Transportistas activos', d.personas.transportistas.activos), mini('Ofertas activas', ofertas.ACTIVA?.total ?? 0, 'info'),
        mini('Capacidad ofertada (cabezas)', ofertas.ACTIVA?.capacidad ?? 0, 'dato'), mini('Solicitudes recibidas', sumar(fletes)),
        mini('Aceptadas', fletes.ACEPTADA ?? 0, 'exito'), mini('Pendientes', fletes.PENDIENTE ?? 0, 'aviso'),
    );
    $('#intel-transporte-resumen').setAttribute('aria-busy', 'false');
}

let cargarTransportistas = () => {};
function iniciarTransportistas() {
    const form = $('#intel-transportistas-filtros');
    const cuerpo = $('#intel-transportistas-cuerpo');
    let pedido = 0;
    const cargar = cargaPaginada(cuerpo, async (pagina) => {
        const actual = ++pedido;
        const valores = Object.fromEntries(new FormData(form));
        if (valores.q.trim().length < 2) delete valores.q;
        if (!valores.estado) delete valores.estado;
        const r = await request(`${API}?${new URLSearchParams({ vista: 'TRANSPORTISTAS', pagina, tamanoPagina: 10, ...valores })}`);
        if (actual !== pedido) throw Object.assign(new Error(''), { obsoleta: true });
        return r;
    }, (data) => tabla('Métricas por transportista',
        [['Transportista'], ['Ofertas activas', 'intel-num'], ['Capacidad', 'intel-num'], ['Vehículos', 'intel-num'], ['Solicitudes', 'intel-num'],
            ['Aceptadas', 'intel-num'], ['Pendientes', 'intel-num'], ['Viajes', 'intel-num']],
        data.items.map((f) => {
            const nombre = el('div', 'intel-tabla__persona');
            nombre.append(enlacePersona(f.personaId, f.nombre), etiqueta(null, f.activo ? 'Activo' : 'Inactivo', f.activo ? 'exito' : 'neutro'));
            return [nombre, formatoNumero(f.ofertasActivas), f.capacidadOfertada === null ? '—' : formatoNumero(f.capacidadOfertada), formatoNumero(f.vehiculos),
                formatoNumero(f.solicitudesRecibidas), formatoNumero(f.solicitudesAceptadas), formatoNumero(f.solicitudesPendientes ?? 0), formatoNumero(f.viajesRegistrados ?? 0)];
        })), 'Páginas de transportistas');
    cargarTransportistas = () => cargar(1).catch(() => {});
    let espera = 0;
    form.addEventListener('input', (e) => { clearTimeout(espera); espera = setTimeout(cargarTransportistas, e.target.name === 'q' ? 250 : 0); });
    form.addEventListener('submit', (e) => { e.preventDefault(); cargarTransportistas(); });
}

/** «Nombre (alias)» solo si el alias dice algo distinto del nombre. Puro. */
export function conAlias({ nombre, alias }) {
    return alias && alias.trim().toLowerCase() !== String(nombre).trim().toLowerCase() ? `${nombre} (${alias})` : nombre;
}

function cantidadYMonto(cantidad, monto) {
    return cantidad ? `${formatoNumero(cantidad)} · ${formatoColones(monto)}` : '0';
}

async function buscarPersonas(q) {
    const destino = $('#intel-persona-resultados');
    const pedido = ++estado.busqueda; // al escribir rápido solo vale la última búsqueda
    destino.replaceChildren(el('p', 'intel-vacio', 'Buscando…'));
    try {
        const { data } = await request(API, { method: 'POST', body: JSON.stringify({ consulta: { vista: 'PERSONA', q } }) });
        if (pedido !== estado.busqueda) return;
        if (!data.personas.length) { destino.replaceChildren(el('p', 'intel-vacio', 'Ninguna persona coincide.')); return; }
        const ul = el('ul', 'intel-resultados');
        data.personas.forEach((p) => {
            const li = el('li');
            const b = boton(conAlias(p), 'intel-resultado');
            b.append(el('small', null, p.actividades.map((a) => etiquetaEstado(a)).join(' · ') || 'Sin actividades'));
            b.addEventListener('click', () => abrirExpediente(p.personaId));
            li.append(b);
            ul.append(li);
        });
        destino.replaceChildren(el('p', 'screen-reader-only', data.personas.length === 1 ? '1 persona encontrada' : `${data.personas.length} personas encontradas`), ul);
    } catch (error) {
        if (pedido !== estado.busqueda) return;
        destino.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudo buscar.'));
    }
}

async function abrirExpediente(personaId) {
    const destino = $('#intel-persona');
    const pedido = ++estado.expediente;
    destino.replaceChildren(el('p', 'intel-vacio', 'Cargando expediente…'));
    destino.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    try {
        const { data } = await request(`${API}?vista=PERSONA&personaId=${Number(personaId)}`);
        if (pedido !== estado.expediente) return;
        const x = data.expediente;
        const card = el('article', 'intel-expediente');
        const titulo = el('h4', 'intel-expediente__nombre', conAlias(x));
        const estadoCuenta = el('div', 'intel-expediente__estado');
        estadoCuenta.append(etiqueta(null, x.activa ? 'Cuenta activa' : 'Cuenta inactiva', x.activa ? 'exito' : 'neutro'),
            el('span', 'intel-nota', `Identidad ${DOCUMENTO[x.documento] ?? 'sin enviar'}`),
            el('span', 'intel-nota', [x.comprador && 'Comprador', x.vendedor && 'Vendedor', x.transportista && 'Transportista'].filter(Boolean).join(' · ') || 'Sin actividades'));
        card.append(titulo, estadoCuenta);
        const bloque = (rotulo, filas) => {
            const sec = el('section', 'intel-expediente__bloque');
            sec.append(el('h5', null, rotulo));
            const dl = el('dl', 'intel-lista');
            filas.forEach(([k, v]) => { const f = el('div', 'intel-lista__fila'); f.append(el('dt', null, k), el('dd', null, v)); dl.append(f); });
            sec.append(dl);
            return sec;
        };
        const conteo = (o) => Object.entries(o ?? {}).map(([k, v]) => `${etiquetaEstado(k)} ${v}`).join(' · ') || '0';
        const bloques = el('div', 'intel-expediente__bloques');
        if (x.comprador) bloques.append(bloque('Como comprador', [['Pujas', formatoNumero(x.comprador.pujas)], ['Subastas ganadas', formatoNumero(x.comprador.subastasGanadas)],
            ['Solicitudes', conteo(x.comprador.solicitudes)], ['Compras', cantidadYMonto(x.comprador.compras, x.comprador.montoComprado)],
            ['Me interesa', formatoNumero(x.comprador.meInteresa)]]));
        if (x.vendedor) bloques.append(bloque('Como vendedor', [['Fincas', formatoNumero(x.vendedor.fincas)], ['Animales', formatoNumero(x.vendedor.animales)],
            ['Publicaciones', formatoNumero(x.vendedor.publicaciones)], ['Subastas', conteo(x.vendedor.subastas)],
            ['Ventas', cantidadYMonto(x.vendedor.ventas, x.vendedor.montoVendido)]]));
        if (x.transportista) bloques.append(bloque('Como transportista', [['Estado', x.transportista.activo ? 'Activo' : 'Inactivo'], ['Ofertas', conteo(x.transportista.ofertas)],
            ['Capacidad ofertada', formatoNumero(x.transportista.capacidadOfertada)], ['Vehículos', formatoNumero(x.transportista.vehiculos)],
            ['Solicitudes de flete', conteo(x.transportista.solicitudesFlete)], ['Viajes realizados', formatoNumero(x.transportista.viajesRegistrados ?? 0)]]));
        if (bloques.childElementCount) card.append(bloques); else card.append(el('p', 'intel-vacio', 'Sin actividades registradas.'));
        destino.replaceChildren(card);
        destino.focus({ preventScroll: true });
    } catch (error) {
        if (pedido !== estado.expediente) return;
        destino.replaceChildren(el('p', 'intel-error', error?.message || 'No se pudo cargar el expediente.'));
    }
}

// ───── Carga ─────

async function cargar() {
    const pedido = ++estado.pedido;
    const form = $('#intel-periodo');
    estado.periodo = parametrosPeriodo(Object.fromEntries(new FormData(form)));
    const botonActualizar = $('#dashboard-refresh');
    const status = $('#dashboard-status');
    botonActualizar.disabled = true;
    status.textContent = 'Actualizando indicadores…';
    try {
        const { data } = await request(`${API}?${consulta()}`);
        if (pedido !== estado.pedido) return; // solo vale la última respuesta
        pintarKpis(data);
        pintarRed(data.personas);
        pintarAreas(data);
        pintarResumenTransporte(data);
        pintarAtencion(data);
        cargarTransportistas();
        const hora = new Intl.DateTimeFormat('es-CR', { hour: '2-digit', minute: '2-digit', timeZone: ZONA }).format(new Date(data.generado));
        status.textContent = `Periodo ${textoPeriodo(data.periodo)} · actualizado a las ${hora}.`;
    } catch (error) {
        if (pedido !== estado.pedido) return;
        status.textContent = error?.message || 'No se pudieron cargar los indicadores.';
        $('#intel-arbol').setAttribute('aria-busy', 'false');
        $('#intel-arbol').replaceChildren(el('p', 'intel-error', 'No se pudieron cargar los procesos. Usa «Actualizar» para reintentar.'));
    } finally {
        if (pedido === estado.pedido) botonActualizar.disabled = false;
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
    const dialogo = $('#intel-dialogo');
    dialogo.querySelector('[data-cerrar-dialogo]').addEventListener('click', cerrarDialogo);
    dialogo.addEventListener('click', (e) => { if (e.target === dialogo) cerrarDialogo(); }); // clic fuera del contenido
    dialogo.addEventListener('close', () => { if (origenDialogo?.isConnected) origenDialogo.focus(); origenDialogo = null; });
    const busqueda = $('#intel-busqueda');
    let espera = 0;
    const buscar = () => {
        clearTimeout(espera);
        const q = busqueda.elements.q.value.trim();
        if (q.length < 2) { estado.busqueda++; $('#intel-persona-resultados').replaceChildren(); return; }
        espera = setTimeout(() => buscarPersonas(q), 250);
    };
    busqueda.addEventListener('input', buscar);
    busqueda.addEventListener('submit', (e) => { e.preventDefault(); buscar(); });
    iniciarTransportistas();
    cargar();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
