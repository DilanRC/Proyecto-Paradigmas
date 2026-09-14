// Deck de Explorar contra el catálogo real de publicaciones.
// El backend decide el ranking: por cercanía cuando hay ubicación de usuario y
// por recencia como degradación cuando GPS no está disponible.

import { request } from './shared/api.js';
import {
    inicializarUbicacionAutomatica,
    leerUbicacionUsuario,
    UBICACION_USUARIO_ERROR_EVENT,
    UBICACION_USUARIO_EVENT,
} from './shared/ubicacion-sesion.js';

const API_URL = 'api/publicaciones.php';
const TAMANO_PAGINA = 25;
let secuenciaCarga = 0;

const state = {
    query: '',
    proposito: 'todos',
    items: [],
    index: 0,
    cargando: false,
    error: null,
};

export function formatPrice(precio) {
    if (typeof precio !== 'number' || !Number.isFinite(precio)) return 'Precio a convenir';
    const entero = String(Math.abs(Math.round(precio)));
    const agrupado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${precio < 0 ? '-' : ''}₡${agrupado}`;
}

export function formatLocation(direccion) {
    const partes = [direccion?.pueblo, direccion?.distrito, direccion?.canton, direccion?.provincia]
        .filter(Boolean);
    return partes.length ? partes.join(', ') : 'Ubicación no registrada';
}

export function formatDistance(distanciaKm) {
    if (typeof distanciaKm !== 'number' || !Number.isFinite(distanciaKm) || distanciaKm < 0) return '';
    if (distanciaKm < 1) return `${Math.max(1, Math.round(distanciaKm * 1000))} m`;
    return `${distanciaKm < 10 ? distanciaKm.toFixed(1) : Math.round(distanciaKm)} km`;
}

export function formatAge(edadMeses) {
    if (typeof edadMeses !== 'number' || !Number.isFinite(edadMeses)) return '—';
    return `${edadMeses} ${edadMeses === 1 ? 'mes' : 'meses'}`;
}

export function formatWeight(peso) {
    if (typeof peso !== 'number' || !Number.isFinite(peso)) return '—';
    return `${Number.isInteger(peso) ? peso : peso.toFixed(1)} kg`;
}

export function formatText(valor) {
    const texto = String(valor ?? '').trim();
    return texto === '' ? '—' : texto;
}

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

export function normalizePurpose(proposito, disponibles) {
    if (!proposito || proposito === 'todos') return 'todos';
    const clave = String(proposito).toUpperCase();
    return disponibles.some((p) => String(p).toUpperCase() === clave) ? clave : 'todos';
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

export function buildCard(publicacion) {
    const article = element('article', 'explore-card');
    const publicacionId = Number(publicacion?.publicacionId);
    const animalId = Number(publicacion?.animalId);
    if (Number.isInteger(publicacionId) && publicacionId > 0) {
        article.dataset.publicacionId = String(publicacionId);
    }
    if (Number.isInteger(animalId) && animalId > 0) {
        article.dataset.animalId = String(animalId);
    }

    const visual = element('div', 'explore-card__visual explore-card__visual--green');
    visual.setAttribute('aria-hidden', 'true');
    const cow = element('i');
    cow.className = 'fa-solid fa-cow';
    visual.append(cow);

    const body = element('div', 'explore-card__body');

    const meta = element('div', 'explore-card__meta');
    const ubicacion = element('span');
    const pin = element('i');
    pin.className = 'fa-solid fa-location-dot';
    pin.setAttribute('aria-hidden', 'true');
    const distancia = formatDistance(publicacion.distanciaKm);
    const textoUbicacion = formatLocation(publicacion.direccion);
    ubicacion.append(pin, ` ${distancia ? `${distancia} · ` : ''}${textoUbicacion}`);
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
    for (const [accion, icono] of [['Pasar', 'fa-xmark'], ['Me interesa', 'fa-heart'],
        ['Contactar', 'fa-message']]) {
        const boton = element('button', null);
        boton.type = 'button';
        boton.dataset.exploreAction = accion;
        const icon = element('i');
        icon.className = `fa-solid ${icono}`;
        icon.setAttribute('aria-hidden', 'true');
        boton.append(icon, element('span', null, accion));
        acciones.append(boton);
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
    return filterByPurpose(state.items, state.proposito);
}

function updatePosition(total) {
    const label = document.querySelector('[data-explore-position]');
    if (!label) return;
    label.textContent = total === 0 ? '0 de 0' : `${state.index + 1} de ${total}`;
}

function scrollToCurrent() {
    const deck = document.querySelector('[data-explore-deck]');
    const current = deck?.children[state.index];
    if (deck && current) {
        const left = current.offsetLeft - Math.max(0, (deck.clientWidth - current.clientWidth) / 2);
        deck.scrollTo({ left, behavior: 'smooth' });
    }
    updatePosition(visibleItems().length);
}

function showToast(message) {
    const toast = document.querySelector('[data-explore-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(() => { toast.hidden = true; }, 2600);
}

function renderPurposeFilters() {
    const contenedor = document.querySelector('[data-explore-filters]');
    if (!contenedor) return;
    const propositos = availablePurposes(state.items);
    state.proposito = normalizePurpose(state.proposito, propositos);
    contenedor.replaceChildren();
    for (const [valor, etiqueta] of [['todos', 'Todo'], ...propositos.map((p) => [p, formatPurpose(p)])]) {
        const boton = element('button', 'explore-chip');
        boton.type = 'button';
        boton.dataset.exploreFilter = valor;
        boton.classList.toggle('is-active', valor === state.proposito);
        boton.append(element('span', null, etiqueta));
        boton.addEventListener('click', () => {
            state.proposito = valor;
            state.index = 0;
            render();
        });
        contenedor.append(boton);
    }
}

function render() {
    const deck = document.querySelector('[data-explore-deck]');
    const empty = document.querySelector('[data-explore-empty]');
    const loading = document.querySelector('[data-explore-loading]');
    const errorBox = document.querySelector('[data-explore-error]');
    const errorMessage = document.querySelector('[data-explore-error-message]');
    if (!deck) return;

    const items = visibleItems();
    state.index = Math.min(state.index, Math.max(0, items.length - 1));

    if (loading) loading.hidden = !state.cargando;
    if (errorBox) errorBox.hidden = state.error === null;
    if (errorMessage && state.error) errorMessage.textContent = state.error;
    if (empty) empty.hidden = state.cargando || state.error !== null || items.length > 0;
    deck.hidden = state.cargando || state.error !== null || items.length === 0;

    deck.replaceChildren(...items.map(buildCard));
    for (const boton of deck.querySelectorAll('[data-explore-action]')) {
        boton.addEventListener('click', () => {
            const accion = boton.dataset.exploreAction;
            showToast(`${accion}: acción visual. Se conectará al servicio correspondiente.`);
            if (accion === 'Pasar' && items.length > 1) {
                state.index = (state.index + 1) % items.length;
                scrollToCurrent();
            }
        });
    }

    document.querySelector('.explore-deck__navigation')
        ?.toggleAttribute('hidden', items.length === 0);
    updatePosition(items.length);
}

async function load() {
    const secuencia = ++secuenciaCarga;
    state.cargando = true;
    state.error = null;
    render();

    const parametros = new URLSearchParams({
        estado: 'ACTIVO', pagina: '1', tamanoPagina: String(TAMANO_PAGINA),
    });
    if (state.query !== '') parametros.set('q', state.query);
    const ubicacion = leerUbicacionUsuario();
    if (ubicacion) {
        parametros.set('latitud', ubicacion.latitud);
        parametros.set('longitud', ubicacion.longitud);
    }

    try {
        const respuesta = await request(`${API_URL}?${parametros}`);
        if (secuencia !== secuenciaCarga) return;
        const lista = Array.isArray(respuesta.data?.publicaciones) ? respuesta.data.publicaciones : [];
        state.items = lista;
        state.index = 0;
    } catch (error) {
        if (secuencia !== secuenciaCarga) return;
        state.items = [];
        state.error = error.message ?? 'No fue posible cargar las publicaciones.';
    } finally {
        if (secuencia === secuenciaCarga) state.cargando = false;
    }
    if (secuencia !== secuenciaCarga) return;
    renderPurposeFilters();
    render();
}

function initialize() {
    const input = document.querySelector('[data-explore-search]');
    if (input) {
        state.query = input.value.trim();
        let temporizador = null;
        input.addEventListener('input', () => {
            clearTimeout(temporizador);
            temporizador = setTimeout(() => {
                state.query = input.value.trim();
                load();
            }, 300);
        });
    }

    document.querySelector('[data-explore-prev]')?.addEventListener('click', () => {
        const total = visibleItems().length;
        if (total === 0) return;
        state.index = (state.index - 1 + total) % total;
        scrollToCurrent();
    });
    document.querySelector('[data-explore-next]')?.addEventListener('click', () => {
        const total = visibleItems().length;
        if (total === 0) return;
        state.index = (state.index + 1) % total;
        scrollToCurrent();
    });
    document.querySelector('[data-explore-reset]')?.addEventListener('click', () => {
        state.proposito = 'todos';
        state.query = '';
        const campo = document.querySelector('[data-explore-search]');
        if (campo) campo.value = '';
        load();
    });
    document.querySelector('[data-explore-retry]')?.addEventListener('click', load);

    window.addEventListener(UBICACION_USUARIO_EVENT, () => load());
    window.addEventListener(UBICACION_USUARIO_ERROR_EVENT, (event) => {
        const kind = event.detail?.kind;
        if (kind === 'denied') showToast('Ubicación denegada: mostramos publicaciones recientes en lugar de cercanas.');
        else if (kind !== 'unsupported') showToast('No pudimos actualizar tu ubicación; Explorar sigue disponible.');
    });

    // Carga inmediata para no bloquear la página. En paralelo el navegador
    // solicita ubicación; al obtenerla se emite un evento y se reordena solo.
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
