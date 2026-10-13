// Validación del navegador (required, min, max, step, type) leída de una sola vez.
//
// Los formularios llevan `novalidate`, así que el navegador no marca nada solo. Antes cada pantalla
// esperaba al servidor (un viaje por error) o usaba `reportValidity()`, que muestra UNA burbuja a la vez.
// `erroresNativos` devuelve todos los campos inválidos juntos, con un mensaje en español, listos para
// pintarse con el mismo método que ya usa cada pantalla para los errores del servidor.

import { describeValidity } from './field-errors.js?v=errores-1';

/**
 * Mensaje de un control inválido. Igual que `describeValidity`, pero con el motivo concreto en los campos
 * numéricos, de fecha, de enlace y de lista (el texto genérico "valor fuera del rango" no dice qué corregir).
 */
export function mensajeCampo(control) {
    const v = control?.validity;
    if (!v || v.valid) return '';
    if (v.customError) return control.validationMessage;
    const tipo = String(control.type ?? '');

    if (v.valueMissing && String(control.tagName ?? '').toLowerCase() === 'select') return 'Elige una opción.';
    if (tipo === 'number') {
        if (v.badInput) return 'Escribe solo números.';
        if (v.rangeUnderflow) return Number(control.min) === 0 ? 'No puede ser negativo.' : `No puede ser menor que ${control.min}.`;
        if (v.rangeOverflow) return `No puede ser mayor que ${control.max}.`;
        if (v.stepMismatch) {
            const paso = parseFloat(control.step);
            return !paso || Number.isInteger(paso) ? 'Escribe un número entero, sin decimales.' : 'Usa como máximo un decimal.';
        }
    }
    if (tipo === 'date') {
        if (v.badInput) return 'Escribe una fecha válida.';
        if (v.rangeOverflow) return 'La fecha no puede ser futura.';
        if (v.rangeUnderflow) return `La fecha no puede ser anterior a ${control.min}.`;
    }
    if (tipo === 'url' && v.typeMismatch) return 'Escribe un enlace válido que empiece con https://.';
    return describeValidity(control);
}

/**
 * Todos los controles inválidos del formulario como `{ nombre: mensaje }` (el primer mensaje por nombre).
 * Ignora los deshabilitados y los ocultos: el navegador tampoco los valida.
 */
export function erroresNativos(form) {
    const errores = {};
    for (const control of form?.elements ?? []) {
        if (!control.name || !control.willValidate || control.validity?.valid) continue;
        if (control.hidden || control.closest?.('[hidden]')) continue;
        errores[control.name] ??= mensajeCampo(control);
    }
    return errores;
}

/**
 * ¿Esta tecla no puede formar parte de un número en este campo? Un `<input type="number">` deja escribir
 * "e", "+" y "-" (notación científica y signos); los precios, pesos y cantidades no los usan. Sin decimales
 * (`step` entero) tampoco se admite el punto ni la coma. Puro: recibe la tecla y el campo.
 */
export function teclaBloqueada(tecla, campo) {
    if (campo?.type !== 'number' || tecla.length !== 1) return false;
    if (/[eE+]/.test(tecla)) return true;
    if (tecla === '-') return campo.min === '' || Number(campo.min) >= 0;
    if (tecla === '.' || tecla === ',') {
        const paso = campo.step === 'any' ? 0.1 : (parseFloat(campo.step) || 1);
        return Number.isInteger(paso);
    }
    return false;
}

// ponytail: un solo oyente global al importar el módulo; los módulos son únicos por URL, así que no se duplica.
if (typeof document !== 'undefined') {
    document.addEventListener('keydown', (evento) => {
        if (evento.ctrlKey || evento.metaKey || evento.altKey) return;
        if (teclaBloqueada(evento.key, evento.target)) evento.preventDefault();
    });
}
