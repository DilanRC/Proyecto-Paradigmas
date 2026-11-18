import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('dashboard administrativo tiene ruta limpia y entrada protegida', () => {
    const routes = read('Public/.htaccess');
    const gate = read('Public/js/shared/auth-gate.js');
    const login = read('Public/js/login.js');
    assert.ok(routes.includes('RewriteRule ^admin/dashboard/?$ dashboard.php [END]'));
    assert.match(gate, /'admin\/dashboard'/);
    assert.match(login, /'admin\/dashboard'/);
});

test('dashboard centraliza indicadores en una API admin autorizada en PHP sin exponer datos sensibles', () => {
    const view = read('Application/View/dashboard/index.php');
    const js = read('Public/js/dashboard.js');
    const endpoint = read('Public/api/admin-dashboard.php');
    const main = view.match(/<main\b[\s\S]*?<\/main>/i)?.[0];
    assert.ok(main, 'El dashboard debe tener un elemento main completo.');
    assert.ok(view.includes('id="dashboard-metrics"'), 'conserva los conteos de la red ganadera');
    assert.doesNotMatch(main, /<img\b/i, 'El contenido del dashboard no debe renderizar imágenes.');
    assert.doesNotMatch(main, /dashboard-gallery|dashboard-hero|dashboard-card__image/, 'sin banner ni contenedores decorativos');
    assert.ok(js.includes("const API = 'api/v1/admin/dashboard'"));
    assert.match(read('Public/.htaccess'), /RewriteRule \^api\/v1\/admin\/dashboard\/\?\$ api\/admin-dashboard\.php \[END\]/);
    assert.match(endpoint, /AdminAuthorization::require\(\$actor, \$conexion\)/, 'la autorización vive en PHP, no en el navegador');
    assert.match(endpoint, /fromGlobalsPermitiendoPersonaNoVinculada/, 'un administrador puede no tener Persona');
    assert.ok(!/\.innerHTML\s*=|insertAdjacentHTML/.test(js), 'sin innerHTML');
    assert.equal(/identificacionNumero|correoElectronico|telefono|password|token/.test(js), false);
    assert.match(view, /js\/dashboard\.js\?v=intel-6/);
    assert.match(view, /css\/admin-dashboard\.css\?v=dashboard-8/);
});

test('el panel no expone tablas ni lo que la plataforma no registra', async () => {
    const { procesos, nombreArea } = await import('../../Public/js/dashboard.js');
    const base = {
        subastas: { estados: { activas: { total: 1 }, adjudicadas: { total: 2 }, desiertas: { total: 0 }, cerradas: { total: 2 }, canceladas: { total: 0 } }, pujas: 3, compradores: { total: 2 }, exito: { porcentaje: 100 }, precioPromedio: { montoTotal: 10, valor: 5 }, incrementoPromedio: { porcentaje: 0 } },
        comercializacion: { interes: { registros: 0, personas: 0, publicaciones: 0 }, solicitudesCreadas: {}, solicitudesRespondidas: {}, ventasPorCanal: {} },
        transporte: { ofertasPorEstado: {}, solicitudesFletePorEstado: { ACEPTADA: 4 }, viajesRegistrados: { total: 0 }, viajesInstrumentados: false },
        inventario: { animalesPorEstado: {}, publicacionesPorEstado: {}, cambiosDeEstado: {}, publicacionesNuevas: 0, conSolicitudesPendientes: 0 },
    };
    const texto = JSON.stringify(procesos(base));
    assert.doesNotMatch(texto, /\btb[a-z]+|→|instrument|API_|Fuente/i, 'sin nombres de tablas, flechas ni jerga interna');
    assert.doesNotMatch(texto, /Viajes/, 'lo que no se registra no aparece, ni como cero');
    const vista = read('Application/View/dashboard/index.php');
    assert.doesNotMatch(vista, /\btb[a-z]+|→|instrumentar|api\/v1/i);
    assert.equal(nombreArea('PUBLICACION_INTERACCION'), 'Me interesa');
    assert.equal(nombreArea('ALGO_NUEVO'), 'Algo nuevo');
});

test('la búsqueda de personas responde al escribir desde 2 letras', () => {
    const js = read('Public/js/dashboard.js');
    assert.match(js, /addEventListener\('input', buscar\)/);
    assert.match(js, /q\.length < 2/);
    assert.doesNotMatch(read('Application/View/dashboard/index.php'), /id="intel-q"[^>]*minlength/);
});

test('los assets ganaderos pueden seguir siendo usados por el sitio público, pero no por el dashboard', () => {
    const path = 'Public/assets/dashboard/subasta-pasarela-costa-rica.png';
    assert.ok(statSync(new URL(`../../${path}`, import.meta.url)).size > 10000, path);
    assert.doesNotMatch(read('Application/View/dashboard/index.php'), /subasta-pasarela-costa-rica\.png/);
});

test('montos grandes en tarjeta: compactos desde un millón, exactos por debajo', async () => {
    const { formatoColonesCorto } = await import('../../Public/js/dashboard.js');
    assert.equal(formatoColonesCorto(950000), '₡950 000');
    assert.match(formatoColonesCorto(4320000), /^₡4,3\s?M$/);
    assert.match(formatoColonesCorto(20000000198), /^₡20\smil\sM$/);
    assert.equal(formatoColonesCorto(null), '—');
});

test('el alias se muestra solo si es distinto del nombre', async () => {
    const { conAlias } = await import('../../Public/js/dashboard.js');
    assert.equal(conAlias({ nombre: 'Lechería Demo', alias: 'lechería demo' }), 'Lechería Demo');
    assert.equal(conAlias({ nombre: 'Jeremi Castillo', alias: 'Abuelo' }), 'Jeremi Castillo (Abuelo)');
    assert.equal(conAlias({ nombre: 'Ana', alias: null }), 'Ana');
});
