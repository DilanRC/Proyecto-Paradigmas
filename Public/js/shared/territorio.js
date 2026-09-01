// Division territorial de Costa Rica: provincia -> canton -> distrito -> codigo.
//
// GENERADO desde fuente oficial, no escrito a mano.
//
//   Instituto Geografico Nacional / Registro Nacional,
//   "Division Territorial Administrativa, 2026" (DTA 2026).
//   Consulta: 2026-08-31. Totales que declara la fuente: 7 / 84 / 494.
//
// El valor de cada distrito es su codigo DTA de cinco digitos (P CC DD), que es
// la clave con la que `poblados.js` indexa las localidades. Se guarda como
// texto y no como numero: "10101" no debe perder su forma ni convertirse en 10101.
//
// RECONCILIACION: la tabla del PDF trae 493 filas frente a las 494 que declara
// su portada. La ausente es 70605 Duacari (Guacimo, Limon), que existe por
// Decreto Ejecutivo 12091-G de 1980 y que el archivo oficial de Centros
// Poblados 2026 referencia con 19 localidades. Los tres huecos restantes de la
// numeracion son legitimos: Rio Cuarto, Monteverde y Puerto Jimenez dejaron de
// ser distritos al convertirse en canton.

/** @type {Record<string, Record<string, Record<string, string>>>} */
export const TERRITORIO = 
{
    'San José': {
        'San José': {
            Carmen: '10101',
            Merced: '10102',
            Hospital: '10103',
            Catedral: '10104',
            Zapote: '10105',
            'San Francisco de Dos Ríos': '10106',
            Uruca: '10107',
            'Mata Redonda': '10108',
            Pavas: '10109',
            Hatillo: '10110',
            'San Sebastián': '10111',
        },
        Escazú: {
            Escazú: '10201',
            'San Antonio': '10202',
            'San Rafael': '10203',
        },
        Desamparados: {
            Desamparados: '10301',
            'San Miguel': '10302',
            'San Juan de Dios': '10303',
            'San Rafael Arriba': '10304',
            'San Antonio': '10305',
            Frailes: '10306',
            Patarrá: '10307',
            'San Cristobal': '10308',
            Rosario: '10309',
            Damas: '10310',
            'San Rafael Abajo': '10311',
            Gravilias: '10312',
            'Los Guido': '10313',
        },
        Puriscal: {
            Santiago: '10401',
            'Mercedes Sur': '10402',
            Barbacoas: '10403',
            'Grifo Alto': '10404',
            'San Rafael': '10405',
            Candelarita: '10406',
            Desamparaditos: '10407',
            'San Antonio': '10408',
            Chires: '10409',
        },
        Tarrazú: {
            'San Marcos': '10501',
            'San Lorenzo': '10502',
            'San Carlos': '10503',
        },
        Aserrí: {
            Aserrí: '10601',
            Tarbaca: '10602',
            'Vuelta de Jorco': '10603',
            'San Gabriel': '10604',
            Legua: '10605',
            Monterrey: '10606',
            Salitrillos: '10607',
        },
        Mora: {
            Colón: '10701',
            Guayabo: '10702',
            Tabarcia: '10703',
            'Piedras Negras': '10704',
            Picagres: '10705',
            Jaris: '10706',
            Quitirrisí: '10707',
        },
        Goicoechea: {
            Guadalupe: '10801',
            'San Francisco': '10802',
            'Calle Blancos': '10803',
            'Mata de Plátano': '10804',
            Ipís: '10805',
            'Rancho Redondo': '10806',
            Purral: '10807',
        },
        'Santa Ana': {
            'Santa Ana': '10901',
            Salitral: '10902',
            Pozos: '10903',
            Uruca: '10904',
            Piedades: '10905',
            Brasil: '10906',
        },
        Alajuelita: {
            Alajuelita: '11001',
            'San Josecito': '11002',
            'San Antonio': '11003',
            Concepción: '11004',
            'San Felipe': '11005',
        },
        'Vázquez de Coronado': {
            'San Isidro': '11101',
            'San Rafael': '11102',
            'Dulce Nombre de Jesús': '11103',
            Patalillo: '11104',
            Cascajal: '11105',
        },
        Acosta: {
            'San Ignacio': '11201',
            Guaitil: '11202',
            Palmichal: '11203',
            Cangrejal: '11204',
            Sabanillas: '11205',
        },
        Tibás: {
            'San Juan': '11301',
            'Cinco Esquinas': '11302',
            'Anselmo Llorente': '11303',
            'León XIII': '11304',
            Colima: '11305',
        },
        Moravia: {
            'San Vicente': '11401',
            'San Jerónimo': '11402',
            'La Trinidad': '11403',
        },
        'Montes de Oca': {
            'San Pedro': '11501',
            Sabanilla: '11502',
            Mercedes: '11503',
            'San Rafael': '11504',
        },
        Turrubares: {
            'San Pablo': '11601',
            'San Pedro': '11602',
            'San Juan de Mata': '11603',
            'San Luis': '11604',
            Carara: '11605',
        },
        Dota: {
            'Santa María': '11701',
            Jardín: '11702',
            Copey: '11703',
        },
        Curridabat: {
            Curridabat: '11801',
            Granadilla: '11802',
            Sánchez: '11803',
            Tirrases: '11804',
        },
        'Pérez Zeledón': {
            'San Isidro de El General': '11901',
            'El General': '11902',
            'Daniel Flores': '11903',
            Rivas: '11904',
            'San Pedro': '11905',
            Platanares: '11906',
            Pejivalle: '11907',
            Cajón: '11908',
            Barú: '11909',
            'Río Nuevo': '11910',
            Páramo: '11911',
            'La Amistad': '11912',
        },
        'León Cortés Castro': {
            'San Pablo': '12001',
            'San Andrés': '12002',
            'Llano Bonito': '12003',
            'San Isidro': '12004',
            'Santa Cruz': '12005',
            'San Antonio': '12006',
        },
    },
    Alajuela: {
        Alajuela: {
            Alajuela: '20101',
            'San José': '20102',
            Carrizal: '20103',
            'San Antonio': '20104',
            Guácima: '20105',
            'San Isidro': '20106',
            Sabanilla: '20107',
            'San Rafael': '20108',
            'Río Segundo': '20109',
            Desamparados: '20110',
            Turrúcares: '20111',
            Tambor: '20112',
            Garita: '20113',
            Sarapiquí: '20114',
        },
        'San Ramón': {
            'San Ramón': '20201',
            Santiago: '20202',
            'San Juan': '20203',
            'Piedades Norte': '20204',
            'Piedades Sur': '20205',
            'San Rafael': '20206',
            'San Isidro': '20207',
            Ángeles: '20208',
            Alfaro: '20209',
            Volio: '20210',
            Concepción: '20211',
            Zapotal: '20212',
            'Peñas Blancas': '20213',
            'San Lorenzo': '20214',
        },
        Grecia: {
            Grecia: '20301',
            'San Isidro': '20302',
            'San José': '20303',
            'San Roque': '20304',
            Tacares: '20305',
            'Puente de Piedra': '20307',
            Bolivar: '20308',
        },
        'San Mateo': {
            'San Mateo': '20401',
            Desmonte: '20402',
            'Jesús María': '20403',
            Labrador: '20404',
        },
        Atenas: {
            Atenas: '20501',
            Jesús: '20502',
            Mercedes: '20503',
            'San Isidro': '20504',
            Concepción: '20505',
            'San José': '20506',
            'Santa Eulalia': '20507',
            Escobal: '20508',
        },
        Naranjo: {
            Naranjo: '20601',
            'San Miguel': '20602',
            'San José': '20603',
            'Cirrí Sur': '20604',
            'San Jerónimo': '20605',
            'San Juan': '20606',
            'El Rosario': '20607',
            Palmitos: '20608',
        },
        Palmares: {
            Palmares: '20701',
            Zaragoza: '20702',
            'Buenos Aires': '20703',
            Santiago: '20704',
            Candelaria: '20705',
            Esquipulas: '20706',
            'La Granja': '20707',
        },
        Poás: {
            'San Pedro': '20801',
            'San Juan': '20802',
            'San Rafael': '20803',
            Carrillos: '20804',
            'Sabana Redonda': '20805',
        },
        Orotina: {
            Orotina: '20901',
            'El Mastate': '20902',
            'Hacienda Vieja': '20903',
            Coyolar: '20904',
            'La Ceiba': '20905',
        },
        'San Carlos': {
            Quesada: '21001',
            Florencia: '21002',
            Buenavista: '21003',
            'Aguas Zarcas': '21004',
            Venecia: '21005',
            Pital: '21006',
            'La Fortuna': '21007',
            'La Tigra': '21008',
            'La Palmera': '21009',
            Venado: '21010',
            Cutris: '21011',
            Monterrey: '21012',
            Pocosol: '21013',
        },
        Zarcero: {
            Zarcero: '21101',
            Laguna: '21102',
            Tapesco: '21103',
            Guadalupe: '21104',
            Palmira: '21105',
            Zapote: '21106',
            Brisas: '21107',
        },
        Sarchí: {
            'Sarchí Norte': '21201',
            'Sarchí Sur': '21202',
            'Toro Amarillo': '21203',
            'San Pedro': '21204',
            Rodríguez: '21205',
        },
        Upala: {
            Upala: '21301',
            'Aguas Claras': '21302',
            'San José O Pizote': '21303',
            Bijagua: '21304',
            Delicias: '21305',
            'Dos Ríos': '21306',
            Yolillal: '21307',
            Canalete: '21308',
        },
        'Los Chiles': {
            'Los Chiles': '21401',
            'Caño Negro': '21402',
            'El Amparo': '21403',
            'San Jorge': '21404',
        },
        Guatuso: {
            'San Rafael': '21501',
            Buenavista: '21502',
            Cote: '21503',
            Katira: '21504',
        },
        'Río Cuarto': {
            'Río Cuarto': '21601',
            'Santa Rita': '21602',
            'Santa Isabel': '21603',
        },
    },
    Cartago: {
        Cartago: {
            Oriental: '30101',
            Occidental: '30102',
            Carmen: '30103',
            'San Nicolás': '30104',
            'Aguacaliente o San Francisco': '30105',
            'Guadalupe o Arenilla': '30106',
            Corralillo: '30107',
            'Tierra Blanca': '30108',
            'Dulce Nombre': '30109',
            'Llano Grande': '30110',
            Quebradilla: '30111',
        },
        Paraíso: {
            Paraíso: '30201',
            Santiago: '30202',
            Orosi: '30203',
            Cachí: '30204',
            'Llanos de Santa Lucía': '30205',
            Birrisito: '30206',
        },
        'La Unión': {
            'Tres Ríos': '30301',
            'San Diego': '30302',
            'San Juan': '30303',
            'San Rafael': '30304',
            Concepción: '30305',
            'Dulce Nombre': '30306',
            'San Ramón': '30307',
            'Río Azul': '30308',
        },
        Jiménez: {
            'Juan Viñas': '30401',
            Tucurrique: '30402',
            Pejibaye: '30403',
            'La Victoria': '30404',
        },
        Turrialba: {
            Turrialba: '30501',
            'La Suiza': '30502',
            Peralta: '30503',
            'Santa Cruz': '30504',
            'Santa Teresita': '30505',
            Pavones: '30506',
            Tuis: '30507',
            Tayutic: '30508',
            'Santa Rosa': '30509',
            'Tres Equis': '30510',
            'La Isabel': '30511',
            Chirripó: '30512',
        },
        Alvarado: {
            Pacayas: '30601',
            Cervantes: '30602',
            Capellades: '30603',
        },
        Oreamuno: {
            'San Rafael': '30701',
            Cot: '30702',
            'Potrero Cerrado': '30703',
            Cipreses: '30704',
            'Santa Rosa': '30705',
        },
        'El Guarco': {
            'El Tejar': '30801',
            'San Isidro': '30802',
            Tobosi: '30803',
            'Patio de Agua': '30804',
        },
    },
    Heredia: {
        Heredia: {
            Heredia: '40101',
            Mercedes: '40102',
            'San Francisco': '40103',
            Ulloa: '40104',
            Varablanca: '40105',
        },
        Barva: {
            Barva: '40201',
            'San Pedro': '40202',
            'San Pablo': '40203',
            'San Roque': '40204',
            'Santa Lucía': '40205',
            'San José de la Montaña': '40206',
            'Puente Salas': '40207',
        },
        'Santo Domingo': {
            'Santo Domingo': '40301',
            'San Vicente': '40302',
            'San Miguel': '40303',
            Paracito: '40304',
            'Santo Tomás': '40305',
            'Santa Rosa': '40306',
            Tures: '40307',
            Pará: '40308',
        },
        'Santa Bárbara': {
            'Santa Bárbara': '40401',
            'San Pedro': '40402',
            'San Juan': '40403',
            Jesús: '40404',
            'Santo Domingo': '40405',
            Purabá: '40406',
        },
        'San Rafael': {
            'San Rafael': '40501',
            'San Josecito': '40502',
            Santiago: '40503',
            Ángeles: '40504',
            Concepción: '40505',
        },
        'San Isidro': {
            'San Isidro': '40601',
            'San José': '40602',
            Concepción: '40603',
            'San Francisco': '40604',
        },
        Belén: {
            'San Antonio': '40701',
            'La Ribera': '40702',
            'La Asunción': '40703',
        },
        Flores: {
            'San Joaquín': '40801',
            Barrantes: '40802',
            Llorente: '40803',
        },
        'San Pablo': {
            'San Pablo': '40901',
            'Rincón de Sabanilla': '40902',
        },
        Sarapiquí: {
            'Puerto Viejo': '41001',
            'La Virgen': '41002',
            'Las Horquetas': '41003',
            'Llanuras del Gaspar': '41004',
            Cureña: '41005',
        },
    },
    Guanacaste: {
        Liberia: {
            Liberia: '50101',
            'Cañas Dulces': '50102',
            Mayorga: '50103',
            Nacascolo: '50104',
            Curubandé: '50105',
        },
        Nicoya: {
            Nicoya: '50201',
            Mansión: '50202',
            'San Antonio': '50203',
            'Quebrada Honda': '50204',
            Sámara: '50205',
            Nosara: '50206',
            'Belén de Nosarita': '50207',
        },
        'Santa Cruz': {
            'Santa Cruz': '50301',
            Bolsón: '50302',
            'Veintisiete de Abril': '50303',
            Tempate: '50304',
            Cartagena: '50305',
            Cuajiniquil: '50306',
            Diriá: '50307',
            'Cabo Velas': '50308',
            Tamarindo: '50309',
        },
        Bagaces: {
            Bagaces: '50401',
            'La Fortuna': '50402',
            Mogote: '50403',
            'Río Naranjo': '50404',
            Pijije: '50405',
        },
        Carrillo: {
            Filadelfia: '50501',
            Palmira: '50502',
            Sardinal: '50503',
            Belén: '50504',
        },
        Cañas: {
            Cañas: '50601',
            Palmira: '50602',
            'San Miguel': '50603',
            Bebedero: '50604',
            Porozal: '50605',
        },
        Abangares: {
            'Las Juntas': '50701',
            Sierra: '50702',
            'San Juan': '50703',
            Colorado: '50704',
        },
        Tilarán: {
            Tilarán: '50801',
            'Quebrada Grande': '50802',
            Tronadora: '50803',
            'Santa Rosa': '50804',
            Líbano: '50805',
            'Tierras Morenas': '50806',
            Arenal: '50807',
            Cabeceras: '50808',
        },
        Nandayure: {
            Carmona: '50901',
            'Santa Rita': '50902',
            Zapotal: '50903',
            'San Pablo': '50904',
            Porvenir: '50905',
            Bejuco: '50906',
        },
        'La Cruz': {
            'La Cruz': '51001',
            'Santa Cecilia': '51002',
            'La Garita': '51003',
            'Santa Elena': '51004',
        },
        Hojancha: {
            Hojancha: '51101',
            'Monte Romo': '51102',
            'Puerto Carrillo': '51103',
            Huacas: '51104',
            Matambú: '51105',
        },
    },
    Puntarenas: {
        Puntarenas: {
            Puntarenas: '60101',
            Pitahaya: '60102',
            Chomes: '60103',
            Lepanto: '60104',
            Paquera: '60105',
            Manzanillo: '60106',
            Guacimal: '60107',
            Barranca: '60108',
            'Isla del Coco': '60110',
            Cóbano: '60111',
            Chacarita: '60112',
            Chira: '60113',
            Acapulco: '60114',
            'El Roble': '60115',
            Arancibia: '60116',
        },
        Esparza: {
            'Espíritu Santo': '60201',
            'San Juan Grande': '60202',
            Macacona: '60203',
            'San Rafael': '60204',
            'San Jerónimo': '60205',
            Caldera: '60206',
        },
        'Buenos Aires': {
            'Buenos Aires': '60301',
            Volcán: '60302',
            'Potrero Grande': '60303',
            Boruca: '60304',
            Pilas: '60305',
            Colinas: '60306',
            Chánguena: '60307',
            Biolley: '60308',
            Brunka: '60309',
            Cabagra: '60310',
        },
        'Montes de Oro': {
            Miramar: '60401',
            'La Unión': '60402',
            'San Isidro': '60403',
        },
        Osa: {
            'Puerto Cortés': '60501',
            Palmar: '60502',
            Sierpe: '60503',
            'Bahía Ballena': '60504',
            'Piedras Blancas': '60505',
            'Bahía Drake': '60506',
        },
        Quepos: {
            Quepos: '60601',
            Savegre: '60602',
            Naranjito: '60603',
        },
        Golfito: {
            Golfito: '60701',
            Guaycará: '60703',
            Pavón: '60704',
        },
        'Coto Brus': {
            'San Vito': '60801',
            Sabalito: '60802',
            Aguabuena: '60803',
            Limoncito: '60804',
            Pittier: '60805',
            'Gutiérrez Braun': '60806',
        },
        Parrita: {
            Parrita: '60901',
        },
        Corredores: {
            Corredor: '61001',
            'La Cuesta': '61002',
            Canoas: '61003',
            Laurel: '61004',
        },
        Garabito: {
            Jacó: '61101',
            Tárcoles: '61102',
            Lagunillas: '61103',
        },
        Monteverde: {
            Monteverde: '61201',
        },
        'Puerto Jiménez': {
            'Puerto Jiménez': '61301',
        },
    },
    Limón: {
        Limón: {
            Limón: '70101',
            'Valle La Estrella': '70102',
            'Río Blanco': '70103',
            Matama: '70104',
        },
        Pococí: {
            Guápiles: '70201',
            Jiménez: '70202',
            Rita: '70203',
            Roxana: '70204',
            Cariari: '70205',
            Colorado: '70206',
            'La Colonia': '70207',
        },
        Siquirres: {
            Siquirres: '70301',
            Pacuarito: '70302',
            Florida: '70303',
            Germania: '70304',
            'El Cairo': '70305',
            Alegría: '70306',
            Reventazón: '70307',
        },
        Talamanca: {
            Bratsi: '70401',
            Sixaola: '70402',
            Cahuita: '70403',
            Telire: '70404',
        },
        Matina: {
            Matina: '70501',
            Batán: '70502',
            Carrandí: '70503',
        },
        Guácimo: {
            Guácimo: '70601',
            Mercedes: '70602',
            Pocora: '70603',
            'Río Jiménez': '70604',
            Duacarí: '70605',
        },
    },
};

/** Nombres de las siete provincias, en el orden de la DTA. */
export function provincias() {
    return Object.keys(TERRITORIO);
}

/** Cantones de una provincia. Una provincia desconocida no rompe la consulta. */
export function cantones(provincia) {
    return Object.keys(TERRITORIO[provincia] ?? {});
}

/** Distritos de un canton. */
export function distritos(provincia, canton) {
    return Object.keys(TERRITORIO[provincia]?.[canton] ?? {});
}

/** Codigo DTA de cinco digitos, o null si la combinacion no existe. */
export function codigoDistrito(provincia, canton, distrito) {
    return TERRITORIO[provincia]?.[canton]?.[distrito] ?? null;
}

/**
 * Rellena un <select> con las opciones dadas.
 *
 * Si `seleccionado` no esta en el catalogo se agrega igualmente. Una direccion
 * guardada antes de este catalogo, o corregida a mano, no puede desaparecer del
 * formulario solo porque el desplegable no la contemple: al guardar de nuevo se
 * perderia sin que nadie lo pidiera.
 */
export function llenarSelect(select, opciones, seleccionado = '', { vacio = 'Seleccione' } = {}) {
    const fragmento = document.createDocumentFragment();
    const marcador = document.createElement('option');
    marcador.value = '';
    marcador.textContent = vacio;
    fragmento.appendChild(marcador);

    const lista = seleccionado && !opciones.includes(seleccionado)
        ? [...opciones, seleccionado] : opciones;
    for (const opcion of lista) {
        const elemento = document.createElement('option');
        elemento.value = opcion;
        elemento.textContent = opcion;
        fragmento.appendChild(elemento);
    }
    select.replaceChildren(fragmento);
    select.value = seleccionado;
}

/** Rellena un <datalist> con sugerencias. No restringe lo que se puede escribir. */
export function llenarDatalist(datalist, opciones) {
    if (!datalist) return;
    const fragmento = document.createDocumentFragment();
    for (const opcion of opciones) {
        const elemento = document.createElement('option');
        elemento.value = opcion;
        fragmento.appendChild(elemento);
    }
    datalist.replaceChildren(fragmento);
}
