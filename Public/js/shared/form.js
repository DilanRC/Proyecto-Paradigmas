// Errores por campo y bloqueo de envio, atados a UN formulario concreto.
//
// bindFormErrors se liga a un formulario en vez de recibirlo en cada llamada.
// Eso resuelve el caso de productores, que tiene dos formularios
// (#formulario-productor y #formulario-direccion-finca): cada uno se liga por
// separado y el dialogo de direccion de finca no puede pintar sus errores
// sobre el formulario principal.

import { describeValidity, mapFieldErrors, pickFirstInvalid } from './field-errors.js';
import { clearFormDraftAfterSuccessfulClose, enableFormDraft } from './form-draft.js';

/** Estado de deshabilitado previo a un envio, por formulario. */
const disabledBefore = new WeakMap();

export function bindFormErrors(form, { collapsePrefixes = [] } = {}) {
    // Calidad pidio no perder lo escrito ante errores. Los formularios CRUD que
    // tienen una identidad estable reciben automaticamente un borrador temporal
    // en sessionStorage. Formularios secundarios sin identidad estable (por
    // ejemplo direccion de finca) no se activan aqui para no mezclar contextos.
    enableFormDraft(form);

    const container = (field) => form.querySelector(`[data-error-for="${CSS.escape(field)}"]`);

    function showErrors(errors) {
        const entries = mapFieldErrors(errors, { collapsePrefixes });
        let first = null;
        for (const { field, message } of entries) {
            const control = form.elements.namedItem(field);
            if (control instanceof HTMLElement) {
                control.setAttribute('aria-invalid', 'true');
                first ??= control;
            }
            const slot = container(field);
            if (slot) slot.textContent = message;
        }
        first?.focus();
        return entries;
    }

    function clearErrors() {
        form.querySelectorAll('[aria-invalid]').forEach((c) => c.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-for]').forEach((slot) => { slot.textContent = ''; });
    }

    function clearControlError(event) {
        const control = event.target;
        if (!control.name || control.form !== form) return;
        control.removeAttribute('aria-invalid');
        const slot = container(control.name);
        if (slot) slot.textContent = '';
    }

    /** Marca un control invalido y escribe el motivo junto a el. */
    function markInvalid(control) {
        control.setAttribute('aria-invalid', 'true');
        const slot = container(control.name);
        if (slot && !slot.textContent) slot.textContent = describeValidity(control);
    }

    function markNativeError(event) {
        markInvalid(event.target);
    }

    /**
     * Marca TODOS los controles que el navegador rechaza y enfoca el primero.
     *
     * Escribe el motivo en cada uno: como el envio se cancela con
     * preventDefault(), el globo nativo del navegador nunca llega a mostrarse y
     * sin este texto el usuario veria los campos en rojo sin explicacion.
     */
    function markFirstInvalid() {
        const controles = [...form.querySelectorAll('input, select, textarea')]
            .filter((control) => typeof control.checkValidity === 'function');
        controles.filter((control) => !control.checkValidity()).forEach(markInvalid);

        const invalid = pickFirstInvalid(controles, (control) => !control.checkValidity());
        invalid?.focus();
        return invalid;
    }

    return { showErrors, clearErrors, clearControlError, markNativeError, markFirstInvalid };
}

/**
 * Bloquea el formulario mientras se envia.
 *
 * Recuerda que controles ya estaban deshabilitados antes del envio y solo
 * rehabilita los que el propio bloqueo apago. La version anterior hacia
 * `control.disabled = false` sobre todos, de modo que un control deshabilitado
 * a proposito volvia habilitado al terminar de guardar.
 */
export function setSaving(form, value, { submitButton = null, savingLabel = 'Guardando…' } = {}) {
    form.setAttribute('aria-busy', String(value));
    const controls = form.querySelectorAll('button, input, select, textarea');

    if (value) {
        const previous = new Set();
        controls.forEach((control) => {
            if (control.disabled) previous.add(control);
            control.disabled = true;
        });
        disabledBefore.set(form, previous);
        if (submitButton) {
            submitButton.dataset.label = submitButton.textContent;
            submitButton.textContent = savingLabel;
        }
        return;
    }

    const previous = disabledBefore.get(form) ?? new Set();
    controls.forEach((control) => { control.disabled = previous.has(control); });
    disabledBefore.delete(form);
    if (submitButton?.dataset.label) {
        submitButton.textContent = submitButton.dataset.label;
        delete submitButton.dataset.label;
    }

    // Los CRUD actuales cierran el dialogo solo despues de una respuesta 2xx.
    // Si seguimos dentro del dialogo fue 422/409/500/red y el borrador debe
    // sobrevivir. Si ya se cerro, el guardado termino bien y se elimina.
    clearFormDraftAfterSuccessfulClose(form);
}

/**
 * Guarda de reentrada para envios. La bandera se levanta de forma sincrona en
 * la primera instruccion, antes de cualquier await, de modo que dos clics o dos
 * Enter en el mismo turno no pueden producir dos peticiones.
 */
export function createSubmitGuard() {
    let busy = false;
    return {
        get busy() { return busy; },
        async run(operation) {
            if (busy) return undefined;
            busy = true;
            try {
                return await operation();
            } finally {
                busy = false;
            }
        },
    };
}
