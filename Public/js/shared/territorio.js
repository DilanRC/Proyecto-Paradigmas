// Division territorial de Costa Rica: provincia -> canton -> distrito -> [localidades].
//
// GENERADO desde fuente oficial, no escrito a mano.
//
//   Distritos: Instituto Geografico Nacional / Registro Nacional,
//              "Division Territorial Administrativa, 2026" (DTA 2026).
//              Tabla por provincias, cantones y distritos.
//   Consulta:  2026-08-31
//
// Totales que declara la propia fuente: 7 provincias, 84 cantones, 494 distritos.
//
// RECONCILIACION DOCUMENTADA: la tabla de distritos del PDF contiene 493 filas,
// una menos que el total que declara su propia portada. El distrito ausente es
// 70605 (Guacimo, Limon), que el archivo "Centros Poblados y Localidades 2026"
// del mismo IGN referencia con el nombre Duacari. Se toma de ahi; no se invento.
// Los tres huecos restantes en la numeracion son legitimos: Rio Cuarto,
// Monteverde y Puerto Jimenez dejaron de ser distritos al convertirse en canton.
//
// LOCALIDADES PENDIENTES: cada distrito apunta a un arreglo vacio. El archivo de
// Centros Poblados y Localidades 2026 no se pudo extraer con fidelidad: su
// fuente incrustada pierde la secuencia "nd" al convertir a texto, de modo que
// "Condominio" sale "Coominio" e "Indigena" sale "Iigena", y nombres como
// Grande, Segundo o Redonda desaparecen. Ver DEC-FRONT-11. Mientras el arreglo
// este vacio el formulario deja el campo como texto libre.

/** @type {Record<string, Record<string, Record<string, string[]>>>} */
export const TERRITORIO = {
    'San José': {
        'San José': {
            Carmen: [],
            Merced: [],
            Hospital: [],
            Catedral: [],
            Zapote: [],
            'San Francisco de Dos Ríos': [],
            Uruca: [],
            'Mata Redonda': [],
            Pavas: [],
            Hatillo: [],
            'San Sebastián': [],
        },
        Escazú: {
            Escazú: [],
            'San Antonio': [],
            'San Rafael': [],
        },
        Desamparados: {
            Desamparados: [],
            'San Miguel': [],
            'San Juan de Dios': [],
            'San Rafael Arriba': [],
            'San Antonio': [],
            Frailes: [],
            Patarrá: [],
            'San Cristobal': [],
            Rosario: [],
            Damas: [],
            'San Rafael Abajo': [],
            Gravilias: [],
            'Los Guido': [],
        },
        Puriscal: {
            Santiago: [],
            'Mercedes Sur': [],
            Barbacoas: [],
            'Grifo Alto': [],
            'San Rafael': [],
            Candelarita: [],
            Desamparaditos: [],
            'San Antonio': [],
            Chires: [],
        },
        Tarrazú: {
            'San Marcos': [],
            'San Lorenzo': [],
            'San Carlos': [],
        },
        Aserrí: {
            Aserrí: [],
            Tarbaca: [],
            'Vuelta de Jorco': [],
            'San Gabriel': [],
            Legua: [],
            Monterrey: [],
            Salitrillos: [],
        },
        Mora: {
            Colón: [],
            Guayabo: [],
            Tabarcia: [],
            'Piedras Negras': [],
            Picagres: [],
            Jaris: [],
            Quitirrisí: [],
        },
        Goicoechea: {
            Guadalupe: [],
            'San Francisco': [],
            'Calle Blancos': [],
            'Mata de Plátano': [],
            Ipís: [],
            'Rancho Redondo': [],
            Purral: [],
        },
        'Santa Ana': {
            'Santa Ana': [],
            Salitral: [],
            Pozos: [],
            Uruca: [],
            Piedades: [],
            Brasil: [],
        },
        Alajuelita: {
            Alajuelita: [],
            'San Josecito': [],
            'San Antonio': [],
            Concepción: [],
            'San Felipe': [],
        },
        'Vázquez de Coronado': {
            'San Isidro': [],
            'San Rafael': [],
            'Dulce Nombre de Jesús': [],
            Patalillo: [],
            Cascajal: [],
        },
        Acosta: {
            'San Ignacio': [],
            Guaitil: [],
            Palmichal: [],
            Cangrejal: [],
            Sabanillas: [],
        },
        Tibás: {
            'San Juan': [],
            'Cinco Esquinas': [],
            'Anselmo Llorente': [],
            'León XIII': [],
            Colima: [],
        },
        Moravia: {
            'San Vicente': [],
            'San Jerónimo': [],
            'La Trinidad': [],
        },
        'Montes de Oca': {
            'San Pedro': [],
            Sabanilla: [],
            Mercedes: [],
            'San Rafael': [],
        },
        Turrubares: {
            'San Pablo': [],
            'San Pedro': [],
            'San Juan de Mata': [],
            'San Luis': [],
            Carara: [],
        },
        Dota: {
            'Santa María': [],
            Jardín: [],
            Copey: [],
        },
        Curridabat: {
            Curridabat: [],
            Granadilla: [],
            Sánchez: [],
            Tirrases: [],
        },
        'Pérez Zeledón': {
            'San Isidro de El General': [],
            'El General': [],
            'Daniel Flores': [],
            Rivas: [],
            'San Pedro': [],
            Platanares: [],
            Pejivalle: [],
            Cajón: [],
            Barú: [],
            'Río Nuevo': [],
            Páramo: [],
            'La Amistad': [],
        },
        'León Cortés Castro': {
            'San Pablo': [],
            'San Andrés': [],
            'Llano Bonito': [],
            'San Isidro': [],
            'Santa Cruz': [],
            'San Antonio': [],
        },
    },
    Alajuela: {
        Alajuela: {
            Alajuela: [],
            'San José': [],
            Carrizal: [],
            'San Antonio': [],
            Guácima: [],
            'San Isidro': [],
            Sabanilla: [],
            'San Rafael': [],
            'Río Segundo': [],
            Desamparados: [],
            Turrúcares: [],
            Tambor: [],
            Garita: [],
            Sarapiquí: [],
        },
        'San Ramón': {
            'San Ramón': [],
            Santiago: [],
            'San Juan': [],
            'Piedades Norte': [],
            'Piedades Sur': [],
            'San Rafael': [],
            'San Isidro': [],
            Ángeles: [],
            Alfaro: [],
            Volio: [],
            Concepción: [],
            Zapotal: [],
            'Peñas Blancas': [],
            'San Lorenzo': [],
        },
        Grecia: {
            Grecia: [],
            'San Isidro': [],
            'San José': [],
            'San Roque': [],
            Tacares: [],
            'Puente de Piedra': [],
            Bolivar: [],
        },
        'San Mateo': {
            'San Mateo': [],
            Desmonte: [],
            'Jesús María': [],
            Labrador: [],
        },
        Atenas: {
            Atenas: [],
            Jesús: [],
            Mercedes: [],
            'San Isidro': [],
            Concepción: [],
            'San José': [],
            'Santa Eulalia': [],
            Escobal: [],
        },
        Naranjo: {
            Naranjo: [],
            'San Miguel': [],
            'San José': [],
            'Cirrí Sur': [],
            'San Jerónimo': [],
            'San Juan': [],
            'El Rosario': [],
            Palmitos: [],
        },
        Palmares: {
            Palmares: [],
            Zaragoza: [],
            'Buenos Aires': [],
            Santiago: [],
            Candelaria: [],
            Esquipulas: [],
            'La Granja': [],
        },
        Poás: {
            'San Pedro': [],
            'San Juan': [],
            'San Rafael': [],
            Carrillos: [],
            'Sabana Redonda': [],
        },
        Orotina: {
            Orotina: [],
            'El Mastate': [],
            'Hacienda Vieja': [],
            Coyolar: [],
            'La Ceiba': [],
        },
        'San Carlos': {
            Quesada: [],
            Florencia: [],
            Buenavista: [],
            'Aguas Zarcas': [],
            Venecia: [],
            Pital: [],
            'La Fortuna': [],
            'La Tigra': [],
            'La Palmera': [],
            Venado: [],
            Cutris: [],
            Monterrey: [],
            Pocosol: [],
        },
        Zarcero: {
            Zarcero: [],
            Laguna: [],
            Tapesco: [],
            Guadalupe: [],
            Palmira: [],
            Zapote: [],
            Brisas: [],
        },
        Sarchí: {
            'Sarchí Norte': [],
            'Sarchí Sur': [],
            'Toro Amarillo': [],
            'San Pedro': [],
            Rodríguez: [],
        },
        Upala: {
            Upala: [],
            'Aguas Claras': [],
            'San José O Pizote': [],
            Bijagua: [],
            Delicias: [],
            'Dos Ríos': [],
            Yolillal: [],
            Canalete: [],
        },
        'Los Chiles': {
            'Los Chiles': [],
            'Caño Negro': [],
            'El Amparo': [],
            'San Jorge': [],
        },
        Guatuso: {
            'San Rafael': [],
            Buenavista: [],
            Cote: [],
            Katira: [],
        },
        'Río Cuarto': {
            'Río Cuarto': [],
            'Santa Rita': [],
            'Santa Isabel': [],
        },
    },
    Cartago: {
        Cartago: {
            Oriental: [],
            Occidental: [],
            Carmen: [],
            'San Nicolás': [],
            'Aguacaliente o San Francisco': [],
            'Guadalupe o Arenilla': [],
            Corralillo: [],
            'Tierra Blanca': [],
            'Dulce Nombre': [],
            'Llano Grande': [],
            Quebradilla: [],
        },
        Paraíso: {
            Paraíso: [],
            Santiago: [],
            Orosi: [],
            Cachí: [],
            'Llanos de Santa Lucía': [],
            Birrisito: [],
        },
        'La Unión': {
            'Tres Ríos': [],
            'San Diego': [],
            'San Juan': [],
            'San Rafael': [],
            Concepción: [],
            'Dulce Nombre': [],
            'San Ramón': [],
            'Río Azul': [],
        },
        Jiménez: {
            'Juan Viñas': [],
            Tucurrique: [],
            Pejibaye: [],
            'La Victoria': [],
        },
        Turrialba: {
            Turrialba: [],
            'La Suiza': [],
            Peralta: [],
            'Santa Cruz': [],
            'Santa Teresita': [],
            Pavones: [],
            Tuis: [],
            Tayutic: [],
            'Santa Rosa': [],
            'Tres Equis': [],
            'La Isabel': [],
            Chirripó: [],
        },
        Alvarado: {
            Pacayas: [],
            Cervantes: [],
            Capellades: [],
        },
        Oreamuno: {
            'San Rafael': [],
            Cot: [],
            'Potrero Cerrado': [],
            Cipreses: [],
            'Santa Rosa': [],
        },
        'El Guarco': {
            'El Tejar': [],
            'San Isidro': [],
            Tobosi: [],
            'Patio de Agua': [],
        },
    },
    Heredia: {
        Heredia: {
            Heredia: [],
            Mercedes: [],
            'San Francisco': [],
            Ulloa: [],
            Varablanca: [],
        },
        Barva: {
            Barva: [],
            'San Pedro': [],
            'San Pablo': [],
            'San Roque': [],
            'Santa Lucía': [],
            'San José de la Montaña': [],
            'Puente Salas': [],
        },
        'Santo Domingo': {
            'Santo Domingo': [],
            'San Vicente': [],
            'San Miguel': [],
            Paracito: [],
            'Santo Tomás': [],
            'Santa Rosa': [],
            Tures: [],
            Pará: [],
        },
        'Santa Bárbara': {
            'Santa Bárbara': [],
            'San Pedro': [],
            'San Juan': [],
            Jesús: [],
            'Santo Domingo': [],
            Purabá: [],
        },
        'San Rafael': {
            'San Rafael': [],
            'San Josecito': [],
            Santiago: [],
            Ángeles: [],
            Concepción: [],
        },
        'San Isidro': {
            'San Isidro': [],
            'San José': [],
            Concepción: [],
            'San Francisco': [],
        },
        Belén: {
            'San Antonio': [],
            'La Ribera': [],
            'La Asunción': [],
        },
        Flores: {
            'San Joaquín': [],
            Barrantes: [],
            Llorente: [],
        },
        'San Pablo': {
            'San Pablo': [],
            'Rincón de Sabanilla': [],
        },
        Sarapiquí: {
            'Puerto Viejo': [],
            'La Virgen': [],
            'Las Horquetas': [],
            'Llanuras del Gaspar': [],
            Cureña: [],
        },
    },
    Guanacaste: {
        Liberia: {
            Liberia: [],
            'Cañas Dulces': [],
            Mayorga: [],
            Nacascolo: [],
            Curubandé: [],
        },
        Nicoya: {
            Nicoya: [],
            Mansión: [],
            'San Antonio': [],
            'Quebrada Honda': [],
            Sámara: [],
            Nosara: [],
            'Belén de Nosarita': [],
        },
        'Santa Cruz': {
            'Santa Cruz': [],
            Bolsón: [],
            'Veintisiete de Abril': [],
            Tempate: [],
            Cartagena: [],
            Cuajiniquil: [],
            Diriá: [],
            'Cabo Velas': [],
            Tamarindo: [],
        },
        Bagaces: {
            Bagaces: [],
            'La Fortuna': [],
            Mogote: [],
            'Río Naranjo': [],
            Pijije: [],
        },
        Carrillo: {
            Filadelfia: [],
            Palmira: [],
            Sardinal: [],
            Belén: [],
        },
        Cañas: {
            Cañas: [],
            Palmira: [],
            'San Miguel': [],
            Bebedero: [],
            Porozal: [],
        },
        Abangares: {
            'Las Juntas': [],
            Sierra: [],
            'San Juan': [],
            Colorado: [],
        },
        Tilarán: {
            Tilarán: [],
            'Quebrada Grande': [],
            Tronadora: [],
            'Santa Rosa': [],
            Líbano: [],
            'Tierras Morenas': [],
            Arenal: [],
            Cabeceras: [],
        },
        Nandayure: {
            Carmona: [],
            'Santa Rita': [],
            Zapotal: [],
            'San Pablo': [],
            Porvenir: [],
            Bejuco: [],
        },
        'La Cruz': {
            'La Cruz': [],
            'Santa Cecilia': [],
            'La Garita': [],
            'Santa Elena': [],
        },
        Hojancha: {
            Hojancha: [],
            'Monte Romo': [],
            'Puerto Carrillo': [],
            Huacas: [],
            Matambú: [],
        },
    },
    Puntarenas: {
        Puntarenas: {
            Puntarenas: [],
            Pitahaya: [],
            Chomes: [],
            Lepanto: [],
            Paquera: [],
            Manzanillo: [],
            Guacimal: [],
            Barranca: [],
            'Isla del Coco': [],
            Cóbano: [],
            Chacarita: [],
            Chira: [],
            Acapulco: [],
            'El Roble': [],
            Arancibia: [],
        },
        Esparza: {
            'Espíritu Santo': [],
            'San Juan Grande': [],
            Macacona: [],
            'San Rafael': [],
            'San Jerónimo': [],
            Caldera: [],
        },
        'Buenos Aires': {
            'Buenos Aires': [],
            Volcán: [],
            'Potrero Grande': [],
            Boruca: [],
            Pilas: [],
            Colinas: [],
            Chánguena: [],
            Biolley: [],
            Brunka: [],
            Cabagra: [],
        },
        'Montes de Oro': {
            Miramar: [],
            'La Unión': [],
            'San Isidro': [],
        },
        Osa: {
            'Puerto Cortés': [],
            Palmar: [],
            Sierpe: [],
            'Bahía Ballena': [],
            'Piedras Blancas': [],
            'Bahía Drake': [],
        },
        Quepos: {
            Quepos: [],
            Savegre: [],
            Naranjito: [],
        },
        Golfito: {
            Golfito: [],
            Guaycará: [],
            Pavón: [],
        },
        'Coto Brus': {
            'San Vito': [],
            Sabalito: [],
            Aguabuena: [],
            Limoncito: [],
            Pittier: [],
            'Gutiérrez Braun': [],
        },
        Parrita: {
            Parrita: [],
        },
        Corredores: {
            Corredor: [],
            'La Cuesta': [],
            Canoas: [],
            Laurel: [],
        },
        Garabito: {
            Jacó: [],
            Tárcoles: [],
            Lagunillas: [],
        },
        Monteverde: {
            Monteverde: [],
        },
        'Puerto Jiménez': {
            'Puerto Jiménez': [],
        },
    },
    Limón: {
        Limón: {
            Limón: [],
            'Valle La Estrella': [],
            'Río Blanco': [],
            Matama: [],
        },
        Pococí: {
            Guápiles: [],
            Jiménez: [],
            Rita: [],
            Roxana: [],
            Cariari: [],
            Colorado: [],
            'La Colonia': [],
        },
        Siquirres: {
            Siquirres: [],
            Pacuarito: [],
            Florida: [],
            Germania: [],
            'El Cairo': [],
            Alegría: [],
            Reventazón: [],
        },
        Talamanca: {
            Bratsi: [],
            Sixaola: [],
            Cahuita: [],
            Telire: [],
        },
        Matina: {
            Matina: [],
            Batán: [],
            Carrandí: [],
        },
        Guácimo: {
            Guácimo: [],
            Mercedes: [],
            Pocora: [],
            'Río Jiménez': [],
            Duacarí: [],
        },
    },
};

export function provincias() {
    return Object.keys(TERRITORIO);
}

export function cantones(provincia) {
    return Object.keys(TERRITORIO[provincia] ?? {});
}

export function distritos(provincia, canton) {
    return Object.keys(TERRITORIO[provincia]?.[canton] ?? {});
}

export function poblados(provincia, canton, distrito) {
    return TERRITORIO[provincia]?.[canton]?.[distrito] ?? [];
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
    if (!datalist) return 0;
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
 * Conecta provincia -> canton -> distrito -> pueblo.
 *
 * Provincia y canton son desplegables porque su catalogo esta completo.
 * Distrito y pueblo son texto libre con sugerencias: mientras su catalogo este
 * vacio deben poder escribirse igualmente.
 */
export function conectarDireccion({
    provincia, canton, distrito, pueblo = null,
    listaDistritos = null, listaPueblos = null,
}) {
    function refrescarDistritos({ conservarDistrito = false } = {}) {
        llenarDatalist(listaDistritos, distritos(provincia.value, canton.value));
        if (!conservarDistrito) distrito.value = '';
        refrescarPueblos({ conservarPueblo: conservarDistrito });
    }

    function refrescarPueblos({ conservarPueblo = false } = {}) {
        if (!pueblo) return;
        llenarDatalist(listaPueblos, poblados(provincia.value, canton.value, distrito.value));
        if (!conservarPueblo) pueblo.value = '';
    }

    provincia.addEventListener('change', () => {
        llenarSelect(canton, cantones(provincia.value), '', { vacio: 'Seleccione un cantón' });
        canton.disabled = provincia.value === '';
        refrescarDistritos();
    });
    canton.addEventListener('change', () => refrescarDistritos());
    distrito.addEventListener('change', () => refrescarPueblos());
    distrito.addEventListener('input', () => refrescarPueblos({ conservarPueblo: true }));

    /** Carga una direccion guardada, conservando valores fuera del catalogo. */
    function aplicar({ provincia: p = '', canton: c = '', distrito: d = '', pueblo: pu = '' } = {}) {
        llenarSelect(provincia, provincias(), p, { vacio: 'Seleccione una provincia' });
        llenarSelect(canton, cantones(p), c, { vacio: 'Seleccione un cantón' });
        canton.disabled = provincia.value === '';
        llenarDatalist(listaDistritos, distritos(p, c));
        distrito.value = d;
        llenarDatalist(listaPueblos, poblados(p, c, d));
        if (pueblo) pueblo.value = pu ?? '';
    }

    return { aplicar };
}
