// Registro pendiente de confirmación de correo.
//
// Si Supabase exige confirmar el correo, el alta no puede terminar en el mismo
// momento: no hay sesión para crear la Persona. Los datos del formulario (sin
// contraseñas) quedan como borrador en esta pestaña; al confirmar el correo e
// iniciar sesión, el login termina el registro con ese borrador y la persona
// no tiene que volver a escribir nada.

import { request } from './api.js';
import { buildRegistrationSummary } from './business-rules.js';

export const REGISTRATION_DRAFT_KEY = 'tindercows:registration-draft';

/** Aviso neutro: no revela si el correo ya tenía cuenta. */
export const CONFIRMACION_PENDIENTE_MESSAGE = 'Si el correo es nuevo, te enviamos un enlace para confirmar tu cuenta. '
    + 'Confírmalo y luego inicia sesión aquí: terminaremos tu registro automáticamente. '
    + 'Si ya tenías cuenta, inicia sesión directamente.';

export function leerBorradorRegistro(storage = globalThis.sessionStorage) {
    try { return JSON.parse(storage?.getItem(REGISTRATION_DRAFT_KEY) || 'null'); } catch { return null; }
}

/**
 * Crea la Persona (como Comprador) con el borrador guardado al registrarse.
 * Devuelve false si no hay borrador para ese correo; el login cae entonces al
 * formulario de registro, como antes.
 */
export async function completarRegistroPendiente(email, { storage = globalThis.sessionStorage, requestImpl = request } = {}) {
    const borrador = leerBorradorRegistro(storage);
    const correo = String(borrador?.persona?.correoElectronico ?? '').trim().toLowerCase();
    if (!borrador || correo === '' || correo !== String(email ?? '').trim().toLowerCase()) return false;
    const { persona } = buildRegistrationSummary(borrador);
    await requestImpl('api/v1/registro', {
        method: 'POST',
        body: JSON.stringify({ persona, capacidades: ['COMPRADOR'], fincas: [] }),
    });
    storage?.removeItem(REGISTRATION_DRAFT_KEY);
    return true;
}
