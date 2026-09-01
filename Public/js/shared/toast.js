// Notificaciones accesibles sobre dos regiones vivas permanentes.
//
// La version anterior tenia un solo nodo con `hidden`: escribia el texto con el
// nodo oculto, luego lo mostraba y ademas alternaba role entre status y alert.
// Eso falla por dos motivos independientes:
//   1. un nodo `hidden` esta fuera del arbol de accesibilidad, asi que el
//      lector no observa el cambio de texto;
//   2. cambiar el role de un nodo vivo en caliente no reinicia su observacion.
//
// Aqui hay dos regiones distintas, siempre presentes y nunca `hidden`. Se
// ocultan por CSS cuando estan vacias (:empty). El texto se escribe sobre una
// region que ya era visible para el lector.

const TIMEOUTS = { success: 4500, info: 4500, warning: 8000, error: 0 };

export function createToast({ polite, assertive }, { timeouts = TIMEOUTS } = {}) {
    let timer = 0;

    function clear() {
        window.clearTimeout(timer);
        polite.replaceChildren();
        assertive.replaceChildren();
        polite.className = 'toast';
        assertive.className = 'toast';
    }

    /**
     * @param {string} message
     * @param {'success'|'error'|'warning'|'info'} type
     * @param {{label: string, onSelect: () => void}|null} action  boton opcional (Reintentar)
     */
    function show(message, type = 'info', { action = null } = {}) {
        clear();
        // Los errores interrumpen; el resto espera a que el lector haga una pausa.
        const region = type === 'error' ? assertive : polite;
        region.className = `toast toast--${type}`;

        const text = document.createElement('span');
        text.className = 'toast__text';
        text.textContent = message;
        region.append(text);

        if (action) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'toast__action';
            button.textContent = action.label;
            button.addEventListener('click', () => { clear(); action.onSelect(); });
            region.append(button);
        }

        const delay = timeouts[type] ?? 0;
        if (delay > 0) {
            timer = window.setTimeout(clear, delay);
        } else {
            // Un error no se desvanece solo, para que de tiempo a leerlo. A
            // cambio necesita una forma explicita de cerrarlo: sin ella el
            // mensaje de una operacion vieja se queda en pantalla sobre la
            // siguiente.
            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'toast__dismiss';
            dismiss.textContent = '×';
            dismiss.setAttribute('aria-label', 'Descartar notificación');
            dismiss.addEventListener('click', clear);
            region.append(dismiss);
        }
    }

    return {
        show,
        dismiss: clear,
        success: (m, o) => show(m, 'success', o),
        error: (m, o) => show(m, 'error', o),
        warning: (m, o) => show(m, 'warning', o),
        info: (m, o) => show(m, 'info', o),
    };
}
