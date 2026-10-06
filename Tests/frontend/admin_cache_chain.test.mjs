// Cadena de caché de las rutas admin (MEMORIA.md, Cuidados #11). En vez de fijar
// un número de versión, exige que toda la cadena use la misma: si alguien sube
// auth-gate y olvida un módulo, esa pantalla queda en blanco o sin ícono.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8');
const MODULOS_ADMIN = ['dashboard', 'productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos', 'publicaciones', 'administradores', 'bitacora', 'adminfletes', 'documentos', 'catalogos'];
const RUTAS_ADMIN = ['admin/dashboard', 'admin/productores', 'admin/compradores', 'admin/transportistas', 'admin/vehiculos', 'admin/metodos-pago', 'admin/publicaciones', 'admin/administradores', 'admin/bitacora', 'admin/fletes', 'admin/documentos', 'admin/catalogos'];

const api = read('Public/js/shared/api.js');
const versionAuthGate = api.match(/^import '\.\/auth-gate\.js\?v=(auth-gate-\d+)';/)?.[1];
const versionAdminUi = api.match(/import '\.\/admin-ui\.js\?v=(admin-\d+)';/)?.[1];

test('api.js versiona auth-gate y admin-ui', () => {
    assert.ok(versionAuthGate, 'api.js debe importar auth-gate.js con ?v=');
    assert.ok(versionAdminUi, 'api.js debe importar admin-ui.js con ?v=');
});

test('todos los módulos admin importan la misma api.js', () => {
    for (const modulo of MODULOS_ADMIN) {
        assert.ok(read(`Public/js/${modulo}.js`).includes(`from './shared/api.js?v=${versionAuthGate}'`),
            `${modulo}.js debe importar shared/api.js?v=${versionAuthGate}`);
    }
});

test('admin-ui y login usan la misma versión de auth-gate, y admin-ui la de su CSS', () => {
    const adminUi = read('Public/js/shared/admin-ui.js');
    assert.ok(adminUi.includes(`from './auth-gate.js?v=${versionAuthGate}'`));
    assert.ok(read('Public/js/login.js').includes(`from './shared/auth-gate.js?v=${versionAuthGate}'`));
    assert.ok(adminUi.includes(`css/admin-refinements.css?v=${versionAdminUi}'`), 'el CSS de íconos sube junto con admin-ui');
});

test('cada ruta admin está en la guarda de sesión, en login, en admin-ui y en el menú de todas las vistas', () => {
    const authGate = read('Public/js/shared/auth-gate.js');
    const login = read('Public/js/login.js');
    const adminUi = read('Public/js/shared/admin-ui.js');
    const css = read('Public/css/admin-refinements.css');
    const vistas = MODULOS_ADMIN.map((modulo) => read(`Application/View/${modulo}/index.php`));
    for (const ruta of RUTAS_ADMIN) {
        assert.ok(authGate.includes(`'${ruta}'`), `${ruta} falta en PRIVATE_ROUTES`);
        assert.ok(login.includes(`'${ruta}'`), `${ruta} falta en ADMIN_DESTINATIONS`);
        assert.ok(adminUi.includes(`'${ruta}'`), `${ruta} falta en MODULES`);
        vistas.forEach((vista, i) => assert.ok(vista.includes(`href="${ruta}"`), `${MODULOS_ADMIN[i]} no enlaza a ${ruta}`));
    }
    for (const ruta of ['admin/administradores', 'admin/bitacora', 'admin/fletes', 'admin/documentos', 'admin/catalogos']) {
        assert.ok(css.includes(`[href$='${ruta}']::before`), `${ruta} no tiene ícono`);
    }
});

test('cada vista admin carga su módulo con versión', () => {
    for (const modulo of MODULOS_ADMIN) {
        assert.match(read(`Application/View/${modulo}/index.php`), new RegExp(`js/${modulo}\\.js\\?v=[a-z0-9-]+"`), `${modulo} sin ?v=`);
    }
});
