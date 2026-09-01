// Restriccion del campo de telefono.
//
// Refleja EXACTAMENTE lo que valida el backend en
// Application/Controller/*Controller.php::validarTelefono(), identico en
// productores, compradores y transportistas:
//
//   - entre 1 y 20 caracteres;
//   - /^\+?[0-9 ()-]+$/  : un mas opcional al inicio, luego digitos, espacios,
//     parentesis y guiones;
//   - entre 8 y 15 digitos, contando solo digitos.
//
// El servidor guarda el numero sin espacios, parentesis ni guiones, pero
// conserva el prefijo. Aqui no se normaliza nada: el cuerpo se envia tal como se
// escribe y el backend decide, para no divergir del contrato.
//
// Antes de esto el campo era `type="tel"` a secas, que en los navegadores no
// valida absolutamente nada: admitia letras y cualquier cantidad de digitos, y
// el error solo aparecia al volver del servidor.

/**
 * Patron para el atributo `pattern`.
 *
 * El adelanto `(?=...)` cuenta los digitos ignorando los separadores, que es lo
 * que hace el backend con preg_replace('/\D+/', ''). No se puede expresar con
 * una clase suelta porque los digitos no van seguidos.
 *
 * Parentesis y guion van escapados a proposito. El atributo `pattern` se compila
 * con la bandera `v`, bajo la cual son caracteres reservados dentro de una clase
 * y su presencia sin escapar es un error de sintaxis; ante un patron que no
 * compila el navegador no avisa, descarta el atributo y deja de validar.
 */
export const PATRON_TELEFONO = '(?=(?:\\D*\\d){8,15}\\D*$)\\+?[0-9 \\(\\)\\-]+';

/** Mensaje que ve quien escribe un numero que no encaja. */
export const TITULO_TELEFONO = 'Use entre 8 y 15 dígitos. Se admiten prefijo +, espacios, paréntesis y guiones.';

/**
 * Mismo juicio que el backend, en forma comprobable. Puro.
 *
 * Se escribe aparte del patron para poder contrastar los dos contra la misma
 * tabla de casos: si el patron y esta funcion discrepan, uno de los dos dejo de
 * reflejar el contrato.
 */
export function telefonoValido(valor) {
    const texto = String(valor ?? '');
    if (texto.length < 1 || texto.length > 20) return false;
    if (!/^\+?[0-9 ()-]+$/.test(texto)) return false;
    const digitos = (texto.match(/\d/g) ?? []).length;
    return digitos >= 8 && digitos <= 15;
}

/**
 * Deja el texto con solo los caracteres que admite el contrato. Puro.
 *
 * El `+` se conserva unicamente en la primera posicion, que es donde lo admite
 * el backend; escrito en medio no es un prefijo y se descarta.
 */
export function sanearTelefono(valor) {
    const texto = String(valor ?? '');
    const prefijo = texto.startsWith('+') ? '+' : '';
    return prefijo + texto.slice(prefijo.length).replace(/[^0-9 ()-]/g, '');
}

/**
 * Impide teclear caracteres que el campo no admite.
 *
 * El atributo `pattern` sigue siendo la validacion de verdad; esto es comodidad;
 * sin ello se puede escribir "aaaaaaaa" y el campo no protesta hasta enviar, que
 * es tarde para darse cuenta. Al filtrar en el momento, el campo solo llega a
 * contener lo que el backend puede aceptar.
 *
 * El cursor se recoloca descontando los caracteres eliminados por delante de el.
 * Sin ese ajuste, corregir un digito en medio de un numero manda el cursor al
 * final en cada pulsacion.
 */
export function aplicarRestriccionTelefono(input) {
    input.addEventListener('input', () => {
        const antes = input.value;
        const despues = sanearTelefono(antes);
        if (antes === despues) return;
        const cursor = input.selectionStart ?? despues.length;
        const eliminados = cursor - sanearTelefono(antes.slice(0, cursor)).length;
        input.value = despues;
        const destino = Math.max(0, cursor - eliminados);
        input.setSelectionRange(destino, destino);
    });
}
