// Fila de tarjetas de Explorar contra el catálogo real de publicaciones.
//
// El orden y el filtrado los decide el backend (api/v1/publicaciones). Aquí no
// hay recomendación ni ranking: este módulo formatea lo que llega y lo pinta.
// Las funciones de formato se exportan puras para poder probarlas sin DOM.

import { request } from './shared/api.js';
import {
    inicializarUbicacionAutomatica,
    leerUbicacionUsuario,
    UBICACION_USUARIO_ERROR_EVENT,
    UBICACION_USUARIO_EVENT,
} from './shared/ubicacion-sesion.js';

const API_URL = 'api/v1/publicaciones';
const TAMANO_PAGINA = 25;

const state = {
    query: '',
    proposito: 'todos',
    ubicacion: '',
    precioMin: null,
    precioMax: null,
    enfocarId: null,
    items: [],
    cargando: false,
    error: null,
};

/**
 * Colones sin decimales: los precios de ganado no se cotizan en céntimos.
 *
 * El agrupado se hace a mano y no con toLocaleString porque el resultado de
 * 'es-CR' depende del ICU del entorno: Node y el navegador no siempre coinciden
 * en el separador, y el precio es justo el dato que no puede variar según dónde
 * se renderice.
 */
export function formatPrice(precio) {
    if (typeof precio !== 'number' || !Number.isFinite(precio)) return 'Precio a convenir';
    const entero = String(Math.abs(Math.round(precio)));
    const agrupado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${precio < 0 ? '-' : ''}₡${agrupado}`;
}

/** De lo específico a lo general, igual que el resto del sistema. */
export function formatLocation(direccion) {
    const partes = [direccion?.pueblo, direccion?.distrito, direccion?.canton, direccion?.provincia]
        .filter(Boolean);
    return partes.length ? partes.join(', ') : 'Ubicación no registrada';
}

export function formatAge(edadMeses) {
    if (typeof edadMeses !== 'number' || !Number.isFinite(edadMeses)) return '—';
    return `${edadMeses} ${edadMeses === 1 ? 'mes' : 'meses'}`;
}

export function formatWeight(peso) {
    if (typeof peso !== 'number' || !Number.isFinite(peso)) return '—';
    return `${Number.isInteger(peso) ? peso : peso.toFixed(1)} kg`;
}

/** Un campo sin observación registrada se muestra vacío, no se inventa. */
export function formatText(valor) {
    const texto = String(valor ?? '').trim();
    return texto === '' ? '—' : texto;
}

/**
 * Los catálogos se guardan en mayúsculas y sin tildes (CRIA, DOBLE PROPOSITO).
 * Eso es correcto en la base y feo en pantalla, así que la ortografía se
 * resuelve en presentación. Un valor que no esté en el mapa se capitaliza en
 * vez de desaparecer: el catálogo puede crecer sin romper la vista.
 */
const ETIQUETAS_PROPOSITO = {
    CRIA: 'Cría',
    ENGORDE: 'Engorde',
    LECHE: 'Leche',
    'DOBLE PROPOSITO': 'Doble propósito',
};

export function formatPurpose(proposito) {
    const clave = String(proposito ?? '').trim().toUpperCase();
    if (clave === '') return '—';
    return ETIQUETAS_PROPOSITO[clave]
        ?? clave.charAt(0) + clave.slice(1).toLocaleLowerCase('es');
}

export function formatSeller(publicacion) {
    const finca = publicacion?.finca?.nombre;
    const vendedor = publicacion?.vendedor?.nombre;
    return [finca, vendedor].filter(Boolean).join(' · ') || 'Vendedor no registrado';
}

/** Propósitos presentes en los datos, para no ofrecer filtros vacíos. */
export function availablePurposes(items) {
    const propositos = new Set();
    for (const item of items) {
        const proposito = String(item?.animal?.proposito ?? '').trim();
        if (proposito !== '') propositos.add(proposito.toUpperCase());
    }
    return [...propositos].sort();
}

export function filterByPurpose(items, proposito) {
    if (!proposito || proposito === 'todos') return items;
    return items.filter(
        (item) => String(item?.animal?.proposito ?? '').toUpperCase() === proposito.toUpperCase(),
    );
}

/** Devuelve el filtro vigente solo si sigue existiendo en los datos; si no, 'todos'. */
export function normalizePurpose(proposito, disponibles) {
    if (!proposito || proposito === 'todos') return 'todos';
    const clave = String(proposito).toUpperCase();
    return disponibles.some((p) => String(p).toUpperCase() === clave) ? clave : 'todos';
}

/** Minúsculas y sin tildes: "San José" encuentra "san jose". */
export function normalizeText(valor) {
    return String(valor ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}

// ponytail: ubicación y precio filtran la página ya cargada (25); pasar a la
// API cuando el catálogo supere una página.
export function filterByLocation(items, texto) {
    const buscado = normalizeText(texto);
    if (buscado === '') return items;
    return items.filter((item) => normalizeText(formatLocation(item?.direccion)).includes(buscado));
}

export function filterByPrice(items, minimo, maximo) {
    if (minimo === null && maximo === null) return items;
    return items.filter((item) => typeof item?.precio === 'number'
        && (minimo === null || item.precio >= minimo)
        && (maximo === null || item.precio <= maximo));
}

/** "engorde" o "Doble propósito" escritos a mano → clave del catálogo, o null. */
export function purposeFromText(texto) {
    const buscado = normalizeText(texto);
    if (buscado === '') return null;
    return Object.keys(ETIQUETAS_PROPOSITO)
        .find((clave) => normalizeText(clave) === buscado || normalizeText(ETIQUETAS_PROPOSITO[clave]) === buscado) ?? null;
}

function parsePrice(valor) {
    const texto = String(valor ?? '').trim();
    if (texto === '') return null;
    const numero = Number(texto);
    return Number.isFinite(numero) && numero >= 0 ? numero : null;
}

function hasActiveFilters() {
    return state.query !== '' || state.proposito !== 'todos' || state.ubicacion !== ''
        || state.precioMin !== null || state.precioMax !== null;
}

/** Solo URLs https absolutas llegan a un <img>; lo demás muestra el ícono. */
export function safeImageUrl(valor) {
    try {
        const url = new URL(String(valor ?? ''));
        return url.protocol === 'https:' ? url.href : null;
    } catch {
        return null;
    }
}

function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function specEntry(icono, etiqueta, valor) {
    const contenedor = element('div');
    const dt = element('dt');
    const icon = element('i');
    icon.className = `fa-solid ${icono}`;
    icon.setAttribute('aria-hidden', 'true');
    dt.append(icon, ` ${etiqueta}`);
    contenedor.append(dt, element('dd', null, valor));
    return contenedor;
}

/**
 * Construye la tarjeta. Se usa createElement y textContent en vez de innerHTML:
 * título, descripción y nombres vienen de la base y podrían contener markup.
 *
 * `compacta` (portada): solo foto, nombre y precio; el resto se despliega con
 * "Ver más información" y no lleva botones de acción.
 */
export function buildCard(publicacion, { compacta = false } = {}) {
    const article = element('article', compacta ? 'explore-card explore-card--compacta' : 'explore-card');
    const publicacionId = Number(publicacion?.publicacionId);
    const animalId = Number(publicacion?.animalId);
    if (Number.isInteger(publicacionId) && publicacionId > 0) {
        article.dataset.publicacionId = String(publicacionId);
    }
    if (Number.isInteger(animalId) && animalId > 0) {
        article.dataset.animalId = String(animalId);
    }

    const visual = element('div', 'explore-card__visual explore-card__visual--green');
    const cow = element('i');
    cow.className = 'fa-solid fa-cow';
    cow.setAttribute('aria-hidden', 'true');
    visual.append(cow);
    const imagen = safeImageUrl(publicacion?.imagenUrl);
    if (imagen) {
        const foto = element('img', 'explore-card__photo');
        foto.src = imagen;
        foto.alt = formatText(publicacion?.titulo);
        foto.loading = 'lazy';
        foto.decoding = 'async';
        foto.referrerPolicy = 'no-referrer';
        // Si la imagen ya no existe, queda el ícono en vez de un recuadro roto.
        foto.addEventListener('error', () => foto.remove(), { once: true });
        visual.append(foto);
        visual.classList.add('explore-card__visual--photo');
    }

    const body = element('div', 'explore-card__body');

    const meta = element('div', 'explore-card__meta');
    const ubicacion = element('span');
    const pin = element('i');
    pin.className = 'fa-solid fa-location-dot';
    pin.setAttribute('aria-hidden', 'true');
    ubicacion.append(pin, ` ${formatLocation(publicacion.direccion)}`);
    meta.append(ubicacion, element('span', null, formatText(publicacion.animal?.identificacion)));

    const precio = element('p', 'explore-card__price');
    precio.append(
        element('strong', null, formatPrice(publicacion.precio)),
        element('span', null, formatText(publicacion.animal?.sexo)),
    );

    const specs = element('dl', 'explore-card__specs');
    specs.append(
        specEntry('fa-dna', 'Raza', formatText(publicacion.animal?.raza)),
        specEntry('fa-hourglass-half', 'Edad', formatAge(publicacion.animal?.edadMeses)),
        specEntry('fa-weight-scale', 'Peso', formatWeight(publicacion.animal?.peso)),
        specEntry('fa-bullseye', 'Propósito', formatPurpose(publicacion.animal?.proposito)),
    );

    const vendedor = element('p', 'explore-card__seller');
    const persona = element('i');
    persona.className = 'fa-solid fa-user-tie';
    persona.setAttribute('aria-hidden', 'true');
    vendedor.append(persona, element('span', null, formatSeller(publicacion)));

    const acciones = element('div', 'explore-card__actions');
    for (const [accion, icono] of [['Me interesa', 'fa-heart'], ['Contactar', 'fa-message']]) {
        const boton = element('button', null);
        boton.type = 'button';
        boton.dataset.exploreAction = accion;
        if (accion === 'Me interesa' && publicacion?.meInteresa === true) {
            boton.dataset.saved = 'true';
            boton.setAttribute('aria-label', 'Me interesa guardado');
        }
        const icon = element('i');
        icon.className = `fa-solid ${icono}`;
        icon.setAttribute('aria-hidden', 'true');
        boton.append(icon, element('span', null, accion));
        acciones.append(boton);
    }

    if (compacta) {
        const mas = element('details', 'explore-card__more');
        mas.append(
            element('summary', null, 'Ver más información'),
            meta,
            element('p', null, formatText(publicacion.descripcion)),
            specs,
            vendedor,
        );
        body.append(element('h2', null, formatText(publicacion.titulo)), precio, mas);
        article.append(visual, body);
        return article;
    }

    body.append(
        meta,
        element('span', 'explore-card__type', formatText(publicacion.estado)),
        element('h2', null, formatText(publicacion.titulo)),
        precio,
        element('p', null, formatText(publicacion.descripcion)),
        specs,
        vendedor,
        acciones,
    );
    article.append(visual, body);
    return article;
}

function visibleItems() {
    return filterByPrice(
        filterByLocation(filterByPurpose(state.items, state.proposito), state.ubicacion),
        state.precioMin,
        state.precioMax,
    );
}

/** Lleva a la tarjeta que se abrió con "Ver" desde la portada y la resalta. */
function focusCard(publicacionId) {
    const card = document.querySelector(`[data-explore-deck] [data-publicacion-id="${publicacionId}"]`);
    if (!card) return;
    card.classList.add('is-focused');
    card.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
}

function showToast(message) {
    const toast = document.querySelector('[data-explore-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(() => { toast.hidden = true; }, 2200);
}

function renderPurposeFilters() {
    const contenedor = document.querySelector('[data-explore-filters]');
    if (!contenedor) return;
    const propositos = availablePurposes(state.items);
    state.proposito = normalizePurpose(state.proposito, propositos);
    contenedor.replaceChildren();
    // "Todo" solo no filtra nada: sin propósitos no hay chips.
    contenedor.hidden = propositos.length === 0;
    if (propositos.length === 0) return;
    for (const [valor, etiqueta] of [['todos', 'Todo'], ...propositos.map((p) => [p, formatPurpose(p)])]) {
        const boton = element('button', 'explore-chip');
        boton.type = 'button';
        boton.dataset.exploreFilter = valor;
        boton.classList.toggle('is-active', valor === state.proposito);
        boton.setAttribute('aria-pressed', String(valor === state.proposito));
        boton.append(element('span', null, etiqueta));
        boton.addEventListener('click', () => {
            state.proposito = valor;
            render();
        });
        contenedor.append(boton);
    }
}

function render() {
    const deck = document.querySelector('[data-explore-deck]');
    const empty = document.querySelector('[data-explore-empty]');
    const emptyCatalog = document.querySelector('[data-explore-empty-catalog]');
    const filterBar = document.querySelector('[data-explore-filterbar]');
    const loading = document.querySelector('[data-explore-loading]');
    const errorBox = document.querySelector('[data-explore-error]');
    const errorMessage = document.querySelector('[data-explore-error-message]');
    if (!deck) return;

    const items = visibleItems();

    if (loading) loading.hidden = !state.cargando;
    if (errorBox) errorBox.hidden = state.error === null;
    if (errorMessage && state.error) errorMessage.textContent = state.error;
    // "Prueba otra búsqueda" solo tiene sentido si la persona buscó o filtró;
    // con el catálogo vacío se invita a publicar.
    const sinResultados = !state.cargando && state.error === null && items.length === 0;
    if (empty) empty.hidden = !(sinResultados && hasActiveFilters());
    if (emptyCatalog) emptyCatalog.hidden = !(sinResultados && !hasActiveFilters());
    if (filterBar) filterBar.hidden = state.items.length === 0 && !hasActiveFilters();
    deck.hidden = state.cargando || state.error !== null || items.length === 0;

    deck.replaceChildren(...items.map(buildCard));
}

async function load() {
    state.cargando = true;
    state.error = null;
    render();

    const parametros = new URLSearchParams({
        estado: 'ACTIVO', pagina: '1', tamanoPagina: String(TAMANO_PAGINA),
    });
    const ubicacion = leerUbicacionUsuario();
    if (ubicacion) {
        parametros.set('latitud', ubicacion.latitud);
        parametros.set('longitud', ubicacion.longitud);
    }
    if (state.query !== '') parametros.set('q', state.query);

    try {
        const respuesta = await request(API_URL, {
            method: 'POST',
            body: JSON.stringify({ consulta: Object.fromEntries(parametros) }),
        });
        const lista = Array.isArray(respuesta.data?.publicaciones) ? respuesta.data.publicaciones : [];
        // Lo que ya marcaste con Me interesa vive en /me-interesa, no en el deck.
        state.items = lista.filter((item) => item.meInteresa !== true);
    } catch (error) {
        state.items = [];
        state.error = error.message ?? 'No fue posible cargar las publicaciones.';
    } finally {
        state.cargando = false;
    }
    renderPurposeFilters();
    render();
    if (state.enfocarId !== null) {
        focusCard(state.enfocarId);
        state.enfocarId = null;
    }
}

/** Lee ?ubicacion, ?tipo y ?publicacion que llegan desde la portada. */
function readUrlFilters(input) {
    const parametros = new URLSearchParams(window.location.search);
    state.ubicacion = (parametros.get('ubicacion') ?? '').trim();
    const tipo = (parametros.get('tipo') ?? '').trim();
    const proposito = purposeFromText(tipo);
    if (proposito) state.proposito = proposito;
    else if (tipo !== '' && state.query === '') {
        // Una raza o texto libre lo busca la API (título, raza, finca, zona).
        state.query = tipo;
        if (input) input.value = tipo;
    }
    const enfocar = Number(parametros.get('publicacion'));
    state.enfocarId = Number.isInteger(enfocar) && enfocar > 0 ? enfocar : null;
}

function initializeFilterBar() {
    const ubicacion = document.querySelector('[data-explore-ubicacion]');
    const minimo = document.querySelector('[data-explore-precio-min]');
    const maximo = document.querySelector('[data-explore-precio-max]');
    if (ubicacion) ubicacion.value = state.ubicacion;
    const aplicar = () => {
        state.ubicacion = ubicacion?.value.trim() ?? '';
        state.precioMin = parsePrice(minimo?.value);
        state.precioMax = parsePrice(maximo?.value);
        render();
    };
    for (const campo of [ubicacion, minimo, maximo]) campo?.addEventListener('input', aplicar);
}

function initialize() {
    // home.js importa los formateadores; sin deck no hay Explorar que iniciar.
    if (!document.querySelector('[data-explore-deck]')) return;
    const input = document.querySelector('[data-explore-search]');
    readUrlFilters(input);
    initializeFilterBar();
    if (input) {
        if (state.query === '') state.query = input.value.trim();
        let temporizador = null;
        input.addEventListener('input', () => {
            clearTimeout(temporizador);
            temporizador = setTimeout(() => {
                state.query = input.value.trim();
                load();
            }, 300);
        });
    }

    document.querySelector('[data-explore-reset]')?.addEventListener('click', () => {
        state.proposito = 'todos';
        state.query = '';
        state.ubicacion = '';
        state.precioMin = null;
        state.precioMax = null;
        for (const selector of ['[data-explore-search]', '[data-explore-ubicacion]',
            '[data-explore-precio-min]', '[data-explore-precio-max]']) {
            const campo = document.querySelector(selector);
            if (campo) campo.value = '';
        }
        load();
    });
    document.querySelector('[data-explore-retry]')?.addEventListener('click', load);
    window.addEventListener('explore:interaction-saved', (event) => {
        if (event.detail?.type !== 'ME_INTERESA') return;
        const id = Number(event.detail.card?.dataset.publicacionId);
        state.items = state.items.filter((item) => Number(item.publicacionId) !== id);
        renderPurposeFilters();
        render();
    });

    window.addEventListener(UBICACION_USUARIO_EVENT, () => {
        leerUbicacionUsuario();
        load();
    });
    window.addEventListener(UBICACION_USUARIO_ERROR_EVENT, (event) => {
        const kind = event.detail?.kind;
        if (kind === 'denied') showToast('Ubicación denegada: mostramos publicaciones recientes en lugar de cercanas.');
        else if (kind !== 'unsupported') showToast('No pudimos actualizar tu ubicación; Explorar sigue disponible.');
    });


    load();
    inicializarUbicacionAutomatica();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}
