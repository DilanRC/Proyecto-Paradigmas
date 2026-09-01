// Cascada provincia -> canton -> distrito -> pueblo sobre el DOM.
//
// Los tres primeros son la Division Territorial Administrativa y forman un
// catalogo cerrado: son <select>. "Pueblo" no es una unidad administrativa sino
// lenguaje comun, y el catalogo de centros poblados no pretende ser exhaustivo,
// asi que es texto libre con sugerencias: se escribe y van apareciendo las
// localidades de ese distrito.

import {
    cantones, codigoDistrito, distritos, llenarDatalist, llenarSelect, provincias,
} from './territorio.js';

// Las 13309 localidades pesan unas 200 KB frente a las 22 KB de la DTA. Cargarlas
// en cada visita, para un campo que muchas direcciones ni siquiera usan, seria
// pagar diez veces el catalogo que si hace falta siempre. Se piden la primera
// vez que se elige un distrito, y una sola vez por pagina.
let promesaPoblados = null;
function cargarPoblados() {
    promesaPoblados ??= import('./poblados.js');
    return promesaPoblados;
}

/**
 * Conecta los cuatro campos de una direccion.
 *
 * @param {{provincia: HTMLSelectElement, canton: HTMLSelectElement,
 *          distrito: HTMLSelectElement, pueblo?: HTMLInputElement,
 *          listaPueblos?: HTMLDataListElement}} campos
 * @returns {{aplicar: (direccion?: object) => void}}
 */
export function conectarDireccion({
    provincia, canton, distrito, pueblo = null, listaPueblos = null,
}) {
    let sugerenciasDe = null;

    function refrescarCantones({ conservar = false } = {}) {
        const elegido = conservar ? canton.value : '';
        llenarSelect(canton, cantones(provincia.value), elegido, { vacio: 'Seleccione un cantón' });
        canton.disabled = provincia.value === '';
        refrescarDistritos({ conservar });
    }

    function refrescarDistritos({ conservar = false } = {}) {
        const elegido = conservar ? distrito.value : '';
        llenarSelect(distrito, distritos(provincia.value, canton.value), elegido,
            { vacio: 'Seleccione un distrito' });
        distrito.disabled = canton.value === '';
        refrescarPueblo({ conservar });
    }

    function refrescarPueblo({ conservar = false } = {}) {
        if (!pueblo) return;
        if (!conservar) pueblo.value = '';
        sugerenciasDe = null;
        llenarDatalist(listaPueblos, []);
        pueblo.disabled = distrito.value === '';
        if (distrito.value !== '') sugerir();
    }

    /**
     * Sugiere localidades del distrito elegido segun lo tecleado.
     *
     * Cada llamada comprueba que el distrito no haya cambiado antes de pintar:
     * la carga del catalogo es asincrona y, sin esa comprobacion, elegir dos
     * distritos seguidos dejaria que la respuesta lenta del primero pintara sus
     * localidades sobre el segundo.
     */
    async function sugerir() {
        if (!pueblo || !listaPueblos) return;
        const codigo = codigoDistrito(provincia.value, canton.value, distrito.value);
        if (!codigo) { llenarDatalist(listaPueblos, []); return; }
        const consulta = pueblo.value;
        const { buscarPoblados } = await cargarPoblados();
        if (codigoDistrito(provincia.value, canton.value, distrito.value) !== codigo) return;
        if (pueblo.value !== consulta) return;
        sugerenciasDe = codigo;
        llenarDatalist(listaPueblos, buscarPoblados(codigo, consulta));
    }

    provincia.addEventListener('change', () => refrescarCantones());
    canton.addEventListener('change', () => refrescarDistritos());
    distrito.addEventListener('change', () => refrescarPueblo());
    pueblo?.addEventListener('input', () => { if (sugerenciasDe) sugerir(); });

    /** Carga una direccion guardada, conservando valores fuera del catalogo. */
    function aplicar({ provincia: p = '', canton: c = '', distrito: d = '', pueblo: pu = '' } = {}) {
        llenarSelect(provincia, provincias(), p, { vacio: 'Seleccione una provincia' });
        llenarSelect(canton, cantones(p), c, { vacio: 'Seleccione un cantón' });
        canton.disabled = provincia.value === '';
        llenarSelect(distrito, distritos(p, c), d, { vacio: 'Seleccione un distrito' });
        distrito.disabled = canton.value === '';
        if (pueblo) {
            pueblo.value = pu ?? '';
            pueblo.disabled = distrito.value === '';
            sugerenciasDe = null;
            llenarDatalist(listaPueblos, []);
            if (distrito.value !== '') sugerir();
        }
    }

    return { aplicar };
}
