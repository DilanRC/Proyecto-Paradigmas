// Indicador de una operación que la persona inició: spinner + la etapa real
// ("Subiendo imagen…", "Guardando publicación…") dentro del botón que la lanzó.
//
// Spinner = acción en curso. No hay porcentaje: Supabase Storage y la API no
// informan progreso de bytes, así que el indicador es indeterminado. El botón
// queda deshabilitado (evita el doble envío) y aria-busy; al terminar, haya
// salido bien o mal, `terminar()` devuelve su contenido y su estado anteriores.

const originales = new WeakMap();

function pintar(boton, texto) {
    const icono = document.createElement('i');
    icono.className = 'fa-solid fa-circle-notch fa-spin';
    icono.setAttribute('aria-hidden', 'true');
    const etiqueta = document.createElement('span');
    etiqueta.textContent = texto;
    boton.replaceChildren(icono, etiqueta);
}

/**
 * Empieza la operación en `boton` con su primera etapa. Devuelve `etapa(texto)`
 * para avanzar y `terminar()` para restaurarlo (llamar siempre en finally).
 */
export function iniciarProgreso(boton, texto) {
    if (!boton) return { etapa() {}, terminar() {} };
    if (!originales.has(boton)) {
        originales.set(boton, { html: boton.innerHTML, disabled: boton.disabled });
    }
    boton.disabled = true;
    boton.setAttribute('aria-busy', 'true');
    pintar(boton, texto);
    return {
        etapa(siguiente) {
            if (originales.has(boton)) pintar(boton, siguiente);
        },
        terminar() {
            const original = originales.get(boton);
            if (!original) return;
            originales.delete(boton);
            boton.innerHTML = original.html;
            // Solo vuelve a habilitarse si estaba habilitado: una regla de negocio que lo apagó se respeta.
            boton.disabled = original.disabled;
            boton.removeAttribute('aria-busy');
        },
    };
}
