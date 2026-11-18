// Mi dashboard (DEC-DASHBOARD-001): un dashboard por rol (Vendedor y Comprador), cada uno igual al de alguien que solo
// tiene ese rol. Estructura (de arriba abajo): resultados (4 KPI), estado en una barra proporcional con leyenda que
// filtra, participación y operación, evolución (dos gráficos) y una tabla única de ejemplares con foto. Cada KPI con
// ejemplares trae «Ver ejemplares», que filtra la tabla por los ids exactos que forman ese número. Colores semánticos
// (verde = adjudicada/ganada, azul = activa, ámbar con rayas = desierta/superada, gris = cancelada/perdida, violeta =
// montos), siempre con texto e ícono. Todo se pinta con DOM (textContent). La ubicación solo vive en memoria.

import { request } from './shared/api.js?v=auth-gate-16';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-4';
import { formatPrice, safeImageUrl } from './explore.js?v=explore-16';
import { barraSegmentada, barrasDivergentes, barrasHorizontales, columnasPorMes, el, porMes } from './dashboard-graficos.js?v=graficos-3';
import { esRegistroIncompleto, mostrarRegistroIncompleto } from './shared/cuenta.js?v=cuenta-2';

const API = 'api/v1/mi-dashboard';
const NIVELES = { TIPO_RAZA: 'misma categoría y raza', TIPO: 'misma categoría', ESPECIE: 'mismo tipo de ganado' };
const ROLES = { VENDEDOR: 'vendedor', COMPRADOR: 'comprador' };
const PERIODOS = { 7: 'últimos 7 días', 30: 'últimos 30 días', 90: 'últimos 90 días', 365: 'últimos 12 meses', todo: 'todo el tiempo' };
const $ = (selector) => document.querySelector(selector);

// Comprador primero: toda cuenta completa lo tiene, así la geometría inicial coincide con la final y
// no hace falta una segunda petición tras un 409 "sin rol".
const estado = { rol: 'COMPRADOR', roles: null, punto: null, catalogos: { especies: [], tipos: [], razas: [] }, pedido: 0 };

// ───────────────────────────── Funciones puras ─────────────────────────────

/** "45 min", "3 h 20 min", "1 d 1 h" o "—". Puro. */
export function formatoDuracion(minutos) {
    if (typeof minutos !== 'number' || !Number.isFinite(minutos) || minutos < 0) return '—';
    if (minutos < 60) return `${Math.round(minutos)} min`;
    const dias = Math.floor(minutos / 1440);
    const horas = Math.round((minutos % 1440) / 60);
    const resto = Math.round(minutos % 60);
    if (dias > 0) return horas ? `${dias} d ${horas} h` : `${dias} d`;
    return resto ? `${Math.floor(minutos / 60)} h ${resto} min` : `${Math.floor(minutos / 60)} h`;
}

/** "62,5 %" o "—". Puro. */
export function formatoPorcentaje(valor, { signo = false } = {}) {
    if (typeof valor !== 'number' || !Number.isFinite(valor)) return '—';
    const texto = valor.toLocaleString('es-CR', { maximumFractionDigits: 1 });
    return `${signo && valor > 0 ? '+' : ''}${texto} %`;
}

/** Fechas AAAA-MM-DD (hora de Costa Rica) de un periodo de N días que termina hoy. Puro. */
export function rangoDias(dias, hoy = new Date()) {
    const formato = (fecha) => fecha.toLocaleDateString('en-CA', { timeZone: 'America/Costa_Rica' });
    const desde = new Date(hoy.getTime() - (dias - 1) * 86400000);
    return { desde: formato(desde), hasta: formato(hoy) };
}

/** Texto del periodo para el subtítulo ("últimos 30 días", "del 2026-10-01 al 2026-10-31"). Puro. */
export function textoPeriodo(valores) {
    if (valores.periodo === 'rango') {
        if (valores.desde && valores.hasta) return `del ${valores.desde} al ${valores.hasta}`;
        if (valores.desde) return `desde el ${valores.desde}`;
        if (valores.hasta) return `hasta el ${valores.hasta}`;
        return 'todo el tiempo';
    }
    return PERIODOS[valores.periodo] ?? PERIODOS[30];
}

/**
 * Parámetros de la API a partir del formulario de filtros. Puro (recibe un objeto plano). El comprador suma el radio
 * y, si la compartió, la ubicación (`punto`).
 */
export function parametrosFiltro(valores, rol, hoy = new Date(), punto = null) {
    const parametros = new URLSearchParams({ rol });
    if (valores.periodo === 'rango') {
        if (valores.desde) parametros.set('desde', valores.desde);
        if (valores.hasta) parametros.set('hasta', valores.hasta);
    } else if (valores.periodo !== 'todo') {
        const { desde, hasta } = rangoDias(Number(valores.periodo) || 30, hoy);
        parametros.set('desde', desde);
        parametros.set('hasta', hasta);
    }
    for (const clave of ['especieId', 'tipoId', 'razaId', 'provincia']) {
        if (valores[clave]) parametros.set(clave, valores[clave]);
    }
    if (rol === 'COMPRADOR') {
        if (valores.radioKm) parametros.set('radioKm', valores.radioKm);
        if (punto) {
            parametros.set('latitud', String(punto.latitud));
            parametros.set('longitud', String(punto.longitud));
        }
    }
    return parametros;
}

/** "₡900.000 frente a ₡880.000 (+2,3 %)" o un texto si no hay comparables. Puro. */
export function textoComparacion(s) {
    const c = s.comparacion;
    if (!c || c.promedio == null) return 'Sin animales comparables todavía';
    const actual = typeof s.precioActual === 'number' ? s.precioActual : s.precioBase;
    return `${formatPrice(actual)} frente a ${formatPrice(c.promedio)} (${formatoPorcentaje(c.diferenciaPorcentaje, { signo: true })})`;
}

/**
 * Estado de cada subasta en la tabla y en la barra segmentada. Vendedor: adjudicada, vivo, sin-ofertas, cancelada.
 * Comprador: ganada, ganando, superada, perdida (sus pujas) o cerca (oportunidad). Puro.
 */
export function claveEstado(s, rol) {
    if (rol === 'VENDEDOR') {
        return { FINALIZADA: 'adjudicada', ACTIVA: 'vivo', DESIERTA: 'sin-ofertas', CANCELADA: 'cancelada' }[s.estado] ?? 'cancelada';
    }
    if (s.oportunidad) return 'cerca';
    if (s.estado === 'FINALIZADA') return s.soyGanador ? 'ganada' : 'perdida';
    return s.voyGanando ? 'ganando' : 'superada';
}

/** Cómo le va a la persona en esa subasta, desde el lado del comprador (texto corto). Puro. */
export function situacionComprador(s) {
    if (s.oportunidad) return s.distanciaKm != null ? `A ${String(s.distanciaKm).replace('.', ',')} km` : 'En la provincia elegida';
    if (s.estado === 'FINALIZADA') return s.soyGanador ? 'La ganaste' : 'La ganó otro comprador';
    return s.voyGanando ? 'Vas ganando' : 'Te superaron';
}

/** Si la persona no tiene el rol de la pestaña actual pero sí el otro, se muestra el otro. Puro. */
export function rolDisponible(rol, roles = {}) {
    if (roles[ROLES[rol]]) return rol;
    return Object.keys(ROLES).find((otro) => roles[ROLES[otro]]) ?? null;
}

/** Definición de los estados de cada rol: nombre, descripción corta y si ocurre "ahora". */
export const ESTADOS = {
    VENDEDOR: [
        { clave: 'adjudicada', etiqueta: 'Adjudicadas', descripcion: 'Cerraron con ganador', icono: 'fa-circle-check' },
        { clave: 'vivo', etiqueta: 'Activas', descripcion: 'Reciben pujas ahora', icono: 'fa-tower-broadcast' },
        { clave: 'sin-ofertas', etiqueta: 'Desiertas', descripcion: 'Cerraron sin pujas', icono: 'fa-hourglass-end' },
        { clave: 'cancelada', etiqueta: 'Canceladas', descripcion: 'Las cancelaste sin pujas', icono: 'fa-ban' },
    ],
    COMPRADOR: [
        { clave: 'ganada', etiqueta: 'Ganadas', descripcion: 'Te las adjudicaron', icono: 'fa-trophy' },
        { clave: 'ganando', etiqueta: 'Vas ganando', descripcion: 'Tu puja es la más alta', icono: 'fa-arrow-trend-up' },
        { clave: 'superada', etiqueta: 'Te superaron', descripcion: 'Otro comprador va arriba', icono: 'fa-triangle-exclamation' },
        { clave: 'perdida', etiqueta: 'Perdidas', descripcion: 'Las ganó otro comprador', icono: 'fa-circle-xmark' },
    ],
};

/** Cuántas subastas hay en cada estado del rol (las oportunidades no cuentan como pujas). Puro. */
export function contarEstados(subastas, rol) {
    const cuenta = new Map();
    subastas.forEach((s) => {
        const clave = claveEstado(s, rol);
        cuenta.set(clave, (cuenta.get(clave) ?? 0) + 1);
    });
    return ESTADOS[rol].map((e) => ({ ...e, cantidad: cuenta.get(e.clave) ?? 0 }));
}

// ───────────────────────────── Piezas de la página ─────────────────────────────

const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

function icono(nombre) {
    const i = el('i', nombre);
    i.setAttribute('aria-hidden', 'true');
    return i;
}

function seccion(id, titulo, ayuda, ...hijos) {
    const s = el('section', 'dash-seccion');
    s.setAttribute('aria-labelledby', id);
    const cabeza = el('div', 'dash-seccion__cabeza');
    const h = el('h2', 'dash-seccion__titulo', titulo);
    h.id = id;
    cabeza.append(h);
    if (ayuda) cabeza.append(el('p', 'dash-seccion__ayuda', ayuda));
    s.append(cabeza, ...hijos.filter(Boolean));
    return s;
}

/**
 * Botón "Ver N ejemplares" que filtra la tabla por las subastas exactas que forman un indicador. Sin ids no se dibuja:
 * un indicador sin ejemplares no promete una lista vacía.
 */
function botonEvidencia(ids, etiqueta, alFiltrar) {
    const lista = Array.isArray(ids) ? ids : [];
    if (!lista.length) return null;
    const b = el('button', 'dash-evidencia');
    b.type = 'button';
    b.append(el('span', null, `Ver ${plural(lista.length, 'ejemplar', 'ejemplares')}`), icono('fa-solid fa-arrow-down'));
    b.setAttribute('aria-label', `Ver los ${plural(lista.length, 'ejemplar', 'ejemplares')} de «${etiqueta}» en la tabla`);
    b.addEventListener('click', () => alFiltrar({ etiqueta, ids: lista }));
    return b;
}

/**
 * KPI: etiqueta, valor, detalle y su evidencia. `tono` es semántico (exito | info | aviso | dato); por defecto neutro.
 * `progreso` (0-100) dibuja una barra solo cuando el porcentaje existe: sin base no hay barra en 0.
 */
function kpi({ etiqueta, valor, detalle, tono = null, progreso = null, ids = [], alFiltrar, definicion }) {
    const t = el('article', `dash-tarjeta dash-kpi${tono ? ` dash-kpi--${tono}` : ''}`);
    const cabeza = el('p', 'dash-kpi__etiqueta', etiqueta);
    if (definicion) cabeza.title = definicion;
    t.append(cabeza, el('p', 'dash-kpi__valor', valor));
    if (typeof progreso === 'number' && Number.isFinite(progreso)) {
        const barra = el('div', 'dash-progreso');
        barra.setAttribute('role', 'progressbar');
        barra.setAttribute('aria-label', etiqueta);
        barra.setAttribute('aria-valuemin', '0');
        barra.setAttribute('aria-valuemax', '100');
        barra.setAttribute('aria-valuenow', String(Math.round(progreso)));
        const lleno = el('div', 'dash-progreso__lleno');
        lleno.style.width = `${Math.max(0, Math.min(100, progreso))}%`;
        barra.append(lleno);
        t.append(barra);
    }
    t.append(el('p', 'dash-kpi__detalle', detalle));
    const evidencia = botonEvidencia(ids, etiqueta, alFiltrar);
    if (evidencia) t.append(evidencia);
    return t;
}

/** Métrica secundaria compacta (ícono, valor, texto y evidencia) para "Participación y operación". */
function metrica({ nombreIcono, valor, texto, ids = [], etiqueta, alFiltrar, accion = null }) {
    const t = el('article', 'dash-tarjeta dash-metrica');
    const caja = el('span', 'dash-metrica__icono');
    caja.append(icono(nombreIcono));
    const cuerpo = el('div', 'dash-metrica__cuerpo');
    cuerpo.append(el('p', 'dash-metrica__valor', valor), el('p', 'dash-metrica__texto', texto));
    const evidencia = botonEvidencia(ids, etiqueta ?? texto, alFiltrar);
    if (evidencia) cuerpo.append(evidencia);
    if (accion) cuerpo.append(accion);
    t.append(caja, cuerpo);
    return t;
}

/** Estados: una barra proporcional y una leyenda compacta cuyos botones filtran la tabla. Sin tarjetas gigantes. */
function estados(conteos, etiquetaTotal, alElegir, nota) {
    const tarjeta = el('div', 'dash-tarjeta dash-estados');
    const total = conteos.reduce((suma, e) => suma + e.cantidad, 0);
    if (total) tarjeta.append(barraSegmentada(conteos, etiquetaTotal));
    else tarjeta.append(el('p', 'grafico__vacio', 'Sin subastas en este periodo.'));
    const leyenda = el('div', 'dash-leyenda');
    leyenda.setAttribute('role', 'group');
    leyenda.setAttribute('aria-label', `${etiquetaTotal}: filtrar la tabla`);
    conteos.forEach((e) => {
        const boton = el('button', `dash-leyenda__item estado-texto--${e.clave}`);
        boton.type = 'button';
        const muestra = el('span', `dash-muestra estado--${e.clave}`);
        muestra.setAttribute('aria-hidden', 'true');
        const textos = el('span', 'dash-leyenda__textos');
        const nombre = el('span', 'dash-leyenda__nombre');
        nombre.append(icono(`fa-solid ${e.icono}`), el('span', null, e.etiqueta));
        textos.append(nombre, el('span', 'dash-leyenda__descripcion', e.descripcion));
        boton.append(muestra, textos, el('strong', 'dash-leyenda__numero', e.cantidad));
        boton.setAttribute('aria-label', `${e.etiqueta}: ${e.cantidad}. ${e.descripcion}. Ver en la tabla.`);
        boton.addEventListener('click', () => alElegir(e.clave));
        leyenda.append(boton);
    });
    tarjeta.append(leyenda);
    if (nota) tarjeta.append(el('p', 'dash-estados__nota', nota));
    return tarjeta;
}

function foto(url, alt) {
    const caja = el('span', 'dash-foto');
    const segura = safeImageUrl(url);
    if (!segura) {
        caja.append(icono('fa-solid fa-cow'));
        caja.title = 'Sin foto';
        return caja;
    }
    const img = el('img');
    img.src = segura;
    img.alt = alt;
    img.loading = 'lazy';
    img.referrerPolicy = 'no-referrer';
    img.width = 48;
    img.height = 48;
    img.addEventListener('error', () => caja.replaceChildren(icono('fa-solid fa-cow')), { once: true });
    caja.append(img);
    return caja;
}

function etiquetaEstado(clave, texto) {
    const definicion = Object.values(ESTADOS).flat().find((e) => e.clave === clave);
    const tag = el('span', `dash-tag estado-texto--${clave}`);
    const muestra = el('span', `dash-muestra estado--${clave}`);
    muestra.setAttribute('aria-hidden', 'true');
    tag.append(muestra, ...(definicion ? [icono(`fa-solid ${definicion.icono}`)] : []), el('span', null, texto));
    return tag;
}

/** Fecha corta (hora de Costa Rica) o "—". Puro. */
export function fechaCorta(iso) {
    const fecha = new Date(iso ?? '');
    if (!iso || Number.isNaN(fecha.getTime())) return '—';
    return fecha.toLocaleDateString('es-CR', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Costa_Rica' });
}

/**
 * Fecha del hecho de una fila: fin programado si cerró (FINALIZADA o DESIERTA), la cancelación, o el inicio si sigue
 * activa (igual que Subasta::SQL_FECHA_HECHO). Puro.
 */
export function fechaHecho(s) {
    if (s.estado === 'FINALIZADA' || s.estado === 'DESIERTA') return s.fechaFin;
    if (s.estado === 'CANCELADA') return s.fechaCierre ?? s.fechaFin;
    return s.fechaInicio;
}

/**
 * Tabla única de ejemplares. Dos filtros que se combinan: un chip de estado y, opcional, la evidencia de un KPI
 * ({ etiqueta, ids }) que deja ver exactamente las subastas que forman ese número. Devuelve la sección y dos funciones.
 */
function tablaEjemplares({ subastas, rol, chips, columnas, etiqueta }) {
    const contenedor = el('div', 'dash-tarjeta dash-ejemplares');
    contenedor.id = 'dashboard-ejemplares';
    contenedor.tabIndex = -1;
    const barra = el('div', 'dash-chips');
    barra.setAttribute('role', 'group');
    barra.setAttribute('aria-label', 'Filtrar ejemplares por estado');
    const filtroKpi = el('div', 'dash-filtro-kpi');
    filtroKpi.setAttribute('role', 'status');
    const caja = el('div', 'dash-tabla');
    caja.tabIndex = 0;
    caja.setAttribute('role', 'region');
    caja.setAttribute('aria-label', 'Tabla de ejemplares (se desplaza a los lados)');
    const tabla = el('table');
    const cabeza = el('tr');
    ['Ejemplar', 'Estado', 'Fecha', ...columnas.map((c) => c.titulo)].forEach((titulo, i) => {
        const th = el('th', i > 2 ? 'dash-tabla__numero' : null, titulo);
        th.scope = 'col';
        cabeza.append(th);
    });
    tabla.append(el('thead'), el('tbody'));
    tabla.tHead.append(cabeza);
    caja.append(tabla);

    const actual = { chip: 'todos', evidencia: null };
    const pintar = () => {
        barra.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.chip === actual.chip)));
        const ids = actual.evidencia ? new Set(actual.evidencia.ids.map(Number)) : null;
        const visibles = subastas.filter((s) => (actual.chip === 'todos' || claveEstado(s, rol) === actual.chip)
            && (!ids || ids.has(Number(s.subastaId))));
        filtroKpi.replaceChildren();
        if (actual.evidencia) {
            const quitar = el('button', 'dash-chip dash-chip--quitar');
            quitar.type = 'button';
            quitar.append(el('span', null, `${actual.evidencia.etiqueta} · ${plural(visibles.length, 'ejemplar', 'ejemplares')}`), icono('fa-solid fa-xmark'));
            quitar.setAttribute('aria-label', `Quitar el filtro «${actual.evidencia.etiqueta}»`);
            quitar.addEventListener('click', () => { actual.evidencia = null; pintar(); });
            filtroKpi.append(el('span', 'dash-filtro-kpi__texto', 'Mostrando los ejemplares de'), quitar);
        }
        tabla.tBodies[0].replaceChildren(...visibles.map((s) => {
            const tr = el('tr');
            const nombre = el('td');
            const enlace = el('a', 'dash-ejemplar');
            enlace.href = `subastas?id=${Number(s.subastaId)}`;
            const textos = el('span', 'dash-ejemplar__textos');
            textos.append(el('strong', null, s.titulo), el('small', null, [s.animal?.tipo || s.animal?.especie, s.animal?.raza, s.zona?.provincia].filter(Boolean).join(' · ')));
            enlace.append(foto(s.imagenUrl, `Foto de ${s.titulo}`), textos);
            nombre.append(enlace);
            const est = el('td');
            est.append(etiquetaEstado(claveEstado(s, rol), etiqueta(s)));
            tr.append(nombre, est, el('td', 'dash-tabla__fecha', fechaCorta(fechaHecho(s))),
                ...columnas.map((c) => el('td', `dash-tabla__numero${c.clase ? ` ${c.clase(s)}` : ''}`, c.valor(s))));
            return tr;
        }));
        if (!visibles.length) {
            const vacio = el('td', 'dash-tabla__vacio', 'No hay ejemplares con este filtro.');
            vacio.colSpan = columnas.length + 3;
            const tr = el('tr');
            tr.append(vacio);
            tabla.tBodies[0].append(tr);
        }
    };
    chips.forEach((c) => {
        const chip = el('button', 'dash-chip', `${c.etiqueta} · ${c.cantidad}`);
        chip.type = 'button';
        chip.dataset.chip = c.clave;
        chip.addEventListener('click', () => { actual.chip = c.clave; pintar(); });
        barra.append(chip);
    });
    contenedor.append(barra, filtroKpi, caja);
    pintar();
    const mostrar = () => {
        contenedor.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        contenedor.focus({ preventScroll: true });
    };
    return {
        contenedor,
        elegir: (clave) => { actual.chip = clave; actual.evidencia = null; pintar(); mostrar(); },
        filtrar: (evidencia) => { actual.chip = 'todos'; actual.evidencia = evidencia; pintar(); mostrar(); },
    };
}

const grilla = (clase, ...hijos) => {
    const g = el('div', clase);
    g.append(...hijos.filter(Boolean));
    return g;
};

// ───────────────────────────── Vendedor ─────────────────────────────

function pintarVendedor(datos) {
    const ind = datos.indicadores;
    const lista = datos.subastas ?? [];
    const adjudicadas = lista.filter((s) => s.estado === 'FINALIZADA' && typeof s.montoAdjudicado === 'number');
    const conteos = contarEstados(lista, 'VENDEDOR');
    const etiquetas = { adjudicada: 'Adjudicada', vivo: 'Activa', 'sin-ofertas': 'Sin ofertas', cancelada: 'Cancelada' };

    const tabla = tablaEjemplares({
        subastas: lista, rol: 'VENDEDOR',
        chips: [{ clave: 'todos', etiqueta: 'Todos', cantidad: lista.length }, ...conteos],
        etiqueta: (s) => etiquetas[claveEstado(s, 'VENDEDOR')],
        columnas: [
            { titulo: 'Precio base', valor: (s) => formatPrice(s.precioBase) },
            { titulo: 'Precio final', valor: (s) => (s.estado === 'FINALIZADA' ? formatPrice(s.montoAdjudicado)
                : s.estado === 'ACTIVA' && typeof s.precioActual === 'number' ? `${formatPrice(s.precioActual)} (va)` : '—') },
            { titulo: 'Incremento', valor: (s) => formatoPorcentaje(s.incrementoPorcentaje, { signo: true }) },
            { titulo: 'Postores', valor: (s) => String(s.postores ?? 0) },
        ],
    });
    const filtrar = tabla.filtrar;
    const exito = ind.exito;

    const kpis = grilla('dash-kpis',
        kpi({ etiqueta: 'Monto adjudicado', valor: formatPrice(ind.precioPromedio.montoTotal), tono: 'dato',
            detalle: `${plural(ind.precioPromedio.animales, 'animal adjudicado', 'animales adjudicados')} · una subasta es un animal`,
            definicion: 'Suma de las pujas ganadoras de las subastas adjudicadas del periodo (fecha de fin).',
            ids: ind.precioPromedio.ids, alFiltrar: filtrar }),
        kpi({ etiqueta: 'Porcentaje de éxito', valor: formatoPorcentaje(exito.porcentaje), tono: 'exito', progreso: exito.porcentaje,
            detalle: exito.finalizadas ? `${exito.adjudicadas} adjudicadas de ${plural(exito.finalizadas, 'cerrada', 'cerradas')} (sin canceladas)`
                : 'Sin subastas cerradas: no se calcula',
            definicion: 'Adjudicadas ÷ (adjudicadas + desiertas). Las canceladas no cuentan.', ids: exito.ids, alFiltrar: filtrar }),
        kpi({ etiqueta: 'Precio promedio por animal', valor: ind.precioPromedio.valor == null ? '—' : formatPrice(ind.precioPromedio.valor),
            tono: 'dato', detalle: ind.precioPromedio.valor == null ? 'Sin ventas adjudicadas' : 'Monto adjudicado ÷ animales adjudicados',
            ids: ind.precioPromedio.ids, alFiltrar: filtrar }),
        kpi({ etiqueta: 'Incremento promedio', valor: formatoPorcentaje(ind.incrementoPromedio.porcentaje, { signo: true }), tono: 'dato',
            detalle: ind.incrementoPromedio.porcentaje == null ? 'Sin adjudicadas con precio base' : 'Del precio base al precio final, por animal',
            ids: ind.incrementoPromedio.ids, alFiltrar: filtrar }),
    );

    const e = ind.estados;
    const participacion = grilla('dash-metricas',
        metrica({ nombreIcono: 'fa-solid fa-users', valor: plural(ind.compradores.total, 'comprador', 'compradores'),
            texto: 'Personas distintas que pujaron', etiqueta: 'Subastas con pujas', ids: ind.compradores.ids, alFiltrar: filtrar }),
        metrica({ nombreIcono: 'fa-regular fa-clock', valor: formatoDuracion(ind.tiempoAdjudicacion.minutos),
            texto: 'Promedio de la apertura al cierre adjudicado', etiqueta: 'Tiempo de adjudicación', ids: ind.tiempoAdjudicacion.ids, alFiltrar: filtrar }),
        metrica({ nombreIcono: 'fa-solid fa-flag-checkered', valor: plural(e.cerradas.total, 'cerrada', 'cerradas'),
            texto: `${e.adjudicadas?.total ?? exito.adjudicadas} adjudicadas + ${e.desiertas?.total ?? ind.sinOfertas.total} desiertas`,
            etiqueta: 'Subastas cerradas', ids: e.cerradas.ids, alFiltrar: filtrar }),
        metrica({ nombreIcono: 'fa-solid fa-hourglass-end', valor: plural(ind.sinOfertas.total, 'animal', 'animales'),
            texto: 'Cerraron sin ninguna puja', etiqueta: 'Animales sin ofertas', ids: ind.sinOfertas.ids, alFiltrar: filtrar }),
    );

    const conIncremento = adjudicadas.filter((s) => typeof s.incrementoPorcentaje === 'number');
    $('#dashboard-contenido').replaceChildren(
        seccion('dash-resultados', 'Resultados del periodo', null, kpis),
        seccion('dash-estados', 'Estado de tus subastas', null,
            estados(conteos, 'Tus subastas por estado', tabla.elegir, 'Cerradas = adjudicadas + desiertas. Una cancelada no es una venta ni una subasta sin ofertas.')),
        seccion('dash-participacion', 'Participación y operación', null, participacion),
        seccion('dash-graficos', 'Evolución y comparación', null, grilla('dash-dos',
            columnasPorMes({ titulo: 'Monto adjudicado por mes', ayuda: 'Según el mes en que terminó cada subasta adjudicada (hora de Costa Rica).',
                vacio: 'Aún no hay subastas adjudicadas en este periodo.', formato: formatPrice, unidad: 'Monto',
                meses: porMes(adjudicadas, (s) => s.fechaFin, (s) => s.montoAdjudicado) }),
            barrasHorizontales({ titulo: 'Incremento por ejemplar', ayuda: 'Cuánto subió cada animal adjudicado desde su precio base.',
                vacio: 'Aún no hay subastas adjudicadas.', columnas: ['Ejemplar', 'Incremento', 'Precio base', 'Precio final'],
                promedio: { etiqueta: 'Promedio del periodo', texto: formatoPorcentaje(ind.incrementoPromedio.porcentaje, { signo: true }) },
                items: conIncremento.map((s) => ({
                    etiqueta: s.titulo, valor: s.incrementoPorcentaje, texto: formatoPorcentaje(s.incrementoPorcentaje, { signo: true }),
                    extra: [[formatPrice(s.precioBase), 'Precio base'], [formatPrice(s.montoAdjudicado), 'Precio final']],
                    tabla: [formatPrice(s.precioBase), formatPrice(s.montoAdjudicado)],
                })) }),
        )),
        seccion('dash-ejemplares-titulo', 'Tus ejemplares', 'Cada fila es una subasta de un animal, con su foto. Los botones «Ver ejemplares» filtran esta tabla.', tabla.contenedor),
    );
}

// ───────────────────────────── Comprador ─────────────────────────────

/** Texto del costo de adquisición: nunca presenta como completo un total al que le faltan fletes. Puro. */
export function textoCosto(costo) {
    if (!costo || !costo.subastas) return { valor: '—', detalle: 'Sin pujas activas' };
    if (costo.completo) return { valor: formatPrice(costo.total), detalle: `Tus pujas + el flete más barato · ${plural(costo.subastas, 'subasta', 'subastas')}` };
    return {
        valor: 'Incompleto',
        detalle: `${plural(costo.sinFlete, 'subasta', 'subastas')} sin precio de flete: el total no se puede calcular`,
    };
}

function desgloseCosto(costo, alFiltrar) {
    const t = el('article', 'dash-tarjeta dash-costo');
    t.append(el('h3', 'grafico__titulo', 'Costo estimado de adquisición'),
        el('p', 'grafico__ayuda', 'De tus pujas activas. El flete es el más barato con precio que cubre la finca; uno «a convenir» no vale ₡0.'));
    const filas = el('dl', 'dash-costo__filas');
    const fila = (nombre, valor, clase = '') => {
        const d = el('div', `dash-costo__fila ${clase}`);
        d.append(el('dt', null, nombre), el('dd', null, valor));
        filas.append(d);
    };
    if (!costo.subastas) {
        t.append(el('p', 'grafico__vacio', 'No tienes pujas activas en este periodo.'));
        return t;
    }
    fila('Tus pujas (conocidas)', formatPrice(costo.pujas));
    fila(`Fletes con precio (${costo.conFlete})`, costo.fletesConocidos == null ? '—' : formatPrice(costo.fletesConocidos));
    fila('Subastas sin precio de flete', String(costo.sinFlete), costo.sinFlete ? 'dash-costo__fila--aviso' : '');
    fila('Total estimado', costo.completo ? formatPrice(costo.total) : 'No calculable', 'dash-costo__fila--total');
    t.append(filas);
    const evidencia = botonEvidencia(costo.idsSinFlete, 'Sin precio de flete', alFiltrar);
    if (evidencia) t.append(evidencia);
    return t;
}

function pintarComprador(datos) {
    const ind = datos.indicadores;
    const lista = datos.subastas ?? [];
    const propias = lista.filter((s) => !s.oportunidad);
    const cerca = lista.filter((s) => s.oportunidad);
    const precio = (s) => (typeof s.precioActual === 'number' ? s.precioActual : s.precioBase);
    const conteos = contarEstados(propias, 'COMPRADOR');
    const op = ind.oportunidades;
    const etiquetas = { ganada: 'Ganada', ganando: 'Vas ganando', superada: 'Te superaron', perdida: 'Perdida' };

    const tabla = tablaEjemplares({
        subastas: [...propias, ...cerca], rol: 'COMPRADOR',
        chips: [{ clave: 'todos', etiqueta: 'Todos', cantidad: lista.length }, ...conteos,
            ...(op.requiereUbicacion ? [] : [{ clave: 'cerca', etiqueta: 'Oportunidades', cantidad: cerca.length }])],
        etiqueta: (s) => (s.oportunidad ? situacionComprador(s) : etiquetas[claveEstado(s, 'COMPRADOR')]),
        columnas: [
            { titulo: 'Precio', valor: (s) => formatPrice(s.estado === 'FINALIZADA' ? s.montoAdjudicado : precio(s)) },
            { titulo: 'Tu puja', valor: (s) => (typeof s.miPuja === 'number' ? formatPrice(s.miPuja) : '—') },
            { titulo: 'Flete', valor: (s) => (s.estado !== 'ACTIVA' || s.oportunidad ? '—' : typeof s.costoFlete === 'number' ? formatPrice(s.costoFlete) : 'Sin precio') },
            { titulo: 'Costo estimado', valor: (s) => (typeof s.costoEstimado === 'number' ? formatPrice(s.costoEstimado) : '—') },
            { titulo: 'Frente a comparables', valor: (s) => formatoPorcentaje(s.comparacion?.diferenciaPorcentaje, { signo: true }) },
        ],
    });
    const filtrar = tabla.filtrar;
    const adj = ind.adjudicacion;
    const diferencia = ind.comparacion.porcentaje;
    const costo = textoCosto(ind.costoAdquisicion);

    const kpis = grilla('dash-kpis',
        kpi({ etiqueta: 'Monto adjudicado', valor: formatPrice(ind.montoAdjudicado.valor), tono: 'dato',
            detalle: plural(ind.montoAdjudicado.animales, 'animal ganado', 'animales ganados'),
            definicion: 'Suma de lo que ganaste en subastas adjudicadas del periodo (es un costo para ti).',
            ids: ind.montoAdjudicado.ids, alFiltrar: filtrar }),
        kpi({ etiqueta: 'Porcentaje de adjudicación', valor: formatoPorcentaje(adj.porcentaje), tono: 'exito', progreso: adj.porcentaje,
            detalle: adj.finalizadas ? `${adj.ganadas} ganadas de ${plural(adj.finalizadas, 'cerrada', 'cerradas')} donde pujaste` : 'Sin subastas cerradas donde pujaras',
            ids: adj.ids, alFiltrar: filtrar }),
        kpi({ etiqueta: 'Pujas activas', valor: String(ind.pujasActivas.total), tono: 'info',
            detalle: `${ind.vasGanando?.total ?? 0} vas ganando · ${plural(ind.pujasSuperadas.total, 'superada', 'superadas')}`,
            ids: ind.pujasActivas.ids, alFiltrar: filtrar }),
        kpi({ etiqueta: 'Costo estimado', valor: costo.valor, tono: ind.costoAdquisicion.completo ? 'dato' : ind.costoAdquisicion.subastas ? 'aviso' : null,
            detalle: costo.detalle, ids: ind.costoAdquisicion.ids, alFiltrar: filtrar }),
    );

    const ubicacion = el('button', 'dash-boton dash-boton--chico');
    ubicacion.type = 'button';
    ubicacion.append(icono('fa-solid fa-location-crosshairs'), el('span', null, 'Usar mi ubicación'));
    ubicacion.addEventListener('click', pedirUbicacion);
    const textoOportunidades = op.requiereUbicacion
        ? 'Comparte tu ubicación o elige una provincia en «Ubicación» para ver subastas abiertas donde aún no pujas.'
        : op.modo === 'PROVINCIA' ? `Subastas abiertas en ${datos.filtros?.provincia ?? 'la provincia elegida'} en las que aún no pujas`
            : `Subastas abiertas a ${op.radioKm} km o menos en las que aún no pujas`;

    const comparables = lista.filter((s) => s.comparacion?.diferenciaPorcentaje != null);
    $('#dashboard-contenido').replaceChildren(
        seccion('dash-resultados', 'Tus pujas y resultados', null, kpis),
        seccion('dash-estados', 'Estado de tus pujas', null,
            estados(conteos, 'Tus pujas por estado', tabla.elegir, 'Activas: vas ganando o te superaron. Cerradas: ganadas o perdidas.')),
        seccion('dash-participacion', 'Costo, precios y oportunidades', null, grilla('dash-tres',
            desgloseCosto(ind.costoAdquisicion, filtrar),
            metrica({ nombreIcono: 'fa-solid fa-scale-balanced', valor: formatoPorcentaje(diferencia, { signo: true }),
                texto: diferencia == null ? 'Sin ventas comparables todavía' : `Tu precio actual frente al promedio pagado por animales comparables (${plural(ind.comparacion.ids.length, 'subasta', 'subastas')})`,
                etiqueta: 'Comparación de precios', ids: ind.comparacion.ids, alFiltrar: filtrar }),
            metrica({ nombreIcono: 'fa-solid fa-location-dot',
                valor: op.requiereUbicacion ? 'Oportunidades cercanas' : plural(op.total, 'oportunidad', 'oportunidades'),
                texto: textoOportunidades, etiqueta: 'Oportunidades cercanas', ids: op.ids, alFiltrar: filtrar,
                accion: op.requiereUbicacion ? ubicacion : null }),
        )),
        seccion('dash-graficos', 'Evolución y comparación', null, grilla('dash-dos',
            columnasPorMes({ titulo: 'Compras y pujas por mes', ayuda: 'Monto ganado (barra) según el mes en que terminó la subasta; el tooltip y la tabla dan las subastas donde pujaste.',
                vacio: 'Aún no has pujado en este periodo.', formato: formatPrice, unidad: 'Monto ganado', cantidadTexto: ['subasta con tu puja', 'subastas con tu puja'],
                meses: porMes(propias, fechaHecho, (s) => (s.soyGanador ? s.montoAdjudicado : 0)) }),
            barrasDivergentes({ titulo: 'Precio frente a comparables', ayuda: 'Contra el promedio pagado por animales parecidos. Más barato es mejor para ti; no es un juicio del animal.',
                vacio: 'Todavía no hay ventas comparables para tus subastas.', izquierda: 'Más barato', derecha: 'Más caro',
                columnas: ['Ejemplar', 'Diferencia', 'Precio actual', 'Promedio comparable'],
                promedio: { etiqueta: 'Promedio', texto: formatoPorcentaje(diferencia, { signo: true }) },
                items: comparables.map((s) => ({
                    etiqueta: s.titulo, valor: s.comparacion.diferenciaPorcentaje,
                    texto: formatoPorcentaje(s.comparacion.diferenciaPorcentaje, { signo: true }),
                    extra: [[formatPrice(precio(s)), 'Precio actual'], [formatPrice(s.comparacion.promedio), `Promedio (${NIVELES[s.comparacion.nivel]}, ${s.comparacion.muestras} ventas)`]],
                    tabla: [formatPrice(precio(s)), formatPrice(s.comparacion.promedio)],
                })) }),
        )),
        seccion('dash-ejemplares-titulo', 'Tus ejemplares', 'Subastas donde pujaste y oportunidades, con su foto. Los botones «Ver ejemplares» filtran esta tabla.', tabla.contenedor),
    );
}

// ───────────────────────────── Carga y estado de la página ─────────────────────────────

function vista(nombre, mensaje = '') {
    $('#dashboard-loading').hidden = nombre !== 'loading';
    $('#dashboard-error').hidden = nombre !== 'error';
    $('#dashboard-sin-rol').hidden = nombre !== 'sin-rol';
    $('#dashboard-vacio').hidden = nombre !== 'vacio';
    $('#dashboard-contenido').hidden = nombre !== 'content';
    $('#dashboard-filtros').hidden = nombre === 'sin-rol';
    $('#dashboard-panel').setAttribute('aria-busy', String(nombre === 'loading'));
    if (mensaje) $('#dashboard-error-message').textContent = mensaje;
}

/** Botones de los roles que la persona tiene; los controles de ubicación solo en el de comprador. */
function pintarRoles(roles) {
    estado.roles = roles;
    let alguno = false;
    document.querySelectorAll('[data-dashboard-rol]').forEach((boton) => {
        boton.hidden = !roles[ROLES[boton.dataset.dashboardRol]];
        alguno ||= !boton.hidden;
        const elegido = boton.dataset.dashboardRol === estado.rol;
        boton.setAttribute('aria-selected', String(elegido));
        if (elegido) $('#dashboard-panel').setAttribute('aria-labelledby', boton.id);
    });
    $('.dash-segmento').hidden = !alguno;
    // Roles confirmados por el servidor: termina la reserva de espacio de la vista.
    document.querySelector('.dash')?.removeAttribute('data-roles-pendientes');
    // Con Comprador y Vendedor, cambiar de pestaña es una acción de la persona: los filtros de comprador se
    // quitan. Sin Comprador (cuenta antigua o desactivada) nunca aparecen: conservan su lugar sin verse para
    // que el panel no suba al confirmar los roles.
    const sinComprador = !roles[ROLES.COMPRADOR];
    document.querySelectorAll('[data-solo-comprador]').forEach((campo) => {
        campo.hidden = estado.rol !== 'COMPRADOR' && !sinComprador;
        campo.toggleAttribute('data-reservado', sinComprador);
    });
}

function valoresFormulario() {
    return Object.fromEntries(new FormData($('#dashboard-filtros')));
}

async function cargar() {
    const pedido = ++estado.pedido; // solo vale la última respuesta
    // Al recargar se conserva lo pintado (atenuado) en vez de vaciar la página.
    const conContenido = !$('#dashboard-contenido').hidden;
    if (conContenido) $('#dashboard-contenido').classList.add('dash-contenido--cargando');
    else vista('loading');
    $('#dashboard-filtros-error').textContent = '';
    const valores = valoresFormulario();
    $('#dashboard-subtitulo').textContent = `Cómo te va en las subastas · ${textoPeriodo(valores)}`;
    try {
        const respuesta = await request(`${API}?${parametrosFiltro(valores, estado.rol, new Date(), estado.punto)}`);
        if (pedido !== estado.pedido) return;
        const datos = respuesta.data ?? {};
        pintarRoles(datos.roles ?? {});
        if (estado.rol === 'COMPRADOR') pintarComprador(datos);
        else pintarVendedor(datos);
        // El comprador siempre ve su página (las oportunidades piden la ubicación aunque no haya pujas).
        vista((datos.subastas ?? []).length || estado.rol === 'COMPRADOR' ? 'content' : 'vacio');
    } catch (error) {
        if (pedido !== estado.pedido) return;
        if (error?.status === 401) { endExpiredSession(error?.authSessionVersion); return; }
        // Sin Persona no hay roles que elegir: el paso que falta es el registro, no "activar un rol".
        if (esRegistroIncompleto(error)) { mostrarRegistroIncompleto(); return; }
        if (error?.status === 409) {
            const roles = error.data?.roles ?? {};
            const otro = rolDisponible(estado.rol, roles);
            if (otro && otro !== estado.rol) {
                estado.rol = otro;
                cargar();
                return;
            }
            pintarRoles(roles);
            vista('sin-rol');
            return;
        }
        if (error?.status === 422 && error.errors) {
            $('#dashboard-filtros-error').textContent = Object.values(error.errors).join(' ');
        }
        vista('error', error?.message || 'No pudimos cargar tus indicadores.');
    } finally {
        // Con la cuenta sin Persona el contenido ya se reemplazó por el paso pendiente.
        if (pedido === estado.pedido) $('#dashboard-contenido')?.classList.remove('dash-contenido--cargando');
    }
}

function opciones(select, items, idClave, todos) {
    const actual = select.value;
    const lista = [new Option(todos, '')];
    items.forEach((item) => lista.push(new Option(item.nombre, String(item[idClave]))));
    select.replaceChildren(...lista);
    select.value = lista.some((o) => o.value === actual) ? actual : '';
}

/** Categoría y raza dependen del tipo de ganado elegido. */
function pintarCatalogos() {
    const form = $('#dashboard-filtros');
    const especieId = Number(form.elements.especieId.value) || null;
    const deEspecie = (item) => especieId === null || item.especieId === especieId;
    opciones(form.elements.especieId, estado.catalogos.especies, 'especieId', 'Todos');
    opciones(form.elements.tipoId, estado.catalogos.tipos.filter(deEspecie), 'tipoId', 'Todas');
    opciones(form.elements.razaId, estado.catalogos.razas.filter(deEspecie), 'razaId', 'Todas');
}

async function cargarCatalogos() {
    try {
        const respuesta = await request('api/v1/catalogos');
        estado.catalogos = {
            especies: respuesta.data?.especies ?? [],
            tipos: respuesta.data?.tipos ?? [],
            razas: respuesta.data?.razas ?? [],
        };
        pintarCatalogos();
    } catch {
        // Sin catálogo el dashboard sigue funcionando; solo faltan esos filtros.
    }
}

/** Pide la ubicación al navegador solo cuando la persona toca el botón; no se guarda en ningún lado. */
function pedirUbicacion() {
    const nota = $('#dashboard-ubicacion-estado');
    const boton = $('#dashboard-ubicacion');
    if (!navigator.geolocation) {
        nota.textContent = 'Este navegador no puede compartir la ubicación.';
        return;
    }
    boton.disabled = true;
    nota.textContent = 'Buscando tu ubicación…';
    navigator.geolocation.getCurrentPosition((posicion) => {
        boton.disabled = false;
        estado.punto = { latitud: posicion.coords.latitude.toFixed(5), longitud: posicion.coords.longitude.toFixed(5) };
        boton.querySelector('span').textContent = 'Actualizar ubicación';
        nota.textContent = 'Usando tu ubicación actual.';
        cargar();
    }, () => {
        boton.disabled = false;
        nota.textContent = 'No pudimos obtener tu ubicación. Revisa el permiso del navegador e inténtalo de nuevo.';
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
}

function iniciar() {
    if (!readAuthSession()) {
        window.location.assign('entrar?next=mi-dashboard');
        return;
    }
    const form = $('#dashboard-filtros');
    const rango = () => {
        const visible = form.elements.periodo.value === 'rango';
        document.querySelectorAll('[data-rango]').forEach((campo) => { campo.hidden = !visible; });
    };
    form.addEventListener('change', (evento) => {
        if (evento.target.name === 'periodo') rango();
        if (evento.target.name === 'especieId') pintarCatalogos();
        if (evento.target.name === 'periodo' && evento.target.value === 'rango') return; // espera las fechas
        cargar();
    });
    form.addEventListener('submit', (evento) => { evento.preventDefault(); cargar(); });
    form.addEventListener('reset', () => setTimeout(() => { rango(); pintarCatalogos(); cargar(); }, 0));
    document.querySelectorAll('[data-dashboard-rol]').forEach((boton) => boton.addEventListener('click', () => {
        if (estado.rol === boton.dataset.dashboardRol) return;
        estado.rol = boton.dataset.dashboardRol;
        $('#dashboard-contenido').hidden = true; // otro rol, otra página: se muestra "Cargando…"
        cargar();
    }));
    $('#dashboard-retry').addEventListener('click', cargar);
    $('#dashboard-ubicacion').addEventListener('click', pedirUbicacion);
    void cargarCatalogos();
    cargar();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', iniciar);
