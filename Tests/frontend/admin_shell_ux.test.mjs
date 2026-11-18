import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const adminCss = read('../../Public/css/admin-v3.css');
const adminJs = read('../../Public/js/shared/admin-ui.js');
const apiJs = read('../../Public/js/shared/api.js');
const publicCss = read('../../Public/css/public-auth.css');

const views = ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos'];

test('el admin comparte la paleta de los logos con el sitio publico', () => {
    for (const token of ['#151a18', '#2f3c2d', '#d24f28', '#eedbca', '#fef7ec', '#394332']) {
        assert.ok(publicCss.includes(token), `el sitio público debe conservar ${token}`);
        assert.ok(adminCss.includes(token), `el admin debe compartir ${token}`);
    }
    assert.ok(adminCss.includes("html[data-theme='light'] body.rural-panel"));
});

test('el shell existente se mejora desde un bootstrap compartido, no con cinco implementaciones', () => {
    const gate = apiJs.match(/^import '(\.\/auth-gate\.js\?v=[^']+)';/m)?.[1];
    assert.ok(gate, 'api inicializa el gate compartido con version');
    assert.match(apiJs, /^import '\.\/admin-ui\.js\?v=[^']+';/m);
    for (const source of [adminJs, read('../../Public/js/login.js'), read('../../Public/js/public-ui.js')]) {
        const imported = source.match(/from '(?:\.\/shared\/|\.\/)(auth-gate\.js\?v=[^']+)'/)?.[1];
        assert.equal(imported, gate.slice(2), 'cada consumidor comparte la misma URL completa de auth-gate');
    }
    assert.ok(adminJs.includes("const ADMIN_CSS = 'css/admin-v3.css?v=admin-10'"));
    assert.ok(adminJs.includes('enhanceSidebarAccount();'));
    assert.ok(adminJs.includes('enhancePageHeader();'));
    assert.ok(adminJs.includes('enhanceFilters();'));
    assert.ok(adminJs.includes('enhanceTable();'));

    for (const view of views) {
        const html = read(`../../Application/View/${view}/index.php`);
        assert.equal(html.includes('admin-v3.css'), false, `${view} no debe duplicar imports del shell`);
        const parcial = html.indexOf("shared/admin-estilos.php");
        assert.ok(parcial > 0 && parcial < html.indexOf('</head>'), `${view} carga los estilos del shell en el head (sin CLS)`);
        assert.ok(html.lastIndexOf('rel="stylesheet"', parcial) < parcial && html.indexOf('rel="stylesheet"', parcial) === -1,
            `${view}: los estilos del shell van después de los de la página (mismo orden que cuando los inyectaba JS)`);
    }
    const estilos = read('../../Application/View/shared/admin-estilos.php');
    for (const constante of ['ADMIN_CSS', 'ADMIN_SIDEBAR_CSS', 'ADMIN_REFINEMENTS_CSS']) {
        const url = adminJs.match(new RegExp(`const ${constante} = '([^']+)'`))[1];
        assert.ok(estilos.includes(`href="${url}"`), `admin-estilos.php y admin-ui.js usan la misma URL de ${constante}`);
    }
    assert.ok(estilos.includes("'tindercows:admin-sidebar-collapsed'") && adminJs.includes("ADMIN_SIDEBAR_KEY = 'tindercows:admin-sidebar-collapsed'"),
        'el estado del sidebar se aplica antes de pintar con la misma clave');
});

test('los botones secundarios del admin conservan contraste en ambos temas y al cargar', () => {
    assert.ok(adminCss.includes('.rural-panel .button--secondary {'));
    assert.ok(adminCss.includes('background:var(--admin-surface-2) !important;'));
    assert.ok(adminCss.includes('.rural-panel .button--secondary:disabled {'));
    assert.ok(adminCss.includes('opacity:1 !important;'));
});

test('los dialogos administrativos permanecen ocultos hasta abrirse', () => {
    assert.ok(adminCss.includes('.rural-panel dialog.modal:not([open]),'));
    assert.ok(adminCss.includes('display:none !important;'));
});

test('la navegación y utilidades mantienen icono mas texto visible', () => {
    assert.ok(adminCss.includes('.rural-panel__nav-item::before'));
    assert.ok(adminCss.includes('.admin-account-card__actions .rural-panel__admin-link'));
    assert.ok(adminJs.includes("siteLink.textContent = 'Ir al sitio'"));
    assert.ok(adminJs.includes("logoutLink.textContent = 'Cerrar sesión'"));
    assert.ok(adminJs.includes('theme-toggle__label'));
});

test('los filtros usan solo capacidades reales y ofrecen limpiar sin reload', () => {
    assert.ok(adminJs.includes("'admin/productores':"));
    assert.ok(adminJs.includes("search: 'Nombre, identificación, correo'"));
    assert.ok(adminJs.includes("search: 'Placa, VIN o modelo'"));
    assert.ok(adminJs.includes("search: 'Nombre o descripción'"));
    assert.ok(adminJs.includes("className = 'admin-clear-filters'"));
    assert.ok(adminJs.includes("dispatchEvent(new Event('input'"));
    assert.ok(adminJs.includes("dispatchEvent(new Event('change'"));
    assert.equal(adminJs.includes('location.reload'), false, 'limpiar filtros no debe recargar la página');
});

test('acciones de tabla quedan centradas y no vuelven a ser iconos sin texto', () => {
    assert.ok(adminCss.includes('.rural-panel td.row-actions {'));
    assert.ok(adminCss.includes('text-align:center !important;'));
    assert.ok(adminCss.includes('.rural-panel .row-actions .action {'));
    assert.ok(adminCss.includes('display:inline-flex !important;'));
    assert.ok(adminCss.includes('font-size:10px !important;'));
    assert.equal(adminCss.includes('.rural-panel .row-actions .action {\n    font-size:0'), false);
    assert.ok(adminJs.includes("label.className = 'admin-actions-heading'"));
});

test('tabla y panel contemplan desktop y adaptación a tarjetas en móvil', () => {
    assert.ok(adminJs.includes("table.classList.add('admin-table')"));
    assert.ok(adminCss.includes('@media (max-width:900px)'));
    assert.ok(adminCss.includes('content:attr(data-label)'));
    assert.ok(adminCss.includes('overflow-x:auto !important;'));
});

test('el mapa opcional de finca conserva la paleta del panel administrativo', () => {
    assert.match(adminCss, /\.rural-panel \.finca-fila__mapa \.farm-map-picker,[\s\S]*?\.rural-panel \.farm-map-picker \{/);
    assert.ok(adminCss.includes('background:var(--admin-surface-3);'));
});

test('ícono, filtros y «Limpiar» vienen del servidor con la misma información que MODULES (sin saltos al cargar)', () => {
    const marco = read('../../Application/View/shared/admin-marco.php');
    const inicio = adminJs.indexOf('const MODULES = {');
    const modulos = Function(`return (${adminJs.slice(inicio + 16, adminJs.indexOf('\n};', inicio) + 2)})`)();
    for (const [ruta, { icon, hint }] of Object.entries(modulos)) {
        const php = hint.replaceAll("'", "\\'");
        assert.ok(marco.includes(`'${ruta}' => ['icon' => '${icon}', 'hint' => '${php}']`), `admin-marco.php difiere de MODULES en ${ruta}`);
    }
    assert.ok(read('../../Application/View/shared/admin-estilos.php').includes("require_once __DIR__ . '/admin-marco.php'"));
    const rutas = { adminfletes: 'admin/fletes', adminsubastas: 'admin/subastas', pagometodos: 'admin/metodos-pago' };
    const todas = ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos', 'publicaciones', 'adminfletes', 'adminsubastas', 'documentos', 'catalogos', 'administradores', 'bitacora'];
    for (const view of todas) {
        const html = read(`../../Application/View/${view}/index.php`);
        if (!html.includes('class="page-header"')) continue;
        const ruta = rutas[view] ?? `admin/${view}`;
        assert.ok(html.includes(`<?= admin_encabezado_icono('${ruta}') ?>`), `${view}: ícono del encabezado desde el servidor`);
        if (html.includes('<div class="tools">')) {
            assert.ok(html.includes(`<?= admin_filtros_cabeza('${ruta}') ?>`) && html.includes('<?= admin_filtros_limpiar() ?>'), `${view}: filtros desde el servidor`);
            assert.equal(/class="search"><span class="screen-reader-only"/.test(html), false, `${view}: la etiqueta Buscar ya es visible`);
        }
    }
    assert.match(adminJs, /if \(!tools\.querySelector\('\.admin-filter-heading'\)\)/, 'admin-ui no duplica el encabezado');
    assert.match(adminJs, /let clear = tools\.querySelector\('\.admin-clear-filters'\)/, 'admin-ui reutiliza el botón del servidor');
});
