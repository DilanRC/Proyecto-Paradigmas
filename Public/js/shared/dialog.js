// Apertura, cierre y foco de los <dialog> nativos.
//
// No reimplementa el modal: <dialog>.showModal() ya aporta trampa de foco,
// cierre con Escape y aria-modal implicito. Lo que faltaba era el retorno del
// foco cuando un dialogo abre a otro.
//
// El foco de retorno se guarda en una pila. Antes era una sola variable, de
// modo que en el recorrido detalle -> editar el retorno del primer dialogo se
// perdia y el foco acababa en el <body>.

export function createDialogController({ isBusy = () => false } = {}) {
    const focusReturn = [];

    function open(dialog, { focus = null } = {}) {
        focusReturn.push(document.activeElement);
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
        const target = focus ?? dialog.querySelector('[autofocus]');
        target?.focus?.();
    }

    function close(dialog) {
        if (dialog.open) dialog.close();
    }

    /**
     * Cierra al pulsar fuera del dialogo, salvo que haya una operacion en curso.
     * `isBusy` es un parametro porque cada panel vigila banderas distintas:
     * productores anade savingFincaAddress y transportistas anade assigning.
     */
    function handleBackdropClick(event) {
        if (event.target === event.currentTarget && !isBusy()) event.currentTarget.close();
    }

    /** Devuelve el foco al elemento que abrio el dialogo, si sigue en el documento. */
    function restoreFocus() {
        const previous = focusReturn.pop();
        if (previous instanceof HTMLElement && previous.isConnected) previous.focus();
    }

    return { open, close, handleBackdropClick, restoreFocus };
}
