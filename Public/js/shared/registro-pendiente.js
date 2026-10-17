// Registro pendiente de confirmación de correo.
//
// Si Supabase exige confirmar el correo, el alta no puede terminar en el mismo
// momento: no hay sesión para crear la Persona. Los datos del formulario (sin
// contraseñas) quedan como borrador en esta pestaña; al confirmar el correo e
// iniciar sesión, el login termina el registro con ese borrador y la persona
// no tiene que volver a escribir nada.

import { request } from './api.js';
import { buildRegistrationSummary, normalizeCapabilities } from './business-rules.js?v=business-2';

export const REGISTRATION_DRAFT_KEY = 'tindercows:registration-draft';

/** Aviso neutro: no revela si el correo ya tenía cuenta. */
export const CONFIRMACION_PENDIENTE_MESSAGE = 'Si el correo es nuevo, te enviamos un enlace para confirmar tu cuenta. '
    + 'Confírmalo y luego inicia sesión aquí: terminaremos tu registro automáticamente. '
    + 'Si ya tenías cuenta, inicia sesión directamente.';

export function leerBorradorRegistro(storage = globalThis.sessionStorage) {
    try { return JSON.parse(storage?.getItem(REGISTRATION_DRAFT_KEY) || 'null'); } catch { return null; }
}

/**
 * Crea la Persona (siempre Comprador, más el rol elegido) con el borrador
 * guardado al registrarse: las fincas si eligió Vendedor y el vehículo si eligió
 * Transportista. Devuelve false si no hay borrador para ese correo; el login cae
 * entonces al formulario de registro, como antes.
 */
export async function completarRegistroPendiente(email, { storage = globalThis.sessionStorage, requestImpl = request } = {}) {
    const borrador = leerBorradorRegistro(storage);
    const correo = String(borrador?.persona?.correoElectronico ?? '').trim().toLowerCase();
    if (!borrador || correo === '' || correo !== String(email ?? '').trim().toLowerCase()) return false;
    const { persona, fincas } = buildRegistrationSummary(borrador);
    const capacidades = normalizeCapabilities(['COMPRADOR', ...(borrador.capacidades ?? [])]);
    await requestImpl('api/v1/registro', {
        method: 'POST',
        body: JSON.stringify({ persona, capacidades, fincas: capacidades.includes('PRODUCTOR') ? fincas : [] }),
    });
    storage?.removeItem(REGISTRATION_DRAFT_KEY);
    if (capacidades.includes('TRANSPORTISTA') && borrador.vehiculo) {
        // La cuenta ya existe: si el vehículo falla se agrega desde Mi panel.
        try {
            await requestImpl('api/v1/mi-vehiculos', { method: 'POST', body: JSON.stringify(borrador.vehiculo) });
        } catch { /* sigue el login */ }
    }
    return true;
}
