// Restricciones del campo de identificacion segun el tipo elegido.
//
// Los patrones son EXACTAMENTE los que aplica el backend en
// Application/Controller/*Controller.php::validarIdentificacion():
//
//   CEDULA_FISICA | CEDULA_JURIDICA | DIMEX   ->  /^[0-9][0-9 -]*$/
//   NITE | PASAPORTE                          ->  /^[A-Za-z0-9][A-Za-z0-9 -]*$/
//
// No se anaden longitudes propias. El servidor solo exige entre 1 y 250
// caracteres, asi que imponer aqui "nueve digitos" rechazaria valores que el
// backend acepta y el formulario dejaria de reflejar el contrato real.

/** Tipos cuyo numero solo admite digitos. */
export const TIPOS_NUMERICOS = ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'];

// El guion va escapado a proposito. El atributo `pattern` de HTML se compila
// con la bandera `v`, bajo la cual un guion literal dentro de una clase es un
// error de sintaxis; y ante un patron que no compila el navegador NO avisa:
// descarta el atributo y deja de validar por completo. Escrito como
// '[0-9][0-9 -]*' el campo aceptaba letras en una cedula sin protestar.
// Con el escape el significado es identico al del backend.
const PATRON_NUMERICO = '[0-9][0-9 \\-]*';
const PATRON_ALFANUMERICO = '[A-Za-z0-9][A-Za-z0-9 \\-]*';

/** Ejemplo y explicacion por tipo. Son orientativos, no restricciones extra. */
const GUIA = {
    CEDULA_FISICA: { ejemplo: '1-1111-1111', formato: 'Nueve dígitos, con o sin guiones.' },
    CEDULA_JURIDICA: { ejemplo: '3-101-111111', formato: 'Diez dígitos, con o sin guiones.' },
    DIMEX: { ejemplo: '111111111111', formato: 'Solo dígitos.' },
    NITE: { ejemplo: '1111111111', formato: 'Letras y dígitos, sin símbolos.' },
    PASAPORTE: { ejemplo: 'AB123456', formato: 'Letras y dígitos, sin símbolos.' },
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
        // El mensaje que vera el usuario si el patron falla.
        titulo: tipo === ''
            ? ''
            : numerico
                ? 'Use únicamente dígitos, espacios o guiones.'
                : 'Use únicamente letras, dígitos, espacios o guiones.',
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
    if (hint) hint.textContent = regla.ayuda;

    return regla;
}
