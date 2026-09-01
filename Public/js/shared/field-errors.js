// Traduccion de los errores 422 del backend a campos del formulario. Puro.
//
// El contrato PHP responde { message, errors: { campo: mensaje } } y las claves
// pueden venir indexadas cuando el campo es una lista, por ejemplo
// "fincas.0.nombre". El formulario de productores captura todas las fincas en un
// unico textarea llamado "fincas", asi que esas claves deben colapsarse o el
// error no se pinta en ningun sitio.

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
