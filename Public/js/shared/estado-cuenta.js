// Estados de la cuenta con sesión y cómo se lee un fallo de api/v1/actividad.
//
// Módulo sin dependencias: public-ui.js lo importa siempre, aun sin sesión,
// mientras que shared/cuenta.js (que trae api.js) solo se descarga con sesión.
// Es la única definición del contrato en el navegador; login.js, el encabezado
// y las páginas privadas clasifican igual el mismo error.

export const ESTADO_CUENTA = Object.freeze({
    ANONIMA: 'ANONIMA',
    VERIFICANDO: 'VERIFICANDO',
    REGISTRO_INCOMPLETO: 'REGISTRO_INCOMPLETO',
    COMPLETA: 'CUENTA_COMPLETA',
    /** 403 u otro 409: la cuenta existe pero no se puede usar (inactiva, correo en dos Personas). Reintentar no lo arregla. */
    CONFLICTO: 'CONFLICTO_CUENTA',
    /** Red, tiempo agotado o 5xx: puede arreglarse reintentando. */
    ERROR: 'ERROR_VERIFICACION',
});

/** Mismo valor que SupabaseActorResolver::CODIGO_REGISTRO_INCOMPLETO. */
export const CODIGO_REGISTRO_INCOMPLETO = 'REGISTRO_INCOMPLETO';

/** Solo el 409 con el código estable; un 409 de otro conflicto no es "registro incompleto". */
export function esRegistroIncompleto(error) {
    return error?.status === 409 && error?.data?.codigo === CODIGO_REGISTRO_INCOMPLETO;
}

/** Estado que corresponde a un fallo al consultar la cuenta con una sesión iniciada. */
export function estadoPorError(error) {
    if (esRegistroIncompleto(error)) return ESTADO_CUENTA.REGISTRO_INCOMPLETO;
    // 401: Supabase ya no acepta la sesión (api.js intentó renovarla una vez).
    if (error?.status === 401) return ESTADO_CUENTA.ANONIMA;
    if (error?.status === 403 || error?.status === 409) return ESTADO_CUENTA.CONFLICTO;
    return ESTADO_CUENTA.ERROR;
}

/** El encabezado publica el estado verificado en <html data-tc-cuenta>; las páginas lo leen aquí. */
export function estadoPublicado(documento = globalThis.document) {
    return documento?.documentElement?.dataset?.tcCuenta ?? '';
}
