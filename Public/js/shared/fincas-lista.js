// Componente "Agregar finca" (Tramo C): lista editable de fincas, cada una con
// su propio nombre y su dirección semántica (provincia → cantón → distrito →
// pueblo → señas).
//
// El backend separa el contrato: el alta de productor acepta `fincas` solo como
// [{nombre}] (ValidacionService exige "únicamente nombre") y la dirección de
// cada finca se asocia con /api/fincas-direccion.php una vez creada. Este
// componente captura ambas cosas en el formulario y deja que el llamador envíe
// la dirección con la API dedicada (201/422/404).
//
// Regla de acumuladores (DEC-31): un nombre repetido dentro del mismo formulario
// o ya existente en el productor no se agrega dos veces; un nombre repetido
// entre productores DISTINTOS siempre se permite (advertencia, no bloqueo).

import { conectarDireccion } from './direccion.js';

export const FINCA_NOMBRE_MAX = 150;

function crearSelect(placeholder) {
    const select = document.createElement('select');
    const vacio = document.createElement('option');
    vacio.value = '';
    vacio.textContent = placeholder;
    select.append(vacio);
    return select;
}

/**
 * @param {{contenedor: HTMLElement, agregarBoton?: HTMLElement,
 *          onCambiar?: () => void}} opts
 * @returns {{crearFila: ({nombre?: string}) => void, obtenerFincas: () => Array,
 *            reiniciar: () => void, hidratar: (fincas: Array) => void}}
 */
export function crearFincasLista({ contenedor, agregarBoton = null, onCambiar = null }) {
    /** @type {Array<{fila: HTMLElement, nombre: HTMLInputElement,
     *                provincia: HTMLSelectElement, canton: HTMLSelectElement,
     *                distrito: HTMLSelectElement, pueblo: HTMLInputElement,
     *                senas: HTMLTextAreaElement}>} */
    const filas = [];

    function notificar() {
        if (typeof onCambiar === 'function') onCambiar();
    }

    function crearFila({ nombre: nombreInicial = '' } = {}) {
        const fila = document.createElement('div');
        fila.className = 'finca-fila';

        const nombre = document.createElement('input');
        nombre.type = 'text';
        nombre.name = 'fincas';
        nombre.className = 'finca-fila__nombre';
        nombre.maxLength = FINCA_NOMBRE_MAX;
        nombre.placeholder = 'Nombre de la finca';
        nombre.autocomplete = 'off';
        nombre.value = String(nombreInicial ?? '');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'finca-fila__toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = 'Dirección ▾';

        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'finca-fila__quitar';
        quitar.textContent = 'Quitar';
        quitar.setAttribute('aria-label', 'Quitar finca');

        const direccionBloque = document.createElement('div');
        direccionBloque.className = 'finca-fila__direccion';
        direccionBloque.hidden = true;

        const provincia = crearSelect('Seleccione una provincia');
        const canton = crearSelect('Seleccione un cantón');
        canton.disabled = true;
        const distrito = crearSelect('Seleccione un distrito');
        distrito.disabled = true;
        const pueblo = document.createElement('input');
        pueblo.type = 'text';
        pueblo.className = 'finca-fila__campo';
        pueblo.maxLength = 150;
        pueblo.placeholder = 'Pueblo o localidad';
        pueblo.autocomplete = 'off';
        pueblo.disabled = true;
        pueblo.setAttribute('aria-label', 'Pueblo de la finca');
        const listaPueblos = document.createElement('datalist');
        const senas = document.createElement('textarea');
        senas.className = 'finca-fila__campo finca-fila__senas';
        senas.maxLength = 500;
        senas.rows = 2;
        senas.placeholder = 'Señas de la dirección (opcional)';
        senas.setAttribute('aria-label', 'Señas de la dirección de la finca');

        direccionBloque.append(provincia, canton, distrito, pueblo, listaPueblos, senas);

        toggle.addEventListener('click', () => {
            const abierta = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', abierta ? 'false' : 'true');
            direccionBloque.hidden = abierta;
            if (!abierta) direccion.aplicar({});
        });

        const direccion = conectarDireccion({ provincia, canton, distrito, pueblo, listaPueblos });

        nombre.addEventListener('input', () => {
            quitar.setAttribute('aria-label', `Quitar finca ${nombre.value.trim() || 'sin nombre'}`);
            notificar();
        });

        quitar.addEventListener('click', () => {
            fila.remove();
            const indice = filas.findIndex((r) => r.fila === fila);
            if (indice >= 0) filas.splice(indice, 1);
            notificar();
        });

        fila.append(nombre, toggle, quitar, direccionBloque);
        contenedor.append(fila);

        const registro = { fila, nombre, provincia, canton, distrito, pueblo, senas, direccionBloque };
        filas.push(registro);
        notificar();
        return registro;
    }

    /** Dirección capturada de la fila, o null si el bloque está cerrado/vacío. */
    function leerDireccion(registro) {
        if (registro.direccionBloque.hidden) return null;
        const { provincia, canton, distrito, pueblo, senas } = registro;
        if (provincia.value === '' && canton.value === '' && distrito.value === '') return null;
        return {
            provincia: provincia.value,
            canton: canton.value,
            distrito: distrito.value,
            pueblo: pueblo.value.trim() || null,
            senas: senas.value.trim() || null,
        };
    }

    /** Fincas del formulario: {nombre, direccion|null} sin filas sin nombre. */
    function obtenerFincas() {
        return filas
            .map((registro) => ({ nombre: registro.nombre.value.trim(), direccion: leerDireccion(registro) }))
            .filter((finca) => finca.nombre !== '');
    }

    function reiniciar() {
        filas.forEach((registro) => registro.fila.remove());
        filas.length = 0;
        notificar();
    }

    function hidratar(fincas) {
        reiniciar();
        (Array.isArray(fincas) ? fincas : []).forEach((finca) => crearFila(finca));
    }

    if (agregarBoton) {
        agregarBoton.addEventListener('click', () => crearFila());
    }

    return { crearFila, obtenerFincas, reiniciar, hidratar };
}