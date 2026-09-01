// Division territorial de Costa Rica para los campos de direccion.
//
// Estructura pensada para completarse sin tocar codigo: cada canton apunta a su
// lista de distritos. Donde la lista esta vacia, el formulario deja el distrito
// como texto libre en lugar de impedir escribirlo. Anadir distritos mas adelante
// es editar este objeto y nada mas.
//
// ALCANCE ACTUAL: 7 provincias y sus 84 cantones. Los distritos (cerca de 490)
// quedan pendientes de una fuente oficial; ver DEC-FRONT-11.
//
// El backend acepta texto libre en provincia, canton y distrito, asi que estos
// catalogos son una ayuda de captura, no una restriccion nueva del contrato.

/** @type {Record<string, Record<string, string[]>>} provincia -> canton -> distritos */
export const TERRITORIO = {
    'San José': {
        'San José': [], 'Escazú': [], 'Desamparados': [], 'Puriscal': [], 'Tarrazú': [],
        'Aserrí': [], 'Mora': [], 'Goicoechea': [], 'Santa Ana': [], 'Alajuelita': [],
        'Vázquez de Coronado': [], 'Acosta': [], 'Tibás': [], 'Moravia': [], 'Montes de Oca': [],
        'Turrubares': [], 'Dota': [], 'Curridabat': [], 'Pérez Zeledón': [], 'León Cortés Castro': [],
    },
    Alajuela: {
        Alajuela: [], 'San Ramón': [], Grecia: [], 'San Mateo': [], Atenas: [], Naranjo: [],
        Palmares: [], Poás: [], Orotina: [], 'San Carlos': [], Zarcero: [], Sarchí: [],
        Upala: [], 'Los Chiles': [], Guatuso: [], 'Río Cuarto': [],
    },
    Cartago: {
        Cartago: [], Paraíso: [], 'La Unión': [], Jiménez: [], Turrialba: [], Alvarado: [],
        Oreamuno: [], 'El Guarco': [],
    },
    Heredia: {
        Heredia: [], Barva: [], 'Santo Domingo': [], 'Santa Bárbara': [], 'San Rafael': [],
        'San Isidro': [], Belén: [], Flores: [], 'San Pablo': [], Sarapiquí: [],
    },
    Guanacaste: {
        Liberia: [], Nicoya: [], 'Santa Cruz': [], Bagaces: [], Carrillo: [], Cañas: [],
        Abangares: [], Tilarán: [], Nandayure: [], 'La Cruz': [], Hojancha: [],
    },
    Puntarenas: {
        Puntarenas: [], Esparza: [], 'Buenos Aires': [], 'Montes de Oro': [], Osa: [],
        Quepos: [], Golfito: [], 'Coto Brus': [], Parrita: [], Corredores: [], Garabito: [],
        Monteverde: [], 'Puerto Jiménez': [],
    },
    Limón: {
        Limón: [], Pococí: [], Siquirres: [], Talamanca: [], Matina: [], Guácimo: [],
    },
};

export function provincias() {
    return Object.keys(TERRITORIO);
}

export function cantones(provincia) {
    return Object.keys(TERRITORIO[provincia] ?? {});
}

export function distritos(provincia, canton) {
    return TERRITORIO[provincia]?.[canton] ?? [];
}

/**
 * Rellena un <select> con las opciones dadas.
 *
 * Si el valor guardado no esta en el catalogo se anade igualmente como opcion.
 * Los datos ya registrados mandan sobre el catalogo: una direccion escrita antes
 * de existir esta lista, o con la tilde puesta de otra forma, no puede perderse
 * solo porque el desplegable no la contemple.
 */
export function llenarSelect(select, opciones, seleccionado = '', { vacio = 'Seleccione' } = {}) {
    const fragment = document.createDocumentFragment();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = vacio;
    fragment.appendChild(placeholder);

    const conocidas = [...opciones];
    if (seleccionado && !conocidas.includes(seleccionado)) conocidas.push(seleccionado);

    for (const opcion of conocidas) {
        const option = document.createElement('option');
        option.value = opcion;
        option.textContent = opcion;
        fragment.appendChild(option);
    }
    select.replaceChildren(fragment);
    select.value = seleccionado;
    return select.value;
}

/** Rellena un <datalist>; con lista vacia el campo queda como texto libre. */
export function llenarDatalist(datalist, opciones) {
    const fragment = document.createDocumentFragment();
    for (const opcion of opciones) {
        const option = document.createElement('option');
        option.value = opcion;
        fragment.appendChild(option);
    }
    datalist.replaceChildren(fragment);
    return opciones.length;
}

/**
 * Conecta provincia -> canton -> distrito.
 *
 * Devuelve `aplicar(direccion)` para cargar una direccion existente sin perder
 * valores que no esten en el catalogo.
 */
export function conectarDireccion({ provincia, canton, distrito, listaDistritos }) {
    function refrescarCantones(valorCanton = '') {
        llenarSelect(canton, cantones(provincia.value), valorCanton, { vacio: 'Seleccione un cantón' });
        canton.disabled = provincia.value === '';
        refrescarDistritos(distrito.value);
    }

    function refrescarDistritos(valorDistrito = '') {
        if (!listaDistritos) return;
        const disponibles = distritos(provincia.value, canton.value);
        llenarDatalist(listaDistritos, disponibles);
        if (valorDistrito !== undefined) distrito.value = valorDistrito;
    }

    provincia.addEventListener('change', () => refrescarCantones(''));
    canton.addEventListener('change', () => refrescarDistritos(distrito.value));

    /** Carga una direccion guardada, conservando valores fuera del catalogo. */
    function aplicar({ provincia: p = '', canton: c = '', distrito: d = '' } = {}) {
        llenarSelect(provincia, provincias(), p, { vacio: 'Seleccione una provincia' });
        llenarSelect(canton, cantones(p), c, { vacio: 'Seleccione un cantón' });
        canton.disabled = provincia.value === '';
        if (listaDistritos) llenarDatalist(listaDistritos, distritos(p, c));
        distrito.value = d;
    }

    return { aplicar, refrescarCantones };
}
