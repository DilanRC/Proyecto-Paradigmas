// Gráficos de Mi dashboard (DEC-DASHBOARD-001), en HTML y CSS sin librerías:
//  - barra segmentada de estados (una parte por estado, con su nombre y número siempre visibles fuera de la barra),
//  - columnas por mes, con el valor encima de cada columna,
//  - barras horizontales ordenadas, con una línea de promedio al final,
//  - barras divergentes desde un 0 central (más barato ← → más caro).
// Colores semánticos (mi-dashboard.css): los estados usan --tc-success / --tc-info / --tc-warning / --tc-neutral-status y
// las cantidades económicas --tc-data (un monto alto no es "éxito"). Bajo protanopía verde y ámbar quedan cerca, así
// que el ámbar lleva rayas y cada estado lleva siempre su texto, ícono y número. Cada gráfico trae tooltip con puntero
// y teclado y su tabla. Escalas desde 0 (no se exageran diferencias). Todo el texto entra con textContent.

const ZONA = 'America/Costa_Rica';

export function el(tag, clase, texto) {
    const nodo = document.createElement(tag);
    if (clase) nodo.className = clase;
    if (texto != null) nodo.textContent = String(texto);
    return nodo;
}

// ───────────────────────────── Datos (puros) ─────────────────────────────

/** Clave AAAA-MM del mes de una fecha ISO, en hora de Costa Rica; null si no es una fecha. Puro. */
export function claveMes(iso) {
    const fecha = new Date(iso ?? '');
    if (Number.isNaN(fecha.getTime())) return null;
    return fecha.toLocaleDateString('en-CA', { timeZone: ZONA }).slice(0, 7);
}

/** "Oct 2026" a partir de "2026-10". Puro. */
export function nombreMes(clave) {
    const [anio, mes] = clave.split('-').map(Number);
    const corto = new Date(Date.UTC(anio, mes - 1, 15)).toLocaleDateString('es-CR', { month: 'short', timeZone: 'UTC' }).replace('.', '');
    return `${corto.charAt(0).toUpperCase()}${corto.slice(1)} ${anio}`;
}

/**
 * Suma `valor(item)` por mes de `fecha(item)`, rellenando con 0 los meses sin datos entre el primero y el último
 * (como mucho los últimos `maximo` meses). Puro. @return [{ clave, etiqueta, valor, cantidad }]
 */
export function porMes(items, fecha, valor, maximo = 12) {
    const sumas = new Map();
    for (const item of items) {
        const clave = claveMes(fecha(item));
        if (!clave) continue;
        const actual = sumas.get(clave) ?? { valor: 0, cantidad: 0 };
        sumas.set(clave, { valor: actual.valor + (Number(valor(item)) || 0), cantidad: actual.cantidad + 1 });
    }
    if (!sumas.size) return [];
    const claves = [...sumas.keys()].sort();
    let [anio, mes] = claves[0].split('-').map(Number);
    const ultima = claves[claves.length - 1];
    const meses = [];
    for (;;) {
        const clave = `${anio}-${String(mes).padStart(2, '0')}`;
        const dato = sumas.get(clave) ?? { valor: 0, cantidad: 0 };
        meses.push({ clave, etiqueta: nombreMes(clave), ...dato });
        if (clave >= ultima) break;
        mes += 1;
        if (mes > 12) { mes = 1; anio += 1; }
    }
    return meses.slice(-maximo);
}

/** "₡3,6 M", "₡720 mil" o "₡8.500": montos cortos para encima de una columna angosta. Puro. */
export function montoCorto(valor) {
    if (typeof valor !== 'number' || !Number.isFinite(valor)) return '—';
    const numero = (n, decimales) => n.toLocaleString('es-CR', { maximumFractionDigits: decimales });
    if (Math.abs(valor) >= 1e6) return `₡${numero(valor / 1e6, 1)} M`;
    if (Math.abs(valor) >= 1e4) return `₡${numero(valor / 1e3, 0)} mil`;
    return `₡${String(Math.round(valor)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

// ───────────────────────────── Tooltip ─────────────────────────────

let tooltip = null;

function mostrarTooltip(objetivo, filas, evento) {
    if (!tooltip) {
        tooltip = el('div', 'grafico-tooltip');
        tooltip.setAttribute('role', 'presentation');
        document.body.append(tooltip);
    }
    tooltip.replaceChildren(...filas.map(([valor, etiqueta]) => {
        const fila = el('div', 'grafico-tooltip__fila');
        fila.append(el('strong', null, valor), el('span', null, etiqueta));
        return fila;
    }));
    tooltip.hidden = false;
    const caja = objetivo.getBoundingClientRect();
    const x = evento?.clientX ?? caja.left + caja.width / 2;
    const y = evento?.clientY ?? caja.top;
    const ancho = tooltip.offsetWidth;
    tooltip.style.left = `${Math.max(8, Math.min(window.innerWidth - ancho - 8, x - ancho / 2))}px`;
    tooltip.style.top = `${Math.max(8, y - tooltip.offsetHeight - 12)}px`;
}

function ocultarTooltip() {
    if (tooltip) tooltip.hidden = true;
}

/** La marca responde al puntero y al foco con el mismo tooltip; su nombre accesible repite el dato. */
function interactivo(nodo, filas) {
    nodo.tabIndex = 0;
    nodo.setAttribute('role', 'img');
    nodo.setAttribute('aria-label', filas.map(([valor, etiqueta]) => (etiqueta ? `${etiqueta}: ${valor}` : valor)).join('. '));
    nodo.addEventListener('pointermove', (evento) => mostrarTooltip(nodo, filas, evento));
    nodo.addEventListener('pointerleave', ocultarTooltip);
    nodo.addEventListener('focus', () => mostrarTooltip(nodo, filas));
    nodo.addEventListener('blur', ocultarTooltip);
}

/** Tabla con los mismos datos del gráfico, plegada. */
function tablaDatos(columnas, filas) {
    const detalles = el('details', 'grafico__tabla');
    detalles.append(el('summary', null, 'Ver como tabla'));
    const t = el('table');
    const cabeza = el('tr');
    columnas.forEach((c) => cabeza.append(el('th', null, c)));
    t.append(el('thead'), el('tbody'));
    t.tHead.append(cabeza);
    filas.forEach((fila) => {
        const tr = el('tr');
        fila.forEach((celda) => tr.append(el('td', null, celda)));
        t.tBodies[0].append(tr);
    });
    detalles.append(t);
    return detalles;
}

/** Tarjeta de gráfico: título, explicación y el dibujo (o un texto si no hay datos). */
function marco({ titulo, ayuda, cuerpo, tabla, vacio }) {
    const figura = el('figure', 'dash-tarjeta grafico');
    const pie = el('figcaption');
    pie.append(el('h3', 'grafico__titulo', titulo));
    if (ayuda) pie.append(el('p', 'grafico__ayuda', ayuda));
    figura.append(pie);
    if (!tabla.filas.length) {
        figura.append(el('p', 'grafico__vacio', vacio));
        return figura;
    }
    figura.append(cuerpo, tablaDatos(tabla.columnas, tabla.filas));
    return figura;
}

// ───────────────────────────── Formas ─────────────────────────────

/**
 * Barra segmentada de estados. estados: [{ clave, etiqueta, cantidad }]; cada segmento lleva la clase
 * `estado--<clave>` (color y textura en el CSS). Los segmentos en 0 no se dibujan.
 */
export function barraSegmentada(estados, etiquetaTotal) {
    const total = estados.reduce((suma, e) => suma + e.cantidad, 0);
    const barra = el('div', 'dash-segmentada');
    estados.filter((e) => e.cantidad > 0).forEach((e) => {
        const parte = el('div', `dash-segmentada__parte estado--${e.clave}`);
        parte.style.flexGrow = String(e.cantidad);
        interactivo(parte, [[`${e.cantidad} de ${total}`, e.etiqueta]]);
        barra.append(parte);
    });
    barra.setAttribute('aria-label', `${etiquetaTotal}: ${estados.map((e) => `${e.etiqueta} ${e.cantidad}`).join(', ')}`);
    return barra;
}

/**
 * Columnas por mes en orden cronológico, desde una línea base en 0, con el valor encima de cada una y el máximo de la
 * escala a la izquierda. Los meses sin operaciones se ven (columna vacía y "₡0" en la tabla). meses: porMes(...).
 * `cantidadTexto` = [singular, plural] de lo que cuenta `cantidad` (por defecto subastas).
 */
export function columnasPorMes({ titulo, ayuda, meses, formato, vacio, unidad = 'Monto', cantidadTexto = ['subasta', 'subastas'] }) {
    const maximo = Math.max(...meses.map((m) => m.valor), 0);
    const hayDatos = meses.some((m) => m.cantidad > 0);
    const plano = el('div', 'grafico-columnas');
    const escala = el('div', 'grafico-columnas__escala');
    escala.setAttribute('aria-hidden', 'true');
    escala.append(el('span', null, montoCorto(maximo)), el('span', null, '₡0'));
    const columnas = el('div', 'grafico-columnas__plano');
    meses.forEach((mes) => {
        const columna = el('div', 'grafico-columnas__columna');
        const zona = el('div', 'grafico-columnas__zona');
        const barra = el('div', 'grafico-columnas__barra');
        barra.style.height = maximo > 0 ? `${(mes.valor / maximo) * 100}%` : '0%';
        if (mes.valor > 0) {
            // Con pocos meses cabe el monto completo; con más, el corto (₡3,6 M). El completo siempre está en el tooltip.
            barra.append(el('span', 'grafico-columnas__valor', meses.length <= 4 ? formato(mes.valor) : montoCorto(mes.valor)));
        }
        interactivo(zona, [[formato(mes.valor), mes.etiqueta], [String(mes.cantidad), mes.cantidad === 1 ? cantidadTexto[0] : cantidadTexto[1]]]);
        zona.append(barra);
        columna.append(zona, el('span', 'grafico-columnas__mes', mes.etiqueta));
        columnas.append(columna);
    });
    plano.append(escala, columnas);
    return marco({ titulo, ayuda, cuerpo: plano, vacio,
        tabla: { columnas: ['Mes', unidad, cantidadTexto[1].charAt(0).toUpperCase() + cantidadTexto[1].slice(1)],
            filas: hayDatos ? meses.map((m) => [m.etiqueta, formato(m.valor), String(m.cantidad)]) : [] } });
}

/** Línea final "Promedio · +19,8 %" separada por una línea fina. */
function filaPromedio(promedio) {
    const fila = el('div', 'grafico-promedio');
    fila.append(el('span', null, promedio.etiqueta), el('strong', null, promedio.texto));
    return fila;
}

/**
 * Barras horizontales ordenadas de mayor a menor, un solo tono (--tc-data). items: [{ etiqueta, valor, texto, extra,
 * tabla }]; `promedio`: { etiqueta, texto } opcional al final. Si algún valor es negativo se dibujan divergentes desde 0
 * (antes los negativos se descartaban en silencio).
 */
export function barrasHorizontales({ titulo, ayuda, items, vacio, columnas = ['Ejemplar', 'Valor'], promedio = null, izquierda = 'Menos', derecha = 'Más' }) {
    const validos = items.filter((i) => typeof i.valor === 'number' && Number.isFinite(i.valor));
    if (validos.some((i) => i.valor < 0)) {
        return barrasDivergentes({ titulo, ayuda, items: validos, vacio, columnas, izquierda, derecha, promedio });
    }
    const ordenados = [...validos].sort((a, b) => b.valor - a.valor);
    const maximo = Math.max(...ordenados.map((i) => i.valor), 0);
    const lista = el('div', 'grafico-barras');
    ordenados.forEach((item) => {
        const fila = el('div', 'grafico-barras__fila');
        const pista = el('div', 'grafico-barras__pista');
        const barra = el('div', 'grafico-barras__barra');
        barra.style.width = `${Math.max(1, maximo > 0 ? (item.valor / maximo) * 100 : 0)}%`;
        interactivo(barra, [[item.texto, item.etiqueta], ...(item.extra ?? [])]);
        pista.append(barra);
        fila.append(el('span', 'grafico-barras__etiqueta', item.etiqueta), pista, el('span', 'grafico-barras__valor', item.texto));
        lista.append(fila);
    });
    if (promedio && ordenados.length) lista.append(filaPromedio(promedio));
    return marco({ titulo, ayuda, cuerpo: lista, vacio, tabla: { columnas, filas: ordenados.map((i) => [i.etiqueta, i.texto, ...(i.tabla ?? [])]) } });
}

/**
 * Barras desde un 0 central: a la izquierda los negativos y a la derecha los positivos, en un solo tono. La posición y
 * el signo dicen la dirección. items: [{ etiqueta, valor, texto, extra, tabla }].
 */
export function barrasDivergentes({ titulo, ayuda, items, vacio, columnas, izquierda, derecha, promedio = null }) {
    const validos = items.filter((i) => typeof i.valor === 'number' && Number.isFinite(i.valor)).sort((a, b) => a.valor - b.valor);
    const maximo = Math.max(...validos.map((i) => Math.abs(i.valor)), 0) || 1;
    const cuerpo = el('div', 'grafico-barras');
    const guia = el('div', 'grafico-divergente__guia');
    guia.append(el('span', null, `← ${izquierda}`), el('span', null, `${derecha} →`));
    cuerpo.append(guia);
    validos.forEach((item) => {
        const fila = el('div', 'grafico-barras__fila');
        const pista = el('div', 'grafico-divergente__pista');
        const barra = el('div', `grafico-divergente__barra grafico-divergente__barra--${item.valor < 0 ? 'izquierda' : 'derecha'}`);
        barra.style.width = `${Math.max(1, (Math.abs(item.valor) / maximo) * 50)}%`;
        interactivo(barra, [[item.texto, item.etiqueta], ...(item.extra ?? [])]);
        pista.append(barra);
        fila.append(el('span', 'grafico-barras__etiqueta', item.etiqueta), pista, el('span', 'grafico-barras__valor', item.texto));
        cuerpo.append(fila);
    });
    if (promedio && validos.length) cuerpo.append(filaPromedio(promedio));
    return marco({ titulo, ayuda, cuerpo, vacio, tabla: { columnas, filas: validos.map((i) => [i.etiqueta, i.texto, ...(i.tabla ?? [])]) } });
}
