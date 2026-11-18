// Banda "Subastas en vivo" de Explorar: las subastas activas como evento, con su contador y la puja más alta.
// Se actualiza sola por Supabase Realtime (tópico `subastas`) y cada 30 s por si el WebSocket no está.

import { request } from './shared/api.js?v=auth-gate-18';
import { formatPrice, safeImageUrl } from './explore.js?v=explore-17';
import { agrupar, alCambiarConexion, escuchar } from './shared/tiempo-real.js?v=tiempo-real-1';
import { describirAnimal, tiempoRestante } from './subastas.js?v=subastas-9';

const MAXIMO = 8;
let desfase = 0;
let recargaPedida = false;
let conectado = false;
let hayActivas = false;
let estadoConsulta = 'loading';
let secuencia = 0;

/** Tarjeta compacta: título (2 líneas, completo en title), tipo, zona, precio con su etiqueta, tiempo y foto si la hay. */
export function item(subasta) {
    const enlace = document.createElement('a');
    enlace.className = 'subastas-banda__item';
    enlace.href = `subastas?id=${Number(subasta.subastaId)}`;
    const conPujas = typeof subasta.precioActual === 'number';
    const monto = formatPrice(conPujas ? subasta.precioActual : subasta.precioBase);
    const zona = [subasta.zona?.canton, subasta.zona?.provincia].filter(Boolean).join(', ');

    const foto = document.createElement('span');
    foto.className = 'subastas-banda__foto';
    const url = safeImageUrl(subasta.imagenUrl);
    if (url) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = '';
        img.width = 64;
        img.height = 64;
        img.loading = 'lazy';
        img.decoding = 'async';
        img.referrerPolicy = 'no-referrer';
        img.addEventListener('error', () => img.remove(), { once: true });
        foto.append(img);
    }
    const icono = document.createElement('i');
    icono.className = 'fa-solid fa-cow';
    icono.setAttribute('aria-hidden', 'true');
    foto.prepend(icono);

    const texto = document.createElement('span');
    texto.className = 'subastas-banda__texto';
    const titulo = document.createElement('span');
    titulo.className = 'subastas-banda__titulo';
    titulo.textContent = subasta.titulo;
    titulo.title = subasta.titulo;
    const detalle = document.createElement('span');
    detalle.className = 'subastas-banda__detalle';
    detalle.textContent = [describirAnimal(subasta.animal), zona].filter(Boolean).join(' · ');
    texto.append(titulo, detalle);

    const precio = document.createElement('span');
    precio.className = 'subastas-banda__precio';
    const etiqueta = document.createElement('small');
    etiqueta.textContent = conPujas ? `Puja actual · ${subasta.pujasTotal === 1 ? '1 puja' : `${Number(subasta.pujasTotal) || 0} pujas`}` : 'Precio base · sin pujas';
    const valor = document.createElement('strong');
    valor.textContent = monto;
    precio.append(etiqueta, valor);

    const reloj = document.createElement('span');
    reloj.className = 'subastas-banda__reloj';
    reloj.innerHTML = '<i class="fa-regular fa-clock" aria-hidden="true"></i>';
    const restante = document.createElement('span');
    restante.dataset.fin = subasta.fechaFin;
    reloj.append(restante);
    enlace.append(foto, texto, precio, reloj);
    return enlace;
}

function pintarEstadoBanda(banda) {
    const indicador = banda.querySelector('[data-subastas-vivo]');
    const aviso = banda.querySelector('[data-subastas-banda-estado]');
    const lista = banda.querySelector('[data-subastas-banda-lista]');
    indicador.dataset.conectado = String(hayActivas && conectado && estadoConsulta === 'ready');
    indicador.textContent = estadoConsulta === 'error' ? 'Actualización pendiente'
        : estadoConsulta === 'loading' ? 'Consultando…'
            : !hayActivas ? 'Sin subastas activas'
                : conectado ? 'En vivo' : 'Reconectando…';
    aviso.hidden = hayActivas;
    lista.hidden = !hayActivas;
    if (!hayActivas) aviso.textContent = estadoConsulta === 'loading'
        ? 'Consultando las subastas activas…'
        : estadoConsulta === 'error' ? 'No pudimos actualizar las subastas. Puedes abrir la lista completa.'
            : 'No hay subastas activas en este momento. Consulta las próximas.';
}

async function cargar(banda) {
    const turno = ++secuencia;
    try {
        const respuesta = await request('api/v1/subastas?vista=ACTIVAS&tamanoPagina=' + MAXIMO);
        if (turno !== secuencia) return;
        const servidor = Date.parse(respuesta.data?.ahora ?? '');
        if (Number.isFinite(servidor)) desfase = servidor - Date.now();
        const subastas = respuesta.data?.subastas ?? [];
        recargaPedida = false;
        hayActivas = subastas.length > 0;
        estadoConsulta = 'ready';
        banda.querySelector('[data-subastas-banda-lista]').replaceChildren(...subastas.map(item));
        banda.toggleAttribute('data-varias', subastas.length > 1);
        pintarEstadoBanda(banda);
        relojes(banda);
    } catch {
        if (turno !== secuencia) return;
        // Las publicaciones siguen disponibles y conservamos las subastas anteriores.
        estadoConsulta = 'error';
        pintarEstadoBanda(banda);
    }
}

/** "Quedan 2 h 05 min": en una tarjeta pequeña un reloj 02:05:10 no dice si son horas o minutos. Puro. */
export function restanteLegible(finIso, ahoraMs) {
    const fin = Date.parse(finIso ?? '');
    if (!Number.isFinite(fin)) return 'Cierre sin hora';
    const total = Math.floor((fin - ahoraMs) / 1000);
    if (total <= 0) return 'Cerrando la subasta…';
    const d = Math.floor(total / 86400);
    const h = Math.floor((total % 86400) / 3600);
    const m = Math.floor((total % 3600) / 60);
    const seg = total % 60;
    const dos = (n) => String(n).padStart(2, '0');
    if (d > 0) return `Quedan ${d} d ${h} h`;
    if (h > 0) return `Quedan ${h} h ${dos(m)} min`;
    if (m > 0) return `Quedan ${m} min ${dos(seg)} s`;
    return `Quedan ${seg} s`;
}

function relojes(banda) {
    let vencio = false;
    banda.querySelectorAll('[data-fin]').forEach((nodo) => {
        const ahora = Date.now() + desfase;
        const texto = tiempoRestante(nodo.dataset.fin, ahora);
        nodo.textContent = restanteLegible(nodo.dataset.fin, ahora);
        nodo.dataset.cerrando = String(texto === 'Terminada');
        // Último minuto: se avisa con texto y estilo, no solo con color.
        nodo.dataset.urgente = String(texto !== 'Terminada' && Date.parse(nodo.dataset.fin) - ahora < 60000);
        if (texto === 'Terminada') vencio = true;
    });
    if (vencio && !recargaPedida) {
        recargaPedida = true;
        setTimeout(() => cargar(banda), 1200);
    }
}

function iniciar() {
    const banda = document.querySelector('[data-subastas-banda]');
    if (!banda) return;
    pintarEstadoBanda(banda);
    alCambiarConexion((disponible) => {
        conectado = disponible;
        pintarEstadoBanda(banda);
    });
    escuchar('subastas', agrupar(() => cargar(banda)));
    setInterval(() => relojes(banda), 1000);
    setInterval(() => cargar(banda), 30000);
    void cargar(banda);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    else iniciar();
}
