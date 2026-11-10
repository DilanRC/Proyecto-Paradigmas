// Mi dashboard (DEC-DASHBOARD-001): un dashboard por rol (Vendedor y Comprador), cada uno igual al de alguien que solo
// tiene ese rol. Estructura (de arriba abajo): resultados del periodo (4 KPI), estados en una barra segmentada con mini
// tarjetas, dos gráficos, actividad y una tabla única de ejemplares con foto y chips de filtro. Todo se pinta con DOM
// (textContent). La ubicación del navegador solo vive en memoria (no se guarda).

import { request } from './shared/api.js';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-3';
import { formatPrice, safeImageUrl } from './explore.js?v=foto-4';
import { barraSegmentada, barrasDivergentes, barrasHorizontales, columnasPorMes, el, porMes } from './dashboard-graficos.js?v=graficos-2';

const API = 'api/v1/mi-dashboard';
const NIVELES = { TIPO_RAZA: 'misma categoría y raza', TIPO: 'misma categoría', ESPECIE: 'mismo tipo de ganado' };
const ROLES = { VENDEDOR: 'vendedor', COMPRADOR: 'comprador' };
const PERIODOS = { 7: 'últimos 7 días', 30: 'últimos 30 días', 90: 'últimos 90 días', 365: 'últimos 12 meses', todo: 'todo el tiempo' };
const $ = (selector) => document.querySelector(selector);

const estado = { rol: 'VENDEDOR', roles: null, punto: null, catalogos: { especies: [], tipos: [], razas: [] }, pedido: 0 };

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
    if (s.oportunidad) return s.distanciaKm != null ? `A ${String(s.distanciaKm).replace('.', ',')} km` : 'Cerca de ti';
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
        { clave: 'adjudicada', etiqueta: 'Adjudicadas', descripcion: 'Cerraron con ganador' },
        { clave: 'vivo', etiqueta: 'En vivo', descripcion: 'Reciben pujas', ahora: true },
        { clave: 'sin-ofertas', etiqueta: 'Sin ofertas', descripcion: 'Cerraron sin pujas' },
        { clave: 'cancelada', etiqueta: 'Canceladas', descripcion: 'Las cancelaste antes de recibir pujas' },
    ],
    COMPRADOR: [
        { clave: 'ganada', etiqueta: 'Ganadas', descripcion: 'Te las adjudicaron' },
        { clave: 'ganando', etiqueta: 'Vas ganando', descripcion: 'Tu puja es la más alta', ahora: true },
        { clave: 'superada', etiqueta: 'Te superaron', descripcion: 'Otro comprador va arriba', ahora: true },
        { clave: 'perdida', etiqueta: 'Perdidas', descripcion: 'Las ganó otro comprador' },
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

function icono(nombre) {
    const i = el('i', nombre);
    i.setAttribute('aria-hidden', 'true');
    return i;
}

function seccion(id, titulo, ...hijos) {
    const s = el('section', 'dash-seccion');
    s.setAttribute('aria-labelledby', id);
    const h = el('h2', 'dash-seccion__titulo', titulo);
    h.id = id;
    s.append(h, ...hijos.filter(Boolean));
    return s;
}

/** Tarjeta KPI: etiqueta, número grande y una línea de detalle; `progreso` (0-100) dibuja una barra. */
function kpi({ etiqueta, valor, detalle, destacado = false, progreso = null, tono = null }) {
    const t = el('article', `dash-tarjeta dash-kpi${destacado ? ' dash-kpi--destacado' : ''}`);
    t.append(el('p', 'dash-kpi__etiqueta', etiqueta));
    const v = el('p', `dash-kpi__valor${tono ? ` dash-kpi__valor--${tono}` : ''}`, valor);
    t.append(v);
    if (progreso != null) {
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
    return t;
}

/** Estados: barra segmentada + mini tarjetas que filtran la tabla + enlace a todos los ejemplares. */
function estados(titulo, conteos, alElegir) {
    const total = conteos.reduce((suma, e) => suma + e.cantidad, 0);
    const tarjeta = el('div', 'dash-tarjeta dash-estados');
    tarjeta.append(barraSegmentada(conteos, titulo));
    const mini = el('div', 'dash-estados__lista');
    conteos.forEach((e) => {
        const boton = el('button', 'dash-estado');
        boton.type = 'button';
        boton.dataset.estado = e.clave;
        const cabeza = el('span', 'dash-estado__cabeza');
        const muestra = el('span', `dash-muestra estado--${e.clave}`);
        muestra.setAttribute('aria-hidden', 'true');
        cabeza.append(muestra, el('span', 'dash-estado__nombre', e.etiqueta));
        if (e.ahora && e.cantidad > 0) cabeza.append(el('span', 'dash-ahora', 'AHORA'));
        boton.append(cabeza, el('span', 'dash-estado__numero', e.cantidad), el('span', 'dash-estado__descripcion', e.descripcion));
        boton.setAttribute('aria-label', `${e.etiqueta}: ${e.cantidad}. ${e.descripcion}. Ver en la tabla.`);
        boton.addEventListener('click', () => alElegir(e.clave));
        mini.append(boton);
    });
    const todos = el('a', 'dash-enlace', 'Ver todos los ejemplares →');
    todos.href = '#dashboard-ejemplares';
    todos.addEventListener('click', (evento) => { evento.preventDefault(); alElegir('todos'); });
    tarjeta.append(mini, todos);
    return { tarjeta, total };
}

/** Tarjeta horizontal con ícono, número y texto (sección "Actividad"). */
function actividad(nombreIcono, valor, texto, accion = null) {
    const t = el('article', 'dash-tarjeta dash-actividad');
    const caja = el('span', 'dash-actividad__icono');
    caja.append(icono(nombreIcono));
    const cuerpo = el('div');
    cuerpo.append(el('p', 'dash-actividad__valor', valor), el('p', 'dash-actividad__texto', texto));
    if (accion) cuerpo.append(accion);
    t.append(caja, cuerpo);
    return t;
}

function foto(url, alt) {
    const caja = el('span', 'dash-foto');
    const segura = safeImageUrl(url);
    if (!segura) {
        caja.append(icono('fa-solid fa-cow'));
        return caja;
    }
    const img = el('img');
    img.src = segura;
    img.alt = alt;
    img.loading = 'lazy';
    img.referrerPolicy = 'no-referrer';
    img.width = 44;
    img.height = 44;
    img.addEventListener('error', () => caja.replaceChildren(icono('fa-solid fa-cow')), { once: true });
    caja.append(img);
    return caja;
}

function etiquetaEstado(clave, texto) {
    const tag = el('span', 'dash-tag');
    const muestra = el('span', `dash-muestra estado--${clave}`);
    muestra.setAttribute('aria-hidden', 'true');
    tag.append(muestra, el('span', null, texto));
    return tag;
}

/**
 * Tabla única de ejemplares con chips de filtro. columnas: [{ titulo, valor(s) → texto, clase }]; `chips`: [{ clave,
 * etiqueta, cantidad }]. Devuelve la sección y una función para elegir un chip desde fuera.
 */
function tablaEjemplares({ subastas, rol, chips, columnas, etiqueta }) {
    const contenedor = el('div', 'dash-tarjeta dash-ejemplares');
    contenedor.id = 'dashboard-ejemplares';
    contenedor.tabIndex = -1;
    const barra = el('div', 'dash-chips');
    barra.setAttribute('role', 'group');
    barra.setAttribute('aria-label', 'Filtrar ejemplares');
    const caja = el('div', 'dash-tabla');
    caja.tabIndex = 0;
    caja.setAttribute('role', 'region');
    caja.setAttribute('aria-label', 'Tabla de ejemplares (se desplaza a los lados)');
    const tabla = el('table');
    const cabeza = el('tr');
    ['Ejemplar', 'Estado', ...columnas.map((c) => c.titulo)].forEach((titulo, i) => {
        const th = el('th', i > 1 ? 'dash-tabla__numero' : null, titulo);
        th.scope = 'col';
        cabeza.append(th);
    });
    tabla.append(el('thead'), el('tbody'));
    tabla.tHead.append(cabeza);
    caja.append(tabla);

    const pintar = (filtro) => {
        barra.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.chip === filtro)));
        const visibles = filtro === 'todos' ? subastas : subastas.filter((s) => claveEstado(s, rol) === filtro);
        tabla.tBodies[0].replaceChildren(...visibles.map((s) => {
            const tr = el('tr');
            const nombre = el('td');
            const enlace = el('a', 'dash-ejemplar');
            enlace.href = `subastas?id=${Number(s.subastaId)}`;
            const textos = el('span', 'dash-ejemplar__textos');
            textos.append(el('strong', null, s.titulo), el('small', null, [s.animal?.tipo || s.animal?.especie, s.animal?.raza].filter(Boolean).join(' · ')));
            enlace.append(foto(s.imagenUrl, ''), textos);
            nombre.append(enlace);
            const est = el('td');
            est.append(etiquetaEstado(claveEstado(s, rol), etiqueta(s)));
            tr.append(nombre, est, ...columnas.map((c) => el('td', `dash-tabla__numero${c.clase ? ` ${c.clase(s)}` : ''}`, c.valor(s))));
            return tr;
        }));
        if (!visibles.length) {
            const vacio = el('td', 'dash-tabla__vacio', 'No hay ejemplares en este estado.');
            vacio.colSpan = columnas.length + 2;
            const tr = el('tr');
            tr.append(vacio);
            tabla.tBodies[0].append(tr);
        }
    };
    chips.forEach((c) => {
        const chip = el('button', 'dash-chip', `${c.etiqueta} · ${c.cantidad}`);
        chip.type = 'button';
        chip.dataset.chip = c.clave;
        chip.addEventListener('click', () => pintar(c.clave));
        barra.append(chip);
    });
    contenedor.append(barra, caja);
    pintar('todos');
    const elegir = (clave) => {
        pintar(clave);
        contenedor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        contenedor.focus({ preventScroll: true });
    };
    return { contenedor, elegir };
}

// ───────────────────────────── Vendedor ─────────────────────────────

function pintarVendedor(datos) {
    const ind = datos.indicadores;
    const lista = datos.subastas ?? [];
    const adjudicadas = lista.filter((s) => s.estado === 'FINALIZADA' && typeof s.montoAdjudicado === 'number');
    const conteos = contarEstados(lista, 'VENDEDOR');
    const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

    const etiquetas = { adjudicada: 'Adjudicada', vivo: 'En vivo', 'sin-ofertas': 'Sin ofertas', cancelada: 'Cancelada' };
    const kpis = el('div', 'dash-kpis');
    kpis.append(
        kpi({ etiqueta: 'Monto vendido', valor: formatPrice(ind.precioPromedio.montoTotal), destacado: true,
            detalle: plural(ind.precioPromedio.animales, 'animal adjudicado', 'animales adjudicados') }),
        kpi({ etiqueta: 'Tasa de éxito', valor: formatoPorcentaje(ind.exito.porcentaje), progreso: ind.exito.porcentaje ?? 0,
            detalle: `${ind.exito.adjudicadas} ${ind.exito.adjudicadas === 1 ? 'adjudicada' : 'adjudicadas'} de ${ind.exito.finalizadas} ${ind.exito.finalizadas === 1 ? 'finalizada' : 'finalizadas'}` }),
        kpi({ etiqueta: 'Precio promedio de venta', valor: ind.precioPromedio.valor == null ? '—' : formatPrice(ind.precioPromedio.valor),
            detalle: 'Por animal adjudicado' }),
        kpi({ etiqueta: 'Incremento promedio de pujas', valor: formatoPorcentaje(ind.incrementoPromedio.porcentaje, { signo: true }),
            tono: ind.incrementoPromedio.porcentaje > 0 ? 'positivo' : null, detalle: 'Del precio base al precio final' }),
    );

    const tabla = tablaEjemplares({
        subastas: lista, rol: 'VENDEDOR',
        chips: [{ clave: 'todos', etiqueta: 'Todos', cantidad: lista.length }, ...conteos],
        etiqueta: (s) => etiquetas[claveEstado(s, 'VENDEDOR')],
        columnas: [
            { titulo: 'Precio final', valor: (s) => (s.estado === 'FINALIZADA' ? formatPrice(s.montoAdjudicado)
                : s.estado === 'ACTIVA' && typeof s.precioActual === 'number' ? `${formatPrice(s.precioActual)} (va)` : '—') },
            { titulo: 'Incremento', valor: (s) => formatoPorcentaje(s.incrementoPorcentaje, { signo: true }),
                clase: (s) => (s.incrementoPorcentaje > 0 ? 'dash-positivo' : '') },
        ],
    });
    const { tarjeta: tarjetaEstados, total } = estados('Tus subastas por estado', conteos, tabla.elegir);

    $('#dashboard-contenido').replaceChildren(
        seccion('dash-resultados', 'Resultados del periodo', kpis),
        seccion('dash-estados', `Tus ${plural(total, 'subasta', 'subastas')} por estado`, tarjetaEstados),
        seccion('dash-graficos', 'Ventas y pujas', (() => {
            const g = el('div', 'dash-dos');
            g.append(
                columnasPorMes({ titulo: 'Monto vendido por mes', ayuda: 'Según el mes en que cerró cada subasta adjudicada.',
                    vacio: 'Aún no hay ventas adjudicadas en este periodo.', formato: formatPrice,
                    meses: porMes(adjudicadas, (s) => s.fechaCierre ?? s.fechaFin, (s) => s.montoAdjudicado) }),
                barrasHorizontales({ titulo: 'Cuánto subió cada subasta', ayuda: 'Del precio base al precio final adjudicado.',
                    vacio: 'Aún no hay subastas adjudicadas.', columnas: ['Ejemplar', 'Incremento'],
                    promedio: { etiqueta: 'Promedio', texto: formatoPorcentaje(ind.incrementoPromedio.porcentaje, { signo: true }) },
                    items: adjudicadas.filter((s) => typeof s.incrementoPorcentaje === 'number').map((s) => ({
                        etiqueta: s.titulo, valor: s.incrementoPorcentaje, texto: formatoPorcentaje(s.incrementoPorcentaje, { signo: true }),
                        extra: [[formatPrice(s.precioBase), 'Precio base'], [formatPrice(s.montoAdjudicado), 'Precio final']],
                    })) }),
            );
            return g;
        })()),
        seccion('dash-actividad', 'Actividad de compradores', (() => {
            const fila = el('div', 'dash-dos');
            fila.append(
                actividad('fa-solid fa-users', plural(ind.compradores.total, 'comprador', 'compradores'),
                    ind.compradores.total ? `Personas distintas pujaron en ${ind.compradores.ids.length} de tus subastas` : 'Nadie ha pujado todavía en este periodo'),
                actividad('fa-regular fa-clock', formatoDuracion(ind.tiempoAdjudicacion.minutos), 'Tiempo promedio desde publicar hasta adjudicar'),
            );
            return fila;
        })()),
        seccion('dash-ejemplares-titulo', 'Tus ejemplares', tabla.contenedor),
    );
}

// ───────────────────────────── Comprador ─────────────────────────────

function pintarComprador(datos) {
    const ind = datos.indicadores;
    const lista = datos.subastas ?? [];
    const propias = lista.filter((s) => !s.oportunidad);
    const cerca = lista.filter((s) => s.oportunidad);
    const ganadas = propias.filter((s) => s.soyGanador && typeof s.montoAdjudicado === 'number');
    const precio = (s) => (typeof s.precioActual === 'number' ? s.precioActual : s.precioBase);
    const conteos = contarEstados(propias, 'COMPRADOR');
    const op = ind.oportunidades;
    const costo = ind.costoAdquisicion;
    const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;
    const etiquetas = { ganada: 'Ganada', ganando: 'Vas ganando', superada: 'Te superaron', perdida: 'Perdida' };

    const tabla = tablaEjemplares({
        subastas: [...propias, ...cerca], rol: 'COMPRADOR',
        chips: [{ clave: 'todos', etiqueta: 'Todos', cantidad: lista.length }, ...conteos,
            ...(op.requiereUbicacion ? [] : [{ clave: 'cerca', etiqueta: 'Cerca de ti', cantidad: cerca.length }])],
        etiqueta: (s) => (s.oportunidad ? situacionComprador(s) : etiquetas[claveEstado(s, 'COMPRADOR')]),
        columnas: [
            { titulo: 'Precio', valor: (s) => formatPrice(s.estado === 'FINALIZADA' ? s.montoAdjudicado : precio(s)) },
            { titulo: 'Tu puja', valor: (s) => (typeof s.miPuja === 'number' ? formatPrice(s.miPuja) : '—') },
            { titulo: 'Costo estimado', valor: (s) => (typeof s.costoEstimado === 'number'
                ? `${formatPrice(s.costoEstimado)}${s.costoFlete == null ? ' (sin flete)' : ''}` : '—') },
            { titulo: 'Frente al promedio', valor: (s) => formatoPorcentaje(s.comparacion?.diferenciaPorcentaje, { signo: true }),
                clase: (s) => (s.comparacion?.diferenciaPorcentaje < 0 ? 'dash-positivo' : '') },
        ],
    });
    const { tarjeta: tarjetaEstados, total } = estados('Tus pujas por estado', conteos, tabla.elegir);

    const ubicacion = el('button', 'dash-boton dash-boton--chico');
    ubicacion.type = 'button';
    ubicacion.append(icono('fa-solid fa-location-crosshairs'), el('span', null, op.requiereUbicacion ? 'Usar mi ubicación' : 'Ver en la tabla'));
    ubicacion.addEventListener('click', () => (op.requiereUbicacion ? pedirUbicacion() : tabla.elegir('cerca')));

    const kpis = el('div', 'dash-kpis');
    const diferencia = ind.comparacion.porcentaje;
    kpis.append(
        kpi({ etiqueta: 'Monto adjudicado', valor: formatPrice(ind.montoAdjudicado.valor), destacado: true,
            detalle: plural(ind.montoAdjudicado.animales, 'animal ganado', 'animales ganados') }),
        kpi({ etiqueta: 'Porcentaje de adjudicación', valor: formatoPorcentaje(ind.adjudicacion.porcentaje), progreso: ind.adjudicacion.porcentaje ?? 0,
            detalle: `${ind.adjudicacion.ganadas} ${ind.adjudicacion.ganadas === 1 ? 'ganada' : 'ganadas'} de ${ind.adjudicacion.finalizadas} ${ind.adjudicacion.finalizadas === 1 ? 'finalizada' : 'finalizadas'}` }),
        kpi({ etiqueta: 'Costo estimado de adquisición', valor: costo.total == null ? '—' : formatPrice(costo.total),
            detalle: costo.sinFlete ? `Tus pujas + flete · ${plural(costo.sinFlete, 'subasta', 'subastas')} sin precio de flete` : 'Tus pujas activas + el flete más barato' }),
        kpi({ etiqueta: 'Precio frente al promedio', valor: formatoPorcentaje(diferencia, { signo: true }),
            tono: diferencia < 0 ? 'positivo' : null,
            detalle: diferencia == null ? 'Sin ventas comparables todavía' : diferencia < 0 ? 'Más barato que animales comparables' : 'Más caro que animales comparables' }),
    );

    $('#dashboard-contenido').replaceChildren(
        seccion('dash-resultados', 'Resultados del periodo', kpis),
        seccion('dash-estados', `Tus ${plural(total, 'puja', 'pujas')} por estado`, tarjetaEstados),
        seccion('dash-graficos', 'Compras y precios', (() => {
            const g = el('div', 'dash-dos');
            g.append(
                columnasPorMes({ titulo: 'Monto comprado por mes', ayuda: 'Según el mes en que cerró cada subasta que ganaste.',
                    vacio: 'Aún no has ganado subastas en este periodo.', formato: formatPrice,
                    meses: porMes(ganadas, (s) => s.fechaCierre ?? s.fechaFin, (s) => s.montoAdjudicado) }),
                barrasDivergentes({ titulo: 'Precio actual frente al promedio', ayuda: 'Contra lo que se pagó antes por animales comparables.',
                    vacio: 'Todavía no hay ventas comparables para tus subastas.', izquierda: 'Más barato', derecha: 'Más caro',
                    columnas: ['Ejemplar', 'Diferencia', 'Precio actual', 'Promedio histórico'],
                    promedio: { etiqueta: 'Promedio', texto: formatoPorcentaje(diferencia, { signo: true }) },
                    items: lista.filter((s) => s.comparacion?.diferenciaPorcentaje != null).map((s) => ({
                        etiqueta: s.titulo, valor: s.comparacion.diferenciaPorcentaje,
                        texto: formatoPorcentaje(s.comparacion.diferenciaPorcentaje, { signo: true }),
                        extra: [[formatPrice(precio(s)), 'Precio actual'], [formatPrice(s.comparacion.promedio), `Promedio (${NIVELES[s.comparacion.nivel]})`]],
                        tabla: [formatPrice(precio(s)), formatPrice(s.comparacion.promedio)],
                    })) }),
            );
            return g;
        })()),
        seccion('dash-actividad', 'Oportunidades y pujas activas', (() => {
            const fila = el('div', 'dash-dos');
            fila.append(
                actividad('fa-solid fa-location-dot', op.requiereUbicacion ? 'Oportunidades cercanas' : plural(op.total, 'oportunidad', 'oportunidades'),
                    op.requiereUbicacion ? 'Comparte tu ubicación para ver las subastas abiertas cerca de ti.'
                        : `Subastas abiertas a ${op.radioKm} km o menos en las que aún no pujas`, ubicacion),
                actividad('fa-solid fa-gavel', plural(ind.pujasActivas.total, 'puja activa', 'pujas activas'),
                    `${plural(ind.pujasSuperadas.total, 'superada', 'superadas')}: otro comprador mantiene la oferta más alta`),
            );
            return fila;
        })()),
        seccion('dash-ejemplares-titulo', 'Tus ejemplares', tabla.contenedor),
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
    document.querySelectorAll('[data-solo-comprador]').forEach((campo) => { campo.hidden = estado.rol !== 'COMPRADOR'; });
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
        if (error?.status === 401) { endExpiredSession(); return; }
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
        if (pedido === estado.pedido) $('#dashboard-contenido').classList.remove('dash-contenido--cargando');
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
