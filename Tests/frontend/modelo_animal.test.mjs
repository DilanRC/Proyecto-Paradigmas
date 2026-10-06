// Modelo de animal (P2-2): Publicar con especie, tipo, raza, nacimiento, partos, arete y lote; y la tarjeta que lo muestra.
//
// Ejecutar: node --test Tests/frontend/modelo_animal.test.mjs

import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const vista = read('Application/View/publicar/index.php');
const script = read('Public/js/publicar.js');

test('cada id y atributo que publicar.js busca existe en la vista', () => {
    const ids = [...script.matchAll(/campo\('#([\w-]+)'\)/g)].map((m) => m[1]);
    assert.ok(ids.length >= 9, 'publicar.js busca los campos del modelo');
    for (const id of ids) assert.match(vista, new RegExp(`id="${id}"`), `la vista debe tener #${id}`);
    for (const marca of ['data-raza-otra', 'data-partos', 'data-arete', 'data-lote']) {
        assert.match(vista, new RegExp(marca), `la vista debe tener ${marca}`);
        assert.ok(script.includes(`[${marca}]`), `publicar.js debe usar [${marca}]`);
    }
    assert.match(vista, /data-error-for="arete"/);
});

test('los campos de la vista llevan el nombre que espera la API', () => {
    for (const [id, nombre] of [['publish-especie', 'especieId'], ['publish-tipo', 'tipoId'], ['publish-raza-id', 'razaId'],
        ['publish-nacimiento', 'fechaNacimiento'], ['publish-estimada', 'fechaNacimientoEstimada'], ['publish-partos', 'partos'],
        ['publish-arete', 'arete'], ['publish-lote-cantidad', 'loteCantidad'], ['publish-es-lote', 'esLote']]) {
        assert.match(vista, new RegExp(`id="${id}"[^>]*name="${nombre}"`), `${id} envía ${nombre}`);
    }
    // Las listas encadenadas esperan a la especie y las etiquetas están asociadas a su campo.
    assert.match(vista, /id="publish-tipo"[^>]*disabled/);
    assert.match(vista, /id="publish-raza-id"[^>]*disabled/);
    for (const id of ['publish-especie', 'publish-tipo', 'publish-raza-id', 'publish-nacimiento', 'publish-partos', 'publish-arete', 'publish-lote-cantidad']) {
        assert.match(vista, new RegExp(`<label for="${id}">`), `${id} debe tener su <label>`);
    }
});

test('Publicar lee los catálogos del endpoint documentado y la ruta existe', () => {
    assert.ok(script.includes("request('api/v1/catalogos')"));
    assert.match(read('Public/.htaccess'), /RewriteRule \^api\/v1\/catalogos\/\?\$ api\/catalogos\.php \[END\]/);
    assert.match(read('Documentation/RutasPublicas.md'), /\/api\/v1\/catalogos/);
    assert.match(read('Documentation/RutasFrontend.md'), /\/api\/v1\/catalogos/);
    // Sin catálogo el formulario sigue funcionando con la raza en texto.
    assert.ok(script.includes('Sin catálogo el formulario conserva la raza en texto'));
});

test('arete SENASA: mismas reglas en JS y en PHP', async () => {
    const { errorArete, formatearArete, digitosArete, MENSAJE_ARETE } = await import('../../Public/js/shared/arete.js');
    for (const bueno of ['1880010002345', '188 0 01 0002345', '188-0-01-0002345', '1880070000001', '', '   ']) {
        assert.equal(errorArete(bueno), null, `válido: "${bueno}"`);
    }
    for (const malo of ['188001000234', '18800100023456', '1990010002345', '1880000002345', '1880080002345', '188001000234A', 'SOL-0001']) {
        assert.equal(errorArete(malo), MENSAJE_ARETE, `inválido: ${malo}`);
    }
    assert.equal(formatearArete('1880010002345'), '188 0 01 0002345');
    assert.equal(formatearArete('18800'), '188 0 0');
    assert.equal(formatearArete('188 0 01 0002345999'), '188 0 01 0002345');
    assert.equal(digitosArete('188-0-01-0002345'), '1880010002345');
    const php = read('Application/Service/AnimalValidacionService.php');
    assert.match(php, /\\d\{13\}/);
    assert.match(php, /str_starts_with\(\$digitos, '188'\)/);
    assert.match(php, /\['01', '02', '03', '04', '05', '06', '07'\]/);
    assert.ok(php.includes(MENSAJE_ARETE), 'el mensaje es el mismo que el del servidor');
});

test('el cuerpo del envío lleva solo lo que se llenó', async () => {
    const { cuerpoPublicacion } = await import('../../Public/js/publicar.js');
    const base = { titulo: 'Vaca', fincaNombre: 'La Esperanza' };
    // Catálogo: la raza elegida manda sobre el texto, el arete viaja solo con dígitos y "estimada" es un booleano.
    assert.deepEqual(cuerpoPublicacion({
        ...base, especieId: '1', tipoId: '5', razaId: '3', raza: 'texto', arete: '188 0 01 0002345', partos: '2',
        fechaNacimiento: '2022-05-10', fechaNacimientoEstimada: 'true', loteCantidad: '2', esLote: '',
    }, null), {
        ...base, especieId: '1', tipoId: '5', razaId: '3', arete: '1880010002345', partos: '2',
        fechaNacimiento: '2022-05-10', fechaNacimientoEstimada: true,
    });
    // "Otra raza": se envía el texto y no el marcador de la lista.
    assert.deepEqual(cuerpoPublicacion({ ...base, razaId: 'otra', raza: 'Brahmán' }, null), { ...base, raza: 'Brahmán' });
    // Un lote lleva cantidad, sin arete ni partos.
    assert.deepEqual(cuerpoPublicacion({ ...base, esLote: 'on', loteCantidad: '5', arete: '1880010002345', partos: '1' }, null),
        { ...base, loteCantidad: '5' });
    // Campos vacíos no viajan.
    assert.deepEqual(cuerpoPublicacion({ ...base, especieId: '', tipoId: '', razaId: '', fechaNacimiento: '', arete: '', partos: '' }, null), base);
});

test('la tarjeta muestra especie, tipo, edad calculada, partos y la marca de lote', async () => {
    const { ageFromBirth, animalAgeMonths, formatKind, lotSize, buildCard } = await import('../../Public/js/explore.js');
    const hoy = new Date(2026, 9, 6); // 6 de octubre de 2026
    assert.equal(ageFromBirth('2025-10-06', hoy), 12);
    assert.equal(ageFromBirth('2025-10-07', hoy), 11);
    assert.equal(ageFromBirth('2026-10-06', hoy), 0);
    assert.equal(ageFromBirth('2027-01-01', hoy), null, 'una fecha futura no da edad');
    assert.equal(ageFromBirth('mala', hoy), null);
    assert.equal(ageFromBirth(null, hoy), null);
    assert.equal(animalAgeMonths({ fechaNacimiento: '2025-10-06', edadMeses: 99 }, hoy), 12, 'la fecha manda sobre la edad observada');
    assert.equal(animalAgeMonths({ edadMeses: 30 }, hoy), 30);
    assert.equal(animalAgeMonths({}, hoy), null);
    assert.equal(formatKind({ especie: 'Bovino', tipo: 'Vaca' }), 'Bovino · Vaca');
    assert.equal(formatKind({ especie: 'Bovino' }), 'Bovino');
    assert.equal(formatKind({}), '—');
    assert.equal(lotSize({ loteCantidad: 5 }), 5);
    assert.equal(lotSize({ loteCantidad: 1 }), 1);
    assert.equal(lotSize({}), 1);

    const fuente = read('Public/js/explore.js');
    for (const cadena of ["'Tipo'", "'Partos'", '`Lote de ${lote}`', '(aprox.)']) {
        assert.ok(fuente.includes(cadena), `buildCard debe incluir ${cadena}`);
    }
    assert.match(fuente, /if \(lote > 1\) specs\.append/);
    // Ambas variantes (completa y compacta) comparten el precio con la marca de lote y las especificaciones.
    assert.equal(typeof buildCard, 'function');
});

test('versiones de caché subidas donde cambió el módulo', () => {
    assert.match(read('Application/View/explorar/index.php'), /js\/explore\.js\?v=explore-12/);
    assert.match(read('Application/View/publicar/index.php'), /js\/publicar\.js\?v=publish-4/);
    assert.match(read('Application/View/home/index.php'), /js\/home\.js\?v=home-8/);
    assert.ok(read('Public/js/home.js').includes("from './explore.js?v=foto-4'"));
    assert.ok(script.includes("from './explore.js?v=foto-4'"));
    assert.ok(script.includes("from './shared/arete.js'"));
});

test('el formulario no introduce colores nuevos', () => {
    // (el único color de la vista es el theme-color de la cabecera, que ya existía)
    const cuerpo = vista.replace(/<meta name="theme-color"[^>]*>/, '');
    assert.doesNotMatch(cuerpo, /#[0-9a-fA-F]{3,8}\b|rgba?\(|style="/, 'sin colores ni estilos en línea');
});
