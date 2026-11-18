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

test('la vista invalida el JS del dashboard y mantiene su hoja de estilos', () => {
    assert.match(vista, /css\/mi-dashboard\.css\?v=dashboard-8/);
    assert.match(vista, /js\/mi-dashboard\.js\?v=dashboard-11/);
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

test('gráficos: montos en el tono de datos, tooltip con teclado, tabla y sin colores sueltos', () => {
    const graficos = readFileSync('Public/js/dashboard-graficos.js', 'utf8');
    assert.ok(!/\.innerHTML\s*=|insertAdjacentHTML/.test(graficos), 'sin innerHTML');
    assert.match(graficos, /addEventListener\('focus'/, 'el tooltip también sale con el teclado');
    assert.match(graficos, /Ver como tabla/, 'cada gráfico trae su tabla');
    assert.match(js, /dashboard-graficos\.js\?v=graficos-3/, 'import versionado');
    // Un monto no es un "éxito": las barras económicas usan --tc-data, no el verde de los estados.
    for (const barra of ['.grafico-columnas__barra', '.grafico-barras__barra', '.grafico-divergente__barra {']) {
        const regla = css.slice(css.indexOf(barra), css.indexOf('}', css.indexOf(barra)));
        assert.match(regla, /var\(--tc-data\)/, `${barra} en el tono de datos`);
    }
    assert.ok(!/#[0-9a-f]{3,6}\b/i.test(css), 'sin colores sueltos: todo sale de los tokens');
});

test('tokens semánticos definidos en los dos temas y con contraste AA', () => {
    const auth = readFileSync('Public/css/public-auth.css', 'utf8');
    const temas = [auth.slice(0, auth.indexOf("html[data-theme='light']")), auth.slice(auth.indexOf("html[data-theme='light']"))];
    const lum = (hex) => {
        const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };
    const contraste = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((m, n) => n - m); return (x + 0.05) / (y + 0.05); };
    for (const tema of temas) {
        const valor = (nombre) => tema.match(new RegExp(`--tc-${nombre}:(#[0-9a-f]{6})`, 'i'))?.[1];
        for (const token of ['success', 'info', 'warning', 'neutral-status', 'data']) {
            assert.ok(valor(token), `--tc-${token} en ambos temas`);
            for (const fondo of ['bg', 'surface']) {
                assert.ok(contraste(valor(token), valor(fondo)) >= 4.5, `--tc-${token} sobre --tc-${fondo} ≥ 4,5:1`);
            }
        }
    }
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

test('cada estado tiene su color semántico y una segunda señal (texto e ícono; rayas en el ámbar)', async () => {
    const { ESTADOS } = await import('../../Public/js/mi-dashboard.js');
    assert.match(css, /\.estado--adjudicada,\s*\.estado--ganada \{ background:var\(--tc-success\); \}/);
    assert.match(css, /\.estado--vivo,\s*\.estado--ganando \{ background:var\(--tc-info\); \}/, 'activa ≠ adjudicada');
    assert.match(css, /\.estado--cancelada,\s*\.estado--perdida \{ background:var\(--tc-neutral-status\); \}/);
    const ambar = css.slice(css.indexOf('.estado--sin-ofertas,'), css.indexOf('}', css.indexOf('.estado--sin-ofertas,')));
    assert.match(ambar, /repeating-linear-gradient\(45deg, var\(--tc-warning\)/, 'ámbar con rayas: bajo protanopía queda cerca del verde');
    for (const lista of Object.values(ESTADOS)) {
        for (const e of lista) assert.ok(e.icono && e.etiqueta && e.descripcion, `${e.clave} con ícono, nombre y descripción`);
        assert.equal(new Set(lista.map((e) => e.icono)).size, lista.length, 'íconos distintos');
    }
});

test('costo de adquisición: un flete sin precio no se suma como cero', async () => {
    const { textoCosto } = await import('../../Public/js/mi-dashboard.js');
    assert.deepEqual(textoCosto({ subastas: 0 }), { valor: '—', detalle: 'Sin pujas activas' });
    assert.equal(textoCosto({ subastas: 2, completo: false, sinFlete: 1, total: null }).valor, 'Incompleto');
    assert.match(textoCosto({ subastas: 2, completo: false, sinFlete: 1 }).detalle, /1 subasta sin precio de flete/);
    assert.equal(textoCosto({ subastas: 1, completo: true, total: 550000 }).valor, '₡550.000');
    assert.doesNotMatch(js, /costoFlete \?\? 0/, 'nunca flete desconocido = 0');
});

test('fecha del hecho: fin programado al cerrar, cancelación, o inicio si sigue activa', async () => {
    const { fechaHecho } = await import('../../Public/js/mi-dashboard.js');
    const s = { fechaInicio: 'I', fechaFin: 'F', fechaCierre: 'C' };
    assert.equal(fechaHecho({ ...s, estado: 'FINALIZADA' }), 'F', 'no la hora en que se procesó el cierre');
    assert.equal(fechaHecho({ ...s, estado: 'DESIERTA' }), 'F');
    assert.equal(fechaHecho({ ...s, estado: 'CANCELADA' }), 'C');
    assert.equal(fechaHecho({ ...s, estado: 'ACTIVA' }), 'I');
});

test('cada KPI con ejemplares filtra la tabla por sus ids exactos', () => {
    assert.match(js, /function botonEvidencia\(ids, etiqueta, alFiltrar\)/);
    assert.match(js, /ids\.has\(Number\(s\.subastaId\)\)/, 'el filtro usa los ids del backend, no una aproximación');
    for (const id of ['precioPromedio.ids', 'exito.ids', 'incrementoPromedio.ids', 'compradores.ids', 'tiempoAdjudicacion.ids',
        'sinOfertas.ids', 'montoAdjudicado.ids', 'adj.ids', 'pujasActivas.ids', 'comparacion.ids', 'op.ids', 'idsSinFlete']) {
        assert.ok(js.includes(id), `evidencia conectada: ${id}`);
    }
});

test('montos cortos sobre columnas angostas', async () => {
    const { montoCorto, nombreMes } = await import('../../Public/js/dashboard-graficos.js');
    assert.equal(montoCorto(3600000), '₡3,6 M');
    assert.equal(montoCorto(720000), '₡720 mil');
    assert.equal(montoCorto(8500), '₡8.500');
    assert.equal(nombreMes('2026-10'), 'Oct 2026');
});

test('la vista reserva el selector de rol y los filtros de comprador hasta que el servidor confirma los roles', () => {
    const vista = readFileSync('Application/View/mi-dashboard/index.php', 'utf8');
    const css = readFileSync('Public/css/mi-dashboard.css', 'utf8');
    const js = readFileSync('Public/js/mi-dashboard.js', 'utf8');
    assert.match(vista, /<main class="activity-main dash" data-roles-pendientes>/);
    assert.doesNotMatch(vista, /class="dash-segmento"[^>]*\bhidden\b/, 'el selector ocupa su lugar desde el primer pintado');
    assert.doesNotMatch(vista, /tindercows:profile|sessionStorage/, 'ninguna caché decide qué actividad ve la persona');
    assert.match(css, /\.dash\[data-roles-pendientes\] \.dash-segmento,[\s\S]*?visibility:hidden/);
    // base.css impone [hidden] { display:none !important }: la reserva no puede apoyarse en hidden.
    assert.doesNotMatch(vista, /<label class="dash-campo" data-solo-comprador hidden|data-solo-comprador hidden><i|data-dashboard-rol="[A-Z]+" hidden/);
    assert.match(css, /\.dash\[data-roles-pendientes\] \[data-solo-comprador\],\s*\.dash \[data-reservado\] \{ visibility:hidden; \}/);
    assert.match(css, /#dashboard-panel \{ min-height:75vh; \}/, 'error y vacío no suben el pie de página');
    // Rol inicial Comprador (toda cuenta completa lo tiene): pestaña y panel coinciden desde el HTML.
    assert.match(js, /const estado = \{ rol: 'COMPRADOR',/);
    assert.match(vista, /aria-selected="true" data-dashboard-rol="COMPRADOR"/);
    assert.match(vista, /role="tabpanel" aria-labelledby="tab-comprador"/);
    assert.match(js, /campo\.toggleAttribute\('data-reservado', sinComprador\)/);
    // Solo la respuesta del servidor (pintarRoles) termina la reserva.
    assert.match(js, /function pintarRoles\(roles\) \{[\s\S]*?removeAttribute\('data-roles-pendientes'\)/);
    assert.doesNotMatch(js, /rolesDesdePerfil|publicProfileForSession/);
});

test('comparables y oportunidades muestran sus ejemplares en la tarjeta, ordenados', async () => {
    const { ordenarEjemplares, EJEMPLARES_EN_TARJETA } = await import('../../Public/js/mi-dashboard.js');
    const c = (id, d) => ({ subastaId: id, comparacion: { diferenciaPorcentaje: d } });
    assert.deepEqual(ordenarEjemplares([c(1, 12), c(2, -40), c(3, 0)], 'comparables').map((s) => s.subastaId), [2, 3, 1], 'del más barato al más caro');
    const o = (id, km, fin) => ({ subastaId: id, distanciaKm: km, fechaFin: fin });
    assert.deepEqual(ordenarEjemplares([o(1, null, '2026-11-20'), o(2, 30, '2026-11-25'), o(3, 5, '2026-11-30'), o(4, null, '2026-11-19')], 'cercania').map((s) => s.subastaId),
        [3, 2, 4, 1], 'primero la más cercana; sin distancia, la que cierra antes');
    assert.equal(EJEMPLARES_EN_TARJETA, 4);
    assert.match(js, /ejemplares\.lista\.length > EJEMPLARES_EN_TARJETA \? botonEvidencia/, '«Ver en la tabla» solo si quedan ejemplares fuera');
});
