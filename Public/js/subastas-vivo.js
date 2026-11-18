// Banda "Subastas en vivo" de Explorar: las subastas activas como evento, con su contador y la puja más alta.
// Se actualiza sola por Supabase Realtime (tópico `subastas`) y cada 30 s por si el WebSocket no está.

import { request } from './shared/api.js?v=auth-gate-17';
import { formatPrice } from './explore.js?v=explore-16';
import { agrupar, alCambiarConexion, escuchar } from './shared/tiempo-real.js?v=tiempo-real-1';
import { describirAnimal, tiempoRestante } from './subastas.js?v=subastas-8';

const MAXIMO = 8;
let desfase = 0;
let recargaPedida = false;
let conectado = false;
let hayActivas = false;
let estadoConsulta = 'loading';
let secuencia = 0;

function item(subasta) {
    const enlace = document.createElement('a');
    enlace.className = 'subastas-banda__item';
    enlace.href = `subastas?id=${Number(subasta.subastaId)}`;
    const titulo = document.createElement('span');
    titulo.textContent = subasta.titulo;
    const precio = document.createElement('strong');
    const conPujas = typeof subasta.precioActual === 'number';
    precio.textContent = `${formatPrice(conPujas ? subasta.precioActual : subasta.precioBase)}${conPujas ? '' : ' (base)'}`;
    const detalle = document.createElement('span');
    detalle.textContent = [describirAnimal(subasta.animal), subasta.finca?.canton].filter(Boolean).join(' · ');
    const reloj = document.createElement('span');
    reloj.dataset.fin = subasta.fechaFin;
    enlace.append(titulo, precio, detalle, reloj);
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
        pintarEstadoBanda(banda);
        relojes(banda);
    } catch {
        if (turno !== secuencia) return;
        // Las publicaciones siguen disponibles y conservamos las subastas anteriores.
        estadoConsulta = 'error';
        pintarEstadoBanda(banda);
    }
}

function relojes(banda) {
    let vencio = false;
    banda.querySelectorAll('[data-fin]').forEach((nodo) => {
        const texto = tiempoRestante(nodo.dataset.fin, Date.now() + desfase);
        nodo.textContent = texto === 'Terminada' ? 'Cerrando…' : `Termina en ${texto}`;
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
