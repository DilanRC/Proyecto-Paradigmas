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
    assert.match(view, /js\/dashboard\.js\?v=intel-1/);
    assert.match(view, /css\/admin-dashboard\.css\?v=dashboard-4/);
});

test('el árbol marca lo no instrumentado en vez de mostrar un cero', async () => {
    const { procesos, sumar, parametrosPeriodo } = await import('../../Public/js/dashboard.js');
    const base = {
        subastas: { estados: { activas: { total: 1 }, adjudicadas: { total: 2 }, desiertas: { total: 0 }, cerradas: { total: 2 }, canceladas: { total: 1 } },
            exito: { porcentaje: 100, finalizadas: 2 }, precioPromedio: { montoTotal: 500, valor: 250 }, incrementoPromedio: { porcentaje: 10 },
            compradores: { total: 3 }, pujas: 7 },
        comercializacion: { interes: { registros: 0, personas: 0, publicaciones: 0 }, solicitudesCreadas: {}, solicitudesRespondidas: {}, ventasPorCanal: {} },
        transporte: { ofertasPorEstado: {}, solicitudesFletePorEstado: { ACEPTADA: 4 }, viajesRegistrados: { total: 0 }, viajesInstrumentados: false },
        inventario: { animalesPorEstado: {}, publicacionesPorEstado: {}, cambiosDeEstado: {}, publicacionesNuevas: 0, conSolicitudesPendientes: 0 },
        instrumentacion: { viajesRealizados: { motivo: 'Sin flujo.' } },
    };
    const transporte = procesos(base).find((p) => p.clave === 'transporte');
    const viajes = transporte.nodos.find((n) => n.nombre === 'Viajes realizados');
    assert.equal(viajes.instrumentado, false);
    assert.equal(viajes.valor, 'Sin instrumentar', 'cuatro fletes aceptados no se convierten en viajes');
    assert.equal(viajes.evidencia, null);
    assert.equal(sumar({ A: 2, B: 3 }), 5);
    assert.deepEqual(parametrosPeriodo({ periodo: 'todo' }), {});
    assert.deepEqual(parametrosPeriodo({ periodo: '30' }, new Date('2026-11-10T01:00:00Z')), { desde: '2026-10-11', hasta: '2026-11-09' });
});

test('los assets ganaderos pueden seguir siendo usados por el sitio público, pero no por el dashboard', () => {
    const path = 'Public/assets/dashboard/subasta-pasarela-costa-rica.png';
    assert.ok(statSync(new URL(`../../${path}`, import.meta.url)).size > 10000, path);
    assert.doesNotMatch(read('Application/View/dashboard/index.php'), /subasta-pasarela-costa-rica\.png/);
});
