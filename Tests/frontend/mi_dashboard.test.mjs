import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    formatoDuracion, formatoPorcentaje, parametrosFiltro, rangoDias, rolDisponible, situacionComprador, textoComparacion,
} from '../../Public/js/mi-dashboard.js';

const js = readFileSync('Public/js/mi-dashboard.js', 'utf8');
const vista = readFileSync('Application/View/mi-dashboard/index.php', 'utf8');
const css = readFileSync('Public/css/mi-dashboard.css', 'utf8');
const htaccess = readFileSync('Public/.htaccess', 'utf8');

test('/mi-dashboard es una ruta con sesión y su API existe', () => {
    assert.match(htaccess, /RewriteRule \^mi-dashboard\/\?\$ mi-dashboard\.php \[END\]/);
    assert.match(htaccess, /RewriteRule \^api\/v1\/mi-dashboard\/\?\$ api\/mi-dashboard\.php \[END\]/);
    assert.ok(js.includes("entrar?next=mi-dashboard"));
    assert.ok(readFileSync('Public/js/public-ui.js', 'utf8').includes('href="mi-dashboard"'), 'enlace en el menú del avatar');
});

test('la vista carga el CSS y el JS con la misma versión y tiene una pestaña por rol', () => {
    assert.match(vista, /css\/mi-dashboard\.css\?v=dashboard-4/);
    assert.match(vista, /js\/mi-dashboard\.js\?v=dashboard-4/);
    assert.match(vista, /data-dashboard-rol="VENDEDOR"/);
    assert.match(vista, /data-dashboard-rol="COMPRADOR"/);
});

test('cada id que usa el script existe en la vista', () => {
    const ids = [...js.matchAll(/\$\('#([a-z0-9-]+)'\)/g)].map((m) => m[1]);
    assert.ok(ids.length > 5);
    for (const id of new Set(ids)) assert.ok(vista.includes(`id="${id}"`), `falta #${id} en la vista`);
});

test('pinta con DOM y solo con colores del sitio', () => {
    assert.ok(!/\.innerHTML\s*=|insertAdjacentHTML/.test(js), 'sin innerHTML');
    assert.ok(js.includes('safeImageUrl'), 'las fotos pasan por safeImageUrl');
    assert.ok(!/#[0-9a-f]{3,6}\b/i.test(css), 'sin colores nuevos');
    assert.ok(!css.includes('--tc-primary)') || css.includes('outline'), 'el coral solo en el foco');
    assert.ok(!/localStorage|sessionStorage/.test(js), 'la ubicación no se guarda');
});

test('formatos', () => {
    assert.equal(formatoDuracion(45), '45 min');
    assert.equal(formatoDuracion(200), '3 h 20 min');
    assert.equal(formatoDuracion(1485), '1 d 1 h');
    assert.equal(formatoDuracion(1560), '1 d 2 h');
    assert.equal(formatoDuracion(null), '—');
    assert.equal(formatoPorcentaje(null), '—');
    assert.equal(formatoPorcentaje(17.3, { signo: true }), '+17,3 %');
    assert.equal(formatoPorcentaje(-5.6, { signo: true }), '-5,6 %');
});

test('período: últimos N días en hora de Costa Rica', () => {
    // 10/11 a las 01:00 UTC todavía es 9/11 en Costa Rica.
    assert.deepEqual(rangoDias(7, new Date('2026-11-10T01:00:00Z')), { desde: '2026-11-03', hasta: '2026-11-09' });
});

test('parámetros: el vendedor no manda ubicación; el comprador manda radio y punto si lo compartió', () => {
    const valores = { periodo: 'todo', especieId: '1', tipoId: '', razaId: '3', provincia: 'Alajuela', radioKm: '25' };
    assert.equal(parametrosFiltro(valores, 'VENDEDOR', new Date(), { latitud: '10', longitud: '-84' }).toString(),
        'rol=VENDEDOR&especieId=1&razaId=3&provincia=Alajuela');
    assert.equal(parametrosFiltro(valores, 'COMPRADOR').toString(),
        'rol=COMPRADOR&especieId=1&razaId=3&provincia=Alajuela&radioKm=25');
    assert.match(parametrosFiltro(valores, 'COMPRADOR', new Date(), { latitud: '10.3', longitud: '-84.4' }).toString(),
        /latitud=10\.3&longitud=-84\.4$/);
    const rango = parametrosFiltro({ periodo: 'rango', desde: '2026-10-01', hasta: '2026-10-31' }, 'VENDEDOR');
    assert.equal(rango.toString(), 'rol=VENDEDOR&desde=2026-10-01&hasta=2026-10-31');
});

test('con un solo rol se abre el dashboard que la persona sí tiene', () => {
    assert.equal(rolDisponible('VENDEDOR', { vendedor: true, comprador: true }), 'VENDEDOR');
    assert.equal(rolDisponible('VENDEDOR', { vendedor: false, comprador: true }), 'COMPRADOR');
    assert.equal(rolDisponible('COMPRADOR', { vendedor: true, comprador: false }), 'VENDEDOR');
    assert.equal(rolDisponible('COMPRADOR', {}), null);
});

test('textos del comprador', () => {
    assert.equal(situacionComprador({ oportunidad: true, distanciaKm: 12.5 }), 'A 12,5 km');
    assert.equal(situacionComprador({ estado: 'ACTIVA', voyGanando: true }), 'Vas ganando');
    assert.equal(situacionComprador({ estado: 'ACTIVA', voyGanando: false }), 'Te superaron');
    assert.equal(situacionComprador({ estado: 'FINALIZADA', soyGanador: true }), 'La ganaste');
    assert.equal(situacionComprador({ estado: 'FINALIZADA', soyGanador: false }), 'La ganó otro comprador');
    assert.equal(textoComparacion({ precioActual: 900000, comparacion: { promedio: 880000, diferenciaPorcentaje: 2.3 } }),
        '₡900.000 frente a ₡880.000 (+2,3 %)');
    assert.equal(textoComparacion({ precioActual: null, precioBase: 780000, comparacion: { promedio: 880000, diferenciaPorcentaje: -11.4 } }),
        '₡780.000 frente a ₡880.000 (-11,4 %)');
    assert.equal(textoComparacion({ comparacion: { promedio: null } }), 'Sin animales comparables todavía');
});

test('gráficos: montos por mes en hora de Costa Rica, con los meses vacíos en 0', async () => {
    const { porMes, claveMes } = await import('../../Public/js/dashboard-graficos.js');
    // 1/10 a las 03:00 UTC todavía es 30/9 en Costa Rica.
    assert.equal(claveMes('2026-10-01T03:00:00Z'), '2026-09');
    assert.equal(claveMes('no es fecha'), null);
    const meses = porMes([
        { f: '2026-08-10T12:00:00Z', v: 100 },
        { f: '2026-10-05T12:00:00Z', v: 50 },
        { f: '2026-10-20T12:00:00Z', v: 25 },
    ], (i) => i.f, (i) => i.v);
    assert.deepEqual(meses.map((m) => [m.clave, m.valor, m.cantidad]), [['2026-08', 100, 1], ['2026-09', 0, 0], ['2026-10', 75, 2]]);
    assert.deepEqual(porMes([], (i) => i.f, (i) => i.v), []);
});

test('gráficos: un solo tono, tooltip con teclado, tabla y sin colores nuevos', () => {
    const graficos = readFileSync('Public/js/dashboard-graficos.js', 'utf8');
    assert.ok(!/\.innerHTML\s*=|insertAdjacentHTML/.test(graficos), 'sin innerHTML');
    assert.match(graficos, /addEventListener\('focus'/, 'el tooltip también sale con el teclado');
    assert.match(graficos, /Ver como tabla/, 'cada gráfico trae su tabla');
    assert.match(js, /dashboard-graficos\.js\?v=graficos-\d+/, 'import versionado');
    const reglas = css;
    assert.ok(reglas.includes('var(--tc-success)'), 'el tono de los datos');
    assert.ok(!/#[0-9a-f]{3,6}\b/i.test(reglas), 'sin colores nuevos');
});

test('estados de cada rol: el vendedor y el comprador cuentan lo suyo', async () => {
    const { claveEstado, contarEstados, textoPeriodo } = await import('../../Public/js/mi-dashboard.js');
    const vendidas = [{ estado: 'FINALIZADA' }, { estado: 'FINALIZADA' }, { estado: 'ACTIVA' }, { estado: 'DESIERTA' }, { estado: 'CANCELADA' }];
    assert.deepEqual(contarEstados(vendidas, 'VENDEDOR').map((e) => [e.clave, e.cantidad]),
        [['adjudicada', 2], ['vivo', 1], ['sin-ofertas', 1], ['cancelada', 1]]);
    const pujas = [
        { estado: 'FINALIZADA', soyGanador: true }, { estado: 'FINALIZADA', soyGanador: false },
        { estado: 'ACTIVA', voyGanando: true }, { estado: 'ACTIVA', voyGanando: false }, { estado: 'ACTIVA', voyGanando: false },
    ];
    assert.deepEqual(contarEstados(pujas, 'COMPRADOR').map((e) => [e.clave, e.cantidad]),
        [['ganada', 1], ['ganando', 1], ['superada', 2], ['perdida', 1]]);
    assert.equal(claveEstado({ oportunidad: true, estado: 'ACTIVA' }, 'COMPRADOR'), 'cerca');
    assert.equal(textoPeriodo({ periodo: '30' }), 'últimos 30 días');
    assert.equal(textoPeriodo({ periodo: 'todo' }), 'todo el tiempo');
    assert.equal(textoPeriodo({ periodo: 'rango', desde: '2026-10-01', hasta: '2026-10-31' }), 'del 2026-10-01 al 2026-10-31');
});

test('cada estado vecino se distingue también por textura, no solo por color', () => {
    const regla = (clase) => css.slice(css.indexOf(`.estado--${clase}`), css.indexOf('}', css.indexOf(`.estado--${clase}`)));
    assert.match(regla('vivo'), /repeating-linear-gradient\(45deg/);
    assert.match(regla('cancelada'), /repeating-linear-gradient\(135deg/);
    assert.match(css, /\.estado--adjudicada,\s*\.estado--ganada \{ background:var\(--tc-success\); \}/);
    assert.match(css, /\.estado--sin-ofertas,\s*\.estado--superada \{ background:var\(--tc-warning\); \}/);
});

test('montos cortos sobre columnas angostas', async () => {
    const { montoCorto, nombreMes } = await import('../../Public/js/dashboard-graficos.js');
    assert.equal(montoCorto(3600000), '₡3,6 M');
    assert.equal(montoCorto(720000), '₡720 mil');
    assert.equal(montoCorto(8500), '₡8.500');
    assert.equal(nombreMes('2026-10'), 'Oct 2026');
});
