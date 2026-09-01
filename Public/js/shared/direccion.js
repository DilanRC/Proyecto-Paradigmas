// Cascada provincia -> canton -> distrito -> pueblo sobre el DOM.
//
// Los tres primeros son la Division Territorial Administrativa y forman un
// catalogo cerrado: son <select>. "Pueblo" no es una unidad administrativa sino
// lenguaje comun, y el catalogo de centros poblados no pretende ser exhaustivo,
// asi que es texto libre con sugerencias: se escribe y van apareciendo las
// localidades de ese distrito.

import {
    cantones, codigoDistrito, distritos, llenarSelect, provincias,
} from './territorio.js';
import { createSuggestionCombobox } from './suggestion-combobox.js';

// Las 13273 localidades pesan unas 200 KB frente a las 22 KB de la DTA. Cargarlas
// en cada visita, para un campo que muchas direcciones ni siquiera usan, seria
// pagar casi diez veces el catalogo que si hace falta siempre. Se piden la primera
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
    const sugerenciasPueblo = pueblo ? createSuggestionCombobox({
        input: pueblo,
        fallbackList: listaPueblos,
        label: 'Sugerencias de pueblos y localidades',
        emptyText: 'No hay coincidencias en este distrito',
        loadingText: 'Cargando localidades…',
        errorText: 'No fue posible cargar las localidades',
        getMeta: () => distrito.value || 'Localidad',
        getSuggestions: async (consulta) => {
            const codigo = codigoDistrito(provincia.value, canton.value, distrito.value);
            if (!codigo) return [];
            const { buscarPoblados } = await cargarPoblados();
            // El usuario puede cambiar de distrito mientras termina el import de
            // 200 KB. Una respuesta vieja nunca debe aparecer bajo el nuevo.
            if (codigoDistrito(provincia.value, canton.value, distrito.value) !== codigo) return [];
            return buscarPoblados(codigo, consulta, { limite: 12 });
        },
    }) : null;

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
        pueblo.disabled = distrito.value === '';
        sugerenciasPueblo?.setDisabled(pueblo.disabled);
        if (!pueblo.disabled) sugerenciasPueblo?.refresh({ open: false });
    }

    provincia.addEventListener('change', () => refrescarCantones());
    canton.addEventListener('change', () => refrescarDistritos());
    distrito.addEventListener('change', () => refrescarPueblo());

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
            sugerenciasPueblo?.setDisabled(pueblo.disabled);
            if (!pueblo.disabled) sugerenciasPueblo?.refresh({ open: false });
        }
    }

    return { aplicar };
}
