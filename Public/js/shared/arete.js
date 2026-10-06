// Arete SENASA (DIIO): 13 dígitos = 188 (Costa Rica) + dígito de control + provincia (01 a 07) + 7 del correlativo.
// Misma regla que AnimalValidacionService::arete() en PHP: si cambia allá, cambia aquí (la prueba las compara).

export const PROVINCIAS_ARETE = ['01', '02', '03', '04', '05', '06', '07'];
export const MENSAJE_ARETE = 'El arete tiene 13 dígitos: 188, un dígito de control, la provincia (01 a 07) y 7 dígitos del correlativo.';

/** Solo los dígitos de lo escrito (se aceptan espacios y guiones). */
export function digitosArete(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}

/** Máscara de escritura: "188 0 01 0002345" (hasta 13 dígitos). */
export function formatearArete(valor) {
    const d = digitosArete(valor).slice(0, 13);
    return [d.slice(0, 3), d.slice(3, 4), d.slice(4, 6), d.slice(6, 13)].filter(Boolean).join(' ');
}

/** Mensaje de error, o null si está vacío (es opcional) o es válido. */
export function errorArete(valor) {
    if (String(valor ?? '').trim() === '') return null;
    const d = String(valor).replace(/[\s-]+/g, '');
    const valido = /^\d{13}$/.test(d) && d.startsWith('188') && PROVINCIAS_ARETE.includes(d.slice(4, 6));
    return valido ? null : MENSAJE_ARETE;
}
