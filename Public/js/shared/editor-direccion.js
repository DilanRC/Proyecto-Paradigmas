// Editor de dirección con mapa: cascada provincia/cantón/distrito/pueblo, señas y
// punto exacto. Si el usuario marca un punto en el mapa, completa la dirección.
// Usa los atributos data-farm-* del bloque de dirección de Mi panel (fincas).
import { conectarDireccion } from './direccion.js';
import { buscarDireccionPorCoordenadas, crearSelectorPuntoFinca } from './finca-mapa.js?v=oferta-1';

/**
 * @param {HTMLElement} raiz bloque con los campos data-farm-province, -canton,
 *   -district, -town, -town-list, -directions y el contenedor data-farm-map.
 * @param {object|null} inicial dirección guardada ({provincia, canton, ..., latitud, longitud}).
 * @param {object} textosMapa opciones de crearSelectorPuntoFinca (titulo, opcional, lugar).
 */
export function montarEditorDireccion(raiz, inicial = null, textosMapa = {}) {
    const campo = (nombre) => raiz.querySelector(`[data-farm-${nombre}]`);
    const [provincia, canton, distrito, pueblo, lista, senas, montaje] =
        ['province', 'canton', 'district', 'town', 'town-list', 'directions', 'map'].map(campo);
    if (![provincia, canton, distrito, pueblo, lista, senas, montaje].every(Boolean)) return null;

    const direccion = conectarDireccion({ provincia, canton, distrito, pueblo, listaPueblos: lista });
    direccion.aplicar(inicial ?? {});
    senas.value = inicial?.senas ?? '';

    let turno = 0;
    let aborto = null;
    const completarDesdePunto = async (punto) => {
        aborto?.abort();
        const miTurno = ++turno;
        if (!punto) return;
        const controlador = new AbortController();
        aborto = controlador;
        try {
            const encontrada = await buscarDireccionPorCoordenadas(punto, { signal: controlador.signal });
            if (miTurno !== turno || !raiz.isConnected) return;
            direccion.aplicar({ ...encontrada, pueblo: encontrada.pueblo || pueblo.value });
        } catch {
            // El usuario conserva el punto y completa la dirección a mano.
        } finally {
            if (aborto === controlador) aborto = null;
        }
    };
    const mapa = crearSelectorPuntoFinca({
        mount: montaje,
        puntoInicial: { latitud: inicial?.latitud ?? null, longitud: inicial?.longitud ?? null },
        onPuntoChange: completarDesdePunto,
        ...textosMapa,
    });

    return {
        /** Dirección tal como la espera la API; latitud y longitud son null sin punto. */
        leer() {
            const punto = mapa.obtenerPunto?.() ?? null;
            return {
                provincia: provincia.value.trim(),
                canton: canton.value.trim(),
                distrito: distrito.value.trim(),
                pueblo: pueblo.value.trim() || null,
                senas: senas.value.trim() || null,
                latitud: punto?.latitud ?? null,
                longitud: punto?.longitud ?? null,
            };
        },
        destruir() {
            turno += 1;
            aborto?.abort();
            aborto = null;
            mapa.destruir?.();
        },
    };
}
