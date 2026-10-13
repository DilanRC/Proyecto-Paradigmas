// Banda "Subastas en vivo" de Explorar: las subastas activas como evento, con su contador y la puja más alta.
// Se actualiza sola por Supabase Realtime (tópico `subastas`) y cada 30 s por si el WebSocket no está.

import { request } from './shared/api.js';
import { formatPrice } from './explore.js?v=foto-4';
import { agrupar, alCambiarConexion, escuchar } from './shared/tiempo-real.js?v=tiempo-real-1';
import { describirAnimal, tiempoRestante } from './subastas.js?v=subastas-5';

const MAXIMO = 8;
let desfase = 0;
let recargaPedida = false;

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

async function cargar(banda) {
    try {
        const respuesta = await request('api/v1/subastas?vista=ACTIVAS&tamanoPagina=' + MAXIMO);
        const servidor = Date.parse(respuesta.data?.ahora ?? '');
        if (Number.isFinite(servidor)) desfase = servidor - Date.now();
        const subastas = respuesta.data?.subastas ?? [];
        recargaPedida = false;
        banda.querySelector('[data-subastas-banda-lista]').replaceChildren(...subastas.map(item));
        banda.hidden = subastas.length === 0;
        relojes(banda);
    } catch {
        // La banda es un extra de Explorar: si falla, Explorar sigue igual.
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
    alCambiarConexion((conectado) => {
        banda.querySelectorAll('[data-subastas-vivo]').forEach((nodo) => {
            nodo.dataset.conectado = String(conectado);
            nodo.textContent = conectado ? 'En vivo' : 'Reconectando…';
        });
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
