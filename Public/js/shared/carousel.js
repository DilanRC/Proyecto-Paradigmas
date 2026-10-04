// Carrusel de tarjetas sobre scroll-snap: flechas, puntos, deslizar con el
// dedo (scroll nativo) y avance automático con loop. Sin dependencias.
//
// Marcado esperado dentro de `root`:
//   [data-carousel-track]  contenedor con scroll horizontal y snap
//   [data-carousel-prev] / [data-carousel-next]  flechas
//   [data-carousel-dots]   contenedor de puntos
//   [data-carousel-status] texto "1–4 de 9" (opcional)

/** Posición de destino con loop: después de la última vuelve a la primera. */
export function siguientePosicion(actual, delta, total) {
    if (total <= 0) return 0;
    return ((actual + delta) % total + total) % total;
}

export function montarCarrusel(root, { intervalo = 4000 } = {}) {
    const track = root?.querySelector('[data-carousel-track]');
    if (!track) return null;
    const dots = root.querySelector('[data-carousel-dots]');
    const status = root.querySelector('[data-carousel-status]');
    const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const tarjetas = () => [...track.children];
    const paso = () => {
        const [primera, segunda] = tarjetas();
        return segunda ? segunda.offsetLeft - primera.offsetLeft : track.clientWidth || 1;
    };
    const porVista = () => Math.max(1, Math.round((track.clientWidth + 1) / paso()));
    const posiciones = () => Math.max(1, tarjetas().length - porVista() + 1);
    const actual = () => Math.min(posiciones() - 1, Math.round(track.scrollLeft / paso()));

    const ir = (posicion) => {
        // Un detalle abierto que sale de la vista dejaría la fila alta y vacía.
        root.querySelectorAll('details[open]').forEach((detalle) => { detalle.open = false; });
        track.scrollTo({ left: siguientePosicion(posicion, 0, posiciones()) * paso(), behavior: reducido ? 'auto' : 'smooth' });
    };
    const mover = (delta) => ir(siguientePosicion(actual(), delta, posiciones()));

    let totalPuntos = 0;
    const pintar = () => {
        const total = posiciones();
        if (dots && total !== totalPuntos) {
            totalPuntos = total;
            dots.replaceChildren(...Array.from({ length: total }, (_, indice) => {
                const punto = document.createElement('button');
                punto.type = 'button';
                punto.setAttribute('aria-label', `Ir a la posición ${indice + 1} de ${total}`);
                punto.addEventListener('click', () => ir(indice));
                return punto;
            }));
        }
        const indice = actual();
        dots?.querySelectorAll('button').forEach((punto, i) => punto.setAttribute('aria-current', i === indice ? 'true' : 'false'));
        if (status) {
            const n = tarjetas().length;
            status.textContent = `${indice + 1}–${Math.min(indice + porVista(), n)} de ${n}`;
        }
    };

    let cuadro = 0;
    const alDesplazar = () => {
        cancelAnimationFrame(cuadro);
        cuadro = requestAnimationFrame(pintar);
    };

    root.querySelector('[data-carousel-prev]')?.addEventListener('click', () => mover(-1));
    root.querySelector('[data-carousel-next]')?.addEventListener('click', () => mover(1));
    track.addEventListener('scroll', alDesplazar, { passive: true });
    track.addEventListener('keydown', (event) => {
        if (event.target !== track) return;
        if (event.key === 'ArrowLeft') { event.preventDefault(); mover(-1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); mover(1); }
    });
    const observador = new ResizeObserver(alDesplazar);
    observador.observe(track);

    // Avance automático: se pausa con el mouse encima, con foco de teclado o si
    // la pestaña no está visible; nunca corre con prefers-reduced-motion.
    let temporizador = null;
    let pausado = false;
    const detener = () => { window.clearInterval(temporizador); temporizador = null; };
    const leyendo = () => Boolean(root.querySelector('details[open]'));
    const iniciar = () => {
        if (reducido || pausado || temporizador || document.hidden || leyendo()) return;
        temporizador = window.setInterval(() => mover(1), intervalo);
    };
    const pausar = () => { pausado = true; detener(); };
    const reanudar = () => { pausado = false; iniciar(); };
    root.addEventListener('pointerenter', pausar);
    root.addEventListener('pointerleave', reanudar);
    root.addEventListener('focusin', pausar);
    root.addEventListener('focusout', (event) => { if (!root.contains(event.relatedTarget)) reanudar(); });
    // "toggle" no burbujea, pero sí pasa por la fase de captura del contenedor.
    root.addEventListener('toggle', () => (leyendo() ? detener() : iniciar()), true);
    const alCambiarVisibilidad = () => (document.hidden ? detener() : iniciar());
    document.addEventListener('visibilitychange', alCambiarVisibilidad);

    pintar();
    iniciar();
    return {
        destruir() {
            detener();
            observador.disconnect();
            document.removeEventListener('visibilitychange', alCambiarVisibilidad);
        },
    };
}
