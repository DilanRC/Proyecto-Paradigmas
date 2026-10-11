// Tiempo real con Supabase Realtime (Broadcast) sobre un WebSocket nativo, sin librerías.
//
// El servidor PHP avisa "esto cambió" en un tópico (subastas, subasta-<id>, notificaciones-<personaId>) y quien
// escucha vuelve a pedir el estado a la API: el aviso nunca trae datos, así un mensaje falso en el canal (es
// público) solo causa una recarga de más. Si el WebSocket se cae, se reconecta solo y, al volver, avisa a todos
// para que se pongan al día. Las páginas además consultan cada pocos segundos por si Realtime no está.

const CONFIG_URL = 'api/v1/auth/config';
const LATIDO_MS = 25000;

const oyentes = new Map(); // tópico → Set de funciones
const alCambiarEstado = new Set();
let socket = null;
let conectado = false;
let siguienteRef = 0;
let latido = null;
let reintentos = 0;
let configuracion = null;
let conectando = false;

/** Mensaje de unión a un tópico de Broadcast (protocolo Phoenix 1.0 de Supabase Realtime). Puro. */
export function mensajeUnion(topico, ref) {
    return {
        topic: `realtime:${topico}`,
        event: 'phx_join',
        payload: { config: { broadcast: { self: false, ack: false }, presence: { key: '' }, private: false } },
        ref: String(ref),
    };
}

/** Tópico de un mensaje entrante si es un aviso de Broadcast; si no, null. Puro. */
export function topicoDeAviso(mensaje) {
    if (mensaje?.event !== 'broadcast' || typeof mensaje.topic !== 'string') return null;
    return mensaje.topic.startsWith('realtime:') ? mensaje.topic.slice('realtime:'.length) : null;
}

/** Espera antes de reconectar: 1, 2, 4… hasta 30 segundos. Puro. */
export function esperaReintento(intento) {
    return Math.min(30000, 1000 * 2 ** Math.max(0, intento));
}

function enviar(mensaje) {
    if (socket?.readyState === WebSocket.OPEN) socket.send(JSON.stringify(mensaje));
}

function avisarEstado() {
    for (const funcion of alCambiarEstado) funcion(conectado);
}

function avisarTodos() {
    for (const funciones of oyentes.values()) for (const funcion of funciones) funcion();
}

async function obtenerConfiguracion() {
    if (configuracion) return configuracion;
    const respuesta = await fetch(CONFIG_URL, { headers: { Accept: 'application/json' }, cache: 'no-store' });
    const datos = (await respuesta.json())?.data ?? {};
    if (!String(datos.url ?? '').startsWith('https://') || !datos.publishableKey) throw new Error('Sin configuración de Realtime.');
    configuracion = { url: datos.url.replace(/\/$/, ''), clave: datos.publishableKey };
    return configuracion;
}

async function conectar() {
    if (socket || conectando || typeof WebSocket === 'undefined') return;
    conectando = true;
    let config;
    try {
        config = await obtenerConfiguracion();
    } catch {
        programarReconexion();
        return;
    } finally {
        conectando = false;
    }
    if (socket) return;
    socket = new WebSocket(`${config.url.replace(/^http/, 'ws')}/realtime/v1/websocket?apikey=${encodeURIComponent(config.clave)}&vsn=1.0.0`);
    socket.addEventListener('open', () => {
        conectado = true;
        reintentos = 0;
        for (const topico of oyentes.keys()) enviar(mensajeUnion(topico, ++siguienteRef));
        latido = setInterval(() => enviar({ topic: 'phoenix', event: 'heartbeat', payload: {}, ref: String(++siguienteRef) }), LATIDO_MS);
        avisarEstado();
        // Lo que cambió mientras no había conexión no llegó: que cada pantalla se ponga al día.
        avisarTodos();
    });
    socket.addEventListener('message', (evento) => {
        let mensaje;
        try { mensaje = JSON.parse(evento.data); } catch { return; }
        const topico = topicoDeAviso(mensaje);
        if (topico) for (const funcion of oyentes.get(topico) ?? []) funcion();
    });
    socket.addEventListener('close', () => {
        clearInterval(latido);
        socket = null;
        conectado = false;
        avisarEstado();
        if (oyentes.size > 0) programarReconexion();
    });
}

function programarReconexion() {
    setTimeout(conectar, esperaReintento(reintentos++));
}

/**
 * Llama a `alCambiar()` cada vez que llega un aviso en `topico` (y al reconectar). Devuelve la función para dejar
 * de escuchar.
 */
export function escuchar(topico, alCambiar) {
    if (!oyentes.has(topico)) {
        oyentes.set(topico, new Set());
        if (conectado) enviar(mensajeUnion(topico, ++siguienteRef));
    }
    oyentes.get(topico).add(alCambiar);
    void conectar();
    return () => {
        const funciones = oyentes.get(topico);
        funciones?.delete(alCambiar);
        if (funciones && funciones.size === 0) {
            oyentes.delete(topico);
            enviar({ topic: `realtime:${topico}`, event: 'phx_leave', payload: {}, ref: String(++siguienteRef) });
        }
    };
}

/** `funcion(conectado)` cada vez que el WebSocket se conecta o se cae (para el indicador "En vivo"). */
export function alCambiarConexion(funcion) {
    alCambiarEstado.add(funcion);
    funcion(conectado);
    return () => alCambiarEstado.delete(funcion);
}

/** Agrupa varias llamadas seguidas en una sola (varios avisos juntos → una sola consulta). */
export function agrupar(funcion, ms = 250) {
    let temporizador = null;
    return () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(funcion, ms);
    };
}
