// Traduccion de los errores 422 del backend a campos del formulario. Puro.
//
// El contrato PHP responde { message, errors: { campo: mensaje } } y las claves
// pueden venir indexadas cuando el campo es una lista, por ejemplo
// "fincas.0.nombre". El formulario de productores captura las fincas en la
// lista editable del componente "Agregar finca" (Tramo C), así que esas claves
// se colapsan sobre el campo "fincas" y el mensaje aparece en su espacio.

/** Colapsa "fincas.0.nombre" -> "fincas" para los prefijos indicados. */
export function normalizeFieldKey(key, { collapsePrefixes = [] } = {}) {
    for (const prefix of collapsePrefixes) {
        if (key === prefix || key.startsWith(`${prefix}.`)) return prefix;
    }
    return key;
}

/**
 * Lista ordenada de { field, message } lista para pintar.
 * Cuando varias claves colapsan en el mismo campo gana la primera: el resultado
 * no puede depender del orden en que PHP serializo el objeto.
 */
export function mapFieldErrors(errors, { collapsePrefixes = [] } = {}) {
    const entries = [];
    const seen = new Set();
    for (const [key, message] of Object.entries(errors ?? {})) {
        const field = normalizeFieldKey(key, { collapsePrefixes });
        if (seen.has(field)) continue;
        seen.add(field);
        entries.push({ field, message: String(message) });
    }
    return entries;
}

export function firstErrorField(entries) {
    return entries[0]?.field ?? null;
}

/**
 * Primer control invalido segun un predicado. Se separa de `:invalid` a
 * proposito: el selector CSS no se puede evaluar sin un motor real, mientras
 * que esta funcion si es comprobable.
 */
export function pickFirstInvalid(controls, isInvalid) {
    return [...controls].find((control) => isInvalid(control)) ?? null;
}

/**
 * Mensaje en espanol para un control que el navegador considera invalido.
 *
 * No se usa `control.validationMessage` porque depende del idioma del navegador
 * ("Completa este campo", "Please fill out this field") y no concuerda con el
 * tono de los mensajes que devuelve el backend. Aqui se redacta a partir del
 * motivo concreto para que la validacion del cliente y la del servidor se lean
 * igual.
 *
 * @param {{validity: object, minLength?: number, maxLength?: number, type?: string,
 *          validationMessage?: string}} control
 */
export function describeValidity(control) {
    const v = control?.validity;
    if (!v || v.valid) return '';

    if (v.valueMissing) return 'Este campo es obligatorio.';
    if (v.typeMismatch) {
        return control.type === 'email'
            ? 'Ingrese un correo electrónico válido.'
            : 'El formato del valor no es válido.';
    }
    if (v.tooShort) {
        return `Debe contener al menos ${control.minLength} caracteres.`;
    }
    if (v.tooLong) {
        return `No puede superar ${control.maxLength} caracteres.`;
    }
    // El control puede llevar en `title` la regla concreta (por ejemplo la del
    // numero de identificacion segun su tipo); es mas util que un texto generico.
    if (v.patternMismatch) return control.title || 'El formato del valor no es válido.';
    if (v.rangeUnderflow || v.rangeOverflow || v.stepMismatch) {
        return 'El valor está fuera del rango permitido.';
    }
    if (v.badInput) return 'El valor introducido no se puede interpretar.';
    // Motivo desconocido: se prefiere el mensaje del navegador a no decir nada.
    return control.validationMessage || 'Revise este campo.';
}

/** ¿El formulario tiene dónde mostrar el error de esta clave? (un control con ese nombre o un espacio data-…-error). */
function tieneLugar(form, clave) {
    const nombres = [clave, clave.split('.').pop()];
    if (nombres.some((nombre) => form.elements?.namedItem?.(nombre))) return true;
    return [...form.querySelectorAll('*')].some((nodo) => [...nodo.attributes].some((atributo) =>
        (atributo.name === 'data-error-for' || /^data-[a-z-]+-error$/.test(atributo.name)) && nombres.includes(atributo.value)));
}

/**
 * Mensajes de error que el formulario no tiene dónde pintar: un dato que no está en pantalla (por ejemplo el lote o
 * un campo que solo valida el servidor). Sin esto la persona solo veía "Revise los campos indicados." sin saber qué.
 */
export function erroresSinLugar(form, errors) {
    return Object.entries(errors ?? {}).filter(([clave]) => !tieneLugar(form, clave)).map(([, mensaje]) => String(mensaje));
}

/** Los pinta en una línea de aviso antes de las acciones del formulario (o la limpia si no hay). */
export function mostrarErroresSinLugar(form, errors) {
    const mensajes = erroresSinLugar(form, errors);
    let aviso = form.querySelector('[data-errores-generales]');
    if (mensajes.length === 0) {
        if (aviso) aviso.textContent = '';
        return mensajes;
    }
    if (!aviso) {
        aviso = document.createElement('p');
        aviso.className = 'field-error field__error';
        aviso.setAttribute('role', 'alert');
        aviso.dataset.erroresGenerales = '';
        const acciones = form.querySelector('[type="submit"]')?.parentElement;
        if (acciones && acciones !== form) acciones.before(aviso);
        else form.append(aviso);
    }
    aviso.textContent = mensajes.join(' ');
    return mensajes;
}
