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
