// El punto del mapa es opcional, pero si se marca debe caer en la misma provincia que la dirección escrita.
// No se exige más (cantón, pueblo): la casa o la finca puede estar en un pueblo que el mapa nombra distinto.
// La provincia del punto sale de Nominatim (buscarDireccionPorCoordenadas). Si el servicio no responde, no se
// bloquea el formulario: la validación es una ayuda, no debe impedir guardar.
import { buscarDireccionPorCoordenadas } from './finca-mapa.js?v=coincide-1';

/** "Provincia de San José" y "san jose" son la misma provincia. Puro. */
export function normalizarProvincia(texto) {
    return String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
        .replace(/^provincia\s+de\s+/, '').trim();
}

/** Mensaje de error si la provincia del punto no es la escrita; null si coinciden o falta un dato. Puro. */
export function desajusteProvincia(provinciaDelPunto, provinciaEscrita) {
    const delPunto = normalizarProvincia(provinciaDelPunto);
    const escrita = normalizarProvincia(provinciaEscrita);
    if (!delPunto || !escrita || delPunto === escrita) return null;
    return `El punto del mapa está en ${provinciaDelPunto}, pero la dirección dice ${provinciaEscrita}. Corrige la provincia o mueve el punto.`;
}

/** Provincia de un punto (para emparejar fletes sin punto en el mapa); null si el servicio no responde. */
export async function provinciaDePunto(punto, { buscar = buscarDireccionPorCoordenadas } = {}) {
    try {
        return (await buscar({ latitud: punto.latitud, longitud: punto.longitud }))?.provincia || null;
    } catch {
        return null;
    }
}

/**
 * Revisa una dirección con punto ({provincia, latitud, longitud}). Devuelve el mensaje de error o null.
 * Sin punto, sin provincia o sin servicio de mapas: null (no bloquea).
 */
export async function validarPuntoConDireccion(direccion, { buscar = buscarDireccionPorCoordenadas } = {}) {
    if (!direccion || direccion.latitud == null || direccion.longitud == null || !direccion.provincia) return null;
    try {
        const encontrada = await buscar({ latitud: direccion.latitud, longitud: direccion.longitud });
        return desajusteProvincia(encontrada?.provincia, direccion.provincia);
    } catch (error) {
        return error?.kind === 'outside-costa-rica' ? error.message : null;
    }
}
