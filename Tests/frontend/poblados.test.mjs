// Centros poblados y su busqueda tolerante al defecto de la fuente.

import assert from 'node:assert/strict';
import test from 'node:test';

import { POBLADOS, buscarPoblados, normalizar, pobladosDe } from '../../Public/js/shared/poblados.js';
import { cantones, codigoDistrito, distritos, provincias } from '../../Public/js/shared/territorio.js';

const CODIGOS = new Set(provincias()
    .flatMap((p) => cantones(p).flatMap((c) => distritos(p, c).map((d) => codigoDistrito(p, c, d)))));

test('hay una entrada por cada uno de los 494 distritos', () => {
    assert.equal(Object.keys(POBLADOS).length, 494);
});

test('ninguna localidad cuelga de un distrito inexistente', () => {
    const huerfanos = Object.keys(POBLADOS).filter((codigo) => !CODIGOS.has(codigo));
    assert.deepEqual(huerfanos, [], `codigos sin distrito: ${huerfanos.join(', ')}`);
});

test('el total es 13274, tras reparar con INEC y deduplicar', () => {
    const total = Object.values(POBLADOS).reduce((n, lista) => n + lista.length, 0);
    assert.equal(total, 13274);
});

test('solo un distrito se queda sin localidad publicada', () => {
    assert.equal(Object.values(POBLADOS).filter((lista) => lista.length === 0).length, 1);
});

test('no hay localidades repetidas dentro de un distrito', () => {
    for (const [codigo, lista] of Object.entries(POBLADOS)) {
        assert.equal(new Set(lista).size, lista.length, `${codigo} repite localidades`);
    }
});

test('un distrito desconocido devuelve lista vacia y no rompe', () => {
    assert.deepEqual(pobladosDe('99999'), []);
    assert.deepEqual(buscarPoblados('99999', 'algo'), []);
});

// --- normalizacion -----------------------------------------------------------

test('normalizar ignora acentos y mayusculas', () => {
    assert.equal(normalizar('Pérez Zeledón'), normalizar('perez zeledon'));
    assert.equal(normalizar('SAN JOSÉ'), normalizar('san jose'));
});

test('normalizar ya NO borra "nd": el apano se retiro al reparar los datos', () => {
    // Hasta DEC-FRONT-14 se quitaba "nd" de la consulta para alcanzar los
    // nombres mutilados del IGN. Reparados con INEC, ese atajo solo podria
    // igualar cadenas que de verdad son distintas.
    assert.notEqual(normalizar('Tamarindo'), normalizar('Tamario'));
    assert.notEqual(normalizar('Llano Grande'), normalizar('Llano Grae'));
    assert.notEqual(normalizar('Condominio'), normalizar('Coominio'));
});

test('normalizar colapsa los espacios repetidos', () => {
    assert.equal(normalizar('  Mata   Redonda '), 'mata redonda');
});

test('normalizar no confunde nombres que de verdad son distintos', () => {
    assert.notEqual(normalizar('Guácimo'), normalizar('Guápiles'));
    assert.notEqual(normalizar('San Isidro'), normalizar('San Rafael'));
});

test('normalizar tolera nulos y vacios', () => {
    assert.equal(normalizar(null), '');
    assert.equal(normalizar(undefined), '');
    assert.equal(normalizar('   '), '');
});

// --- busqueda ----------------------------------------------------------------

test('los nombres reparados con INEC 2024 quedan escritos bien', () => {
    // Cruce por codigo de distrito contra el shapefile del INEC, que si
    // conserva "nd". Ver Documentation/correcciones-localidades.csv.
    for (const [codigo, correcto, roto] of [
        ['10108', 'Mata Redonda', 'Mata Redoa'],
        ['50309', 'Tamarindo', 'Tamario'],
        ['30110', 'Llano Grande', 'Llano Grae'],
        ['10106', 'Méndez', 'Méez'],
        ['10105', 'Indiana', 'Iiana'],
        ['10201', 'La Condesa', 'La Coesa'],
    ]) {
        assert.ok(pobladosDe(codigo).includes(correcto), `${codigo}: falta ${correcto}`);
        assert.ok(!pobladosDe(codigo).includes(roto), `${codigo}: sobrevive ${roto}`);
    }
});

test('la reparacion conserva las tildes y la caja del IGN', () => {
    // INEC publica MENDEZ, todo en mayuscula y sin tilde. Solo se tomo de ahi
    // donde va la secuencia perdida, no la grafia.
    assert.ok(pobladosDe('10106').includes('Méndez'));
    assert.ok(!pobladosDe('10106').includes('MENDEZ'));
    assert.ok(pobladosDe('10109').includes('Rincón Grande'));
});

test('escribir el nombre correcto encuentra el registro', () => {
    const resultado = buscarPoblados('50309', 'Tamarindo');
    assert.ok(resultado.includes('Tamarindo'));
    // Los vecinos tambien se repararon: eran "Tamario Diria" y "Palmas de Tamario".
    assert.ok(resultado.some((n) => n !== 'Tamarindo' && n.includes('Tamarindo')));
});

test('341 nombres recuperaron su "nd"; antes no lo tenia ninguno', () => {
    const con = Object.values(POBLADOS).flat().filter((n) => normalizar(n).includes('nd'));
    assert.equal(con.length, 341);
});

test('las coincidencias por prefijo van antes que las del interior', () => {
    const resultado = buscarPoblados('10101', 'san', { limite: 25 });
    const primerInterior = resultado.findIndex((n) => !normalizar(n).startsWith(normalizar('san')));
    if (primerInterior !== -1) {
        const posteriores = resultado.slice(primerInterior);
        assert.ok(
            posteriores.every((n) => !normalizar(n).startsWith(normalizar('san'))),
            'un resultado por prefijo aparece despues de uno por interior',
        );
    }
});

test('una consulta vacia sugiere el principio de la lista', () => {
    const resultado = buscarPoblados('10101', '');
    assert.ok(resultado.length > 0);
    assert.deepEqual(resultado, pobladosDe('10101').slice(0, resultado.length));
});

test('el limite se respeta', () => {
    assert.ok(buscarPoblados('10101', '', { limite: 3 }).length <= 3);
    assert.ok(buscarPoblados('10101', 'a', { limite: 5 }).length <= 5);
});

test('la busqueda solo mira el distrito pedido', () => {
    const codigo = '70605';
    for (const nombre of buscarPoblados(codigo, '', { limite: 50 })) {
        assert.ok(pobladosDe(codigo).includes(nombre), `${nombre} no pertenece a ${codigo}`);
    }
});

test('Duacari, el distrito reconciliado, si trae sus localidades', () => {
    assert.ok(pobladosDe('70605').length >= 18);
});

// --- proteccion contra reparaciones inventadas --------------------------------
//
// Un modelo estadistico propuso en su dia insertar "nd" donde "sonaba probable",
// y eso convertia nombres legitimos en disparates: Roads en Rondads, Hanoi en
// Hanondi, McKenzie en McKezinde. La ausencia de un nombre en INEC no prueba que
// este mutilado; solo prueba que INEC no lo cubre. Estas pruebas fijan que esas
// propuestas nunca llegaron al catalogo.

test('los nombres legitimos que la heuristica queria cambiar siguen intactos', () => {
    const todos = new Set(Object.values(POBLADOS).flat());
    for (const nombre of [
        'Roads', 'Hanoi', 'Fields', 'McKenzie', 'Kezia', 'Wong', 'Tirol',
        'Zaida', 'Zafira', 'Williamsburg', 'Bohío', 'Callao', 'Yolaa',
    ]) {
        assert.ok(todos.has(nombre), `${nombre}: desaparecio del catalogo`);
    }
});

test('ninguna de las reconstrucciones inventadas entro en el catalogo', () => {
    const todos = new Set(Object.values(POBLADOS).flat().map((n) => normalizar(n)));
    for (const invento of [
        'Rondads', 'Hanondi', 'Findelds', 'McKezinde', 'Kezinda', 'Wndong',
        'Tindrol', 'Zandida', 'Zafindra', 'Willindamsburg', 'Bohindo',
        'Callando', 'Undaca', 'Ondurut', 'Sukinda', 'Sand Vicente',
    ]) {
        assert.ok(!todos.has(normalizar(invento)), `entro una reconstruccion inventada: ${invento}`);
    }
});

test('la palabra reconstruida no basta: hace falta el par confirmado', () => {
    // "Sa Vicente" no es perdida de "nd" sino de "n", y es el unico caso del
    // catalogo. Convertirlo en "Sand Vicente" porque SAND existe en INEC seria
    // exactamente el error que estas pruebas impiden.
    const todos = new Set(Object.values(POBLADOS).flat());
    assert.ok(todos.has('Sa Vicente'), 'el original debe conservarse hasta que se revise');
    assert.ok(!todos.has('Sand Vicente'));
});
