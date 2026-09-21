// Restricciones del campo de identificacion segun el tipo elegido.
//
// Los patrones son EXACTAMENTE los que aplica el backend en
// Application/Controller/*Controller.php::validarIdentificacion():
//
// Las longitudes se basan en los formatos oficiales consultados del TSE y del
// Ministerio de Hacienda. El backend repite estas reglas; el navegador solo
// ofrece una primera ayuda y nunca sustituye la validación del servidor.

/** Tipos cuyo numero solo admite digitos. */
export const TIPOS_NUMERICOS = ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'];

// El guion va escapado a proposito. El atributo `pattern` de HTML se compila
// con la bandera `v`, bajo la cual un guion literal dentro de una clase es un
// error de sintaxis; y ante un patron que no compila el navegador NO avisa:
// descarta el atributo y deja de validar por completo. Escrito como
// '[0-9][0-9 -]*' el campo aceptaba letras en una cedula sin protestar.
// Con el escape el significado es identico al del backend.
const PATRON_NUMERICO = '[0-9][0-9 \\-]*';
const PATRON_ALFANUMERICO = '[A-Za-z0-9][A-Za-z0-9]*';

/** Ejemplo y explicacion por tipo. Son orientativos, no restricciones extra. */
const GUIA = {
    CEDULA_FISICA: { ejemplo: '1-1111-1111', formato: '9 dígitos; se aceptan guiones al escribir.', minLength: 9, maxLength: 12 },
    CEDULA_JURIDICA: { ejemplo: '3-101-111111', formato: '10 dígitos; se aceptan guiones al escribir.', minLength: 10, maxLength: 13 },
    DIMEX: { ejemplo: '111111111111', formato: '11 o 12 dígitos, sin guiones.', minLength: 11, maxLength: 12 },
    NITE: { ejemplo: '1111111111', formato: '10 dígitos, sin guiones.', minLength: 10, maxLength: 10 },
    PASAPORTE: { ejemplo: 'AB1234567', formato: 'Hasta 9 letras y dígitos, sin símbolos.', minLength: 1, maxLength: 9 },
};

/**
 * Regla aplicable al numero para un tipo dado.
 * Con tipo vacio devuelve la regla permisiva: el usuario aun no eligio.
 */
export function reglaIdentificacion(tipo) {
    const numerico = TIPOS_NUMERICOS.includes(tipo);
    const guia = GUIA[tipo];
    return {
        numerico,
        pattern: tipo === '' ? null : (numerico ? PATRON_NUMERICO : PATRON_ALFANUMERICO),
        inputMode: numerico ? 'numeric' : 'text',
        placeholder: guia?.ejemplo ?? '',
        minLength: guia?.minLength ?? 1,
        maxLength: guia?.maxLength ?? 12,
        // El mensaje que vera el usuario si el patron falla.
        titulo: tipo === ''
            ? ''
            : numerico
                ? 'Use únicamente dígitos, espacios o guiones.'
                : 'Use únicamente letras y dígitos, sin símbolos.',
        ayuda: tipo === ''
            ? 'Elija primero el tipo de identificación.'
            : `${guia?.formato ?? ''} Se guarda sin espacios ni guiones.`.trim(),
    };
}

/**
 * Aplica la regla al control del numero.
 * `hint` es el elemento donde se explica el formato; puede faltar.
 */
export function aplicarRestriccionIdentificacion(numero, tipo, { hint = null } = {}) {
    const regla = reglaIdentificacion(tipo);

    if (regla.pattern) numero.setAttribute('pattern', regla.pattern);
    else numero.removeAttribute('pattern');

    if (regla.titulo) numero.setAttribute('title', regla.titulo);
    else numero.removeAttribute('title');

    numero.inputMode = regla.inputMode;
    numero.placeholder = regla.placeholder;
    numero.minLength = regla.minLength;
    numero.maxLength = regla.maxLength;
    if (hint) hint.textContent = regla.ayuda;

    return regla;
}
