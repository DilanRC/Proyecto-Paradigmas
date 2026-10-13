import { openDialog, closeDialog } from './shared/dialog.js?v=dialog-2';
// Subastas (DEC-SUBASTA-001): lista (activas, historial, mis subastas, donde pujé), detalle en vivo con pujas y
// comentarios, el trato del ganador (contactos, finca y fletes) y el diálogo para abrir una subasta.
// Tiempo real: Supabase Realtime avisa "cambió" (shared/tiempo-real.js) y esta página vuelve a pedir el estado a la
// API. Además consulta cada pocos segundos por si el WebSocket no está. La hora sale del servidor (`ahora`).

import { request } from './shared/api.js';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-3';
import { formatPrice, safeImageUrl } from './explore.js?v=foto-4';
import { createToast } from './shared/toast.js';
import { montarCampoFoto } from './shared/foto-campo.js?v=foto-campo-1';
import { erroresSinLugar } from './shared/field-errors.js?v=errores-1';
import { erroresNativos } from './shared/validacion-nativa.js?v=nativa-1';
import { mostrarEstado } from './shared/estado.js?v=estado-1';
import { agrupar, alCambiarConexion, escuchar } from './shared/tiempo-real.js?v=tiempo-real-1';

const API = 'api/v1/subastas';
const ESTADOS = { ACTIVA: 'En vivo', FINALIZADA: 'Finalizada', DESIERTA: 'Sin pujas', CANCELADA: 'Cancelada' };
const VACIOS = {
    ACTIVAS: ['No hay subastas activas', 'Cuando alguien abra una, aparecerá aquí al instante.'],
    HISTORIAL: ['Todavía no hay subastas terminadas', 'Las subastas que terminen quedarán aquí.'],
    MIAS: ['No has abierto subastas', 'Toca “Abrir subasta” para subastar un animal de tu inventario.'],
    PARTICIPO: ['Aún no has pujado', 'Las subastas donde pujes aparecerán aquí.'],
};

const $ = (selector) => document.querySelector(selector);
let toast = null;
let desfase = 0; // hora del servidor − hora del navegador
const ahora = () => Date.now() + desfase;

/** "2 d 03:15:20", "15:20" o "Terminada". Puro. */
export function tiempoRestante(finIso, ahoraMs) {
    const fin = Date.parse(finIso ?? '');
    if (!Number.isFinite(fin)) return '—';
    let segundos = Math.floor((fin - ahoraMs) / 1000);
    if (segundos <= 0) return 'Terminada';
    const dias = Math.floor(segundos / 86400);
    segundos %= 86400;
    const dos = (n) => String(n).padStart(2, '0');
    const h = Math.floor(segundos / 3600);
    const m = Math.floor((segundos % 3600) / 60);
    const s = segundos % 60;
    const reloj = h > 0 || dias > 0 ? `${dos(h)}:${dos(m)}:${dos(s)}` : `${dos(m)}:${dos(s)}`;
    return dias > 0 ? `${dias} d ${reloj}` : reloj;
}

/** "Vaca · Jersey" o la especie; "Animal" si no hay datos. Puro. */
export function describirAnimal(animal = {}) {
    return [animal.tipo || animal.especie, animal.raza].filter(Boolean).join(' · ') || 'Animal';
}

function el(tag, clase, texto) {
    const nodo = document.createElement(tag);
    if (clase) nodo.className = clase;
    if (texto != null) nodo.textContent = String(texto);
    return nodo;
}

function hora(iso) {
    const fecha = new Date(iso ?? '');
    return Number.isNaN(fecha.getTime()) ? '' : fecha.toLocaleString('es-CR', { dateStyle: 'short', timeStyle: 'short' });
}

function sincronizarHora(respuesta) {
    const servidor = Date.parse(respuesta?.data?.ahora ?? '');
    if (Number.isFinite(servidor)) desfase = servidor - Date.now();
}

function foto(contenedor, url) {
    const segura = safeImageUrl(url);
    contenedor.replaceChildren();
    if (segura) {
        const img = el('img');
        img.src = segura;
        img.alt = '';
        img.loading = 'lazy';
        img.referrerPolicy = 'no-referrer';
        img.addEventListener('error', () => contenedor.replaceChildren(icono('fa-cow')), { once: true });
        contenedor.append(img);
    } else {
        contenedor.append(icono('fa-cow'));
    }
}

function icono(nombre) {
    const i = el('i', `fa-solid ${nombre}`);
    i.setAttribute('aria-hidden', 'true');
    return i;
}

function fila(dl, etiqueta, valor) {
    if (valor == null || valor === '') return;
    const div = el('div');
    div.append(el('dt', null, etiqueta), el('dd', null, valor));
    dl.append(div);
}

function manejarSesion(error) {
    if (error?.status === 401) { endExpiredSession(); return true; }
    return false;
}

function pintarConexion() {
    alCambiarConexion((conectado) => {
        document.querySelectorAll('[data-subastas-vivo]').forEach((nodo) => {
            nodo.dataset.conectado = String(conectado);
            nodo.textContent = conectado ? 'En vivo' : 'Reconectando…';
        });
    });
}

// ───────────────────────────── Lista ─────────────────────────────
const lista = { vista: 'ACTIVAS', q: '', items: [], recargaPedida: false };

function tarjeta(subasta) {
    const card = el('article', 'subasta-card');
    card.dataset.subastaId = String(subasta.subastaId);
    const visual = el('div', 'subasta-card__foto');
    foto(visual, subasta.imagenUrl);
    const estado = el('span', 'subasta-estado', ESTADOS[subasta.estado] ?? subasta.estado);
    estado.dataset.estado = subasta.estado;
    visual.append(estado);
    const cuerpo = el('div', 'subasta-card__cuerpo');
    cuerpo.append(el('h2', null, subasta.titulo));
    cuerpo.append(el('p', 'subasta-card__meta', [describirAnimal(subasta.animal), [subasta.finca?.canton, subasta.finca?.provincia].filter(Boolean).join(', ')].filter(Boolean).join(' · ')));
    const precio = el('p', 'subasta-card__precio');
    const conPujas = typeof subasta.precioActual === 'number';
    precio.append(el('strong', null, formatPrice(conPujas ? subasta.precioActual : subasta.precioBase)),
        el('span', null, conPujas ? ` · ${subasta.pujasTotal} puja${subasta.pujasTotal === 1 ? '' : 's'}` : ' · precio base'));
    cuerpo.append(precio);
    const reloj = el('p', 'subasta-card__reloj');
    if (subasta.estado === 'ACTIVA') {
        reloj.append(icono('fa-regular fa-clock'), el('span', null, ''));
        reloj.lastChild.dataset.fin = subasta.fechaFin;
    } else {
        reloj.textContent = subasta.soyGanador ? '¡La ganaste!' : `Cerró el ${hora(subasta.fechaCierre ?? subasta.fechaFin)}`;
    }
    cuerpo.append(reloj);
    if (subasta.soyVendedor) cuerpo.append(el('p', 'subasta-card__tuya', 'Tu subasta'));
    const entrar = el('a', `activity-button${subasta.estado === 'ACTIVA' ? ' activity-button--primary' : ''}`, subasta.estado === 'ACTIVA' ? 'Entrar a pujar' : 'Ver detalle');
    entrar.href = `subastas?id=${Number(subasta.subastaId)}`;
    if (subasta.soyVendedor && subasta.estado === 'ACTIVA') entrar.textContent = 'Ver cómo va';
    cuerpo.append(entrar);
    card.append(visual, cuerpo);
    return card;
}

function vistaLista(estado, mensaje = '') {
    $('#subastas-loading').hidden = estado !== 'loading';
    $('#subastas-error').hidden = estado !== 'error';
    $('#subastas-empty').hidden = estado !== 'empty';
    $('#subastas-grid').hidden = estado !== 'content';
    if (mensaje) $('#subastas-error-message').textContent = mensaje;
}

async function cargarLista({ silencioso = false } = {}) {
    if (!silencioso) vistaLista('loading');
    try {
        const parametros = new URLSearchParams({ vista: lista.vista });
        if (lista.q) parametros.set('q', lista.q);
        const respuesta = await request(`${API}?${parametros}`);
        sincronizarHora(respuesta);
        lista.items = respuesta.data?.subastas ?? [];
        lista.recargaPedida = false;
        $('#subastas-grid').replaceChildren(...lista.items.map(tarjeta));
        const [titulo, texto] = VACIOS[lista.vista];
        $('#subastas-empty-title').textContent = lista.q ? 'Ninguna subasta coincide con la búsqueda' : titulo;
        $('#subastas-empty-text').textContent = lista.q ? 'Prueba con otra palabra.' : texto;
        vistaLista(lista.items.length ? 'content' : 'empty');
        relojes();
    } catch (error) {
        if (manejarSesion(error)) return;
        if (!silencioso) vistaLista('error', error?.message || 'No pudimos cargar las subastas.');
    }
}

/** Actualiza todos los contadores; si una activa llegó a cero, la API la cierra al volver a pedir la lista. */
function relojes() {
    let vencio = false;
    document.querySelectorAll('[data-fin]').forEach((nodo) => {
        const texto = tiempoRestante(nodo.dataset.fin, ahora());
        nodo.textContent = texto === 'Terminada' ? 'Cerrando…' : `Termina en ${texto}`;
        if (texto === 'Terminada') vencio = true;
    });
    if (vencio && !lista.recargaPedida) {
        lista.recargaPedida = true;
        setTimeout(() => cargarLista({ silencioso: true }), 1200);
    }
}

function iniciarLista() {
    $('#subastas-lista').hidden = false;
    const pedida = new URLSearchParams(window.location.search).get('vista');
    if (VACIOS[pedida]) {
        lista.vista = pedida;
        document.querySelectorAll('[data-subastas-vista]').forEach((boton) => boton.setAttribute('aria-selected', String(boton.dataset.subastasVista === pedida)));
    }
    document.querySelectorAll('[data-subastas-vista]').forEach((boton) => boton.addEventListener('click', () => {
        lista.vista = boton.dataset.subastasVista;
        document.querySelectorAll('[data-subastas-vista]').forEach((otro) => otro.setAttribute('aria-selected', String(otro === boton)));
        cargarLista();
    }));
    let temporizador = null;
    $('#subastas-q').addEventListener('input', (evento) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => { lista.q = evento.target.value.trim(); cargarLista(); }, 350);
    });
    $('#subastas-retry').addEventListener('click', () => cargarLista());
    escuchar('subastas', agrupar(() => cargarLista({ silencioso: true })));
    setInterval(relojes, 1000);
    setInterval(() => cargarLista({ silencioso: true }), 30000);
    cargarLista();
}

// ──────────────────────────── Detalle ────────────────────────────
const detalle = { id: 0, subasta: null, recargaPedida: false, avisadoGanador: false };

function vistaDetalle(estado, mensaje = '') {
    $('#subasta-loading').hidden = estado !== 'loading';
    $('#subasta-error').hidden = estado !== 'error';
    $('#subasta-contenido').hidden = estado !== 'content';
    if (mensaje) $('#subasta-error-message').textContent = mensaje;
}

function pintarDetalle(s) {
    const anterior = detalle.subasta;
    detalle.subasta = s;
    document.title = `${s.titulo} | Subastas | Ganado Cerca`;
    foto($('#subasta-foto'), s.imagenUrl);
    const estado = $('#subasta-estado');
    estado.textContent = ESTADOS[s.estado] ?? s.estado;
    estado.dataset.estado = s.estado;
    $('#subasta-titulo').textContent = s.titulo;
    $('#subasta-descripcion').textContent = s.descripcion ?? '';
    const datos = $('#subasta-datos');
    datos.replaceChildren();
    fila(datos, 'Animal', describirAnimal(s.animal));
    fila(datos, 'Sexo', s.animal?.sexo ? s.animal.sexo.charAt(0) + s.animal.sexo.slice(1).toLowerCase() : null);
    fila(datos, 'Peso', s.animal?.peso != null ? `${s.animal.peso} kg` : null);
    fila(datos, 'Edad', s.animal?.edadMeses != null ? `${s.animal.edadMeses} meses` : null);
    fila(datos, 'Propósito', s.animal?.proposito);
    fila(datos, 'Partos', s.animal?.partos);
    fila(datos, 'Zona', [s.finca?.distrito, s.finca?.canton, s.finca?.provincia].filter(Boolean).join(', '));
    fila(datos, 'Vendedor', s.vendedor?.nombre);
    fila(datos, 'Precio base', formatPrice(s.precioBase));
    fila(datos, 'Incremento mínimo', formatPrice(s.incrementoMinimo));
    fila(datos, s.estado === 'ACTIVA' ? 'Termina' : 'Cerró', hora(s.estado === 'ACTIVA' ? s.fechaFin : (s.fechaCierre ?? s.fechaFin)));

    const conPujas = typeof s.precioActual === 'number';
    $('#subasta-precio-etiqueta').textContent = s.estado === 'FINALIZADA' ? 'Precio final' : (conPujas ? 'Puja más alta' : 'Precio base');
    $('#subasta-precio').textContent = formatPrice(conPujas ? s.precioActual : s.precioBase);
    $('#subasta-pujas-total').textContent = s.pujasTotal ? `${s.pujasTotal} puja${s.pujasTotal === 1 ? '' : 's'}${s.voyGanando ? ' · vas ganando' : ''}` : 'Todavía nadie ha pujado';

    const pujar = $('#subasta-pujar');
    pujar.hidden = !s.puedoPujar;
    if (s.puedoPujar) {
        pujar.elements.monto.min = String(Math.ceil(s.minimoSiguiente));
        $('#subasta-minimo').textContent = `Puja mínima: ${formatPrice(s.minimoSiguiente)}`;
        // Si la puja mínima subió mientras escribías, se corrige el valor sugerido (sin pisar uno mayor).
        if (!pujar.elements.monto.value || Number(pujar.elements.monto.value) < s.minimoSiguiente) {
            pujar.elements.monto.value = String(Math.ceil(s.minimoSiguiente));
        }
    }
    const motivo = $('#subasta-motivo');
    motivo.hidden = !s.motivoNoPuja;
    motivo.textContent = s.motivoNoPuja ?? '';
    $('#subasta-cancelar').hidden = !s.puedoCancelar;
    $('#subasta-comentar').hidden = !s.puedoComentar;

    $('#subasta-pujas').replaceChildren(...(s.pujas.length ? s.pujas.map((p) => {
        const li = el('li', p.esMia ? 'es-mia' : null);
        li.append(el('strong', null, formatPrice(p.monto)), el('span', null, ` ${p.participante} · ${hora(p.fecha)}`));
        return li;
    }) : [el('li', 'subasta-lista__vacia', 'Sé el primero en pujar.')]));

    const comentarios = $('#subasta-comentarios');
    const alFinal = comentarios.scrollHeight - comentarios.scrollTop - comentarios.clientHeight < 40;
    comentarios.replaceChildren(...(s.comentarios.length ? s.comentarios.map((c) => {
        const li = el('li', c.esMio ? 'es-mio' : null);
        li.append(el('strong', null, c.autor), el('span', 'subasta-comentarios__hora', hora(c.fecha)), el('p', null, c.texto));
        return li;
    }) : [el('li', 'subasta-lista__vacia', 'Todavía no hay comentarios. Pregunta lo que quieras saber del animal.')]));
    if (alFinal || !anterior) comentarios.scrollTop = comentarios.scrollHeight;

    pintarTrato(s);
    relojDetalle();
    if (s.soyGanador && s.estado === 'FINALIZADA' && anterior?.estado === 'ACTIVA' && !detalle.avisadoGanador) {
        detalle.avisadoGanador = true;
        toast?.success('¡Ganaste la subasta! Abajo tienes los datos del vendedor y los fletes.');
    }
}

function pintarTrato(s) {
    const panel = $('#subasta-trato');
    const fletes = $('#subasta-fletes');
    if (!s.trato) {
        panel.hidden = true;
        // Sin trato se explica quién ganó (o que nadie pujó).
        if (s.estado === 'FINALIZADA' || s.estado === 'DESIERTA') {
            panel.hidden = false;
            $('#subasta-trato-titulo').textContent = s.estado === 'FINALIZADA' ? 'Subasta finalizada' : 'Terminó sin pujas';
            $('#subasta-trato-texto').textContent = s.estado === 'FINALIZADA'
                ? `Ganó ${s.ganador?.participante ?? 'otro participante'} con ${formatPrice(s.precioActual)}.`
                : 'Nadie pujó en esta subasta.';
            $('#subasta-trato-datos').replaceChildren();
            fletes.hidden = true;
        }
        return;
    }
    panel.hidden = false;
    const t = s.trato;
    $('#subasta-trato-titulo').textContent = s.soyGanador ? '¡Ganaste la subasta!' : 'Tu subasta tiene ganador';
    $('#subasta-trato-texto').textContent = s.soyGanador
        ? `Precio final ${formatPrice(t.precioFinal)}. Contacta al vendedor para acordar el pago y la entrega.`
        : `Precio final ${formatPrice(t.precioFinal)}. Contacta al comprador para acordar el pago y la entrega.`;
    const datos = $('#subasta-trato-datos');
    datos.replaceChildren();
    const otro = s.soyGanador ? t.vendedor : t.ganador;
    fila(datos, s.soyGanador ? 'Vendedor' : 'Comprador', otro?.nombre);
    fila(datos, 'Teléfono', otro?.telefono);
    fila(datos, 'Correo', otro?.correo);
    fila(datos, 'Finca', t.finca?.nombre);
    fila(datos, 'Dirección', [t.finca?.pueblo, t.finca?.distrito, t.finca?.canton, t.finca?.provincia].filter(Boolean).join(', '));
    fila(datos, 'Señas', t.finca?.senas);
    fletes.hidden = !s.soyGanador;
    if (s.soyGanador) void cargarFletes(t.finca?.tienePunto === true);
}

async function cargarFletes(tienePunto) {
    const listaFletes = $('#subasta-fletes-lista');
    if (listaFletes.dataset.cargado === String(detalle.id)) return;
    listaFletes.dataset.cargado = String(detalle.id);
    if (!tienePunto) {
        listaFletes.replaceChildren(el('li', 'subasta-lista__vacia', 'La finca no tiene su ubicación en el mapa: busca transporte en Fletes o pregúntale al vendedor.'));
        return;
    }
    listaFletes.replaceChildren(el('li', null, 'Buscando fletes…'));
    try {
        const ofertas = (await request(`api/v1/fletes?subastaId=${detalle.id}`)).data?.ofertas ?? [];
        listaFletes.replaceChildren(...(ofertas.length ? ofertas.map((o) => {
            const li = el('li');
            li.append(el('strong', null, o.transportista ?? 'Transportista'),
                el('span', null, ` · ${o.vehiculo?.modelo ?? 'Vehículo'} · hasta ${o.capacidad} cabezas${o.distanciaKm == null ? ' · en la misma provincia' : ` · a ${o.distanciaKm} km`} · ${o.precio == null ? 'precio a convenir' : formatPrice(o.precio)}`));
            if (o.telefono) {
                const tel = el('a', 'subasta-tel', o.telefono);
                tel.href = `tel:${String(o.telefono).replace(/[^\d+]/g, '')}`;
                li.append(el('br'), tel);
            }
            return li;
        }) : [el('li', 'subasta-lista__vacia', 'No hay fletes que cubran la finca por ahora. Revisa Fletes más tarde.')]));
    } catch (error) {
        listaFletes.replaceChildren(el('li', 'subasta-lista__vacia', error?.message || 'No pudimos buscar fletes.'));
    }
}

function relojDetalle() {
    const s = detalle.subasta;
    if (!s) return;
    const reloj = $('#subasta-reloj');
    if (s.estado !== 'ACTIVA') {
        reloj.textContent = ESTADOS[s.estado] ?? s.estado;
        return;
    }
    const texto = tiempoRestante(s.fechaFin, ahora());
    reloj.textContent = texto === 'Terminada' ? 'Cerrando…' : `Termina en ${texto}`;
    if (texto === 'Terminada' && !detalle.recargaPedida) {
        detalle.recargaPedida = true;
        $('#subasta-pujar').hidden = true;
        setTimeout(() => cargarDetalle({ silencioso: true }), 1200);
    }
}

async function cargarDetalle({ silencioso = false } = {}) {
    if (!silencioso) vistaDetalle('loading');
    try {
        const respuesta = await request(`${API}?id=${detalle.id}`);
        sincronizarHora(respuesta);
        detalle.recargaPedida = false;
        pintarDetalle(respuesta.data.subasta);
        vistaDetalle('content');
    } catch (error) {
        if (manejarSesion(error)) return;
        if (!silencioso) vistaDetalle('error', error?.status === 404 ? 'Esta subasta no existe.' : (error?.message || 'No pudimos cargar la subasta.'));
    }
}

function iniciarDetalle(id) {
    detalle.id = id;
    $('#subastas-lista').hidden = true;
    $('#subasta-detalle').hidden = false;
    $('#subasta-retry').addEventListener('click', () => cargarDetalle());

    const pujar = $('#subasta-pujar');
    pujar.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const boton = pujar.querySelector('[type="submit"]');
        const error = $('#subasta-pujar-error');
        const monto = Number(pujar.elements.monto.value);
        error.textContent = '';
        const nativo = erroresNativos(pujar).monto;
        if (nativo) { error.textContent = nativo; return; }
        if (!Number.isFinite(monto) || monto < (detalle.subasta?.minimoSiguiente ?? 0)) {
            error.textContent = `La puja mínima es ${formatPrice(detalle.subasta?.minimoSiguiente)}.`;
            return;
        }
        boton.disabled = true;
        try {
            const respuesta = await request(API, { method: 'PATCH', body: JSON.stringify({ subastaId: detalle.id, accion: 'PUJAR', monto }) });
            sincronizarHora(respuesta);
            pujar.elements.monto.value = '';
            pintarDetalle(respuesta.data.subasta);
            toast?.success(`Pujaste ${formatPrice(monto)}. ¡Vas ganando!`);
        } catch (fallo) {
            if (manejarSesion(fallo)) return;
            error.textContent = fallo?.errors?.monto ?? fallo?.message ?? 'No pudimos registrar la puja.';
            void cargarDetalle({ silencioso: true });
        } finally {
            boton.disabled = false;
        }
    });

    const comentar = $('#subasta-comentar');
    comentar.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const texto = comentar.elements.texto.value.trim();
        const error = $('#subasta-comentar-error');
        error.textContent = '';
        if (!texto) { error.textContent = 'Escribe algo antes de enviar.'; return; }
        const boton = comentar.querySelector('[type="submit"]');
        boton.disabled = true;
        try {
            await request(API, { method: 'PATCH', body: JSON.stringify({ subastaId: detalle.id, accion: 'COMENTAR', texto }) });
            comentar.reset();
            await cargarDetalle({ silencioso: true });
        } catch (fallo) {
            if (manejarSesion(fallo)) return;
            error.textContent = fallo?.errors?.texto ?? fallo?.message ?? 'No pudimos publicar el comentario.';
        } finally {
            boton.disabled = false;
        }
    });

    $('#subasta-cancelar').addEventListener('click', async (evento) => {
        if (!window.confirm('¿Cancelar la subasta? El animal vuelve a tu inventario.')) return;
        evento.currentTarget.disabled = true;
        try {
            const respuesta = await request(API, { method: 'PATCH', body: JSON.stringify({ subastaId: detalle.id, accion: 'CANCELAR' }) });
            pintarDetalle(respuesta.data.subasta);
            toast?.success(respuesta.message);
        } catch (fallo) {
            toast?.error(fallo?.message || 'No pudimos cancelar la subasta.');
        } finally {
            evento.currentTarget.disabled = false;
        }
    });

    escuchar(`subasta-${id}`, agrupar(() => cargarDetalle({ silencioso: true })));
    setInterval(relojDetalle, 1000);
    setInterval(() => cargarDetalle({ silencioso: true }), 15000);
    cargarDetalle();
}

// ──────────────────────── Abrir una subasta ────────────────────────
function iniciarCrear(animalPreseleccionado) {
    const dialog = $('#subasta-modal');
    const form = $('#subasta-form');
    const estado = $('#subasta-form-status');
    const campoFoto = montarCampoFoto(form.querySelector('[data-foto-campo="subasta"]'));
    const errores = (mapa = {}) => {
        form.querySelectorAll('[data-subasta-error]').forEach((nodo) => { nodo.textContent = mapa[nodo.dataset.subastaError] ?? ''; });
    };
    const cerrar = () => { closeDialog(dialog); $('#subasta-abrir').focus(); };
    dialog.querySelectorAll('[data-subasta-cerrar]').forEach((boton) => boton.addEventListener('click', cerrar));

    const abrir = async (animalId = null) => {
        form.reset();
        errores();
        campoFoto.reiniciar(null);
        mostrarEstado(estado, 'Cargando tus animales…', 'info');
        openDialog(dialog);
        try {
            // Animales y fincas se cargan por separado: si falla una lista, la otra igual se muestra.
            const [animales, fincas] = await Promise.allSettled([request('api/v1/mi-animales'), request('api/v1/mi-fincas')]);
            if (animales.status === 'rejected') throw animales.reason;
            const todos = animales.value.data?.animales ?? [];
            const disponibles = todos.filter((a) => a.estado === 'ACTIVO');
            const activas = fincas.status === 'fulfilled' ? (fincas.value.data?.fincas ?? []).filter((f) => f.estado === 'ACTIVO') : [];
            const nombre = (a) => [a.arete ? `Arete ${a.arete}` : null, describirAnimal({ tipo: a.tipo?.nombre, especie: a.especie?.nombre, raza: a.raza }), a.peso != null ? `${a.peso} kg` : null].filter(Boolean).join(' · ');
            const ESTADO_ANIMAL = { PUBLICADO: 'publicado en Explorar', SUBASTA: 'ya está en una subasta', VENDIDO: 'vendido', INACTIVO: 'inactivo' };
            // Se muestran todos tus animales; los que no están en el inventario aparecen deshabilitados con el motivo.
            form.elements.animalId.replaceChildren(
                ...(disponibles.length ? [] : [new Option('Ningún animal disponible en tu inventario', '')]),
                ...disponibles.map((a) => new Option(nombre(a), String(a.animalId))),
                ...todos.filter((a) => a.estado !== 'ACTIVO').map((a) => Object.assign(new Option(`${nombre(a)} (${ESTADO_ANIMAL[a.estado] ?? a.estado})`, String(a.animalId)), { disabled: true })),
            );
            form.elements.fincaNombre.replaceChildren(...activas.map((f) => new Option(f.nombre, f.nombre)));
            if (!disponibles.length) {
                mostrarEstado(estado, todos.length
                    ? 'Tus animales están publicados o vendidos. Para subastar uno, regístralo en Mi panel → Mis animales (los publicados en Explorar no se subastan).'
                    : 'No tienes animales en tu inventario. Regístralos en Mi panel → Mis animales.', 'error');
            } else if (!activas.length) {
                mostrarEstado(estado, fincas.status === 'rejected' ? (fincas.reason?.message || 'No pudimos cargar tus fincas.') : 'Primero registra una finca en Mi panel.', 'error');
            } else {
                mostrarEstado(estado, '', 'info');
            }
            if (animalId) form.elements.animalId.value = String(animalId);
            const elegido = disponibles.find((a) => String(a.animalId) === form.elements.animalId.value);
            if (elegido && !form.elements.titulo.value) form.elements.titulo.value = describirAnimal({ tipo: elegido.tipo?.nombre, especie: elegido.especie?.nombre, raza: elegido.raza });
        } catch (error) {
            mostrarEstado(estado, error?.status === 409 ? 'Activa la actividad Vendedor en Ajustes → Cómo participo para abrir subastas.'
                : (error?.message || 'No pudimos cargar tus animales.'), 'error');
        }
    };

    $('#subasta-abrir').addEventListener('click', () => abrir());
    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        errores();
        form.querySelectorAll('[aria-invalid]').forEach((nodo) => nodo.removeAttribute('aria-invalid'));
        const nativos = erroresNativos(form);
        if (Object.keys(nativos).length) {
            errores(nativos);
            Object.keys(nativos).forEach((nombre) => form.elements.namedItem(nombre)?.setAttribute?.('aria-invalid', 'true'));
            mostrarEstado(estado, 'Revisa los datos señalados.', 'error');
            form.querySelector('[aria-invalid="true"]')?.focus();
            return;
        }
        const boton = form.querySelector('[type="submit"]');
        boton.disabled = true;
        mostrarEstado(estado, 'Abriendo la subasta…', 'info');
        try {
            const datos = Object.fromEntries(new FormData(form));
            const imagenUrl = await campoFoto.resolver();
            const cuerpo = {
                animalId: Number(datos.animalId), fincaNombre: datos.fincaNombre, titulo: String(datos.titulo ?? '').trim(),
                precioBase: Number(datos.precioBase), incrementoMinimo: Number(datos.incrementoMinimo), duracionMinutos: Number(datos.duracionMinutos),
                ...(String(datos.descripcion ?? '').trim() && { descripcion: String(datos.descripcion).trim() }),
                ...(imagenUrl && { imagenUrl }),
            };
            const respuesta = await request(API, { method: 'POST', body: JSON.stringify(cuerpo) });
            window.location.assign(`subastas?id=${respuesta.data.subasta.subastaId}`);
        } catch (error) {
            errores(error?.errors);
            mostrarEstado(estado, [error?.message || 'No pudimos abrir la subasta.', ...erroresSinLugar(form, error?.errors)].join(' '), 'error');
        } finally {
            boton.disabled = false;
        }
    });
    if (animalPreseleccionado) void abrir(animalPreseleccionado);
}

function iniciar() {
    if (!$('#subastas-lista')) return;
    if (!readAuthSession()) { window.location.replace(`entrar?next=${encodeURIComponent(`subastas${window.location.search}`)}`); return; }
    toast = createToast({ polite: $('#toast-status'), assertive: $('#toast-alert') });
    pintarConexion();
    const parametros = new URLSearchParams(window.location.search);
    const id = Number(parametros.get('id'));
    const nueva = Number(parametros.get('nueva'));
    iniciarCrear(Number.isInteger(nueva) && nueva > 0 ? nueva : null);
    if (Number.isInteger(id) && id > 0) iniciarDetalle(id);
    else iniciarLista();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    else iniciar();
}
