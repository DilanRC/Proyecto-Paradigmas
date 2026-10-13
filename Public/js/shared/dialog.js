export const DIALOG_OPEN_EVENT = 'tindercows:dialog-open';

function notifyOpened(dialog) {
    if (typeof dialog?.dispatchEvent !== 'function') return;
    try {
        const EventCtor = dialog.ownerDocument?.defaultView?.Event ?? globalThis.Event;
        if (typeof EventCtor === 'function') {
            dialog.dispatchEvent(new EventCtor(DIALOG_OPEN_EVENT));
        }
    } catch {
        // El evento solo coordina mejoras del frontend; abrir el dialogo nunca
        // debe fallar porque un entorno no tenga constructor Event utilizable.
    }
}

const fallbacks = new WeakMap();
const modalStacks = new WeakMap();
const focusSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function openDialog(dialog, { isBusy = () => false, restore = true } = {}) {
    if (dialog.open) return;
    if (typeof dialog.showModal === 'function' && typeof dialog.close === 'function') {
        dialog.showModal();
        return;
    }
    const doc = dialog.ownerDocument ?? document;
    const previous = doc.activeElement;
    const stack = modalStacks.get(doc) ?? [];
    stack.push(dialog);
    modalStacks.set(doc, stack);
    const isTop = () => stack[stack.length - 1] === dialog;
    const attributes = ['role', 'aria-modal', 'tabindex'].map((name) => [name, dialog.getAttribute(name)]);
    const oldStyle = dialog.getAttribute('style');
    const oldHidden = dialog.hidden;
    const overflow = doc.body?.style.overflow;
    if (doc.body?.style) doc.body.style.overflow = 'hidden';
    const siblings = Array.from(doc.body?.children ?? []).filter((node) => node !== dialog && !node.contains?.(dialog));
    const background = siblings.map((node) => [node, node.inert, node.getAttribute('aria-hidden')]);
    for (const [node] of background) { node.inert = true; node.setAttribute('aria-hidden', 'true'); }
    dialog.hidden = false;
    dialog.setAttribute('open', '');
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('tabindex', '-1');
    Object.assign(dialog.style, { position: 'fixed', inset: '0', margin: 'auto', zIndex: '2147483647', maxHeight: '90vh', overflow: 'auto', boxShadow: '0 0 0 100vmax rgba(0,0,0,.55)' });
    const controls = () => Array.from(dialog.querySelectorAll(focusSelector)).filter((node) => !node.hidden && node.getAttribute?.('aria-hidden') !== 'true' && (typeof node.getClientRects !== 'function' || node.getClientRects().length));
    const focusInside = () => (controls()[0] ?? dialog).focus?.();
    const keydown = (event) => {
        if (!isTop()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            if (!isBusy()) {
                const EventCtor = doc.defaultView?.Event ?? globalThis.Event;
                const cancel = typeof EventCtor === 'function' ? new EventCtor('cancel', { cancelable: true }) : null;
                if (!cancel || dialog.dispatchEvent(cancel)) closeDialog(dialog);
            }
        } else if (event.key === 'Tab') {
            const nodes = controls();
            const first = nodes[0] ?? dialog;
            const last = nodes[nodes.length - 1] ?? dialog;
            if (!dialog.contains(doc.activeElement) || (event.shiftKey ? doc.activeElement === first : doc.activeElement === last) || !nodes.length) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus?.();
            }
        }
    };
    const focusin = (event) => { if (isTop() && !dialog.contains(event.target)) focusInside(); };
    const outside = (event) => {
        if (isTop() && !dialog.contains(event.target)) { event.preventDefault(); event.stopPropagation(); }
    };
    doc.addEventListener('keydown', keydown, true);
    doc.addEventListener('focusin', focusin, true);
    doc.addEventListener('click', outside, true);
    fallbacks.set(dialog, { doc, previous, restore, attributes, oldStyle, oldHidden, overflow, background, keydown, focusin, outside });
    focusInside();
}

export function closeDialog(dialog) {
    const state = fallbacks.get(dialog);
    if (!state) {
        if (dialog.open && typeof dialog.close === 'function') dialog.close();
        return;
    }
    fallbacks.delete(dialog);
    const stack = modalStacks.get(state.doc);
    if (stack) {
        const index = stack.indexOf(dialog);
        if (index !== -1) stack.splice(index, 1);
        if (!stack.length) modalStacks.delete(state.doc);
    }
    dialog.removeAttribute('open');
    dialog.hidden = state.oldHidden;
    if (state.doc.body?.style) state.doc.body.style.overflow = state.overflow;
    for (const [name, value] of [...state.attributes, ['style', state.oldStyle]]) {
        if (value === null) dialog.removeAttribute(name); else dialog.setAttribute(name, value);
    }
    for (const [node, inert, aria] of state.background) {
        node.inert = inert;
        if (aria === null) node.removeAttribute('aria-hidden'); else node.setAttribute('aria-hidden', aria);
    }
    state.doc.removeEventListener('keydown', state.keydown, true);
    state.doc.removeEventListener('focusin', state.focusin, true);
    state.doc.removeEventListener('click', state.outside, true);
    if (state.restore && state.previous?.isConnected) state.previous.focus?.();
    // Native close events are queued; keep chained-dialog focus restoration in the same order.
    Promise.resolve().then(() => {
        const EventCtor = state.doc.defaultView?.Event ?? globalThis.Event;
        if (typeof EventCtor === 'function') dialog.dispatchEvent?.(new EventCtor('close'));
    });
}

export function createDialogController({ isBusy = () => false } = {}) {
    const focusReturn = [];

    function open(dialog, { focus = null } = {}) {
        focusReturn.push(document.activeElement);
        openDialog(dialog, { isBusy, restore: false });

        // Las restauraciones de borrador deben ocurrir de forma sincrona ANTES
        // de mover el foco. La version anterior observaba el atributo `open`
        // con MutationObserver; ese callback asincrono podia ejecutar despues
        // de que el usuario ya abriera un <select> nativo y cerraba su menu al
        // reescribir el control. Este evento fija un unico punto de apertura.
        notifyOpened(dialog);

        const target = focus ?? dialog.querySelector('[autofocus]');
        target?.focus?.();
    }

    function close(dialog) {
        closeDialog(dialog);
    }

    /**
     * Cierra al pulsar fuera del dialogo, salvo que haya una operacion en curso.
     * `isBusy` es un parametro porque cada panel vigila banderas distintas:
     * productores anade savingFincaAddress y transportistas anade assigning.
     */
    function handleBackdropClick(event) {
        if (event.target === event.currentTarget && !isBusy()) close(event.currentTarget);
    }

    /** Devuelve el foco al elemento que abrio el dialogo, si sigue en el documento. */
    function restoreFocus() {
        const previous = focusReturn.pop();
        if (previous instanceof HTMLElement && previous.isConnected) previous.focus();
    }

    return { open, close, handleBackdropClick, restoreFocus };
}
